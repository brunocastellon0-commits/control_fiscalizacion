<?php

namespace App\Http\Controllers\Administrador;

use App\Http\Controllers\Controller;
use App\Models\Asignacion;
use App\Models\Expediente;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $usuario = $request->user();

        // Seguridad adicional:
        // esta API solamente puede ser utilizada por ADMIN.
        if (($usuario->rol?->codigo ?? null) !== Rol::CODIGO_ADMIN) {
            abort(403, 'No tiene permisos para acceder al dashboard administrativo.');
        }

        /*
        |--------------------------------------------------------------------------
        | USUARIOS
        |--------------------------------------------------------------------------
        */

        $usuariosTotal = Usuario::count();

        $usuariosActivos = Usuario::where('activo', true)->count();

        $usuariosInactivos = Usuario::where('activo', false)->count();


        /*
        |--------------------------------------------------------------------------
        | EXPEDIENTES
        |--------------------------------------------------------------------------
        */

        $expedientesTotal = Expediente::count();


        /*
        |--------------------------------------------------------------------------
        | ASIGNACIONES
        |--------------------------------------------------------------------------
        */

        $asignacionesActivas = Asignacion::where('activa', true)->count();


        /*
        |--------------------------------------------------------------------------
        | USUARIOS POR ROL
        |--------------------------------------------------------------------------
        */

        $roles = Rol::withCount([
            'usuarios as usuarios_activos_count' => function ($query) {
                $query->where('activo', true);
            },
        ])->get();


        $usuariosPorRol = $roles->mapWithKeys(function ($rol) {
            return [
                $rol->codigo => $rol->usuarios_activos_count,
            ];
        });


        /*
        |--------------------------------------------------------------------------
        | RESPUESTA
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'usuarios' => [
                'total' => $usuariosTotal,
                'activos' => $usuariosActivos,
                'inactivos' => $usuariosInactivos,
            ],

            'expedientes' => [
                'total' => $expedientesTotal,
            ],

            'asignaciones' => [
                'activas' => $asignacionesActivas,
            ],

            'usuarios_por_rol' => $usuariosPorRol,
        ]);
    }
}