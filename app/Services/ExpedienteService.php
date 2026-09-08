<?php

namespace App\Services;

use App\Models\CatalogoActuado;
use App\Models\CatalogoEstado;
use App\Models\Expediente;
use App\Models\Parte;
use App\Models\Usuario;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExpedienteService
{
    public function __construct(
        protected NurejGeneratorService $generadorNurej,
        protected ActuadoService $actuadoService,
        protected SorteoAlgorithmService $sorteoService,
    ) {}

    /**
     * Apertura de causa por un técnico de fiscalización (rol TECNICO).
     *
     * En una única transacción: genera el NUREJ padre, crea el expediente en
     * PENDIENTE_SORTEO, registra el actuado ACT_REGISTRO_DIGITALIZACION
     * (cadena de custodia resuelta por el trigger de MySQL) y registra las
     * partes involucradas. No crea ninguna asignación: la causa queda en
     * PENDIENTE_SORTEO disponible en la bandeja de sorteo de la Encargada
     * (que se consulta por estado), y la primera asignación formal recién se
     * genera cuando la Encargada emite ACT_SORTEO_INICIAL.
     * Si algo falla, toda la operación se revierte (ni siquiera se quema un
     * número de NUREJ, porque el correlativo vive dentro de la transacción).
     *
     * @param  array{via: string, reglamento_id: int, resumen_hechos?: string|null, partes?: array<int, array{tipo: string, nombre_completo: string, documento_identidad?: string|null, cargo_institucion?: string|null}>}  $datos
     */
    public function aperturaCausa(
        array $datos,
        Usuario $tecnico,
        ?string $ipOrigen = null,
        ?UploadedFile $adjunto = null,
    ): Expediente {
        return DB::transaction(function () use ($datos, $tecnico, $ipOrigen, $adjunto) {
            $nurejCode = $this->generadorNurej->generarPadre();

            $estadoPendiente = CatalogoEstado::where('codigo', 'PENDIENTE_SORTEO')->firstOrFail();
            $catalogoActuado = CatalogoActuado::where('codigo', 'ACT_REGISTRO_DIGITALIZACION')->firstOrFail();

            $expediente = Expediente::create([
                'nurej_code' => $nurejCode,
                'via' => $datos['via'],
                'reglamento_id' => $datos['reglamento_id'],
                'estado_actual_id' => $estadoPendiente->id,
                'resumen_hechos' => $datos['resumen_hechos'] ?? null,
                'fecha_ingreso' => now(),
                'creado_por' => $tecnico->id,
                'created_at' => now(),
            ]);

            $actuado = $this->actuadoService->registerActuado(
                expediente: $expediente,
                catalogoActuado: $catalogoActuado,
                emisor: $tecnico,
                descripcion: 'Apertura de causa '.$nurejCode,
                usuarioDestinoId: null,
                metadatos: ['tipo' => 'APERTURA'],
                ipOrigen: $ipOrigen,
                adjunto: $adjunto,
            );

            foreach ($datos['partes'] ?? [] as $parte) {
                Parte::create([
                    'expediente_id' => $expediente->id,
                    'tipo' => $parte['tipo'],
                    'nombre_completo' => $parte['nombre_completo'],
                    'documento_identidad' => $parte['documento_identidad'] ?? null,
                    'cargo_institucion' => $parte['cargo_institucion'] ?? null,
                    'actuado_origen_id' => $actuado->id,
                    'vigente_desde' => now(),
                    'es_version_actual' => true,
                ]);
            }

            $expediente->refresh();

            return $expediente;
        }, 3);
    }

    /**
     * Sorteo ciego del expediente (rol ENCARGADA).
     *
     * En una única transacción: valida que la causa esté en PENDIENTE_SORTEO,
     * ejecuta el algoritmo probabilístico (que incrementa el peso del ganador
     * en `sorteo_pesos`) y emite ACT_SORTEO_INICIAL hacia el ganador, lo que
     * transiciona a EN_EVALUACION y abre el plazo natural. Si cualquiera de
     * los dos pasos falla, se revierte también el incremento de peso.
     *
     * @return Usuario El funcionario ganador del sorteo.
     */
    public function ejecutarSorteo(
        Expediente $expediente,
        Usuario $encargada,
        ?string $descripcion = null,
        ?string $ipOrigen = null,
    ): Usuario {
        return DB::transaction(function () use ($expediente, $encargada, $descripcion, $ipOrigen) {
            $estadoPendiente = CatalogoEstado::where('codigo', 'PENDIENTE_SORTEO')->firstOrFail();

            if ($expediente->estado_actual_id !== $estadoPendiente->id) {
                throw ValidationException::withMessages([
                    'expediente' => 'El expediente no está pendiente de sorteo.',
                ]);
            }

            $catalogoActuado = CatalogoActuado::where('codigo', 'ACT_SORTEO_INICIAL')->firstOrFail();
            $ganador = $this->sorteoService->sortear($expediente);

            $this->actuadoService->registerActuado(
                expediente: $expediente,
                catalogoActuado: $catalogoActuado,
                emisor: $encargada,
                descripcion: $descripcion ?? 'Sorteo probabilístico inicial',
                usuarioDestinoId: $ganador->id,
                metadatos: ['tipo' => 'SORTEO_INICIAL', 'via' => $expediente->via],
                ipOrigen: $ipOrigen,
            );

            return $ganador;
        }, 3);
    }

    /**
     * Sorteo en lote de todas las causas en PENDIENTE_SORTEO (rol ENCARGADA).
     *
     * Todo-o-nada: dentro de una única transacción ejecuta `ejecutarSorteo`
     * por cada causa (internamente son savepoints), de modo que si una vía no
     * tiene candidatos o cualquier causa falla, todo el lote se revierte y no
     * quedan sorteos ni incrementos de peso parciales.
     *
     * @return array<int, array{expediente: Expediente, ganador: Usuario}>
     */
    public function sortearTodas(
        Usuario $encargada,
        ?string $ipOrigen = null,
    ): array {
        return DB::transaction(function () use ($encargada, $ipOrigen) {
            $estadoPendiente = CatalogoEstado::where('codigo', 'PENDIENTE_SORTEO')->firstOrFail();

            $pendientes = Expediente::where('estado_actual_id', $estadoPendiente->id)
                ->orderBy('fecha_ingreso')
                ->orderBy('id')
                ->get();

            if ($pendientes->isEmpty()) {
                throw ValidationException::withMessages([
                    'expediente' => 'No hay causas pendientes de sorteo.',
                ]);
            }

            $resultados = [];

            foreach ($pendientes as $expediente) {
                $ganador = $this->ejecutarSorteo(
                    expediente: $expediente,
                    encargada: $encargada,
                    descripcion: 'Sorteo probabilístico masivo',
                    ipOrigen: $ipOrigen,
                );

                $resultados[] = [
                    'expediente' => $expediente,
                    'ganador' => $ganador,
                ];
            }

            return $resultados;
        }, 3);
    }
}
