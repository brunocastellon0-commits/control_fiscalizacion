<?php

namespace App\Services;

use App\Models\Expediente;

/**
 * Deriva el estado de los pasos del workstation a partir del estado procesal
 * real y de los actuados inmutables ya registrados. Es información solo de
 * presentación: la autorización de cada acción sigue siendo competencia de
 * las policies y los FormRequests.
 */
class PasosExpedienteService
{
    private const ESTADOS_EVALUACION = [
        'EN_EVALUACION',
        'OBSERVADO',
        'EN_SUBSANACION',
        'RECHAZADO',
        'EN_IMPUGNACION',
    ];

    private const ESTADOS_POST_ADMISION = [
        'ADMITIDO',
        'EN_PLANIFICACION',
        'PENDIENTE_VISTO_BUENO',
        'EN_EJECUCION',
        'EN_INVESTIGACION',
        'EN_DESCARGOS',
        'PENDIENTE_APROBACION_AMPLIACION',
        'PENDIENTE_VISTO_BUENO_FINAL',
        'LISTO_PARA_REPARTO',
        'PENDIENTE_REMISION_TRANSPARENCIA',
        'CONCLUIDO',
        'CONCLUIDO_REMITIDO',
        'DERIVADO_TRANSPARENCIA',
    ];

    private const ESTADOS_POST_PLANIFICACION = [
        'EN_EJECUCION',
        'EN_INVESTIGACION',
        'EN_DESCARGOS',
        'PENDIENTE_APROBACION_AMPLIACION',
        'PENDIENTE_VISTO_BUENO_FINAL',
        'LISTO_PARA_REPARTO',
        'PENDIENTE_REMISION_TRANSPARENCIA',
        'CONCLUIDO',
        'CONCLUIDO_REMITIDO',
        'DERIVADO_TRANSPARENCIA',
    ];

    private const ESTADOS_EJECUCION = [
        'EN_EJECUCION',
        'EN_INVESTIGACION',
        'EN_DESCARGOS',
        'PENDIENTE_APROBACION_AMPLIACION',
    ];

    private const ESTADOS_POST_INFORME = [
        'PENDIENTE_VISTO_BUENO_FINAL',
        'LISTO_PARA_REPARTO',
        'PENDIENTE_REMISION_TRANSPARENCIA',
        'CONCLUIDO',
        'CONCLUIDO_REMITIDO',
        'DERIVADO_TRANSPARENCIA',
    ];

    private const ESTADOS_FINALES = [
        'CONCLUIDO',
        'CONCLUIDO_REMITIDO',
        'DERIVADO_TRANSPARENCIA',
        'ARCHIVO_DEFINITIVO',
        'ARCHIVO_POR_ABANDONO',
    ];

    private const ACTUADOS_INFORME_FINAL = [
        'ACT_INFORME_FINAL',
        'ACT_INFORME_AUDITORIA_FINANCIERA_CON_RESPONSABILIDAD',
        'ACT_INFORME_AUDITORIA_FINANCIERA_SIN_RESPONSABILIDAD',
        'ACT_INFORME_TECNICO_CON_RESPONSABILIDAD',
        'ACT_INFORME_TECNICO_SIN_RESPONSABILIDAD',
        'ACT_INFORME_TECNICO_CON_RESPONSABILIDAD_RECOMENDACION',
        'ACT_INFORME_TECNICO_SIN_RESPONSABILIDAD_RECOMENDACION',
        'ACT_INFORME_AUDITORIA_JURIDICA_CON_RESPONSABILIDAD',
        'ACT_INFORME_AUDITORIA_JURIDICA_SIN_RESPONSABILIDAD',
    ];

