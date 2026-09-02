<?php

namespace App\Http\Resources;

use App\Models\CatalogoActuado;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CatalogoActuadoResource extends JsonResource
{
    /**
     * Transforma un catalogo de actuado a su representacion del workstation.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var CatalogoActuado $catalogo */
        $catalogo = $this->resource;

        return [
            'id' => $catalogo->id,
            'codigo' => $catalogo->codigo,
            'nombre' => $catalogo->nombre,
            'fase' => $catalogo->fase,
            'requiere_adjunto' => $catalogo->requiere_adjunto,
            'estado_origen' => $this->whenLoaded('estadoOrigen', fn () => $catalogo->estadoOrigen ? [
                'id' => $catalogo->estadoOrigen->id,
                'codigo' => $catalogo->estadoOrigen->codigo,
                'nombre' => $catalogo->estadoOrigen->nombre,
            ] : null),
            'estado_destino' => $this->whenLoaded('estadoDestino', fn () => $catalogo->estadoDestino ? [
                'id' => $catalogo->estadoDestino->id,
                'codigo' => $catalogo->estadoDestino->codigo,
                'nombre' => $catalogo->estadoDestino->nombre,
            ] : null),
        ];
    }
}
