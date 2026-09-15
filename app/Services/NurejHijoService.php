<?php

namespace App\Services;

use App\Models\CatalogoActuado;
use App\Models\CatalogoEstado;
use App\Models\Expediente;
use App\Models\Parte;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;

class NurejHijoService
{
    public function __construct(
        protected NurejGeneratorService $nurejGenerator,
        protected ActuadoService $actuadoService,
    ) {}

    /**
     * E9-S1 (RN-10): crea un NUREJ Hijo derivado de un expediente padre.
     *
     * Transaccional e indivisible:
     * 1. Genera el NUREJ Hijo (formato YYYY-NNNNN-X).
     * 2. Crea el expediente hijo con los metadatos informativos del padre
     *    (via, reglamento, resumen_hechos, partes) pero sin actuados,
     *    plazos ni asignaciones previas — línea de tiempo en cero.
     * 3. Registra ACT_CREACION_NUREJ_HIJO sobre el padre como evidencia
     *    del vínculo genealógico. El padre conserva su estado actual.
     *
     * El hijo nace en PENDIENTE_SORTEO para ser sorteado por la Encargada.
     */
    public function crearHijo(
        Expediente $padre,
        Usuario $encargada,
        string $motivo,
        ?string $ipOrigen = null,
    ): Expediente {
        return DB::transaction(function () use ($padre, $encargada, $motivo, $ipOrigen) {
            $nurejHijo = $this->nurejGenerator->generarHijo($padre->id);

            $estadoPendienteSorteo = CatalogoEstado::where('codigo', 'PENDIENTE_SORTEO')->firstOrFail();

            $hijo = Expediente::create([
                'nurej_code' => $nurejHijo,
                'nurej_padre_id' => $padre->id,
                'via' => $padre->via,
                'reglamento_id' => $padre->reglamento_id,
                'estado_actual_id' => $estadoPendienteSorteo->id,
                'resumen_hechos' => $padre->resumen_hechos,
                'fecha_ingreso' => now(),
                'creado_por' => $encargada->id,
            ]);

            $this->copiarPartesVigentes($padre, $hijo);

            $catalogoActuado = CatalogoActuado::where('codigo', 'ACT_CREACION_NUREJ_HIJO')->firstOrFail();

            $this->actuadoService->registerActuado(
                expediente: $padre,
                catalogoActuado: $catalogoActuado,
                emisor: $encargada,
                descripcion: $motivo,
                metadatos: [
                    'expediente_hijo_id' => $hijo->id,
                    'nurej_hijo_code' => $nurejHijo,
                ],
                ipOrigen: $ipOrigen,
                estadoNuevoIdExplicito: $padre->estado_actual_id,
            );

            return $hijo->refresh();
        }, 3);
    }

    /**
     * Copia las partes vigentes del padre al hijo como nuevas versiones.
     * Cada parte del hijo es una entidad independiente que puede evolucionar
     * sin afectar al padre.
     */
    protected function copiarPartesVigentes(Expediente $padre, Expediente $hijo): void
    {
        $partesVigentes = $padre->partesVigentes()->get();

        foreach ($partesVigentes as $parte) {
            Parte::create([
                'expediente_id' => $hijo->id,
                'tipo' => $parte->tipo,
                'nombre_completo' => $parte->nombre_completo,
                'documento_identidad' => $parte->documento_identidad,
                'cargo_institucion' => $parte->cargo_institucion,
                'actuado_origen_id' => null,
                'vigente_desde' => now(),
                'vigente_hasta' => null,
                'es_version_actual' => true,
            ]);
        }
    }
}
