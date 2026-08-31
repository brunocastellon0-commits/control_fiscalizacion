<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Models\SesionAcceso;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $ip = $request->ip();
        
        $usuario = Usuario::with('rol')
            ->where('username', $request->username)
            ->first();

        // 1. Validar usuario y contraseña hasheada
        if (!$usuario || !Hash::check($request->password, $usuario->password_hash)) {
            if ($usuario) {
                SesionAcceso::create([
                    'usuario_id' => $usuario->id,
                    'ip_origen'  => $ip,
                    'login_at'   => now(),
                    'exitoso'    => false,
                ]);
            }

            return response()->json([
                'message' => 'Credenciales inválidas.',
            ], 401);
        }

        // 2. Validar que la cuenta no esté desactivada
        if (!$usuario->activo) {
            SesionAcceso::create([
                'usuario_id' => $usuario->id,
                'ip_origen'  => $ip,
                'login_at'   => now(),
                'exitoso'    => false,
            ]);

            return response()->json([
                'message' => 'Usuario inactivo. Contacte al Administrador.',
            ], 403);
        }

        // 3. Registrar auditoría de acceso exitoso (RNF-01)
        SesionAcceso::create([
            'usuario_id' => $usuario->id,
            'ip_origen'  => $ip,
            'login_at'   => now(),
            'exitoso'    => true,
        ]);

        // 4. Generar Token Bearer
        $token = $usuario->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Autenticación exitosa',
            'token'   => $token,
            'usuario' => [
                'id'        => $usuario->id,
                'ci'        => $usuario->ci,
                'nombre'    => $usuario->nombres . ' ' . $usuario->apellidos,
                'username'  => $usuario->username,
                'rol'       => $usuario->rol->codigo,
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

        // Revocar el token actual
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Sesión cerrada exitosamente',
        ], 200);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user()->load('rol'));
    }
}