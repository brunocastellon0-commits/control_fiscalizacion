<?php

namespace App\Policies;

use App\Models\CatalogoActuado;
use App\Models\Expediente;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\AmpliacionService;
use App\Services\CierreExpedienteService;
use App\Services\DescargoFinancieroService;
use App\Services\PlanificacionService;
use App\Services\TransparenciaService;

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

    /**
     * US-2.5: devolución de la planificación con observaciones. Solo la
     * Encargada activa sobre un expediente en PENDIENTE_VISTO_BUENO.
     */
    public function devolverPlanificacion(Usuario $user, Expediente $expediente): bool
    {
        return $user->activo
            && ($user->rol?->codigo ?? null) === Rol::CODIGO_ENCARGADA
            && $expediente->estadoActual?->codigo === PlanificacionService::ESTADO_PENDIENTE_VISTO_BUENO;
    }

    /**
     * US-2.6: solicitud de ampliación de plazo. Solo el Técnico con la bandeja
     * activa de un expediente en EN_EJECUCION, cuyo rol corresponda al
     * reglamento AC022 (pivote del catálogo de actuados).
     */
    public function solicitarAmpliacion(Usuario $user, Expediente $expediente): bool
    {
        if (! $user->activo) {
            return false;
        }

        if ($expediente->estadoActual?->codigo !== AmpliacionService::ESTADO_EJECUCION) {
            return false;
        }

        if ($expediente->asignacionActiva?->usuario_id !== $user->id) {
            return false;
        }

        $catalogo = CatalogoActuado::where('codigo', AmpliacionService::CODIGO_ACT_SOLICITAR_AMPLIACION)->first();

        return $catalogo?->perteneceAlRolConReglamento(
            rolId: $user->rol_id,
            reglamentoId: $expediente->reglamento_id,
        ) ?? false;
    }

    /**
     * US-2.6: aprobación de la ampliación de plazo. Solo la Encargada activa
     * sobre un expediente en PENDIENTE_APROBACION_AMPLIACION.
     */
    public function aprobarAmpliacion(Usuario $user, Expediente $expediente): bool
    {
        return $user->activo
            && ($user->rol?->codigo ?? null) === Rol::CODIGO_ENCARGADA
            && $expediente->estadoActual?->codigo === AmpliacionService::ESTADO_PENDIENTE_APROBACION_AMPLIACION;
    }

    /**
     * E9-S1 (RN-10): derivación de NUREJ Hijo. Solo la Encargada activa
     * puede crear un expediente derivado a partir de un padre.
     */
    public function derivarNurejHijo(Usuario $user, Expediente $expediente): bool
    {
        return $user->activo
            && ($user->rol?->codigo ?? null) === Rol::CODIGO_ENCARGADA;
    }

    /**
     * E10-S1: Visto Bueno Final de cierre. Solo la Encargada activa sobre un
     * expediente en PENDIENTE_VISTO_BUENO_FINAL.
     */
    public function aprobarVistoBuenoFinal(Usuario $user, Expediente $expediente): bool
    {
        return $user->activo
            && ($user->rol?->codigo ?? null) === Rol::CODIGO_ENCARGADA
            && $expediente->estadoActual?->codigo === CierreExpedienteService::ESTADO_PENDIENTE_VISTO_BUENO_FINAL;
    }

    /**
     * E10-S2: reparto institucional de cierre. Solo la Encargada activa sobre
     * un expediente en LISTO_PARA_REPARTO.
     */
    public function ejecutarRepartoInstitucional(Usuario $user, Expediente $expediente): bool
    {
        return $user->activo
            && ($user->rol?->codigo ?? null) === Rol::CODIGO_ENCARGADA
            && $expediente->estadoActual?->codigo === CierreExpedienteService::ESTADO_LISTO_PARA_REPARTO;
    }

    /**
     * E5-S5 (RN-09): derivación por incompetencia vía Transparencia. Cualquier
     * operador operativo (Técnico, Auditor Jurídico, Auditor Financiero) activo
     * con el expediente en su bandeja; transversal a los acuerdos (pivote con
     * reglamento_id null). La Encargada lo recibe, no lo puede autogenerar.
     */
    public function derivarPorIncompetencia(Usuario $user, Expediente $expediente): bool
    {
        if (! $user->activo) {
            return false;
        }

        if ($expediente->asignacionActiva?->usuario_id !== $user->id) {
            return false;
        }

        $catalogo = CatalogoActuado::where('codigo', TransparenciaService::CODIGO_ACT_DERIVACION)->first();

        return $catalogo?->perteneceAlRolConReglamento(
            rolId: $user->rol_id,
            reglamentoId: $expediente->reglamento_id,
        ) ?? false;
    }

    /**
     * E5-S5 (RN-09): remisión a Transparencia. Solo la Encargada activa sobre
     * un expediente en PENDIENTE_REMISION_TRANSPARENCIA emite la salida.
     */
    public function remitirTransparencia(Usuario $user, Expediente $expediente): bool
    {
        return $user->activo
            && ($user->rol?->codigo ?? null) === Rol::CODIGO_ENCARGADA
            && $expediente->estadoActual?->codigo === TransparenciaService::ESTADO_PENDIENTE_REMISION;
    }

    /**
     * E7-S* (RN-09): la comunicación de hallazgos exige al Auditor Financiero
     * activo, asignado a la bandeja del expediente, en estado EN_EJECUCION,
     * dentro de la auditoría financiera (AC055).
     */
    public function comunicarHallazgos(Usuario $user, Expediente $expediente): bool
    {
        return $this->puedeTramitarDescargos($user, $expediente);
    }

    /**
     * E7-S* (RN-09): la recepción de descargos comparte los requisitos de
     * bandeja y competencia de la comunicación de hallazgos.
     */
    public function recibirDescargos(Usuario $user, Expediente $expediente): bool
    {
        return $this->puedeTramitarDescargos($user, $expediente);
    }

    /**
     * Requisitos comunes de la fase de descargos: rol Auditor Financiero
     * activo, bandeja propia y expediente en ejecución bajo el AC055.
     */
    private function puedeTramitarDescargos(Usuario $user, Expediente $expediente): bool
    {
        if (! $user->activo) {
            return false;
        }

        if (($user->rol?->codigo ?? null) !== Rol::CODIGO_AUD_FINANCIERO) {
            return false;
        }

        if ($expediente->estadoActual?->codigo !== DescargoFinancieroService::ESTADO_EJECUCION) {
            return false;
        }

        if ($expediente->asignacionActiva?->usuario_id !== $user->id) {
            return false;
        }

        $catalogo = CatalogoActuado::where('codigo', DescargoFinancieroService::CODIGO_ACT_COMUNICACION)->first();

        return $catalogo?->perteneceAlRolConReglamento(
            rolId: $user->rol_id,
            reglamentoId: $expediente->reglamento_id,
        ) ?? false;
    }
}
