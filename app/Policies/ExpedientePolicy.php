<?php

namespace App\Policies;

use App\Models\CatalogoActuado;
use App\Models\Expediente;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\PlanificacionService;

class ExpedientePolicy
{
    public function crearActuado(Usuario $user, Expediente $expediente, CatalogoActuado $catalogoActuado): bool
    {
        if (! $user->activo) {
            return false;
        }

        $esRolDelCatalogo = $catalogoActuado->perteneceAlRolConReglamento(
            rolId: $user->rol_id,
            reglamentoId: $expediente->reglamento_id,
        );

        if (($user->rol?->codigo ?? null) === Rol::CODIGO_ENCARGADA) {
            return $esRolDelCatalogo;
        }

        return $esRolDelCatalogo && $expediente->asignacionActiva?->usuario_id === $user->id;
    }

    /**
     * Evaluación de admisibilidad (RF-04): solo el operador que tiene el
     * expediente asignado en su bandeja. Sin excepciones para jerarquías.
     */
    public function evaluarAdmisibilidad(Usuario $user, Expediente $expediente): bool
    {
        if (! $user->activo) {
            return false;
        }

        return $expediente->asignacionActiva?->usuario_id === $user->id;
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

    /**
     * RN-08 (Remisión): solo el operador operativo con asignación activa
     * puede remitir a la Encargada un expediente en estado RECHAZADO.
     */
    public function remitirImpugnacion(Usuario $user, Expediente $expediente): bool
    {
        if (! $user->activo) {
            return false;
        }

        $esOperativo = in_array($user->rol?->codigo ?? null, [
            Rol::CODIGO_TECNICO,
            Rol::CODIGO_AUD_JURIDICO,
            Rol::CODIGO_AUD_FINANCIERO,
        ], true);

        return $esOperativo
            && $expediente->asignacionActiva?->usuario_id === $user->id
            && $expediente->estadoActual?->codigo === 'RECHAZADO';
    }

    /**
     * RN-08 (Resolución): solo la Encargada activa puede resolver la
     * impugnación de un expediente en estado EN_IMPUGNACION.
     */
    public function resolverImpugnacion(Usuario $user, Expediente $expediente): bool
    {
        return $user->activo
            && ($user->rol?->codigo ?? null) === Rol::CODIGO_ENCARGADA
            && $expediente->estadoActual?->codigo === 'EN_IMPUGNACION';
    }

    /**
     * US-2.4: carga de planificación. Solo el operador con la bandeja activa
     * de un expediente en EN_PLANIFICACION, cuyo rol corresponda al tipo de
     * planificación de su reglamento (Técnico-AC022 → Cronograma; Auditor
     * AC054/055 → MPA), resuelto contra la tabla pivote del catálogo.
     */
    public function cargarPlanificacion(Usuario $user, Expediente $expediente): bool
    {
        if (! $user->activo) {
            return false;
        }

        if ($expediente->estadoActual?->codigo !== PlanificacionService::ESTADO_PLANIFICACION) {
            return false;
        }

        if ($expediente->asignacionActiva?->usuario_id !== $user->id) {
            return false;
        }

        $codigoCatalogo = match ($expediente->reglamento?->codigo) {
            PlanificacionService::REGLAMENTO_AC022 => PlanificacionService::CODIGO_ACT_CRONOGRAMA,
            PlanificacionService::REGLAMENTO_AC054,
            PlanificacionService::REGLAMENTO_AC055 => PlanificacionService::CODIGO_ACT_MPA,
            default => null,
        };

        if ($codigoCatalogo === null) {
            return false;
        }

        $catalogo = CatalogoActuado::where('codigo', $codigoCatalogo)->first();

        return $catalogo?->perteneceAlRolConReglamento(
            rolId: $user->rol_id,
            reglamentoId: $expediente->reglamento_id,
        ) ?? false;
    }

    /**
     * US-2.4: Visto Bueno a la Planificación. Solo la Encargada activa sobre
     * un expediente en PENDIENTE_VISTO_BUENO.
     */
    public function aprobarPlanificacion(Usuario $user, Expediente $expediente): bool
    {
        return $user->activo
            && ($user->rol?->codigo ?? null) === Rol::CODIGO_ENCARGADA
            && $expediente->estadoActual?->codigo === PlanificacionService::ESTADO_PENDIENTE_VISTO_BUENO;
    }
}
