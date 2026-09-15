<?php

namespace App\Services;

use App\Models\Actuado;
use App\Models\Asignacion;
use App\Models\CatalogoActuado;
use App\Models\Expediente;
use App\Models\Plazo;
use App\Models\Rol;
use App\Models\Usuario;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlanificacionService
{
    public const CODIGO_ACT_CRONOGRAMA = 'ACT_CRONOGRAMA_TRABAJO';

    public const CODIGO_ACT_MPA = 'ACT_MPA';

    public const CODIGO_ACT_VISTO_BUENO = 'ACT_VISTO_BUENO_PLANIFICACION';

    public const CODIGO_ACT_DEVOLUCION = 'ACT_DEVOLUCION_OBSERVACION';

    public const REGLAMENTO_AC022 = 'AC_022_2018';

    public const REGLAMENTO_AC054 = 'AC_054_2018';

    public const REGLAMENTO_AC055 = 'AC_055_2018';

    public const ESTADO_PLANIFICACION = 'EN_PLANIFICACION';

    public const ESTADO_PENDIENTE_VISTO_BUENO = 'PENDIENTE_VISTO_BUENO';

    public const TIPO_PLAZO_PLANIFICACION = 'PLANIFICACION';

    public const METADATO_FECHA_LIMITE = 'fecha_limite';

    public function __construct(
        protected ActuadoService $actuadoService,
    ) {}

    /**
     * US-2.4: carga la planificación del expediente y la remite a la bandeja
     * de la Encargada para su Visto Bueno. Transaccional:
     *
     * 1. Valida que el expediente esté en EN_PLANIFICACION.
     * 2. Resuelve el actuado según el reglamento: AC_022_2018 → Cronograma
     *    (Técnico); AC_054_2018/AC_055_2018 → MPA (Auditor). El MPA exige
     *    fecha_limite_propuesta, que se guarda en metadatos del actuado.
     * 3. Cierra el plazo de PLANIFICACION (el operador cumplió al entregar).
     * 4. Emite el actuado vía ActuadoService, que reasigna la bandeja del
     *    operador a la Encargada y transiciona a PENDIENTE_VISTO_BUENO.
     */
    public function cargarPlanificacion(
        Expediente $expediente,
        Usuario $emisor,
        string $descripcion,
        ?string $fechaLimitePropuesta,
        ?UploadedFile $adjunto = null,
    ): Actuado {
        return DB::transaction(function () use ($expediente, $emisor, $descripcion, $fechaLimitePropuesta, $adjunto) {
            $this->validarEstado($expediente, static::ESTADO_PLANIFICACION);

            $catalogo = $this->catalogoSegunReglamento($expediente);

            $metadatos = ['tipo' => 'PLANIFICACION'];

            if ($catalogo->codigo === static::CODIGO_ACT_MPA) {
                $metadatos[static::METADATO_FECHA_LIMITE] = $this->validarFechaLimite($fechaLimitePropuesta)->format('Y-m-d');
            }

            $this->cerrarPlazoPlanificacion($expediente);

            $encargada = $this->resolverEncargada();

            return $this->actuadoService->registerActuado(
                expediente: $expediente,
                catalogoActuado: $catalogo,
                emisor: $emisor,
                descripcion: $descripcion,
                usuarioDestinoId: $encargada->id,
                metadatos: $metadatos,
                adjunto: $adjunto,
            );
        }, 3);
    }

    /**
     * US-2.4: la Encargada emite el Visto Bueno a la Planificación, que
     * arranca el reloj de ejecución. Transaccional:
     *
     * 1. Valida que el expediente esté en PENDIENTE_VISTO_BUENO.
     * 2. Si la planificación cargada fue un MPA (AC054/055), el plazo de
     *    EJECUCION usa la fecha límite dinámica propuesta (RN-05). Para el
     *    Cronograma (AC022) el reloj corre los 10 días hábiles base.
     * 3. Reasigna la bandeja de la Encargada al operador original (buscando
     *    su asignación previa inactiva).
     */
    public function aprobarVistoBueno(Expediente $expediente, Usuario $encargada, string $descripcion): Actuado
    {
        return DB::transaction(function () use ($expediente, $encargada, $descripcion) {
            $this->validarEstado($expediente, static::ESTADO_PENDIENTE_VISTO_BUENO);

            $planificacion = $this->planificacionMasReciente($expediente);

            $fechaLimite = $this->fechaLimiteDePlanificacion($planificacion);

            $operadorOriginal = $this->operadorOriginalDelExpediente($expediente);

            return $this->actuadoService->registerActuado(
                expediente: $expediente,
                catalogoActuado: $this->catalogoPorCodigo(static::CODIGO_ACT_VISTO_BUENO),
                emisor: $encargada,
                descripcion: $descripcion,
                usuarioDestinoId: $operadorOriginal->id,
                metadatos: ['tipo' => 'VISTO_BUENO'],
                fechaLimiteExplicita: $fechaLimite,
            );
        }, 3);
    }

    /**
     * US-2.5: la Encargada devuelve la planificación con observaciones.
     * Transaccional:
     *
     * 1. Valida que el expediente esté en PENDIENTE_VISTO_BUENO.
     * 2. Resuelve el operador original (última asignación inactiva: quien
     *    envió la planificación).
     * 3. Emite ACT_DEVOLUCION_OBSERVACION vía ActuadoService, que desactiva
     *    la bandeja de la Encargada, reasigna al operador original, retorna
     *    el expediente a EN_PLANIFICACION y reabre el plazo de PLANIFICACION
     *    (2 días hábiles). El plazo anterior permanece CERRADO como evidencia.
     */
    public function devolverPlanificacion(Expediente $expediente, Usuario $encargada, string $justificacion): Actuado
    {
        return DB::transaction(function () use ($expediente, $encargada, $justificacion) {
            $this->validarEstado($expediente, static::ESTADO_PENDIENTE_VISTO_BUENO);

            $operadorOriginal = $this->operadorOriginalDelExpediente($expediente);

            return $this->actuadoService->registerActuado(
                expediente: $expediente,
                catalogoActuado: $this->catalogoPorCodigo(static::CODIGO_ACT_DEVOLUCION),
                emisor: $encargada,
                descripcion: $justificacion,
                usuarioDestinoId: $operadorOriginal->id,
                metadatos: ['tipo' => 'DEVOLUCION'],
            );
        }, 3);
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
     * Resuelve el actuado de planificación según el reglamento del expediente:
     * AC_022_2018 → Cronograma; AC_054/055 → MPA. Otros acuerdos no soportan
     * planificación en esta etapa.
     */
    protected function catalogoSegunReglamento(Expediente $expediente): CatalogoActuado
    {
        $codigo = match ($expediente->reglamento?->codigo) {
            static::REGLAMENTO_AC022 => static::CODIGO_ACT_CRONOGRAMA,
            static::REGLAMENTO_AC054, static::REGLAMENTO_AC055 => static::CODIGO_ACT_MPA,
            default => null,
        };

        if ($codigo === null) {
            throw ValidationException::withMessages([
                'expediente' => 'El reglamento del expediente no define una planificación (Cronograma o MPA).',
            ]);
        }

        return $this->catalogoPorCodigo($codigo);
    }

    /**
     * Valida la fecha límite propuesta del MPA: obligatoria, con formato
     * calendario estricto y mayor a la fecha de hoy.
     */
    protected function validarFechaLimite(?string $fechaLimitePropuesta): Carbon
    {
        if ($fechaLimitePropuesta === null || $fechaLimitePropuesta === '') {
            throw ValidationException::withMessages([
                'fecha_limite_propuesta' => 'El MPA exige una fecha límite propuesta.',
            ]);
        }

        $fecha = Carbon::createFromFormat('Y-m-d', $fechaLimitePropuesta);

        if ($fecha === false || $fecha->format('Y-m-d') !== $fechaLimitePropuesta) {
            throw ValidationException::withMessages([
                'fecha_limite_propuesta' => 'La fecha límite debe tener el formato YYYY-MM-DD.',
            ]);
        }

        if ($fecha->startOfDay()->lessThanOrEqualTo(Carbon::today())) {
            throw ValidationException::withMessages([
                'fecha_limite_propuesta' => 'La fecha límite debe ser mayor a la fecha de hoy.',
            ]);
        }

        return $fecha;
    }

    /**
     * Cierra el plazo de planificación vigente (el operador ya entregó).
     */
    protected function cerrarPlazoPlanificacion(Expediente $expediente): void
    {
        Plazo::where('expediente_id', $expediente->id)
            ->where('tipo_plazo', static::TIPO_PLAZO_PLANIFICACION)
            ->where('estado', 'VIGENTE')
            ->update(['estado' => 'CERRADO']);
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
     * Último actuado de planificación cargado (Cronograma o MPA).
     */
    protected function planificacionMasReciente(Expediente $expediente): ?Actuado
    {
        return Actuado::where('expediente_id', $expediente->id)
            ->whereHas('tipoActuado', fn ($query) => $query->whereIn('codigo', [
                static::CODIGO_ACT_CRONOGRAMA,
                static::CODIGO_ACT_MPA,
            ]))
            ->latest('id')
            ->first();
    }

    /**
     * Fecha límite dinámica guardada en el MPA (si la planificación fue un MPA).
     */
    protected function fechaLimiteDePlanificacion(?Actuado $planificacion): ?Carbon
    {
        if ($planificacion === null) {
            return null;
        }

        $fechaLimite = $planificacion->contenido[static::METADATO_FECHA_LIMITE] ?? null;

        return is_string($fechaLimite) ? Carbon::parse($fechaLimite) : null;
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
