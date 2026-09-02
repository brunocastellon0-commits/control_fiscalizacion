<?php

namespace App\Http\Resources;

use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UsuarioResource extends JsonResource
{
    /**
     * Transforma un usuario del catálogo operativo en su representación.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Usuario $usuario */
        $usuario = $this->resource;

        return [
            'id' => $usuario->id,
            'nombres' => $usuario->nombres,
            'apellidos' => $usuario->apellidos,
            'ci' => $usuario->ci,
            'rol' => $usuario->rol ? [
                'codigo' => $usuario->rol->codigo,
                'nombre' => $usuario->rol->nombre,
            ] : null,
        ];
    }
}
