<?php

namespace App\Services;

use App\Models\Actuado;
use App\Models\Asignacion;
use App\Models\CatalogoActuado;
use App\Models\Expediente;
use App\Models\Plazo;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransparenciaService
{
    public const CODIGO_ACT_DERIVACION = 'ACT_DERIVACION_INCOMPETENCIA';

    public const CODIGO_ACT_REMISION = 'ACT_REMISION_TRANSPARENCIA';

    public const ESTADO_PENDIENTE_REMISION = 'PENDIENTE_REMISION_TRANSPARENCIA';

    public const ESTADO_DERIVADO_TRANSPARENCIA = 'DERIVADO_TRANSPARENCIA';

    public const ESTADO_PLAZO_SUSPENDIDO = 'SUSPENDIDO';

    public function __construct(
        protected ActuadoService $actuadoService,
    ) {}

    /**
     * E5-S5 (RN-09): el operador deriva el NUREJ a la Encargada por
     * incompetencia de la vía penal. Transaccional:
     *
     * 1. Resuelve la Encargada activa (destino de la bandeja).
     * 2. Congela todos los relojes VIGENTES del expediente (estado
     *    SUSPENDIDO): el motor de cálculo los ignora, el NUREJ deja de correr.
     * 3. Emite ACT_DERIVACION_INCOMPETENCIA vía ActuadoService con el adjunto
     *    probatorio (obligatorio): transiciona a
     *    PENDIENTE_REMISION_TRANSPARENCIA y reasigna la bandeja a la Encargada.
     */
    public function derivarPorIncompetencia(
        Expediente $expediente,
        Usuario $operador,
        string $justificacion,
        ?UploadedFile $adjunto,
        ?string $ipOrigen = null,
    ): Actuado {
        return DB::transaction(function () use ($expediente, $operador, $justificacion, $adjunto, $ipOrigen) {
            $encargada = $this->resolverEncargada();

            $this->congelarPlazos($expediente);

            return $this->actuadoService->registerActuado(
                expediente: $expediente,
                catalogoActuado: $this->catalogoPorCodigo(static::CODIGO_ACT_DERIVACION),
                emisor: $operador,
                descripcion: $justificacion,
                usuarioDestinoId: $encargada->id,
                metadatos: [
                    'tipo' => 'DERIVACION_TRANSPARENCIA',
                    'motivo' => 'INCOMPETENCIA',
                ],
                ipOrigen: $ipOrigen,
                adjunto: $adjunto,
            );
        }, 3);
    }

    /**
     * E5-S5 (RN-09): la Encargada remite el NUREJ a Transparencia, salida
     * excepcional que deja el expediente fuera del circuito. Transaccional:
     *
     * 1. Valida que el expediente esté en PENDIENTE_REMISION_TRANSPARENCIA.
     * 2. Emite ACT_REMISION_TRANSPARENCIA vía ActuadoService: transiciona a
     *    DERIVADO_TRANSPARENCIA (estado final) conservando los relojes
     *    congelados.
     * 3. Desactiva la asignación activa: el NUREJ desaparece de los tableros.
     */
    public function remitirTransparencia(
        Expediente $expediente,
        Usuario $encargada,
        string $notaRemision,
        ?string $ipOrigen = null,
    ): Actuado {
        return DB::transaction(function () use ($expediente, $encargada, $notaRemision, $ipOrigen) {
            $this->validarEstado($expediente, static::ESTADO_PENDIENTE_REMISION);

            $actuado = $this->actuadoService->registerActuado(
                expediente: $expediente,
                catalogoActuado: $this->catalogoPorCodigo(static::CODIGO_ACT_REMISION),
                emisor: $encargada,
                descripcion: $notaRemision,
                usuarioDestinoId: null,
                metadatos: [
                    'tipo' => 'REMISION_TRANSPARENCIA',
                    'via' => 'PENAL',
                ],
                ipOrigen: $ipOrigen,
            );

            $this->cerrarBandejaActiva($expediente);

            return $actuado;
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
     * Congela los relojes vigentes del expediente: los plazos pasan a
     * SUSPENDIDO y el motor de cálculo (marcarVencidos/semáforo) los ignora.
     */
    protected function congelarPlazos(Expediente $expediente): void
    {
        Plazo::where('expediente_id', $expediente->id)
            ->where('estado', 'VIGENTE')
            ->update([
                'estado' => static::ESTADO_PLAZO_SUSPENDIDO,
                'fecha_pausa' => now(),
            ]);
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
     * Desactiva la bandeja activa del expediente (el NUREJ sale de los
     * tableros al quedar en DERIVADO_TRANSPARENCIA).
     */
    protected function cerrarBandejaActiva(Expediente $expediente): void
    {
        Asignacion::where('expediente_id', $expediente->id)
            ->where('activa', true)
            ->update(['activa' => false]);
    }

    /**
     * Catálogo de actuado por código.
     */
    protected function catalogoPorCodigo(string $codigo): CatalogoActuado
    {
        return CatalogoActuado::where('codigo', $codigo)->firstOrFail();
    }
}
