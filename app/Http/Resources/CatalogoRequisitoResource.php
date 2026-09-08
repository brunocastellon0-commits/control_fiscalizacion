<?php

namespace App\Http\Resources;

use App\Models\CatalogoRequisito;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CatalogoRequisitoResource extends JsonResource
{
    /**
     * Transforma un requisito del catálogo a su representación del checklist
     * de admisibilidad.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var CatalogoRequisito $requisito */
        $requisito = $this->resource;

        return [
            'id' => $requisito->id,
            'descripcion' => $requisito->descripcion,
            'orden' => $requisito->orden,
            'es_critico' => $requisito->es_critico,
            'reglamento' => $this->whenLoaded('reglamento', fn () => $requisito->reglamento ? [
                'id' => $requisito->reglamento->id,
                'codigo' => $requisito->reglamento->codigo,
                'nombre' => $requisito->reglamento->nombre,
            ] : null),
        ];
    }
}
