<?php

namespace App\Services;

use App\Models\Actuado;
use App\Models\CatalogoActuado;
use App\Models\Expediente;
use App\Models\ParametroPlazo;
use App\Models\Plazo;
use App\Models\Usuario;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DescargoFinancieroService
{
    public const CODIGO_ACT_COMUNICACION = 'ACT_COMUNICACION_HALLAZGOS';

    public const CODIGO_ACT_RECEPCION = 'ACT_RECEPCION_DESCARGOS';

    public const CODIGO_INFORME_FINANCIERO_CON_RESPONSABILIDAD = 'ACT_INFORME_AUDITORIA_FINANCIERA_CON_RESPONSABILIDAD';

    public const CODIGO_INFORME_FINANCIERO_SIN_RESPONSABILIDAD = 'ACT_INFORME_AUDITORIA_FINANCIERA_SIN_RESPONSABILIDAD';

    /**
     * Informes finales de auditoría financiera (AC055). Su emisión exige la
     * fase de descargos previa (Bloqueo de Salida, RN-09).
     *
     * @var array<int, string>
     */
    public const CODIGOS_INFORMES_FINANCIEROS = [
        self::CODIGO_INFORME_FINANCIERO_CON_RESPONSABILIDAD,
        self::CODIGO_INFORME_FINANCIERO_SIN_RESPONSABILIDAD,
    ];

    public const REGLAMENTO_AC055 = 'AC_055_2018';

    public const ESTADO_EJECUCION = 'EN_EJECUCION';

    public const TIPO_PLAZO_EJECUCION = 'EJECUCION';

    public const TIPO_PLAZO_DESCARGOS = 'DESCARGOS';

    public const ESTADO_PLAZO_VIGENTE = 'VIGENTE';

    public const ESTADO_PLAZO_SUSPENDIDO = 'SUSPENDIDO';

    public const ESTADO_PLAZO_CUMPLIDO = 'CUMPLIDO';

    public function __construct(
        protected ActuadoService $actuadoService,
        protected PlazoCalculatorService $calculadoraPlazo,
    ) {}

    /**
     * RN-09 (Fase 1, AC055): el auditor financiero comunica los hallazgos a
     * los auditados. Transaccional:
     *
     * 1. Valida el reglamento AC055, el estado EN_EJECUCION, que exista un
     *    reloj principal (EJECUCION) VIGENTE y que no haya una fase de
     *    descargos ya registrada (única por causa).
     * 2. Emite ACT_COMUNICACION_HALLAZGOS con el oficio adjunto (no-op de
     *    estado: el expediente continúa en EN_EJECUCION y la bandeja queda
     *    en el auditor).
     * 3. Congela el reloj principal: EJECUCION -> SUSPENDIDO con
     *    fecha_pausa = hoy; el motor de cálculo lo ignora mientras corre
     *    el sub-reloj.
     * 4. Abre el sub-reloj estricto DESCARGOS por los días hábiles del
     *    parámetro (5, AC055) desde hoy.
     */
    public function comunicarHallazgos(
        Expediente $expediente,
        Usuario $auditor,
        string $descripcion,
        ?UploadedFile $adjunto,
        ?string $ipOrigen = null,
    ): Actuado {
        return DB::transaction(function () use ($expediente, $auditor, $descripcion, $adjunto, $ipOrigen) {
            $this->validarReglamento($expediente);
            $this->validarEstado($expediente, static::ESTADO_EJECUCION);
            $this->validarRelojEjecucionVigente($expediente);
            $this->validarSinDescargosPrevios($expediente);

            $actuado = $this->actuadoService->registerActuado(
                expediente: $expediente,
                catalogoActuado: $this->catalogoPorCodigo(static::CODIGO_ACT_COMUNICACION),
                emisor: $auditor,
                descripcion: $descripcion,
                metadatos: ['tipo' => 'COMUNICACION_HALLAZGOS'],
                ipOrigen: $ipOrigen,
                adjunto: $adjunto,
                estadoNuevoIdExplicito: $expediente->estado_actual_id,
            );

            $this->congelarRelojEjecucion($expediente);
            $this->abrirSubRelojDescargos($expediente, $actuado);

            return $actuado;
        }, 3);
    }

    /**
     * RN-09 (Fase 5, AC055): el auditor financiero recibe los descargos de
     * los auditados. Transaccional:
     *
     * 1. Valida que exista el sub-reloj DESCARGOS VIGENTE y el reloj
     *    principal EJECUCION SUSPENDIDO.
     * 2. Emite ACT_RECEPCION_DESCARGOS con el escrito adjunto (no-op de
     *    estado, la bandeja sigue en el auditor).
     * 3. Cierra el sub-reloj: DESCARGOS -> CUMPLIDO, con referencia al
     *    actuado de recepción (evidencia de cierre).
     * 4. Reanuda el reloj principal: EJECUCION -> VIGENTE, fecha_reanudacion
     *    = hoy y fecha_limite recalculada como hoy + los días hábiles que
     *    restaban al momento de la pausa (businessDaysBetween). Si no resta
     *    ningún día hábil, el límite queda en el mismo día de reanudación.
     */
    public function recibirDescargos(
        Expediente $expediente,
        Usuario $auditor,
        string $descripcion,
        ?UploadedFile $adjunto,
        ?string $ipOrigen = null,
    ): Actuado {
        return DB::transaction(function () use ($expediente, $auditor, $descripcion, $adjunto, $ipOrigen) {
            $subReloj = $this->plazoDescargosVigente($expediente);
            $relojPrincipal = $this->plazoEjecucionSuspendido($expediente);

            $actuado = $this->actuadoService->registerActuado(
                expediente: $expediente,
                catalogoActuado: $this->catalogoPorCodigo(static::CODIGO_ACT_RECEPCION),
                emisor: $auditor,
                descripcion: $descripcion,
                metadatos: ['tipo' => 'RECEPCION_DESCARGOS'],
                ipOrigen: $ipOrigen,
                adjunto: $adjunto,
                estadoNuevoIdExplicito: $expediente->estado_actual_id,
            );

            $subReloj->update([
                'estado' => static::ESTADO_PLAZO_CUMPLIDO,
                'actuado_cierre_id' => $actuado->id,
            ]);

            $this->reanudarRelojEjecucion($relojPrincipal);

            return $actuado;
        }, 3);
    }

    /**
     * Bloqueo de Salida (RN-09): un Informe Final de auditoría financiera no
     * puede emitirse si el NUREJ no registró previamente ACT_RECEPCION_DESCARGOS.
     * Lanza 422 (ValidationException) — es la defensa en profundidad del
     * endpoint genérico de emisión de actuados.
     */
    public function validarFaseDescargosPrevia(Expediente $expediente): void
    {
        $tieneDescargos = Actuado::where('expediente_id', $expediente->id)
            ->whereHas('tipoActuado', fn ($query) => $query->where('codigo', static::CODIGO_ACT_RECEPCION))
            ->exists();

        if (! $tieneDescargos) {
            throw ValidationException::withMessages([
                'catalogo_actuado_id' => 'La emisión del Informe Final de auditoría financiera exige la fase de descargos previa (RN-09).',
            ]);
        }
    }

    /**
     * Indica si el código de catálogo corresponde a un Informe Final de
     * auditoría financiera (sujeto al Bloqueo de Salida).
     */
    public function esInformeFinanciero(string $codigo): bool
    {
        return in_array($codigo, static::CODIGOS_INFORMES_FINANCIEROS, true);
    }

    /**
     * La fase de descargos aplica exclusivamente a la auditoría financiera
     * (Acuerdo AC-055).
     */
    protected function validarReglamento(Expediente $expediente): void
    {
        if ($expediente->reglamento?->codigo !== static::REGLAMENTO_AC055) {
            throw ValidationException::withMessages([
                'expediente' => 'La fase de descargos aplica solo al Acuerdo AC-055.',
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
     * La comunicación exige que el reloj principal de EJECUCION esté corriendo.
     */
    protected function validarRelojEjecucionVigente(Expediente $expediente): void
    {
        $existe = Plazo::where('expediente_id', $expediente->id)
            ->where('tipo_plazo', static::TIPO_PLAZO_EJECUCION)
            ->where('estado', static::ESTADO_PLAZO_VIGENTE)
            ->exists();

        if (! $existe) {
            throw ValidationException::withMessages([
                'expediente' => 'No hay un reloj de EJECUCION vigente que pausar.',
            ]);
        }
    }

    /**
     * La fase de descargos es única por expediente: si ya existe un plazo
     * DESCARGOS (en cualquier estado) no se admite una nueva comunicación.
     */
    protected function validarSinDescargosPrevios(Expediente $expediente): void
    {
        $existe = Plazo::where('expediente_id', $expediente->id)
            ->where('tipo_plazo', static::TIPO_PLAZO_DESCARGOS)
            ->exists();

        if ($existe) {
            throw ValidationException::withMessages([
                'expediente' => 'El expediente ya registra una fase de descargos (única por causa).',
            ]);
        }
    }

    /**
     * Congela el reloj principal de EJECUCION vigente.
     */
    protected function congelarRelojEjecucion(Expediente $expediente): void
    {
        Plazo::where('expediente_id', $expediente->id)
            ->where('tipo_plazo', static::TIPO_PLAZO_EJECUCION)
            ->where('estado', static::ESTADO_PLAZO_VIGENTE)
            ->update([
                'estado' => static::ESTADO_PLAZO_SUSPENDIDO,
                'fecha_pausa' => now(),
            ]);
    }

    /**
     * Abre el sub-reloj estricto de descargos por los días hábiles del
     * parámetro normativo (AC055), calculados desde hoy.
     */
    protected function abrirSubRelojDescargos(Expediente $expediente, Actuado $actuado): void
    {
        $parametro = ParametroPlazo::where('reglamento_id', $expediente->reglamento_id)
            ->where('tipo_plazo', static::TIPO_PLAZO_DESCARGOS)
            ->whereNull('subtipo')
            ->firstOrFail();

        Plazo::create([
            'expediente_id' => $expediente->id,
            'tipo_plazo' => static::TIPO_PLAZO_DESCARGOS,
            'parametro_plazo_id' => $parametro->id,
            'dias_habiles_otorgados' => $parametro->dias_habiles,
            'fecha_inicio' => now(),
            'fecha_limite' => $this->calculadoraPlazo->calculateDueDate(now(), $parametro->dias_habiles),
            'estado' => static::ESTADO_PLAZO_VIGENTE,
            'actuado_disparador_id' => $actuado->id,
        ]);
    }

    /**
     * Reanuda el reloj principal congelado: conserva la pausa histórica
     * (fecha_pausa) y recalcula su vencimiento sumando los días hábiles que
     * restaban al momento de la pausa.
     */
    protected function reanudarRelojEjecucion(Plazo $reloj): void
    {
        $diasRestantes = $reloj->fecha_pausa !== null
            ? $this->calculadoraPlazo->businessDaysBetween($reloj->fecha_pausa, $reloj->fecha_limite)
            : 0;

        $fechaLimite = $diasRestantes > 0
            ? $this->calculadoraPlazo->calculateDueDate(Carbon::now(), $diasRestantes)
            : Carbon::now()->endOfDay();

        $reloj->update([
            'estado' => static::ESTADO_PLAZO_VIGENTE,
            'fecha_reanudacion' => Carbon::now(),
            'fecha_limite' => $fechaLimite,
        ]);
    }

    /**
     * Sub-reloj DESCARGOS vigente del expediente, o 422 si no existe.
     */
    protected function plazoDescargosVigente(Expediente $expediente): Plazo
    {
        $subReloj = Plazo::where('expediente_id', $expediente->id)
            ->where('tipo_plazo', static::TIPO_PLAZO_DESCARGOS)
            ->where('estado', static::ESTADO_PLAZO_VIGENTE)
            ->first();

        if ($subReloj === null) {
            throw ValidationException::withMessages([
                'expediente' => 'No hay un sub-reloj de DESCARGOS vigente que cerrar.',
            ]);
        }

        return $subReloj;
    }

    /**
     * Reloj principal EJECUCION suspendido del expediente, o 422 si no existe.
     */
    protected function plazoEjecucionSuspendido(Expediente $expediente): Plazo
    {
        $reloj = Plazo::where('expediente_id', $expediente->id)
            ->where('tipo_plazo', static::TIPO_PLAZO_EJECUCION)
            ->where('estado', static::ESTADO_PLAZO_SUSPENDIDO)
            ->first();

        if ($reloj === null) {
            throw ValidationException::withMessages([
                'expediente' => 'No hay un reloj de EJECUCION suspendido que reanudar.',
            ]);
        }

        return $reloj;
    }

    /**
     * Catálogo de actuado por código.
     */
    protected function catalogoPorCodigo(string $codigo): CatalogoActuado
    {
        return CatalogoActuado::where('codigo', $codigo)->firstOrFail();
    }
}
