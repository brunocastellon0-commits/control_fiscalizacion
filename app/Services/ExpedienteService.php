<?php

namespace App\Services;

use App\Models\CatalogoActuado;
use App\Models\CatalogoEstado;
use App\Models\Expediente;
use App\Models\Parte;
use App\Models\Usuario;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class ExpedienteService
{
    public function __construct(
        protected NurejGeneratorService $generadorNurej,
        protected ActuadoService $actuadoService,
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
        });
    }
}
