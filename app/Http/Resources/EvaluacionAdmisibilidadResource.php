<?php

namespace App\Http\Resources;

use App\Models\EvaluacionAdmisibilidad;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EvaluacionAdmisibilidadResource extends JsonResource
{
    /**
     * Transforma un resultado del checklist de admisibilidad.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var EvaluacionAdmisibilidad $evaluacion */
        $evaluacion = $this->resource;

        return [
            'id' => $evaluacion->id,
            'expediente_id' => $evaluacion->expediente_id,
            'requisito_id' => $evaluacion->requisito_id,
            'cumple' => $evaluacion->cumple,
            'operador_id' => $evaluacion->operador_id,
            'actuado_id' => $evaluacion->actuado_id,
            'fecha' => $evaluacion->fecha?->format('Y-m-d H:i:s'),
            'requisito' => $this->whenLoaded('requisito', fn () => [
                'id' => $evaluacion->requisito->id,
                'descripcion' => $evaluacion->requisito->descripcion,
                'es_critico' => $evaluacion->requisito->es_critico,
            ]),
            'operador' => $this->whenLoaded('operador', fn () => $evaluacion->operador ? [
                'id' => $evaluacion->operador->id,
                'nombres' => $evaluacion->operador->nombres,
                'apellidos' => $evaluacion->operador->apellidos,
            ] : null),
        ];
    }
}