    /**
     * @return array<int, array{codigo: string, numero: int, nombre: string, descripcion: string, estado: string, mensaje_bloqueo: ?string}>
     */
    public function calcular(Expediente $expediente): array
    {
        $estado = $expediente->estadoActual?->codigo ?? '';
        $actuados = $this->codigosActuados($expediente);

        $recibido = $this->fueRecibido($expediente, $estado, $actuados);
        $admision = in_array('ACT_ADMISION', $actuados, true)
            || in_array('ACT_RESOLUCION_REVOCA_RECHAZO', $actuados, true)
            || in_array($estado, self::ESTADOS_POST_ADMISION, true);
        $planificacionAprobada = in_array('ACT_VISTO_BUENO_PLANIFICACION', $actuados, true)
            || in_array($estado, self::ESTADOS_POST_PLANIFICACION, true);
        $informeFinal = $this->tieneInformeFinal($actuados, $estado);
        $cerrado = $informeFinal && (in_array('ACT_REPARTO_INSTITUCIONAL', $actuados, true)
            || in_array($estado, self::ESTADOS_FINALES, true));

        $mensajeBloqueoEjecucion = $estado === 'PENDIENTE_VISTO_BUENO'
            ? 'Primero debe cargar el MPA y esperar el Visto Bueno de la Encargada.'
            : 'Primero debe cargar el cronograma de trabajo o el MPA.';

        $pasos = [
            $this->paso(
                'recepcion',
                1,
                'Recepción',
                'Solo consulta',
                $recibido ? 'completado' : 'activo',
                null
            ),
            $this->paso(
                'admisibilidad',
                2,
                'Admisibilidad',
                'Requisitos y subsanación',
                $admision ? 'completado' : (in_array($estado, self::ESTADOS_EVALUACION, true) ? 'activo' : 'bloqueado'),
                'La Encargada debe sortear el expediente y asignarlo a su bandeja para habilitar la evaluación de admisibilidad.'
            ),
            $this->paso(
                'planificacion',
                3,
                'Cronograma / MPA',
                'Plan de trabajo',
                $planificacionAprobada ? 'completado'
                    : ($admision && in_array($estado, ['EN_PLANIFICACION', 'PENDIENTE_VISTO_BUENO'], true) ? 'activo' : 'bloqueado'),
                'Primero debe registrar la evaluación de admisibilidad y obtener la admisión del expediente.'
            ),
            $this->paso(
                'ejecucion',
                4,
                'Ejecución',
                'Investigación y descargos',
                $informeFinal ? 'completado'
                    : ($planificacionAprobada && in_array($estado, self::ESTADOS_EJECUCION, true) ? 'activo' : 'bloqueado'),
                $mensajeBloqueoEjecucion
            ),
            $this->paso(
                'cierre',
                5,
                'Cierre',
                'Informe y reparto',
                $cerrado ? 'completado'
                    : ($informeFinal && in_array($estado, self::ESTADOS_POST_INFORME, true) ? 'activo' : 'bloqueado'),
                'Primero debe registrar el informe final de la investigación para habilitar el cierre.'
            ),
        ];

        if ($estado === 'REMITIDO_UNIDAD_EXTERNA') {
            foreach ($pasos as $indice => $paso) {
                if ($indice === 0) {
                    continue;
                }

                $pasos[$indice]['estado'] = 'bloqueado';
                $pasos[$indice]['mensaje_bloqueo'] = 'El expediente está remitido a una unidad externa. Al registrar su recepción se restablecerá la etapa previa.';
            }
        }

        return $pasos;
    }

    /**
     * @return array<int, string>
     */
    private function codigosActuados(Expediente $expediente): array
    {
        if (! $expediente->relationLoaded('actuados')) {
            return [];
        }

        return $expediente->actuados
            ->pluck('tipoActuado.codigo')
            ->filter()
            ->map(fn ($codigo) => (string) $codigo)
            ->all();
    }

    /**
     * @param  array<int, string>  $actuados
     */
    private function fueRecibido(Expediente $expediente, string $estado, array $actuados): bool
    {
        if ($expediente->relationLoaded('asignacionActiva') && $expediente->asignacionActiva !== null) {
            return true;
        }

        return in_array('ACT_SORTEO_INICIAL', $actuados, true)
            || ($estado !== '' && $estado !== 'PENDIENTE_SORTEO');
    }

    /**
     * @param  array<int, string>  $actuados
     */
    private function tieneInformeFinal(array $actuados, string $estado): bool
    {
        foreach (self::ACTUADOS_INFORME_FINAL as $codigo) {
            if (in_array($codigo, $actuados, true)) {
                return true;
            }
        }

        return in_array($estado, self::ESTADOS_POST_INFORME, true);
    }

    /**
     * @return array{codigo: string, numero: int, nombre: string, descripcion: string, estado: string, mensaje_bloqueo: ?string}
     */
    private function paso(string $codigo, int $numero, string $nombre, string $descripcion, string $estado, ?string $mensajeBloqueo): array
    {
        return [
            'codigo' => $codigo,
            'numero' => $numero,
            'nombre' => $nombre,
            'descripcion' => $descripcion,
            'estado' => $estado,
            'mensaje_bloqueo' => $estado === 'bloqueado' ? $mensajeBloqueo : null,
        ];
    }
}
