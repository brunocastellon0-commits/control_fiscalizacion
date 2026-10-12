<?php

namespace App\Services;

use App\Models\Actuado;
use App\Models\CatalogoActuado;
use App\Models\CatalogoEstado;
use App\Models\Expediente;
use App\Models\Unidad;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransparenciaService
{
    public const CODIGO_ACT_SOLICITUD = 'ACT_SOLICITUD_INCOMPETENCIA';

    public const CODIGO_ACT_VB = 'ACT_VB_INCOMPETENCIA';

    /**
     * Estado de espera de visto bueno del flujo CTR-10 (respuesta literal del
     * contrato al solicitar; la causa vuelve a su estado original al rechazo
     * o con la recepción CTR-05).
     */
    public const ESTADO_ESPERA_VB = 'EN_EVALUACION';

    public function __construct(
        protected ActuadoService $actuadoService,
        protected TransferenciaUnidadService $transferenciaUnidadService,
    ) {}

    /**
     * CTR-10 / B2.2 (RN-09 Fase 5): el operador solicita incompetencia y el
     * caso pasa a espera de visto bueno de la Encargada. Transaccional:
     *
     * 1. Bloquea el expediente (lockForUpdate, patrón D-6g) y valida en
     *    profundidad que pueda solicitar (no final, no remitido) y que no
     *    haya una solicitud abierta (una sola solicitud a la vez).
     * 2. Emite ACT_SOLICITUD_INCOMPETENCIA con estadoNuevoIdExplicito =
     *    EN_EVALUACION; el estado_anterior_id del actuado conserva el estado
     *    original de la causa para restaurarlo en el rechazo o la recepción.
     * 3. No congela relojes, no crea transferencia y no toca la asignación:
     *    la causa sigue en la bandeja del operador hasta el visto bueno.
     */
    public function solicitarIncompetencia(
        Expediente $expediente,
        Usuario $operador,
        int $unidadDestinoId,
        string $motivo,
        ?string $ipOrigen = null,
    ): Actuado {
        return DB::transaction(function () use ($expediente, $operador, $unidadDestinoId, $motivo, $ipOrigen) {
            $expediente = Expediente::query()->lockForUpdate()->findOrFail($expediente->id);

            $this->validarSolicitudHabilitada($expediente);

            if ($this->solicitudAbierta($expediente) !== null) {
                throw ValidationException::withMessages([
                    'expediente' => 'Ya existe una solicitud de incompetencia pendiente de visto bueno.',
                ]);
            }

            $unidad = Unidad::query()
                ->where('id', $unidadDestinoId)
                ->where('activa', true)
                ->first();

            if ($unidad === null) {
                throw ValidationException::withMessages([
                    'unidad_destino_id' => 'La unidad indicada no existe o no está activa.',
                ]);
            }

            $estadoEspera = CatalogoEstado::where('codigo', self::ESTADO_ESPERA_VB)->firstOrFail();

            return $this->actuadoService->registerActuado(
                expediente: $expediente,
                catalogoActuado: $this->catalogoPorCodigo(self::CODIGO_ACT_SOLICITUD),
                emisor: $operador,
                descripcion: $motivo,
                metadatos: [
                    'tipo' => 'SOLICITUD_INCOMPETENCIA',
                    'unidad_destino_id' => $unidad->id,
                ],
                ipOrigen: $ipOrigen,
                estadoNuevoIdExplicito: (int) $estadoEspera->id,
            );
        }, 3);
    }

    /**
     * CTR-10 / B2.2: la Encargada resuelve la solicitud de incompetencia.
     * Transaccional:
     *
     * 1. Bloquea el expediente, exige estado EN_EVALUACION (defensa frente a
     *    la política) y una solicitud abierta (último ACT_SOLICITUD sin VB
     *    posterior).
     * 2. Aprobado: congela los relojes vigentes y remite a la unidad destino
     *    de la solicitud vía TransferenciaUnidadService (unidad en metadatos
     *    de la remisión con estado_previo_id = estado original pre-solicitud);
     *    la recepción CTR-05 restaura ese estado exacto y reanuda los relojes.
     * 3. Rechazado: emite ACT_VB_INCOMPETENCIA con estadoNuevoIdExplicito =
     *    estado original pre-solicitud, sin transferencia ni congelación.
     * 4. En ningún caso se toca la asignación: la causa vuelve sola a la
     *    bandeja del operador asignado.
     */
    public function vistoBuenoIncompetencia(
        Expediente $expediente,
        Usuario $encargada,
        bool $aprobado,
        ?string $observaciones,
        ?string $ipOrigen = null,
    ): void {
        DB::transaction(function () use ($expediente, $encargada, $aprobado, $observaciones, $ipOrigen) {
            $expediente = Expediente::query()->lockForUpdate()->findOrFail($expediente->id);

            $this->validarEstado($expediente, self::ESTADO_ESPERA_VB);

            $solicitud = $this->solicitudAbierta($expediente);

            if ($solicitud === null) {
                throw ValidationException::withMessages([
                    'aprobado' => 'No hay una solicitud de incompetencia pendiente de visto bueno.',
                ]);
            }

            $estadoOriginalId = $solicitud->estado_anterior_id;

            if ($estadoOriginalId === null) {
                throw ValidationException::withMessages([
                    'aprobado' => 'La solicitud de incompetencia no registra el estado original a restaurar.',
                ]);
            }

            $estadoOriginalId = (int) $estadoOriginalId;

            if ($aprobado) {
                $unidadDestinoId = (int) ($solicitud->contenido['unidad_destino_id'] ?? 0);

                if ($unidadDestinoId <= 0) {
                    throw ValidationException::withMessages([
                        'unidad_destino_id' => 'La solicitud de incompetencia no registra una unidad destino válida.',
                    ]);
                }

                $this->transferenciaUnidadService->remitirPorIncompetencia(
                    expediente: $expediente,
                    encargada: $encargada,
                    catalogoActuado: $this->catalogoPorCodigo(self::CODIGO_ACT_VB),
                    unidadId: $unidadDestinoId,
                    estadoPrevioId: $estadoOriginalId,
                    motivo: (string) ($solicitud->contenido['descripcion'] ?? 'Remisión por incompetencia procesal.'),
                    metadatos: [
                        'tipo' => 'REMISION_UNIDAD_INCOMPETENCIA',
                        'via' => 'INCOMPETENCIA',
                        'solicitud_actuado_id' => $solicitud->id,
                        'aprobado' => true,
                        'observaciones' => $observaciones,
                    ],
                    ipOrigen: $ipOrigen,
                );

                return;
            }

            $this->actuadoService->registerActuado(
                expediente: $expediente,
                catalogoActuado: $this->catalogoPorCodigo(self::CODIGO_ACT_VB),
                emisor: $encargada,
                descripcion: $observaciones ?? 'Rechazo de la solicitud de incompetencia procesal.',
                metadatos: [
                    'tipo' => 'INCOMPETENCIA',
                    'solicitud_actuado_id' => $solicitud->id,
                    'aprobado' => false,
                    'observaciones' => $observaciones,
                ],
                ipOrigen: $ipOrigen,
                estadoNuevoIdExplicito: $estadoOriginalId,
            );
        }, 3);
    }

    /**
     * Última solicitud de incompetencia sin visto bueno posterior (solicitud
     * abierta), o null si no hay ninguna en curso.
     */
    protected function solicitudAbierta(Expediente $expediente): ?Actuado
    {
        $ultimaSolicitud = Actuado::query()
            ->where('expediente_id', $expediente->id)
            ->whereHas('tipoActuado', fn ($query) => $query->where('codigo', self::CODIGO_ACT_SOLICITUD))
            ->orderByDesc('id')
            ->first();

        if ($ultimaSolicitud === null) {
            return null;
        }

        $vistoBuenoPosterior = Actuado::query()
            ->where('expediente_id', $expediente->id)
            ->where('id', '>', $ultimaSolicitud->id)
            ->whereHas('tipoActuado', fn ($query) => $query->where('codigo', self::CODIGO_ACT_VB))
            ->exists();

        return $vistoBuenoPosterior ? null : $ultimaSolicitud;
    }

    /**
     * Defensa en profundidad de la política solicitarIncompetencia: nunca un
     * estado final ni una causa ya remitida a unidad externa.
     */
    protected function validarSolicitudHabilitada(Expediente $expediente): void
    {
        $estado = CatalogoEstado::find($expediente->estado_actual_id);

        if ($estado === null
            || $estado->es_final
            || $estado->codigo === TransferenciaUnidadService::ESTADO_REMITIDO_UNIDAD_EXTERNA) {
            throw ValidationException::withMessages([
                'expediente' => 'El expediente no puede iniciar una solicitud de incompetencia desde su estado actual.',
            ]);
        }
    }

    /**
     * Valida que el estado actual del expediente sea el esperado.
     */
    protected function validarEstado(Expediente $expediente, string $codigoEsperado): void
    {
        $estado = $expediente->estadoActual()->first();

        if ($estado?->codigo !== $codigoEsperado) {
            throw ValidationException::withMessages([
                'expediente' => "El expediente debe estar en {$codigoEsperado}.",
            ]);
        }
    }

    /**
     * Catálogo de actuado por código.
     */
    protected function catalogoPorCodigo(string $codigo): CatalogoActuado
    {
        return CatalogoActuado::where('codigo', $codigo)->firstOrFail();
    }
}
