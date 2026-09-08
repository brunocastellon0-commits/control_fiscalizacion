<?php

namespace App\Services;

use App\Models\AuditoriaUsuario;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Revocación central de sesiones y credenciales. Punto único de verdad para
 * expulsar a un usuario del sistema en tiempo real: inactiva la cuenta,
 * revoca todos sus tokens Sanctum, elimina sus sesiones stateful de `sessions`
 * y deja constancia auditable en `auditoria_usuarios`.
 */
class SeguridadSesionesService
{
    /**
     * Purgar las filas de `sessions` del usuario (framework SESSION_DRIVER=database).
     * No espera al GC de Laravel: la expulsión es inmediata en todos los
     * navegadores donde el funcionario tenga la sesión abierta.
     */
    public function purgeSesiones(Usuario $usuario): void
    {
        DB::table('sessions')->where('user_id', $usuario->id)->delete();
    }

    /**
     * Expulsa al usuario objetivo: desactiva la cuenta, revoca todos sus
     * tokens Sanctum, purga sus sesiones activas y audita la acción, todo
     * dentro de una única transacción.
     *
     * @param  Usuario  $admin  Funcionario ADMIN que ejecuta la acción.
     */
    public function expulsar(Usuario $admin, Usuario $objetivo, ?string $ipOrigen = null): void
    {
        DB::transaction(function () use ($admin, $objetivo, $ipOrigen) {
            if (! $objetivo->activo) {
                throw ValidationException::withMessages([
                    'usuario' => 'El usuario objetivo ya se encuentra inactivo.',
                ]);
            }

            if ($admin->id === $objetivo->id) {
                throw ValidationException::withMessages([
                    'usuario' => 'Un administrador no puede inactivarse a sí mismo.',
                ]);
            }

            $objetivo->update(['activo' => false]);

            $objetivo->tokens()->delete();

            $this->purgeSesiones($objetivo);

            AuditoriaUsuario::create([
                'admin_id' => $admin->id,
                'usuario_objetivo_id' => $objetivo->id,
                'accion' => AuditoriaUsuario::ACCION_INACTIVACION,
                'ip_origen' => $ipOrigen,
            ]);
        });
    }
}
