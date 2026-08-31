<?php

namespace App\Policies;

use App\Models\CatalogoActuado;
use App\Models\Expediente;
use App\Models\Rol;
use App\Models\Usuario;

class ExpedientePolicy
{
    public function crearActuado(Usuario $user, Expediente $expediente, CatalogoActuado $catalogoActuado): bool
    {
        if (! $user->activo) {
            return false;
        }

        $esRolDelCatalogo = $catalogoActuado->rol_id === $user->rol_id;

        if (($user->rol?->codigo ?? null) === Rol::CODIGO_ENCARGADA) {
            return $esRolDelCatalogo;
        }

        return $esRolDelCatalogo && $expediente->asignacionActiva?->usuario_id === $user->id;
    }
}
