<?php

namespace App\Services;

use App\Models\Expediente;
use App\Models\Rol;
use App\Models\SorteoPeso;
use App\Models\Usuario;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sorteo probabilístico no predictivo basado en pesos por especialidad.
 *
 * Modelo SIREJ (Órgano Judicial de Bolivia) adaptado: la Encargada ya no
 * elige destinatario; el sistema balancea la carga por reglamento usando la
 * tabla `sorteo_pesos` (desacoplada del COUNT de expedientes).
 *
 * SimpleRandom: TICKETS = (peso_maximo - peso_candidato) + 1, de modo que
 * el menos cargado tiene más tickets pero el más cargado conserva una
 * posibilidad remota (el "+1"), evitando previsibilidad absoluta.
 */
class SorteoAlgorithmService
{
    /**
     * Mapeo vía del expediente -> rol que participa del sorteo.
     */
    protected const MAPA_VIA_ROL = [
        'TECNICO' => Rol::CODIGO_TECNICO,
        'JURIDICO' => Rol::CODIGO_AUD_JURIDICO,
        'FINANCIERO' => Rol::CODIGO_AUD_FINANCIERO,
    ];

    /**
     * @param  Closure(int): int|null  $randomInt  RNG inyectable para tests deterministas.
     */
    public function __construct(
        protected ?Closure $randomInt = null,
    ) {}

    /**
     * Ejecuta el sorteo ciego: selecciona el ganador entre los candidatos
     * válidos de la vía e incrementa su peso (+1) para ese reglamento.
     *
     * Debe ejecutarse dentro de la transacción del llamador (ejecutarSorteo)
     * para que el incremento de peso se revierta si el sorteo falla.
     */
    public function sortear(Expediente $expediente): Usuario
    {
        $candidatos = $this->candidatos($expediente);

        if ($candidatos->isEmpty()) {
            throw ValidationException::withMessages([
                'expediente' => 'No hay funcionarios activos disponibles para la vía '.$expediente->via,
            ]);
        }

        $entradas = $this->calcularTickets($candidatos, $expediente->reglamento_id);
        $ganador = $this->seleccionarGanador($entradas);

        $this->incrementarPeso($ganador, $expediente->reglamento_id);

        return $ganador;
    }

    /**
     * Resuelve el rol participante según la vía del expediente.
     */
    public function rolParaVia(string $via): string
    {
        return static::MAPA_VIA_ROL[$via] ?? throw ValidationException::withMessages([
            'via' => 'Vía de expediente no soportada para el sorteo: '.$via,
        ]);
    }

    /**
     * Candidatos activos del rol correspondiente a la vía del expediente.
     *
     * @return Collection<int, Usuario>
     */
    public function candidatos(Expediente $expediente): Collection
    {
        $codigoRol = $this->rolParaVia($expediente->via);

        return Usuario::query()
            ->where('activo', true)
            ->whereHas('rol', fn ($query) => $query->where('codigo', $codigoRol))
            ->orderBy('id')
            ->get();
    }

    /**
     * Calcula los tickets de probabilidad de cada candidato usando la
     * fórmula TICKETS = (peso_maximo - peso_candidato) + 1.
     *
     * @param  Collection<int, Usuario>  $candidatos
     * @return array<int, array{usuario: Usuario, peso: int, tickets: int}>
     */
    public function calcularTickets(Collection $candidatos, int $reglamentoId): array
    {
        $pesos = SorteoPeso::query()
            ->whereIn('usuario_id', $candidatos->pluck('id'))
            ->where('reglamento_id', $reglamentoId)
            ->pluck('peso', 'usuario_id');

        $pesoMaximo = $candidatos->max(
            fn (Usuario $usuario) => (int) $pesos->get($usuario->id, 0),
        );

        $entradas = [];

        foreach ($candidatos as $candidato) {
            $pesoCandidato = (int) $pesos->get($candidato->id, 0);

            $entradas[] = [
                'usuario' => $candidato,
                'peso' => $pesoCandidato,
                'tickets' => ($pesoMaximo - $pesoCandidato) + 1,
            ];
        }

        return $entradas;
    }

    /**
     * Incrementa en +1 el peso del ganador para el reglamento (upsert).
     *
     * Usa ON DUPLICATE KEY UPDATE de MySQL para que el contador sea atómico
     * ante sorteos concurrentes sobre el mismo candidato (DB::raw justificado
     * por atomicidad; valores controlados, sin input de usuario).
     */
    protected function incrementarPeso(Usuario $ganador, int $reglamentoId): void
    {
        $ahora = now();

        SorteoPeso::query()->upsert(
            values: [
                'usuario_id' => $ganador->id,
                'reglamento_id' => $reglamentoId,
                'peso' => 1,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ],
            uniqueBy: ['usuario_id', 'reglamento_id'],
            update: [
                'peso' => DB::raw('peso + 1'),
                'updated_at' => $ahora,
            ],
        );
    }

    /**
     * Selecciona el ganador mediante ruleta ponderada por tickets.
     *
     * @param  array<int, array{usuario: Usuario, peso: int, tickets: int}>  $entradas
     */
    protected function seleccionarGanador(array $entradas): Usuario
    {
        $totalTickets = array_sum(array_column($entradas, 'tickets'));
        $tiro = $this->lanzarDado($totalTickets);

        $acumulado = 0;

        foreach ($entradas as $entrada) {
            $acumulado += $entrada['tickets'];

            if ($tiro <= $acumulado) {
                return $entrada['usuario'];
            }
        }

        $ultima = $entradas[array_key_last($entradas)];

        return $ultima['usuario'];
    }

    /**
     * Devuelve un entero aleatorio en [1, $max]. Con RNG inyectado en tests.
     */
    protected function lanzarDado(int $max): int
    {
        if ($this->randomInt !== null) {
            $random = $this->randomInt;

            return $random($max);
        }

        return random_int(1, $max);
    }
}
