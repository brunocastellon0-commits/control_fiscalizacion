<?php

namespace App\Http\Middleware;

use App\Models\Rol;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if (! $usuario) {
            return redirect()->route('login');
        }

        if (! $usuario->activo) {
            abort(403, 'Usuario inactivo.');
        }

        if (($usuario->rol?->codigo ?? null) !== Rol::CODIGO_ADMIN) {
            abort(403, 'No tiene permisos para acceder al panel administrativo.');
        }

        return $next($request);
    }
}