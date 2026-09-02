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

    public function bandejaSorteo(Usuario $user): bool
    {
        return $user->activo && ($user->rol?->codigo ?? null) === Rol::CODIGO_ENCARGADA;
    }

    public function operadorBandeja(Usuario $user): bool
    {
        if (! $user->activo) {
            return false;
        }

        return in_array($user->rol?->codigo ?? null, [
            Rol::CODIGO_TECNICO,
            Rol::CODIGO_AUD_JURIDICO,
            Rol::CODIGO_AUD_FINANCIERO,
        ], true);
    }

    public function view(Usuario $user, Expediente $expediente): bool
    {
        if (! $user->activo) {
            return false;
        }

        if (($user->rol?->codigo ?? null) === Rol::CODIGO_ENCARGADA) {
            return true;
        }

        if ($expediente->asignacionActiva?->usuario_id === $user->id) {
            return true;
        }

        // El creador (ventanilla de ingreso) conserva lectura mientras la
        // causa no haya sido sorteada; una vez asignada, pierde acceso (RF-03).
        return $expediente->creado_por === $user->id
            && $expediente->asignaciones()->doesntExist();
    }

    /**
     * Apertura de causa nueva: solo rol TECNICO activo.
     */
    public function aperturaCausa(Usuario $user): bool
    {
        return $user->activo && ($user->rol?->codigo ?? null) === Rol::CODIGO_TECNICO;
    }

    /**
     * Consulta de catalogos de soporte (actuados, reglamentos, estados):
     * roles operativos, Encargada y ADMIN activos.
     */
    public function verCatalogoActuados(Usuario $user): bool
    {
        return $this->esRolConAccesoCatalogos($user);
    }

    public function verCatalogoReglamentos(Usuario $user): bool
    {
        return $this->esRolConAccesoCatalogos($user);
    }

    public function verCatalogoEstados(Usuario $user): bool
    {
        return $this->esRolConAccesoCatalogos($user);
    }

    private function esRolConAccesoCatalogos(Usuario $user): bool
    {
        if (! $user->activo) {
            return false;
        }

        return in_array($user->rol?->codigo ?? null, [
            Rol::CODIGO_ENCARGADA,
            Rol::CODIGO_TECNICO,
            Rol::CODIGO_AUD_JURIDICO,
            Rol::CODIGO_AUD_FINANCIERO,
            Rol::CODIGO_ADMIN,
        ], true);
    }
}
