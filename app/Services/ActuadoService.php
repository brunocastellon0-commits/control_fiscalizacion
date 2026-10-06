<?php

namespace App\Services;

use App\Models\Actuado;
use App\Models\Asignacion;
use App\Models\CatalogoActuado;
use App\Models\CatalogoEstado;
use App\Models\Expediente;
use App\Models\ParametroPlazo;
use App\Models\Plazo;
use App\Models\Usuario;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ActuadoService
{
    /**
     * Código del actuado automático que cierra el hueco AUD-0021 #2:
     * toda transición que aterrice en ADMITIDO continúa a EN_PLANIFICACION.
     */
    public const CODIGO_PASO_PLANIFICACION = 'ACT_PASO_PLANIFICACION';

    /**
     * Código del actuado de salida de éxito de EN_SUBSANACION (RN-03,
     * AUD-0032 / B1.4): acepta la subsanación presentada, retorna el
     * expediente a EN_EVALUACION y cierra su reloj de subsanación.
     */
    public const CODIGO_ACEPTACION_SUBSANACION = 'ACT_SUBSANACION_ACEPTADA';

    protected ?int $estadoAdmitidoId = null;

    protected bool $estadoAdmitidoResuelto = false;

    public function __construct(
        protected PlazoCalculatorService $calculadoraPlazo,
        protected AdjuntoService $adjuntoService,
    ) {}

    /**
     * Mapea cada código de actuado del catálogo con el tipo de plazo que abre.
     * Fuera de este mapa, el actuado no dispara ningún plazo.
     *
     * El reloj de investigación (EJECUCION) NO arranca con la admisión: lo
     * hace recién con el Visto Bueno a la Planificación (RN-04/RN-05). La
     * admisión solo abre el plazo de planificación del Técnico (2 días hábiles
     * para cargar el Cronograma).
     */
    protected const MAPA_TIPO_PLAZO = [
        'ACT_SORTEO_INICIAL' => 'EVALUACION',
        'ACT_OBSERVACION' => 'SUBSANACION',
        'ACT_ADMISION' => 'PLANIFICACION',
        'ACT_VISTO_BUENO_PLANIFICACION' => 'EJECUCION',
        // Impugnaciones (RN-08)
        'ACT_RECHAZO' => 'IMPUGNACION_REMITIR',           // 1 día: el operador remite a la Encargada
        'ACT_REMITIR_IMPUGNACION' => 'IMPUGNACION_RESOLVER', // 3 días: la Encargada resuelve
        'ACT_RESOLUCION_REVOCA_RECHAZO' => 'PLANIFICACION',  // Revocación: retorna a planificación del operador
        'ACT_DEVOLUCION_OBSERVACION' => 'PLANIFICACION',     // Devolución de la Encargada: reabre el plazo del operador
    ];

    /**
     * B1.3 (AUD-0030): mapea cada código de actuado con los tipos de plazo que
     * DEBE cerrar al emitirse, es decir, los relojes de la fase que el actuado
     * concluye. Fuera de este mapa ningún plazo se cierra automáticamente: las
     * pausas de descargos, las congelaciones por transparencia (SUSPENDIDO) y
     * los relojes de fases todavía vivas quedan intactos.
     *
     * B1.4 (AUD-0031/0032): SUBSANACION se cierra con la aceptación de la
     * subsanación (su salida de éxito). El archivo por abandono no figura
     * aquí: transiciona el reloj a VENCIDO por sí mismo. Los relojes huérfanos
     * de SUBSANACION fuera de la fase quedan fuera de alcance de B1.4
     * (higiene documentada como hallazgo residual).
     */
    protected const MAPA_CIERRA_PLAZO = [
        // Admisibilidad: salir de EN_EVALUACION concluye su reloj.
        'ACT_ADMISION' => ['EVALUACION'],
        'ACT_OBSERVACION' => ['EVALUACION'],
        // Subsanación aceptada: concluye el reloj de la fase (B1.4, RN-03).
        self::CODIGO_ACEPTACION_SUBSANACION => ['SUBSANACION'],
        // Investigación: el informe final concluye la ejecución.
        'ACT_INFORME_FINAL' => ['EJECUCION', 'EJECUCION_AMPLIADA'],
        'ACT_INFORME_AUDITORIA_FINANCIERA_CON_RESPONSABILIDAD' => ['EJECUCION', 'EJECUCION_AMPLIADA'],
        'ACT_INFORME_AUDITORIA_FINANCIERA_SIN_RESPONSABILIDAD' => ['EJECUCION', 'EJECUCION_AMPLIADA'],
        'ACT_INFORME_TECNICO_CON_RESPONSABILIDAD' => ['EJECUCION', 'EJECUCION_AMPLIADA'],
        'ACT_INFORME_TECNICO_SIN_RESPONSABILIDAD' => ['EJECUCION', 'EJECUCION_AMPLIADA'],
        'ACT_INFORME_TECNICO_CON_RESPONSABILIDAD_RECOMENDACION' => ['EJECUCION', 'EJECUCION_AMPLIADA'],
        'ACT_INFORME_TECNICO_SIN_RESPONSABILIDAD_RECOMENDACION' => ['EJECUCION', 'EJECUCION_AMPLIADA'],
        'ACT_INFORME_AUDITORIA_JURIDICA_CON_RESPONSABILIDAD' => ['EJECUCION', 'EJECUCION_AMPLIADA'],
        'ACT_INFORME_AUDITORIA_JURIDICA_SIN_RESPONSABILIDAD' => ['EJECUCION', 'EJECUCION_AMPLIADA'],
        // Cierre jerárquico y formal: red de seguridad del reloj de ejecución.
        'ACT_VISTO_BUENO_FINAL' => ['EJECUCION', 'EJECUCION_AMPLIADA'],
        'ACT_REPARTO_INSTITUCIONAL' => ['EJECUCION', 'EJECUCION_AMPLIADA'],
    ];

    /**
     * Registra un actuado de forma inmutable (append-only), transiciona el
     * estado del expediente, actualiza la bandeja y abre plazos si aplica.
     *
     * El `hash_anterior` y `hash_actuado` los calcula el trigger de MySQL.
     *
     * @param  Carbon|null  $fechaLimiteExplicita  Fecha límite calendario para
     *                                             plazos de límite fijo (ej. MPA de AC054/055): anula el cálculo
     *                                             por días hábiles. `null` conserva el cálculo normativo estándar.
     * @param  int|null  $estadoNuevoIdExplicito  Estado destino override para
     *                                            actuados cuyo catálogo no define un destino (RN-10): recibe el
     *                                            estado actual del expediente como no-op. `null` usa el catálogo.
     */
    public function registerActuado(
        Expediente $expediente,
        CatalogoActuado $catalogoActuado,
        Usuario $emisor,
        string $descripcion,
        ?int $usuarioDestinoId = null,
        array $metadatos = [],
        ?string $ipOrigen = null,
        ?UploadedFile $adjunto = null,
        ?Carbon $fechaLimiteExplicita = null,
        ?int $estadoNuevoIdExplicito = null,
    ): Actuado {
        return DB::transaction(function () use (
            $expediente,
            $catalogoActuado,
            $emisor,
            $descripcion,
            $usuarioDestinoId,
            $metadatos,
            $ipOrigen,
            $adjunto,
            $fechaLimiteExplicita,
            $estadoNuevoIdExplicito,
        ) {
            // D-6g (AUD-0033): bloqueo pesimista del expediente antes de
            // validar el estado origen, para que dos emisiones concurrentes
            // no puedan pasar ambas la validación con un estado desactualizado.
            $expediente = Expediente::query()->lockForUpdate()->findOrFail($expediente->id);

            if ($catalogoActuado->requiere_adjunto && $adjunto === null) {
                throw ValidationException::withMessages([
                    'adjunto' => 'Este actuado exige adjuntar un documento.',
                ]);
            }

            $this->verificarEstadoOrigen($expediente, $catalogoActuado);

            // B1.4 (AUD-0031/0032): la aceptación de subsanación solo es
            // válida mientras su reloj no haya vencido.
            $this->verificarPlazoSubsanacionNoVencido($expediente, $catalogoActuado);

            $estadoAnteriorId = $expediente->estado_actual_id;
            $estadoNuevoId = $estadoNuevoIdExplicito ?? $catalogoActuado->estado_destino_id;

            $contenido = array_merge(
                ['descripcion' => $descripcion],
                $metadatos,
            );

            if ($usuarioDestinoId !== null) {
                $contenido['usuario_destino_id'] = $usuarioDestinoId;
            }

            $actuado = Actuado::create([
                'expediente_id' => $expediente->id,
                'catalogo_actuado_id' => $catalogoActuado->id,
                'usuario_id' => $emisor->id,
                'estado_anterior_id' => $estadoAnteriorId,
                'estado_nuevo_id' => $estadoNuevoId,
                'contenido' => $contenido,
                'actuado_referencia_id' => null,
                'ip_origen' => $ipOrigen,
            ]);

            // La cadena de custodia (hash_anterior/hash_actuado) la resuelve el
            // trigger BEFORE INSERT de MySQL; refresh() trae esos valores a la
            // instancia para que queden disponibles sin recargar el registro.
            $actuado->refresh();

            if ($estadoNuevoId !== null) {
                $expediente->update([
                    'estado_actual_id' => $estadoNuevoId,
                ]);
            }

            if ($usuarioDestinoId !== null) {
                $this->reasignarBandeja($expediente, $usuarioDestinoId, $actuado);
            }

            // B1.3 (AUD-0030): primero cierra los relojes de la fase que el
            // actuado concluye; después abre los de la nueva fase si aplica.
            $this->cerrarPlazosDeFase($expediente, $catalogoActuado, $actuado);

            $this->abrirPlazoSiAplica($expediente, $catalogoActuado, $actuado, $fechaLimiteExplicita);

            if ($adjunto !== null) {
                $this->adjuntoService->guardarParaActuado($actuado, $adjunto, $emisor);
            }

            // D-6b (AUD-0033): el único camino real hacia ADMITIDO es la
            // revocación del rechazo; en la misma transacción el expediente
            // continúa a EN_PLANIFICACION con un actuado formal encadenado.
            if ($estadoNuevoId !== null
                && $estadoAnteriorId !== $estadoNuevoId
                && $estadoNuevoId === $this->estadoAdmitidoId()) {
                $this->registrarPasoPlanificacion($expediente, $emisor);
            }

            return $actuado;
        }, 3);
    }

    /**
     * D-6g (AUD-0033): valida que el estado actual del expediente coincida
     * con el estado origen definido en el catálogo para el actuado. Un origen
     * null significa que el actuado es aplicable desde cualquier estado
     * (actuados no-op como la creación de NUREJ Hijo o el registro de
     * digitalización). Debe invocarse siempre con el expediente bajo
     * lockForUpdate para que la validación sea atómica frente a concurrencia.
     *
     * @throws ValidationException Cuando el estado actual no es el esperado.
     */
    protected function verificarEstadoOrigen(Expediente $expediente, CatalogoActuado $catalogoActuado): void
    {
        if ($catalogoActuado->estado_origen_id === null) {
            return;
        }

        if ($catalogoActuado->estado_origen_id === $expediente->estado_actual_id) {
            return;
        }

        $esperado = CatalogoEstado::find($catalogoActuado->estado_origen_id)?->codigo
            ?? $catalogoActuado->estado_origen_id;
        $actual = CatalogoEstado::find($expediente->estado_actual_id)?->codigo
            ?? $expediente->estado_actual_id;

        throw ValidationException::withMessages([
            'estado_origen_id' => "El actuado {$catalogoActuado->codigo} solo puede emitirse desde el estado {$esperado}; el expediente está en {$actual}.",
        ]);
    }

    /**
     * B1.4 (AUD-0031/0032): condición de vencimiento para la aceptación de
     * subsanación (RN-03). El vencimiento temporal es independiente de la
     * ejecución del CRON: si `fecha_limite` ya fue superada — comparación
     * estricta contra la fecha actual en la timezone institucional
     * (`app.timezone` = America/La_Paz; la columna `plazos.fecha_limite` es
     * `date`, sin componente horario, y el modelo la castea a `date`) — o el
     * plazo ya está VENCIDO (marca de una corrida previa del CRON), la
     * aceptación se rechaza con 422. Sin plazo parametrizado no hay
     * vencimiento que validar y la aceptación procede; el CRON ejecuta
     * posteriormente la consecuencia del vencimiento (archivo por abandono).
     *
     * @throws ValidationException Cuando el plazo de subsanación ya venció.
     */
    protected function verificarPlazoSubsanacionNoVencido(Expediente $expediente, CatalogoActuado $catalogoActuado): void
    {
        if ($catalogoActuado->codigo !== static::CODIGO_ACEPTACION_SUBSANACION) {
            return;
        }

        $plazo = Plazo::query()
            ->where('expediente_id', $expediente->id)
            ->where('tipo_plazo', 'SUBSANACION')
            ->whereIn('estado', ['VIGENTE', 'VENCIDO'])
            ->orderByDesc('id')
            ->first();

        if ($plazo === null) {
            return;
        }

        if ($plazo->estado === 'VENCIDO' || $plazo->fecha_limite->lt(today())) {
            throw ValidationException::withMessages([
                'plazo' => 'El plazo de subsanación ya venció; no se puede aceptar la subsanación.',
            ]);
        }
    }

    /**
     * D-6b (AUD-0033): emite el actuado automático ACT_PASO_PLANIFICACION
     * dentro de la misma transacción que dejó el expediente en ADMITIDO.
     * No lleva usuario_destino (la bandeja ya la reasignó la revocación) y
     * no tiene entrada en MAPA_TIPO_PLAZO (el plazo PLANIFICACION lo abre
     * la revocación).
     */
    protected function registrarPasoPlanificacion(Expediente $expediente, Usuario $emisor): Actuado
    {
        $catalogoPaso = CatalogoActuado::where('codigo', static::CODIGO_PASO_PLANIFICACION)->firstOrFail();

        return $this->registerActuado(
            expediente: $expediente,
            catalogoActuado: $catalogoPaso,
            emisor: $emisor,
            descripcion: 'Paso automático a EN_PLANIFICACION tras aterrizar en ADMITIDO (AUD-0033, D-6b).',
            metadatos: [
                'tipo' => 'AUTOMATICO',
                'motivo' => 'PASO_ADMITIDO_PLANIFICACION',
            ],
        );
    }

    /**
     * ID del estado ADMITIDO, resuelto una sola vez por instancia. Devuelve
     * null cuando el catálogo de estados no define ADMITIDO (fixtures de
     * tests parciales): en ese caso ningún actuado puede aterrizar ahí y el
     * paso automático no aplica.
     */
    protected function estadoAdmitidoId(): ?int
    {
        if (! $this->estadoAdmitidoResuelto) {
            $this->estadoAdmitidoId = CatalogoEstado::where('codigo', 'ADMITIDO')->first()?->id;
            $this->estadoAdmitidoResuelto = true;
        }

        return $this->estadoAdmitidoId;
    }

    /**
     * Cierra la bandeja activa previa y crea una nueva asignación para el
     * usuario destino, tomando su rol.
     */
    protected function reasignarBandeja(Expediente $expediente, int $usuarioDestinoId, Actuado $actuado): void
    {
        Asignacion::where('expediente_id', $expediente->id)
            ->where('activa', true)
            ->update(['activa' => false]);

        $usuarioDestino = Usuario::findOrFail($usuarioDestinoId);

        Asignacion::create([
            'expediente_id' => $expediente->id,
            'usuario_id' => $usuarioDestinoId,
            'rol_id' => $usuarioDestino->rol_id,
            'actuado_origen_id' => $actuado->id,
            'fecha_asignacion' => now(),
            'activa' => true,
        ]);
    }

    /**
     * Abre un plazo cuando el actuado tiene un tipo de plazo asociado.
     *
     * Cuando se recibe una fecha límite explícita (MPA de AC054/055) esta
     * prevalece sobre el cálculo por días hábiles: el plazo pasa a límite
     * fijo, sin parámetro y con dias_habiles_otorgados 0 (informativo, RN-05).
     */
    protected function abrirPlazoSiAplica(
        Expediente $expediente,
        CatalogoActuado $catalogoActuado,
        Actuado $actuado,
        ?Carbon $fechaLimiteExplicita = null,
    ): void {
        $tipoPlazo = $this->resolveTipoPlazo($catalogoActuado);

        if ($tipoPlazo === null) {
            return;
        }

        $subtipo = $tipoPlazo === 'EJECUCION'
            ? $this->resolveSubtipoEjecucion($expediente)
            : null;

        $parametro = ParametroPlazo::where('reglamento_id', $expediente->reglamento_id)
            ->where('tipo_plazo', $tipoPlazo)
            ->where('subtipo', $subtipo)
            ->first();

        if ($parametro === null && $subtipo !== null) {
            $parametro = ParametroPlazo::where('reglamento_id', $expediente->reglamento_id)
                ->where('tipo_plazo', $tipoPlazo)
                ->whereNull('subtipo')
                ->first();
        }

        if ($parametro === null && $fechaLimiteExplicita === null) {
            return;
        }

        if ($fechaLimiteExplicita !== null) {
            $fechaLimite = $fechaLimiteExplicita->copy()->endOfDay();
            $diasOtorgados = $parametro?->dias_habiles ?? 0;
        } else {
            $fechaLimite = $this->calculadoraPlazo->calculateDueDate(now(), $parametro->dias_habiles);
            $diasOtorgados = $parametro->dias_habiles;
        }

        Plazo::create([
            'expediente_id' => $expediente->id,
            'tipo_plazo' => $tipoPlazo,
            'parametro_plazo_id' => $parametro?->id,
            'dias_habiles_otorgados' => $diasOtorgados,
            'fecha_inicio' => now(),
            'fecha_limite' => $fechaLimite,
            'estado' => 'VIGENTE',
            'actuado_disparador_id' => $actuado->id,
        ]);
    }

    /**
     * Devuelve el tipo de plazo que abre un actuado, o null si ninguno.
     */
    protected function resolveTipoPlazo(CatalogoActuado $catalogoActuado): ?string
    {
        return static::MAPA_TIPO_PLAZO[$catalogoActuado->codigo] ?? null;
    }

    /**
     * B1.3 (AUD-0030): cierra los relojes VIGENTES de la fase que el actuado
     * concluye, dentro de la misma transacción y lock de registerActuado.
     *
     * Solo actualiza filas en estado VIGENTE (idempotente) y deja intactos los
     * SUSPENDIDO (congelación por transparencia), los relojes pausados de
     * descargos y cualquier otro tipo fuera de MAPA_CIERRA_PLAZO. El cierre
     * queda firmado por el actuado que lo causó: no existe `fecha_cierre` en
     * la tabla `plazos` (decisión de B1.3, opción A sin migración), por lo que
     * la fecha de cierre se deriva de `actuados.fecha_hora` vía
     * `actuado_cierre_id`.
     */
    protected function cerrarPlazosDeFase(
        Expediente $expediente,
        CatalogoActuado $catalogoActuado,
        Actuado $actuado,
    ): void {
        $tipos = static::MAPA_CIERRA_PLAZO[$catalogoActuado->codigo] ?? [];

        if ($tipos === []) {
            return;
        }

        Plazo::query()
            ->where('expediente_id', $expediente->id)
            ->whereIn('tipo_plazo', $tipos)
            ->where('estado', 'VIGENTE')
            ->update([
                'estado' => 'CERRADO',
                'actuado_cierre_id' => $actuado->id,
            ]);
    }

    /**
     * Resuelve el subtipo base del plazo de ejecución: JURISDICCIONAL (10 días
     * hábiles) para todos los acuerdos. La ampliación de plazo (ADMINISTRATIVA,
     * 15 días) es una historia futura y no se aplica en este reloj.
     */
    protected function resolveSubtipoEjecucion(Expediente $expediente): string
    {
        return 'JURISDICCIONAL';
    }
}
