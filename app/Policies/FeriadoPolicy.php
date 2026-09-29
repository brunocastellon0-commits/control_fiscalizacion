<?php

namespace App\Policies;

use App\Models\Feriado;
use App\Models\Rol;
use App\Models\Usuario;

class FeriadoPolicy
{
    /**
     * Gestión completa de feriados.
     * Solo un administrador activo puede realizar estas operaciones.
     */
    public function gestionar(Usuario $usuario): bool
    {
        return $usuario->activo
            && ($usuario->rol?->codigo ?? null) === Rol::CODIGO_ADMIN;
    }
}