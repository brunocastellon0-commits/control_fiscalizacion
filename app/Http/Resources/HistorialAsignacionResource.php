<?php

namespace App\Http\Resources;

use App\Models\Asignacion;
use App\Models\Expediente;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HistorialAsignacionResource extends JsonResource
{
    /**
     * Fila liviana del historial de asignaciones de un operador.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Expediente $expediente */
        $expediente = $this->resource;

        /** @var Asignacion|null $asignacion */
        $asignacion = $expediente->relationLoaded('asignaciones')
            ? $expediente->asignaciones->first()
            : null;

        return [
            'id' => $expediente->id,
            'nurej_code' => $expediente->nurej_code,
            'via' => $expediente->via,
            'numero_hoja_ruta' => $expediente->numero_hoja_ruta,
            'naturaleza' => $expediente->naturaleza,
            'fecha_ingreso' => $expediente->fecha_ingreso?->format('Y-m-d H:i:s'),
            'resumen_hechos' => $expediente->resumen_hechos,
            'reglamento' => $this->whenLoaded('reglamento', fn () => $expediente->reglamento ? [
                'id' => $expediente->reglamento->id,
                'codigo' => $expediente->reglamento->codigo,
                'nombre' => $expediente->reglamento->nombre,
            ] : null),
            'estado_actual' => $this->whenLoaded('estadoActual', fn () => $expediente->estadoActual ? [
                'id' => $expediente->estadoActual->id,
                'codigo' => $expediente->estadoActual->codigo,
                'nombre' => $expediente->estadoActual->nombre,
                'es_final' => (bool) $expediente->estadoActual->es_final,
            ] : null),
            'asignacion' => $asignacion ? [
                'id' => $asignacion->id,
                'fecha_asignacion' => $asignacion->fecha_asignacion?->format('Y-m-d H:i:s'),
                'activa' => (bool) $asignacion->activa,
                'rol' => $asignacion->relationLoaded('rol') && $asignacion->rol ? [
                    'id' => $asignacion->rol->id,
                    'codigo' => $asignacion->rol->codigo,
                    'nombre' => $asignacion->rol->nombre,
                ] : null,
            ] : null,
        ];
    }
}
