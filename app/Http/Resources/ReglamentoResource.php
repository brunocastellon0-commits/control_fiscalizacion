<?php

namespace App\Http\Resources;

use App\Models\Reglamento;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReglamentoResource extends JsonResource
{
    /**
     * Reglamento para el select de apertura de causa.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Reglamento $reglamento */
        $reglamento = $this->resource;

        return [
            'id' => $reglamento->id,
            'codigo' => $reglamento->codigo,
            'nombre' => $reglamento->nombre,
            'version' => $reglamento->version,
        ];
    }
}
