<?php

namespace App\Services;

use App\Models\Actuado;
use App\Models\Asignacion;
use App\Models\CatalogoActuado;
use App\Models\Expediente;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CierreExpedienteService
{
    public const CODIGO_ACT_VISTO_BUENO_FINAL = 'ACT_VISTO_BUENO_FINAL';

    public const CODIGO_ACT_REPARTO_INSTITUCIONAL = 'ACT_REPARTO_INSTITUCIONAL';

    public const ESTADO_PENDIENTE_VISTO_BUENO_FINAL = 'PENDIENTE_VISTO_BUENO_FINAL';

    public const ESTADO_LISTO_PARA_REPARTO = 'LISTO_PARA_REPARTO';

    public const ESTADO_CONCLUIDO_REMITIDO = 'CONCLUIDO_REMITIDO';

    /**
     * Destinos externos contemplados por la norma (RN-09/RN-12): solo estos
     * cuatro valores pueden cerrar el NUREJ vía reparto institucional.
     */
    public const DESTINOS_REPARTO = [
        'Juzgado Disciplinario',
        'Sumariante',
        'Asesoría Legal',
        'Asesoría Jurídica',
    ];

    public function __construct(
        protected ActuadoService $actuadoService,
    ) {}

    /**
     * E10-S1: la Encargada aprueba el informe final con Visto Bueno Final.
     * Transaccional:
     *
     * 1. Valida que el expediente esté en PENDIENTE_VISTO_BUENO_FINAL.
     * 2. Emite ACT_VISTO_BUENO_FINAL vía ActuadoService: el expediente
     *    transiciona a LISTO_PARA_REPARTO y la bandeja queda en la Encargada
     *    (se reasigna a sí misma) de cara al reparto institucional.
     */
    public function aprobarVistoBueno(
        Expediente $expediente,
        Usuario $encargada,
        string $descripcion,
        ?string $ipOrigen = null,
    ): Actuado {
        return DB::transaction(function () use ($expediente, $encargada, $descripcion, $ipOrigen) {
            $this->validarEstado($expediente, static::ESTADO_PENDIENTE_VISTO_BUENO_FINAL);

            return $this->actuadoService->registerActuado(
                expediente: $expediente,
                catalogoActuado: $this->catalogoPorCodigo(static::CODIGO_ACT_VISTO_BUENO_FINAL),
                emisor: $encargada,
                descripcion: $descripcion,
                usuarioDestinoId: $encargada->id,
                metadatos: ['tipo' => 'VISTO_BUENO_FINAL'],
                ipOrigen: $ipOrigen,
            );
        }, 3);
    }

    /**
     * E10-S2: la Encargada ejecuta el reparto institucional y cierra el ciclo.
     * Transaccional:
     *
     * 1. Valida que el expediente esté en LISTO_PARA_REPARTO y que el destino
     *    sea uno de los contemplados por la norma (RN-09/RN-12).
     * 2. Emite ACT_REPARTO_INSTITUCIONAL con el destino dentro del contenido
     *    JSON del actuado; el expediente transiciona a CONCLUIDO_REMITIDO.
     * 3. Desactiva la asignación activa (la bandeja de la Encargada): el
     *    expediente queda sin asignaciones activas y sale de los tableros.
     */
    public function ejecutarReparto(
        Expediente $expediente,
        Usuario $encargada,
        string $destino,
        string $justificacion,
        ?string $ipOrigen = null,
    ): Actuado {
        return DB::transaction(function () use ($expediente, $encargada, $destino, $justificacion, $ipOrigen) {
            $this->validarEstado($expediente, static::ESTADO_LISTO_PARA_REPARTO);
            $this->validarDestinoReparto($destino);

            $actuado = $this->actuadoService->registerActuado(
                expediente: $expediente,
                catalogoActuado: $this->catalogoPorCodigo(static::CODIGO_ACT_REPARTO_INSTITUCIONAL),
                emisor: $encargada,
                descripcion: $justificacion,
                usuarioDestinoId: null,
                metadatos: ['tipo' => 'REPARTO', 'destino_reparto' => $destino],
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
     * Defensa en profundidad del destino: aunque el Form Request ya valida la
     * regla in:, el servicio re-valida contra la norma por si se invoca desde
     * fuera del flujo HTTP.
     */
    protected function validarDestinoReparto(string $destino): void
    {
        if (! in_array($destino, static::DESTINOS_REPARTO, true)) {
            throw ValidationException::withMessages([
                'destino' => 'El destino seleccionado no está contemplado en la norma (RN-09/RN-12).',
            ]);
        }
    }

    /**
     * Cierra la bandeja activa del expediente: el NUREJ finalizado no debe
     * aparecer en tableros operativos (asignacionActiva() queda vacía).
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
