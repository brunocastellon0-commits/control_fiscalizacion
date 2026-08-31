<?php

namespace App\Http\Resources;

use App\Models\Parte;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ParteResource extends JsonResource
{
    /**
     * Transforma una parte (denunciante/denunciado) a su representacion
     * del workstation.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Parte $parte */
        $parte = $this->resource;

        return [
            'id' => $parte->id,
            'tipo' => $parte->tipo,
            'nombre_completo' => $parte->nombre_completo,
            'documento_identidad' => $parte->documento_identidad,
            'cargo_institucion' => $parte->cargo_institucion,
            'vigente_desde' => $parte->vigente_desde?->format('Y-m-d H:i:s'),
            'es_version_actual' => $parte->es_version_actual,
        ];
    }
}
