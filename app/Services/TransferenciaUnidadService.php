<?php

namespace App\Services;

use App\Models\Actuado;
use App\Models\CatalogoActuado;
use App\Models\CatalogoEstado;
use App\Models\Expediente;
use App\Models\Plazo;
use App\Models\TransferenciaUnidad;
use App\Models\Unidad;
use App\Models\Usuario;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransferenciaUnidadService
{
    public const ESTADO_REMITIDO_UNIDAD_EXTERNA = 'REMITIDO_UNIDAD_EXTERNA';

    public const TIPO_REMISION = 'REMISION';

    public const TIPO_RECEPCION = 'RECEPCION';

    public const CODIGO_ACT_REMISION_UNIDAD = 'ACT_REMISION_UNIDAD';

    public const CODIGO_ACT_RECEPCION_UNIDAD = 'ACT_RECEPCION_UNIDAD';

    public const ESTADO_PLAZO_VIGENTE = 'VIGENTE';

    public const ESTADO_PLAZO_SUSPENDIDO = 'SUSPENDIDO';

    public function __construct(
        protected ActuadoService $actuadoService,
        protected PlazoCalculatorService $calculadoraPlazo,
    ) {}

    /**
     * CTR-04 (RF-05): remisión física de la causa a una unidad externa.
     * Transaccional:
     *
     * 1. Bloquea el expediente (lockForUpdate, patrón D-6g) y valida que sea
     *    remisible (no final, aún no remitido).
     * 2. Registra la transferencia REMISION con `estado_previo_id` = fase
     *    procesal exacta al momento de remitir.
     * 3. Congela los relojes VIGENTES (SUSPENDIDO + fecha_pausa, patrón de
     *    TransparenciaService::congelarPlazos) y guarda sus IDs en los
     *    metadatos del actuado para reanudarlos exactos en la recepción.
     * 4. Emite ACT_REMISION_UNIDAD con hash encadenado: transiciona la causa
     *    a REMITIDO_UNIDAD_EXTERNA. La asignación activa no se toca (B2.1):
     *    la causa sale de la bandeja del operador por estado (B2.3 añade la
     *    protección de emisión mientras esté remitida).
     */
    public function remitir(
        Expediente $expediente,
        Usuario $encargada,
        int $unidadId,
        string $motivo,
        ?UploadedFile $adjunto = null,
        ?string $ipOrigen = null,
    ): TransferenciaUnidad {
        return DB::transaction(function () use ($expediente, $encargada, $unidadId, $motivo, $adjunto, $ipOrigen) {
            $expediente = Expediente::query()->lockForUpdate()->findOrFail($expediente->id);

            $this->validarRemisible($expediente);
            $unidad = $this->unidadActiva($unidadId, 'unidad_id');

            $estadoPrevioId = (int) $expediente->estado_actual_id;
            $plazosSuspendidos = $this->congelarPlazos($expediente);

            $transferencia = TransferenciaUnidad::create([
                'expediente_id' => $expediente->id,
                'unidad_id' => $unidad->id,
                'tipo' => self::TIPO_REMISION,
                'actuado_id' => null,
                'motivo' => $motivo,
                'fecha' => now(),
                'estado_previo_id' => $estadoPrevioId,
            ]);

            $actuado = $this->actuadoService->registerActuado(
                expediente: $expediente,
                catalogoActuado: $this->catalogoPorCodigo(self::CODIGO_ACT_REMISION_UNIDAD),
                emisor: $encargada,
                descripcion: $motivo,
                metadatos: [
                    'tipo' => 'REMISION_UNIDAD_EXTERNA',
                    'unidad_destino_id' => $unidad->id,
                    'plazos_suspendidos' => $plazosSuspendidos,
                ],
                ipOrigen: $ipOrigen,
                adjunto: $adjunto,
            );

            $transferencia->update(['actuado_id' => $actuado->id]);

            return $transferencia;
        }, 3);
    }

    /**
     * CTR-05 (RF-05): recepción física desde la unidad externa. Transaccional:
     *
     * 1. Bloquea el expediente y exige estado REMITIDO_UNIDAD_EXTERNA con una
     *    remisión abierta (último movimiento tipo REMISION); `unidad_origen_id`
     *    debe coincidir con la unidad destino de esa remisión (sin recepción
     *    cruzada).
     * 2. Registra la transferencia RECEPCION (unidad de origen).
     * 3. Emite ACT_RECEPCION_UNIDAD con `estadoNuevoIdExplicito` =
     *    `estado_previo_id` de la remisión abierta: restaura la fase procesal
     *    exacta, sin asumir estado fijo.
     * 4. Reanuda únicamente los relojes congelados por esa remisión,
     *    conservando exactamente los días hábiles restantes (patrón de
     *    DescargoFinancieroService::reanudarRelojEjecucion).
     */
    public function registrarRecepcion(
        Expediente $expediente,
        Usuario $encargada,
        int $unidadOrigenId,
        string $motivo,
        ?UploadedFile $adjunto = null,
        ?string $ipOrigen = null,
    ): TransferenciaUnidad {
        return DB::transaction(function () use ($expediente, $encargada, $unidadOrigenId, $motivo, $adjunto, $ipOrigen) {
            $expediente = Expediente::query()->lockForUpdate()->findOrFail($expediente->id);

            $remisionAbierta = $this->remisionAbierta($expediente);
            $this->unidadActiva($unidadOrigenId, 'unidad_origen_id');

            if ((int) $remisionAbierta->unidad_id !== $unidadOrigenId) {
                throw ValidationException::withMessages([
                    'unidad_origen_id' => 'La unidad de origen debe coincidir con la unidad destino de la remisión abierta.',
                ]);
            }

            $plazosSuspendidos = $this->plazosSuspendidosDe($remisionAbierta);

            $transferencia = TransferenciaUnidad::create([
                'expediente_id' => $expediente->id,
                'unidad_id' => $unidadOrigenId,
                'tipo' => self::TIPO_RECEPCION,
                'actuado_id' => null,
                'motivo' => $motivo,
                'fecha' => now(),
                'estado_previo_id' => null,
            ]);

            $actuado = $this->actuadoService->registerActuado(
                expediente: $expediente,
                catalogoActuado: $this->catalogoPorCodigo(self::CODIGO_ACT_RECEPCION_UNIDAD),
                emisor: $encargada,
                descripcion: $motivo,
                metadatos: [
                    'tipo' => 'RECEPCION_UNIDAD_EXTERNA',
                    'unidad_origen_id' => $unidadOrigenId,
                    'plazos_reanudados' => $plazosSuspendidos,
                ],
                ipOrigen: $ipOrigen,
                adjunto: $adjunto,
                estadoNuevoIdExplicito: (int) $remisionAbierta->estado_previo_id,
            );

            $transferencia->update(['actuado_id' => $actuado->id]);

            $this->reanudarPlazos($expediente, $plazosSuspendidos);

            return $transferencia;
        }, 3);
    }

    /**
     * CTR-10 / B2.2: remisión de la causa a la unidad externa cuando la
     * Encargada aprueba el Visto Bueno de una solicitud de incompetencia.
     * Mismo mecanismo que remitir(), con tres diferencias contractuales:
     *
     * 1. El actuado ampara lo decide el caller (ACT_VB_INCOMPETENCIA) y la
     *    transición a REMITIDO_UNIDAD_EXTERNA se fija con
     *    estadoNuevoIdExplicito (el catálogo del VB tiene destino null para
     *    que el rechazo no transicione).
     * 2. `estado_previo_id` es el estado original de la causa previo a la
     *    solicitud de incompetencia (no el estado al momento del VB): la
     *    recepción CTR-05 devuelve la causa a su fase exacta pre-solicitud.
     * 3. Sin adjunto (contrato CTR-10: `{aprobado, observaciones?}`).
     *
     * @param  array<string, mixed>  $metadatos
     */
    public function remitirPorIncompetencia(
        Expediente $expediente,
        Usuario $encargada,
        CatalogoActuado $catalogoActuado,
        int $unidadId,
        int $estadoPrevioId,
        string $motivo,
        array $metadatos = [],
        ?string $ipOrigen = null,
    ): TransferenciaUnidad {
        return DB::transaction(function () use ($expediente, $encargada, $catalogoActuado, $unidadId, $estadoPrevioId, $motivo, $metadatos, $ipOrigen) {
            $expediente = Expediente::query()->lockForUpdate()->findOrFail($expediente->id);

            $this->validarRemisible($expediente);
            $unidad = $this->unidadActiva($unidadId, 'unidad_destino_id');

            $plazosSuspendidos = $this->congelarPlazos($expediente);

            $transferencia = TransferenciaUnidad::create([
                'expediente_id' => $expediente->id,
                'unidad_id' => $unidad->id,
                'tipo' => self::TIPO_REMISION,
                'actuado_id' => null,
                'motivo' => $motivo,
                'fecha' => now(),
                'estado_previo_id' => $estadoPrevioId,
            ]);

            $estadoRemitido = CatalogoEstado::where('codigo', self::ESTADO_REMITIDO_UNIDAD_EXTERNA)->firstOrFail();

            $actuado = $this->actuadoService->registerActuado(
                expediente: $expediente,
                catalogoActuado: $catalogoActuado,
                emisor: $encargada,
                descripcion: $motivo,
                metadatos: array_merge($metadatos, [
                    'unidad_destino_id' => $unidad->id,
                    'plazos_suspendidos' => $plazosSuspendidos,
                ]),
                ipOrigen: $ipOrigen,
                estadoNuevoIdExplicito: (int) $estadoRemitido->id,
            );

            $transferencia->update(['actuado_id' => $actuado->id]);

            return $transferencia;
        }, 3);
    }

    /**
     * Valida dentro de la transacción que la causa pueda remitirse (defensa
     * en profundidad frente a la política remitirUnidad: nunca un estado
     * final ni ya remitido).
     */
    protected function validarRemisible(Expediente $expediente): void
    {
        $estado = CatalogoEstado::find($expediente->estado_actual_id);

        if ($estado === null
            || $estado->codigo === self::ESTADO_REMITIDO_UNIDAD_EXTERNA
            || $estado->es_final) {
            throw ValidationException::withMessages([
                'expediente' => 'El expediente no puede remitirse a una unidad externa desde su estado actual.',
            ]);
        }
    }

    /**
     * La remisión abierta del expediente: la causa debe estar en
     * REMITIDO_UNIDAD_EXTERNA y el último movimiento de la bitácora de
     * transferencias debe ser una REMISION que aún no tenga RECEPCION.
     *
     * @throws ValidationException Cuando no hay remisión abierta que recibir.
     */
    protected function remisionAbierta(Expediente $expediente): TransferenciaUnidad
    {
        $estado = CatalogoEstado::find($expediente->estado_actual_id);

        if ($estado?->codigo !== self::ESTADO_REMITIDO_UNIDAD_EXTERNA) {
            throw ValidationException::withMessages([
                'expediente' => 'La causa no está remitida a una unidad externa.',
            ]);
        }

        $ultima = TransferenciaUnidad::query()
            ->where('expediente_id', $expediente->id)
            ->orderByDesc('id')
            ->first();

        if ($ultima === null || $ultima->tipo !== self::TIPO_REMISION) {
            throw ValidationException::withMessages([
                'expediente' => 'No hay una remisión abierta que recibir.',
            ]);
        }

        if ($ultima->estado_previo_id === null) {
            throw ValidationException::withMessages([
                'expediente' => 'La remisión abierta no registra el estado previo a restaurar.',
            ]);
        }

        return $ultima;
    }

    /**
     * Unidad externa activa o 422 (la política ya filtró el rol; aquí se
     * valida el catálogo).
     */
    protected function unidadActiva(int $unidadId, string $clave): Unidad
    {
        $unidad = Unidad::query()
            ->where('id', $unidadId)
            ->where('activa', true)
            ->first();

        if ($unidad === null) {
            throw ValidationException::withMessages([
                $clave => 'La unidad indicada no existe o no está activa.',
            ]);
        }

        return $unidad;
    }

    /**
     * Relojes congelados por la remisión: congela todos los relojes VIGENTES
     * (patrón TransparenciaService::congelarPlazos) y devuelve sus IDs para
     * reanudar exactamente esos en la recepción.
     *
     * @return array<int, int>
     */
    protected function congelarPlazos(Expediente $expediente): array
    {
        $ids = Plazo::query()
            ->where('expediente_id', $expediente->id)
            ->where('estado', self::ESTADO_PLAZO_VIGENTE)
            ->pluck('id')
            ->all();

        if ($ids !== []) {
            Plazo::query()
                ->whereIn('id', $ids)
                ->update([
                    'estado' => self::ESTADO_PLAZO_SUSPENDIDO,
                    'fecha_pausa' => now(),
                ]);
        }

        return $ids;
    }

    /**
     * IDs de los relojes que la remisión abierta congeló (metadatos del
     * actuado ACT_REMISION_UNIDAD que la ampara).
     *
     * @return array<int, int>
     */
    protected function plazosSuspendidosDe(TransferenciaUnidad $remision): array
    {
        $actuado = $remision->actuado_id !== null
            ? Actuado::find($remision->actuado_id)
            : null;

        $ids = $actuado?->contenido['plazos_suspendidos'] ?? [];

        return array_values(array_map('intval', (array) $ids));
    }

    /**
     * Reanuda exactamente los relojes congelados por la remisión: conserva
     * la pausa histórica (fecha_pausa) y recalcula el vencimiento sumando
     * los días hábiles que restaban al momento de la pausa (mismo cálculo
     * que DescargoFinancieroService::reanudarRelojEjecucion).
     *
     * @param  array<int, int>  $plazoIds
     */
    protected function reanudarPlazos(Expediente $expediente, array $plazoIds): void
    {
        if ($plazoIds === []) {
            return;
        }

        $plazos = Plazo::query()
            ->whereIn('id', $plazoIds)
            ->where('expediente_id', $expediente->id)
            ->where('estado', self::ESTADO_PLAZO_SUSPENDIDO)
            ->get();

        foreach ($plazos as $plazo) {
            $diasRestantes = $plazo->fecha_pausa !== null
                ? $this->calculadoraPlazo->businessDaysBetween($plazo->fecha_pausa, $plazo->fecha_limite)
                : 0;

            $fechaLimite = $diasRestantes > 0
                ? $this->calculadoraPlazo->calculateDueDate(Carbon::now(), $diasRestantes)
                : Carbon::now()->endOfDay();

            $plazo->update([
                'estado' => self::ESTADO_PLAZO_VIGENTE,
                'fecha_reanudacion' => Carbon::now(),
                'fecha_limite' => $fechaLimite,
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
