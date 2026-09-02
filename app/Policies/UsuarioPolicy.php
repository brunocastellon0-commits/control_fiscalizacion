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
    public function viewOperativos(Usuario $user): bool
    {
        if (! $user->activo) {
            return false;
        }

        return ($user->rol?->codigo ?? null) === Rol::CODIGO_ENCARGADA;
    }
}
