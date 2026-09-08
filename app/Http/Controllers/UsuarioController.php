<?php

namespace App\Http\Controllers;

use App\Http\Requests\InactivarUsuarioRequest;
use App\Http\Requests\IndexUsuariosRequest;
use App\Http\Resources\UsuarioResource;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\SeguridadSesionesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UsuarioController extends Controller
{
    public function __construct(
        protected SeguridadSesionesService $seguridadSesiones,
    ) {}

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

    /**
     * Inactivación administrativa (rol ADMIN). Expulsa al usuario en tiempo
     * real: desactiva la cuenta, revoca todos sus tokens Sanctum y purga sus
     * sesiones, dejando registro auditable. Las reglas de negocio devuelven
     * 422 vía el servicio; la autorización 403 vía UsuarioPolicy.
     */
    public function inactivar(InactivarUsuarioRequest $request, Usuario $usuario): JsonResponse
    {
        $this->authorize('inactivar', $usuario);

        $this->seguridadSesiones->expulsar(
            admin: $request->user(),
            objetivo: $usuario,
            ipOrigen: $request->ip(),
        );

        return response()->json([
            'message' => 'Usuario inactivado. Sesiones cerradas y tokens revocados.',
        ]);
    }
}
