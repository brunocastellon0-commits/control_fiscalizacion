<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexUsuariosRequest;
use App\Http\Resources\UsuarioResource;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UsuarioController extends Controller
{
    /**
     * Catálogo de usuarios operativos asignables en el sorteo (rol ENCARGADA).
     * Retorna únicamente usuarios activos con roles operativos.
     */
    public function indexOperativos(IndexUsuariosRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewOperativos', Usuario::class);

        $rolesOperativos = Rol::whereIn('codigo', [
            Rol::CODIGO_TECNICO,
            Rol::CODIGO_AUD_JURIDICO,
            Rol::CODIGO_AUD_FINANCIERO,
        ])->pluck('id');

        return UsuarioResource::collection(
            Usuario::with('rol')
                ->where('activo', true)
                ->whereIn('rol_id', $rolesOperativos)
                ->orderBy('apellidos')
                ->orderBy('nombres')
                ->paginate(15),
        );
    }
}
