<?php

namespace App\Policies;

use App\Models\Rol;
use App\Models\Usuario;

class UsuarioPolicy
{
    /**
     * Catálogo de usuarios operativos para el sorteo.
     * Solo la Encargada activa puede consultarlo (least privilege).
     */
    //* codigo 1
    // public function viewOperativos(Usuario $user): bool
    // {
    //     if (!$user->activo) {
    //         return false;
    //     }

    //     return ($user->rol?->codigo ?? null) === Rol::CODIGO_ENCARGADA;
    // }
    //* nuevo codigo
    public function viewOperativos(Usuario $usuario): bool
    {
        if (!$usuario->activo) {
            return false;
        }

        $rol = $usuario->rol?->codigo;

        return in_array($rol, [
            Rol::CODIGO_ENCARGADA,
            Rol::CODIGO_ADMIN,
        ], true);
    }

    /**
     * Inactivación/expulsión en tiempo real de un usuario. Autorización: solo
     * un ADMIN activo. Las reglas de negocio (auto-inactivación, objetivo ya
     * inactivo) las valida el Servicio con 422 para distinguirlas del 403.
     */
    public function inactivar(Usuario $admin, Usuario $objetivo): bool
    {
        return $admin->activo && ($admin->rol?->codigo ?? null) === Rol::CODIGO_ADMIN;
    }
}
