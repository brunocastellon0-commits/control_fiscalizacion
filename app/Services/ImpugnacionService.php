<?php

namespace App\Services;

use App\Models\Actuado;
use App\Models\Asignacion;
use App\Models\CatalogoActuado;
use App\Models\Expediente;
use App\Models\Impugnacion;
use App\Models\Plazo;
use App\Models\Rol;
use App\Models\Usuario;
use App\Notifications\AvisoParaRevision;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ImpugnacionService
{
    public const CODIGO_ACT_REMITIR = 'ACT_REMITIR_IMPUGNACION';

    public const CODIGO_ACT_RATIFICA = 'ACT_RESOLUCION_RATIFICA_RECHAZO';

    public const CODIGO_ACT_REVOCA = 'ACT_RESOLUCION_REVOCA_RECHAZO';

    public const CODIGO_ACT_RECHAZO = 'ACT_RECHAZO';

    public const ESTADO_RECHAZADO = 'RECHAZADO';

    public const ESTADO_EN_IMPUGNACION = 'EN_IMPUGNACION';

    public const TIPO_PLAZO_IMPUGNACION_REMITIR = 'IMPUGNACION_REMITIR';

    public const TIPO_PLAZO_IMPUGNACION_RESOLVER = 'IMPUGNACION_RESOLVER';

    public const RESULTADO_PENDIENTE = 'PENDIENTE';

    public const RESULTADO_RATIFICADO = 'RATIFICADO';

    public const RESULTADO_REVOCADO = 'REVOCADO';

    public function __construct(
        protected ActuadoService $actuadoService,
        protected AdjuntoService $adjuntoService,
    ) {}

    /**
     * RN-08 (Remisión): el operador que rechazó el expediente lo remite a la
     * Encargada dentro del plazo de 1 día hábil. Transaccional:
     *
     * 1. Valida que el expediente esté en RECHAZADO.
     * 2. Emite ACT_REMITIR_IMPUGNACION vía ActuadoService (transiciona a
     *    EN_IMPUGNACION, mantiene la bandeja en el operador que resuelve y
     *    abre el plazo IMPUGNACION_RESOLVER de 3 días hábiles).
     * 3. Crea el registro de impugnación con resultado PENDIENTE.
     */
    public function remitirImpugnacion(Expediente $expediente, Usuario $emisor, string $descripcion): Impugnacion
    {
        return DB::transaction(function () use ($expediente, $emisor, $descripcion) {
            $this->validarEstado($expediente, static::ESTADO_RECHAZADO);

            $this->cerrarPlazoImpugnacionRemitir($expediente);

            $catalogoRemitir = $this->catalogoPorCodigo(static::CODIGO_ACT_REMITIR);

            $actuadoRemision = $this->actuadoService->registerActuado(
                expediente: $expediente,
                catalogoActuado: $catalogoRemitir,
                emisor: $emisor,
                descripcion: $descripcion,
                metadatos: [
                    'tipo' => 'IMPUGNACION',
                    'fase' => 'REMITIR',
                ],
            );

            $actuadoRechazo = $this->actuadoRechazoDelExpediente($expediente);

            $plazoResolucion = Plazo::where('expediente_id', $expediente->id)
                ->where('actuado_disparador_id', $actuadoRemision->id)
                ->first();

            return Impugnacion::create([
                'expediente_id' => $expediente->id,
                'actuado_rechazo_id' => $actuadoRechazo->id,
                'fecha_presentacion' => now(),
                'fecha_limite_resolucion' => $plazoResolucion?->fecha_limite ?? now(),
                'resultado' => static::RESULTADO_PENDIENTE,
            ]);
        }, 3);
    }

    /**
     * RN-08 (Resolución): la Encargada resuelve la impugnación pendiente.
     *
     * - RATIFICA (true): emite ACT_RESOLUCION_RATIFICA_RECHAZO, cierra todos
     *   los plazos vigentes y desactiva la bandeja. El expediente queda en
     *   ARCHIVO_DEFINITIVO.
     * - REVOCA (false): emite ACT_RESOLUCION_REVOCA_RECHAZO. El expediente
     *   vuelve a ADMITIDO y abre el plazo de PLANIFICACION (2 días).
     *
     * En ambos casos la Encargada recibe solo un aviso para marcar revisado.
     */
    public function resolverImpugnacion(
        Expediente $expediente,
        bool $ratifica,
        string $justificacion,
        Usuario $operador,
        ?UploadedFile $archivo = null,
        ?int $requisitoId = null,
    ): void {
        DB::transaction(function () use ($expediente, $ratifica, $justificacion, $operador, $archivo, $requisitoId) {
            $this->validarEstado($expediente, static::ESTADO_EN_IMPUGNACION);

            $impugnacion = $this->impugnacionPendiente($expediente);

            if ($ratifica) {
                $actuadoResolucion = $this->actuadoService->registerActuado(
                    expediente: $expediente,
                    catalogoActuado: $this->catalogoPorCodigo(static::CODIGO_ACT_RATIFICA),
                    emisor: $operador,
                    descripcion: $justificacion,
                    metadatos: [
                        'tipo' => 'IMPUGNACION',
                        'fase' => 'RATIFICA',
                        'impugnacion_id' => $impugnacion->id,
                    ],
                );

                $this->cerrarPlazosActivos($expediente);
                $this->cerrarBandejaActiva($expediente);

                $impugnacion->update([
                    'resultado' => static::RESULTADO_RATIFICADO,
                    'actuado_resolucion_id' => $actuadoResolucion->id,
                ]);

                $this->registrarRespuestaParaRevision(
                    expediente: $expediente,
                    actuado: $actuadoResolucion,
                    operador: $operador,
                    archivo: $archivo,
                    requisitoId: $requisitoId,
                    titulo: 'Impugnación resuelta: rechazo ratificado',
                    detalle: sprintf(
                        'El expediente %s ratificó el rechazo. La resolución queda disponible para su visto de revisado; no requiere su aprobación.',
                        $expediente->nurej_code,
                    ),
                );

                return;
            }

            $this->cerrarPlazoImpugnacionResolver($expediente);

            $actuadoResolucion = $this->actuadoService->registerActuado(
                expediente: $expediente,
                catalogoActuado: $this->catalogoPorCodigo(static::CODIGO_ACT_REVOCA),
                emisor: $operador,
                descripcion: $justificacion,
                metadatos: [
                    'tipo' => 'IMPUGNACION',
                    'fase' => 'REVOCA',
                    'impugnacion_id' => $impugnacion->id,
                ],
            );

            $impugnacion->update([
                'resultado' => static::RESULTADO_REVOCADO,
                'actuado_resolucion_id' => $actuadoResolucion->id,
            ]);

            $this->registrarRespuestaParaRevision(
                expediente: $expediente,
                actuado: $actuadoResolucion,
                operador: $operador,
                archivo: $archivo,
                requisitoId: $requisitoId,
                titulo: 'Impugnación resuelta: rechazo revocado',
                detalle: sprintf(
                    'El expediente %s revocó el rechazo y pasó a planificación. El PDF presentado queda disponible para su visto de revisado; no requiere su aprobación.',
                    $expediente->nurej_code,
                ),
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
     * Impugnación pendiente más reciente del expediente.
     */
    protected function impugnacionPendiente(Expediente $expediente): Impugnacion
    {
        return Impugnacion::where('expediente_id', $expediente->id)
            ->where('resultado', static::RESULTADO_PENDIENTE)
            ->latest('id')
            ->firstOrFail();
    }

    /**
     * Encargada activa de la unidad (sistema no depende de IDs hardcodeados).
     */
    protected function resolverEncargada(): ?Usuario
    {
        return Usuario::query()
            ->where('activo', true)
            ->whereHas('rol', fn ($query) => $query->where('codigo', Rol::CODIGO_ENCARGADA))
            ->orderBy('id')
            ->first();
    }

    /**
     * Adjunta el PDF de la impugnación (si se cargó) y avisa a la Encargada
     * para que solo marque el visto de revisado.
     */
    private function registrarRespuestaParaRevision(
        Expediente $expediente,
        Actuado $actuado,
        Usuario $operador,
        ?UploadedFile $archivo,
        ?int $requisitoId,
        string $titulo,
        string $detalle,
    ): void {
        $adjunto = null;

        if ($archivo !== null) {
            $adjunto = $this->adjuntoService->guardarParaActuado($actuado, $archivo, $operador);
            $adjunto->update([
                'requisito_id' => $requisitoId,
                'finalidad' => AdjuntoService::FINALIDAD_IMPUGNACION,
            ]);
        }

        $encargada = $this->resolverEncargada();
        if ($encargada === null) {
            return;
        }

        $encargada->notify(new AvisoParaRevision(
            expediente: $expediente,
            actuado: $actuado,
            titulo: $titulo,
            detalle: $detalle,
            categoria: 'impugnacion',
            adjunto: $adjunto,
        ));
    }

    /**
     * Operador con la asignación del expediente previa a la impugnación
     * (la remisión desactiva la del operador y activa la de la Encargada).
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
     * Actuado de rechazo más reciente del expediente (origen de la impugnación).
     */
    protected function actuadoRechazoDelExpediente(Expediente $expediente): Actuado
    {
        return Actuado::where('expediente_id', $expediente->id)
            ->whereHas('tipoActuado', fn ($query) => $query->where('codigo', static::CODIGO_ACT_RECHAZO))
            ->latest('id')
            ->firstOrFail();
    }

    /**
     * Catálogo de actuado por código.
     */
    protected function catalogoPorCodigo(string $codigo): CatalogoActuado
    {
        return CatalogoActuado::where('codigo', $codigo)->firstOrFail();
    }

    /**
     * Cierra todos los plazos vigentes del expediente (ratificación: nada que
     * correr, el expediente queda cerrado de forma definitiva).
     */
    protected function cerrarPlazosActivos(Expediente $expediente): void
    {
        Plazo::where('expediente_id', $expediente->id)
            ->where('estado', 'VIGENTE')
            ->update(['estado' => 'CERRADO']);
    }

    /**
     * Cierra el plazo de remisión del operador (ya entregó la impugnación).
     */
    protected function cerrarPlazoImpugnacionRemitir(Expediente $expediente): void
    {
        Plazo::where('expediente_id', $expediente->id)
            ->where('tipo_plazo', static::TIPO_PLAZO_IMPUGNACION_REMITIR)
            ->where('estado', 'VIGENTE')
            ->update(['estado' => 'CERRADO']);
    }

    /**
     * Cierra únicamente el plazo de resolución de la Encargada, dejando
     * intacto el nuevo plazo de PLANIFICACION del operador.
     */
    protected function cerrarPlazoImpugnacionResolver(Expediente $expediente): void
    {
        Plazo::where('expediente_id', $expediente->id)
            ->where('tipo_plazo', static::TIPO_PLAZO_IMPUGNACION_RESOLVER)
            ->where('estado', 'VIGENTE')
            ->update(['estado' => 'CERRADO']);
    }

    /**
     * Desactiva la bandeja activa del expediente (el caso quedó cerrado).
     */
    protected function cerrarBandejaActiva(Expediente $expediente): void
    {
        Asignacion::where('expediente_id', $expediente->id)
            ->where('activa', true)
            ->update(['activa' => false]);
    }
}
