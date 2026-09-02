<?php

namespace App\Http\Resources;

use App\Models\CatalogoEstado;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CatalogoEstadoResource extends JsonResource
{
    /**
     * Estado del workflow para filtros de bandejas y detalle.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var CatalogoEstado $estado */
        $estado = $this->resource;

        return [
            'id' => $estado->id,
            'codigo' => $estado->codigo,
            'nombre' => $estado->nombre,
            'es_final' => $estado->es_final,
            'estado_padre' => $this->whenLoaded('padre', fn () => $estado->padre ? [
                'id' => $estado->padre->id,
                'codigo' => $estado->padre->codigo,
                'nombre' => $estado->padre->nombre,
            ] : null),
        ];
    }
}
