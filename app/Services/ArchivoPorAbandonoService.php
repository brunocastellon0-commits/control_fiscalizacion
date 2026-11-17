<?php

namespace App\Services;

use App\Models\Asignacion;
use App\Models\CatalogoActuado;
use App\Models\CatalogoEstado;
use App\Models\Expediente;
use App\Models\Plazo;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ArchivoPorAbandonoService
{
    public const CODIGO_CATALOGO_ARCHIVO = 'ACT_ARCHIVO_POR_ABANDONO';

    public const TIPO_PLAZO_SUBSANACION = 'SUBSANACION';

    public const ESTADO_PLAZO_VIGENTE = 'VIGENTE';

    public const ESTADO_PLAZO_VENCIDO = 'VENCIDO';

    public const ESTADO_EXPEDIENTE_SUBSANACION = 'EN_SUBSANACION';

    public function __construct(
        protected ActuadoService $actuadoService,
    ) {}

    /**
     * Ejecuta el archivo automático por abandono (RN-03): detecta los plazos
     * de SUBSANACION cuyo vencimiento (fecha_limite) ya fue superado de forma
     * estricta y cuyo plazo sigue VIGENTE, y para cada uno:
     *
     * 1. Marca el plazo como VENCIDO.
     * 2. Emite el actuado sistema ACT_ARCHIVO_POR_ABANDONO vía
     *    ActuadoService (inmutable, transiciona el expediente a
     *    ARCHIVO_POR_ABANDONO y encadena el hash de seguridad).
     * 3. Cierra la bandeja activa del operador (el caso queda archivado).
     *
     * B1.4 (AUD-0031): el filtro exige además que el expediente esté
     * ACTUALMENTE en EN_SUBSANACION — un plazo huérfano de un expediente que
     * salió de la fase no dispara el archivo. Cada expediente se procesa en su
     * propio try/catch: una falla inesperada se registra en log y no cancela
     * el archivo del resto de causas de la corrida.
     *
     * Todo dentro de transacciones individuales por expediente. Idempotente:
     * tras marcar el plazo VENCIDO ya no vuelve a salir en la consulta.
     *
     * @return int Cantidad de expedientes archivados por abandono.
     */
    public function archivarVencidos(): int
    {
        $emisorSistema = $this->resolverUsuarioSistema();

        $catalogoArchivo = CatalogoActuado::where('codigo', static::CODIGO_CATALOGO_ARCHIVO)->firstOrFail();

        $estadoEnSubsanacion = CatalogoEstado::where('codigo', static::ESTADO_EXPEDIENTE_SUBSANACION)->firstOrFail();

        $plazosVencidos = Plazo::query()
            ->where('tipo_plazo', static::TIPO_PLAZO_SUBSANACION)
            ->where('estado', static::ESTADO_PLAZO_VIGENTE)
            ->where('fecha_limite', '<', now()->toDateString())
            ->whereHas('expediente', fn ($query) => $query->where('estado_actual_id', $estadoEnSubsanacion->id))
            ->with('expediente')
            ->get();

        $archivados = 0;

        foreach ($plazosVencidos as $plazo) {
            try {
                DB::transaction(function () use ($plazo, $emisorSistema, $catalogoArchivo): void {
                    $plazo->update([
                        'estado' => static::ESTADO_PLAZO_VENCIDO,
                        'fuera_de_plazo' => true,
                    ]);

                    $expediente = $plazo->expediente;

                    $this->actuadoService->registerActuado(
                        expediente: $expediente,
                        catalogoActuado: $catalogoArchivo,
                        emisor: $emisorSistema,
                        descripcion: 'Archivo automático por caducidad del plazo de subsanación sin respuesta del interesado (RN-03).',
                        metadatos: [
                            'tipo' => 'AUTOMATICO',
                            'motivo' => 'FALTA_SUBSANACION',
                            'plazo_id' => $plazo->id,
                        ],
                    );

                    $this->cerrarBandejaOperador($expediente);
                }, 3);
            } catch (\Throwable $e) {
                Log::error('Archivo por abandono: fallo al procesar un expediente; se continúa con el resto de la corrida.', [
                    'plazo_id' => $plazo->id,
                    'expediente_id' => $plazo->expediente_id,
                    'exception' => $e::class,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            $archivados++;
        }

        return $archivados;
    }

    /**
     * Usuario del sistema: primer usuario activo con rol ADMIN, usado como
     * emisor de los actuados automáticos. No depende de IDs hardcodeados.
     */
    protected function resolverUsuarioSistema(): Usuario
    {
        return Usuario::query()
            ->where('activo', true)
            ->whereHas('rol', fn ($query) => $query->where('codigo', Rol::CODIGO_ADMIN))
            ->orderBy('id')
            ->firstOrFail();
    }

    /**
     * Al archivar el expediente se cierra su bandeja activa: la asignación
     * ya no debe aparecer como pendiente del operador.
     */
    protected function cerrarBandejaOperador(Expediente $expediente): void
    {
        Asignacion::where('expediente_id', $expediente->id)
            ->where('activa', true)
            ->update(['activa' => false]);
    }
}
