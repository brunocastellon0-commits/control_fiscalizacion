<?php

namespace App\Http\Resources;

use App\Models\Actuado;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActuadoResource extends JsonResource
{
    /**
     * Transforma un actuado a su representacion del workstation, incluyendo
     * la cadena de custodia (hashes) y el contenido versionado en JSON.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Actuado $actuado */
        $actuado = $this->resource;

        $contenido = $actuado->contenido ?? [];

        return [
            'id' => $actuado->id,
            'fecha_hora' => $actuado->fecha_hora?->format('Y-m-d H:i:s'),
            'tipo_actuado' => $this->whenLoaded('tipoActuado', fn () => [
                'id' => $actuado->tipoActuado->id,
                'codigo' => $actuado->tipoActuado->codigo,
                'nombre' => $actuado->tipoActuado->nombre,
            ]),
            'descripcion' => $contenido['descripcion'] ?? null,
            'estado_anterior' => $this->whenLoaded('estadoAnterior', fn () => $actuado->estadoAnterior ? [
                'id' => $actuado->estadoAnterior->id,
                'codigo' => $actuado->estadoAnterior->codigo,
                'nombre' => $actuado->estadoAnterior->nombre,
            ] : null),
            'estado_nuevo' => $this->whenLoaded('estadoNuevo', fn () => $actuado->estadoNuevo ? [
                'id' => $actuado->estadoNuevo->id,
                'codigo' => $actuado->estadoNuevo->codigo,
                'nombre' => $actuado->estadoNuevo->nombre,
            ] : null),
            'usuario' => $this->whenLoaded('usuario', fn () => $actuado->usuario ? [
                'id' => $actuado->usuario->id,
                'nombres' => $actuado->usuario->nombres,
                'apellidos' => $actuado->usuario->apellidos,
            ] : null),
            'hash_anterior' => $actuado->hash_anterior,
            'hash_actuado' => $actuado->hash_actuado,
            'adjuntos' => $this->whenLoaded('adjuntos', function () use ($actuado) {
                return collect($actuado->adjuntos)->map(fn ($adjunto) => [
                    'id' => $adjunto->id,
                    'nombre_original' => $adjunto->nombre_original,
                    'mime_type' => $adjunto->mime_type,
                    'tamanio_bytes' => $adjunto->tamanio_bytes,
                    'hash_sha256' => $adjunto->hash_sha256,
                ])->all();
            }),
        ];
    }
}
