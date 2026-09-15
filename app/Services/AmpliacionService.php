<?php

namespace App\Services;

use App\Models\Actuado;
use App\Models\Asignacion;
use App\Models\CatalogoActuado;
use App\Models\Expediente;
use App\Models\ParametroPlazo;
use App\Models\Plazo;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AmpliacionService
{
    public const CODIGO_ACT_SOLICITAR_AMPLIACION = 'ACT_SOLICITAR_AMPLIACION';

    public const CODIGO_ACT_APROBAR_AMPLIACION = 'ACT_APROBAR_AMPLIACION';

    public const REGLAMENTO_AC022 = 'AC_022_2018';

    public const ESTADO_EJECUCION = 'EN_EJECUCION';

    public const ESTADO_PENDIENTE_APROBACION_AMPLIACION = 'PENDIENTE_APROBACION_AMPLIACION';

    public const TIPO_PLAZO_EJECUCION = 'EJECUCION';

    public const TIPO_PLAZO_EJECUCION_AMPLIADA = 'EJECUCION_AMPLIADA';

    public function __construct(
        protected ActuadoService $actuadoService,
        protected PlazoCalculatorService $calculadoraPlazo,
    ) {}

    /**
     * US-2.6: el Técnico (solo AC022) solicita la única ampliación del plazo
     * de ejecución. Transaccional:
     *
     * 1. Valida el reglamento (AC022), el estado EN_EJECUCION, que exista un
     *    plazo EJECUCION vigente y que no exista una ampliación previa.
     * 2. Emite ACT_SOLICITAR_AMPLIACION con la justificación; el expediente
     *    pasa a PENDIENTE_APROBACION_AMPLIACION y la bandeja a la Encargada.
     *    El reloj EJECUCION sigue corriendo mientras se espera la aprobación.
     */
    public function solicitarAmpliacion(
        Expediente $expediente,
        Usuario $tecnico,
        string $justificacion,
        ?string $ipOrigen = null,
    ): Actuado {
        return DB::transaction(function () use ($expediente, $tecnico, $justificacion, $ipOrigen) {
            $this->validarReglamentoAmpliacion($expediente);
            $this->validarEstado($expediente, static::ESTADO_EJECUCION);
            $this->validarSinAmpliacionPrevia($expediente);
            $this->validarRelojEjecucionVigente($expediente);

            $encargada = $this->resolverEncargada();

            return $this->actuadoService->registerActuado(
                expediente: $expediente,
                catalogoActuado: $this->catalogoPorCodigo(static::CODIGO_ACT_SOLICITAR_AMPLIACION),
                emisor: $tecnico,
                descripcion: $justificacion,
                usuarioDestinoId: $encargada->id,
                metadatos: ['tipo' => 'AMPLIACION'],
                ipOrigen: $ipOrigen,
            );
        }, 3);
    }

    /**
     * US-2.6: la Encargada aprueba la ampliación. Transaccional:
     *
     * 1. Valida el estado PENDIENTE_APROBACION_AMPLIACION y la existencia del
     *    plazo EJECUCION vigente que se va a ampliar.
     * 2. Cierra el plazo EJECUCION original (queda CERRADO como evidencia del
     *    plazo base de 10 días) y emite ACT_APROBAR_AMPLIACION, que retorna el
     *    expediente a EN_EJECUCION y la bandeja al Técnico original.
     * 3. Abre EJECUCION_AMPLIADA por 5 días hábiles, con fecha límite calculada
     *    sobre el vencimiento original (PlazoCalculatorService), preservando la
     *    ventana total de 10+5 días hábiles desde el inicio.
     */
    public function aprobarAmpliacion(
        Expediente $expediente,
        Usuario $encargada,
        ?string $ipOrigen = null,
    ): Actuado {
        return DB::transaction(function () use ($expediente, $encargada, $ipOrigen) {
            $this->validarEstado($expediente, static::ESTADO_PENDIENTE_APROBACION_AMPLIACION);

            $plazoOriginal = $this->plazoEjecucionVigente($expediente);

            if ($plazoOriginal === null) {
                throw ValidationException::withMessages([
                    'expediente' => 'No hay un plazo de EJECUCION vigente que ampliar.',
                ]);
            }

            $operadorOriginal = $this->operadorOriginalDelExpediente($expediente);

            $parametroAmpliacion = $this->parametroAmpliacion($expediente);
            $diasAmpliacion = $parametroAmpliacion->dias_habiles;

            $fechaLimiteAmpliada = $this->calculadoraPlazo->calculateDueDate(
                $plazoOriginal->fecha_limite,
                $diasAmpliacion,
            );

            $plazoOriginal->update(['estado' => 'CERRADO']);

            $actuadoAprobacion = $this->actuadoService->registerActuado(
                expediente: $expediente,
                catalogoActuado: $this->catalogoPorCodigo(static::CODIGO_ACT_APROBAR_AMPLIACION),
                emisor: $encargada,
                descripcion: 'Ampliación de plazo aprobada.',
                usuarioDestinoId: $operadorOriginal->id,
                metadatos: [
                    'tipo' => 'AMPLIACION',
                    'dias_habiles_otorgados' => $diasAmpliacion,
                    'fecha_limite_anterior' => $plazoOriginal->fecha_limite->format('Y-m-d'),
                    'fecha_limite_ampliada' => $fechaLimiteAmpliada->format('Y-m-d'),
                ],
                ipOrigen: $ipOrigen,
            );

            Plazo::create([
                'expediente_id' => $expediente->id,
                'tipo_plazo' => static::TIPO_PLAZO_EJECUCION_AMPLIADA,
                'parametro_plazo_id' => $parametroAmpliacion->id,
                'dias_habiles_otorgados' => $diasAmpliacion,
                'fecha_inicio' => now(),
                'fecha_limite' => $fechaLimiteAmpliada,
                'estado' => 'VIGENTE',
                'actuado_disparador_id' => $actuadoAprobacion->id,
            ]);

            return $actuadoAprobacion;
        }, 3);
    }

    /**
     * La ampliación de plazo aplica exclusivamente al Acuerdo AC-022.
     */
    protected function validarReglamentoAmpliacion(Expediente $expediente): void
    {
        if ($expediente->reglamento?->codigo !== static::REGLAMENTO_AC022) {
            throw ValidationException::withMessages([
                'expediente' => 'La ampliación de plazo aplica solo al Acuerdo AC-022.',
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
     * La ampliación es única por expediente: si ya existe un plazo
     * EJECUCION_AMPLIADA (sin importar su estado) se rechaza la solicitud.
     */
    protected function validarSinAmpliacionPrevia(Expediente $expediente): void
    {
        $existe = Plazo::where('expediente_id', $expediente->id)
            ->where('tipo_plazo', static::TIPO_PLAZO_EJECUCION_AMPLIADA)
            ->exists();

        if ($existe) {
            throw ValidationException::withMessages([
                'expediente' => 'El expediente ya dispone de una ampliación de plazo (única por causa).',
            ]);
        }
    }

    /**
     * La solicitud exige que el reloj base de EJECUCION esté corriendo.
     */
    protected function validarRelojEjecucionVigente(Expediente $expediente): void
    {
        if ($this->plazoEjecucionVigente($expediente) === null) {
            throw ValidationException::withMessages([
                'expediente' => 'No hay un plazo de EJECUCION vigente que ampliar.',
            ]);
        }
    }

    /**
     * Plazo base de ejecución vigente del expediente, o null.
     */
    protected function plazoEjecucionVigente(Expediente $expediente): ?Plazo
    {
        return Plazo::where('expediente_id', $expediente->id)
            ->where('tipo_plazo', static::TIPO_PLAZO_EJECUCION)
            ->where('estado', 'VIGENTE')
            ->first();
    }

    /**
     * Parámetro normativo de la ampliación (5 días hábiles) del reglamento.
     */
    protected function parametroAmpliacion(Expediente $expediente): ParametroPlazo
    {
        return ParametroPlazo::where('reglamento_id', $expediente->reglamento_id)
            ->where('tipo_plazo', static::TIPO_PLAZO_EJECUCION_AMPLIADA)
            ->whereNull('subtipo')
            ->firstOrFail();
    }

    /**
     * Encargada activa de la unidad (sistema no depende de IDs hardcodeados).
     */
    protected function resolverEncargada(): Usuario
    {
        return Usuario::query()
            ->where('activo', true)
            ->whereHas('rol', fn ($query) => $query->where('codigo', Rol::CODIGO_ENCARGADA))
            ->orderBy('id')
            ->firstOrFail();
    }

    /**
     * Operador con la asignación del expediente previa al paso por la bandeja
     * de la Encargada (búsqueda sobre asignaciones inactivas, la más reciente).
     */
    protected function operadorOriginalDelExpediente(Expediente $expediente): Usuario
    {
        $asignacionOperador = Asignacion::where('expediente_id', $expediente->id)
            ->where('activa', false)
            ->orderByDesc('fecha_asignacion')
            ->first();

        if ($asignacionOperador === null) {
            throw ValidationException::withMessages([
                'expediente' => 'No se encontró la asignación original del operador.',
            ]);
        }

        return Usuario::findOrFail($asignacionOperador->usuario_id);
    }

    /**
     * Catálogo de actuado por código.
     */
    protected function catalogoPorCodigo(string $codigo): CatalogoActuado
    {
        return CatalogoActuado::where('codigo', $codigo)->firstOrFail();
    }
}
