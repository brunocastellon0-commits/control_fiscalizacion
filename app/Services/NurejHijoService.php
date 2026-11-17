<?php

namespace App\Services;

use App\Models\CatalogoActuado;
use App\Models\CatalogoEstado;
use App\Models\Expediente;
use App\Models\Parte;
use App\Models\Reglamento;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class NurejHijoService
{
    /**
     * MATRIZ_DERIVACIONES §2 (filas M1-M4): actuados habilitantes, todos
     * con origen en vía TECNICO. D-P1: sin uno de estos informes previos
     * en el padre no se permite ninguna derivación.
     *
     * @var array<int, string>
     */
    protected const ACTUADOS_HABILITANTES = [
        'ACT_INFORME_TECNICO_CON_RESPONSABILIDAD_RECOMENDACION',
        'ACT_INFORME_TECNICO_SIN_RESPONSABILIDAD_RECOMENDACION',
    ];

    /**
     * MATRIZ_DERIVACIONES §2 (M1-M4 + M5): whitelist estricta de
     * combinaciones permitidas — solo las que la matriz lista
     * explícitamente (D-P2). TECNICO no figura porque M5 lo prohíbe;
     * no existe regla genérica "destino distinto del padre" (D-P3).
     *
     * @var array<int, string>
     */
    protected const DESTINOS_PERMITIDOS = [
        'JURIDICO',
        'FINANCIERO',
    ];

    /**
     * Mapeo fijo vía destino → reglamento destino, resuelto solo en el
     * servidor (R2 de la matriz; reglamentos verificados en
     * ReglamentoSeeder). La entrada TECNICO existe para completar el
     * mapeo normativo pero jamás se alcanza: TECNICO está fuera de la
     * whitelist.
     *
     * @var array<string, string>
     */
    protected const MAPA_VIA_DESTINO_REGLAMENTO = [
        'TECNICO' => 'AC_022_2018',
        'JURIDICO' => 'AC_054_2018',
        'FINANCIERO' => 'AC_055_2018',
    ];

    public function __construct(
        protected NurejGeneratorService $nurejGenerator,
        protected ActuadoService $actuadoService,
    ) {}

    /**
     * E9-S1 (RN-10): crea un NUREJ Hijo derivado de un expediente padre.
     *
     * Transaccional e indivisible:
     * 1. Guard RN-10 (AUD-0038): bloquea sub-derivar antes de cualquier
     *    otra validación, conservando su 422 con mensaje propio.
     * 2. Valida la combinación contra la MATRIZ_DERIVACIONES (D-P1:
     *    informe técnico habilitante previo; D-P2: whitelist estricta).
     * 3. Genera el NUREJ Hijo (formato YYYY-NNNNN-X).
     * 4. Crea el expediente hijo con la vía y el reglamento de destino
     *    elegidos (no hereda los del padre) más los metadatos
     *    informativos (resumen_hechos, partes) pero sin actuados, plazos
     *    ni asignaciones previas — línea de tiempo en cero.
     * 5. Registra ACT_CREACION_NUREJ_HIJO sobre el padre con la
     *    trazabilidad via_destino/reglamento_destino_id (C8). El padre
     *    conserva su estado actual.
     *
     * El hijo nace en PENDIENTE_SORTEO para ser sorteado por la Encargada
     * hacia la bandeja de la especialidad destino (MAPA_VIA_ROL).
     */
    public function crearHijo(
        Expediente $padre,
        Usuario $encargada,
        string $motivo,
        string $viaDestino,
        ?string $ipOrigen = null,
    ): Expediente {
        return DB::transaction(function () use ($padre, $encargada, $motivo, $viaDestino, $ipOrigen) {
            $nurejHijo = $this->nurejGenerator->generarHijo($padre->id);

            $reglamentoDestino = $this->verificarDerivable($padre, $viaDestino);

            $estadoPendienteSorteo = CatalogoEstado::where('codigo', 'PENDIENTE_SORTEO')->firstOrFail();

            $hijo = Expediente::create([
                'nurej_code' => $nurejHijo,
                'nurej_padre_id' => $padre->id,
                'via' => $viaDestino,
                'reglamento_id' => $reglamentoDestino->id,
                'estado_actual_id' => $estadoPendienteSorteo->id,
                'resumen_hechos' => $padre->resumen_hechos,
                'fecha_ingreso' => now(),
                'creado_por' => $encargada->id,
            ]);

            $this->copiarPartesVigentes($padre, $hijo);

            $catalogoActuado = CatalogoActuado::where('codigo', 'ACT_CREACION_NUREJ_HIJO')->firstOrFail();

            $this->actuadoService->registerActuado(
                expediente: $padre,
                catalogoActuado: $catalogoActuado,
                emisor: $encargada,
                descripcion: $motivo,
                metadatos: [
                    'expediente_hijo_id' => $hijo->id,
                    'nurej_hijo_code' => $nurejHijo,
                    'via_destino' => $viaDestino,
                    'reglamento_destino_id' => $reglamentoDestino->id,
                ],
                ipOrigen: $ipOrigen,
                estadoNuevoIdExplicito: $padre->estado_actual_id,
            );

            return $hijo->refresh();
        }, 3);
    }

    /**
     * D-P1/D-P2 (MATRIZ_DERIVACIONES §2): única fuente de verdad de qué
     * combinaciones están permitidas. Lanza 422 cuando (a) no existe un
     * informe técnico habilitante previo en el padre o su vía no es la
     * de origen de las filas M1-M4 (M6/M7/M8), o (b) la vía destino no
     * está en la whitelist de la matriz (M5 u otra no listada).
     *
     * @return Reglamento Reglamento destino resuelto server-side.
     */
    protected function verificarDerivable(Expediente $padre, string $viaDestino): Reglamento
    {
        $tieneHabilitante = $padre->via === 'TECNICO'
            && $padre->actuados()
                ->whereHas('tipoActuado', function ($query) {
                    $query->whereIn('codigo', self::ACTUADOS_HABILITANTES);
                })
                ->exists();

        if (! $tieneHabilitante) {
            throw ValidationException::withMessages([
                'via_destino' => 'La derivación requiere un informe técnico con recomendación previo en el expediente (RN-10, matriz de derivaciones M1-M4).',
            ]);
        }

        if (! in_array($viaDestino, self::DESTINOS_PERMITIDOS, true)) {
            throw ValidationException::withMessages([
                'via_destino' => 'La combinación de especialidad origen/destino no está permitida por la matriz de derivaciones.',
            ]);
        }

        return Reglamento::where('codigo', self::MAPA_VIA_DESTINO_REGLAMENTO[$viaDestino])->firstOrFail();
    }

    /**
     * Copia las partes vigentes del padre al hijo como nuevas versiones.
     * Cada parte del hijo es una entidad independiente que puede evolucionar
     * sin afectar al padre.
     */
    protected function copiarPartesVigentes(Expediente $padre, Expediente $hijo): void
    {
        $partesVigentes = $padre->partesVigentes()->get();

        foreach ($partesVigentes as $parte) {
            Parte::create([
                'expediente_id' => $hijo->id,
                'tipo' => $parte->tipo,
                'nombre_completo' => $parte->nombre_completo,
                'documento_identidad' => $parte->documento_identidad,
                'cargo_institucion' => $parte->cargo_institucion,
                'actuado_origen_id' => null,
                'vigente_desde' => now(),
                'vigente_hasta' => null,
                'es_version_actual' => true,
            ]);
        }
    }
}
