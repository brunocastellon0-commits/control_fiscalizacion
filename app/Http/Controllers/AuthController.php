<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Models\SesionAcceso;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        // El login es exclusivo de la workstation (SPA stateful). Se rechaza a
        // cualquier cliente que no provenga de un frontend con sesión (sin
        // cookie de sesión), cerrando así la superficie de tokens Bearer.
        if (! $request->hasSession()) {
            return response()->json([
                'message' => 'Autenticación no disponible para este cliente. Utilice la workstation.',
            ], 403);
        }

        $ip = $request->ip();

        $usuario = Usuario::with('rol')
            ->where('username', $request->username)
            ->first();

        // 1. Validar usuario y contraseña hasheada
        if (! $usuario || ! Hash::check($request->password, $usuario->password_hash)) {
            if ($usuario) {
                SesionAcceso::create([
                    'usuario_id' => $usuario->id,
                    'ip_origen' => $ip,
                    'login_at' => now(),
                    'exitoso' => false,
                ]);
            }

            return response()->json([
                'message' => 'Credenciales inválidas.',
            ], 401);
        }

        // 2. Validar que la cuenta no esté desactivada
        if (! $usuario->activo) {
            SesionAcceso::create([
                'usuario_id' => $usuario->id,
                'ip_origen' => $ip,
                'login_at' => now(),
                'exitoso' => false,
            ]);

            return response()->json([
                'message' => 'Usuario inactivo. Contacte al Administrador.',
            ], 403);
        }

        // 3. Registrar auditoría de acceso exitoso (RNF-01)
        SesionAcceso::create([
            'usuario_id' => $usuario->id,
            'ip_origen' => $ip,
            'login_at' => now(),
            'exitoso' => true,
        ]);

        // 4. Sesión stateful (cookies HttpOnly) para la workstation SPA.
        //    Petición ya validada como frontend con sesión (guard al inicio).
        Auth::guard('web')->login($usuario);

        return response()->json([
            'message' => 'Autenticación exitosa',
            'usuario' => [
                'id' => $usuario->id,
                'ci' => $usuario->ci,
                'nombre' => $usuario->nombres.' '.$usuario->apellidos,
                'username' => $usuario->username,
                'rol' => $usuario->rol->codigo,
            ],
        ], 200);
    }

    public function logout(Request $request): JsonResponse
    {
        // Registrar logout en la última sesión abierta
        SesionAcceso::where('usuario_id', $request->user()->id)
            ->whereNull('logout_at')
            ->latest('login_at')
            ->first()
            ?->update(['logout_at' => now()]);

        // Revocar el token actual si la petición vino con un token real
        // (cliente API). En autenticación stateful (cookies) Sanctum devuelve
        // un TransientToken sin delete(); no hay token que revocar.
        $token = $request->user()->currentAccessToken();
        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        // Cerrar la sesión stateful de la workstation (cookies). Solo si la
        // petición viene con sesión (SPA stateful); los clientes API puros
        // (token Bearer) no disponen de sesión y solo revocan el token.
        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json([
            'message' => 'Sesión cerrada exitosamente',
        ], 200);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user()->load('rol'));
    }
}
