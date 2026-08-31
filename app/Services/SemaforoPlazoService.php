<?php

namespace App\Services;

use App\Models\Expediente;
use App\Models\Plazo;
use Illuminate\Support\Collection;

class SemaforoPlazoService
{
    public function __construct(
        protected PlazoCalculatorService $calculadoraPlazo,
    ) {}

    /**
     * Evalúa el semáforo de un plazo en tiempo real contra now() y
     * fecha_limite (RF-R01/RF-R05), con días hábiles normativos reales
     * (sin fines de semana, feriados ni suspensiones):
     *
     * - FUERA_DE_PLAZO: el vencimiento (día completo) ya fue superado.
     * - ROJO: 0 o 1 días hábiles restantes (vence hoy o el próximo día hábil).
     * - AMARILLO: plazos cortos (<= 3 días) con exactamente 2 restantes;
     *   plazos largos cuando los restantes caen en el último tercio otorgado.
     * - VERDE: resto.
     * - Estados no vigentes (CERRADO, SUSPENDIDO, ...) se retornan sin cálculo.
     *
     * @return array{codigo_color: string, dias_restantes: int|null, porcentaje_consumido: float|null, fecha_limite: string, es_fuera_de_plazo: bool}
     */
    public function evaluarPlazo(Plazo $plazo): array
    {
        $fechaLimite = $plazo->fecha_limite->format('Y-m-d');

        if ($plazo->estado !== 'VIGENTE') {
            return [
                'codigo_color' => $plazo->estado,
                'dias_restantes' => null,
                'porcentaje_consumido' => null,
                'fecha_limite' => $fechaLimite,
                'es_fuera_de_plazo' => false,
            ];
        }

        $esFueraDePlazo = now()->greaterThan($plazo->fecha_limite->copy()->endOfDay());
        $diasRestantes = $this->calculadoraPlazo->daysRemaining($plazo->fecha_limite);
        $codigoColor = $this->resolverColor($esFueraDePlazo, $plazo, $diasRestantes);

        return [
            'codigo_color' => $codigoColor,
            'dias_restantes' => $diasRestantes,
            'porcentaje_consumido' => $this->calcularPorcentajeConsumido($plazo),
            'fecha_limite' => $fechaLimite,
            'es_fuera_de_plazo' => $esFueraDePlazo,
        ];
    }

    /**
     * Metricas consolidadas del semáforo para un conjunto de expedientes.
     * Por cada expediente se toma su plazo vigente más urgente (fecha límite
     * más próxima); los que no tienen plazos vigentes se omiten.
     *
     * @param  Collection<int, Expediente>|array<int, Expediente>  $expedientes
     * @return array{total_en_plazo: int, total_precaucion: int, total_urgente: int, total_fuera_de_plazo: int}
     */
    public function resumenBandeja(Collection|array $expedientes): array
    {
        $resumen = [
            'total_en_plazo' => 0,
            'total_precaucion' => 0,
            'total_urgente' => 0,
            'total_fuera_de_plazo' => 0,
        ];

        $mapaResumen = [
            'VERDE' => 'total_en_plazo',
            'AMARILLO' => 'total_precaucion',
            'ROJO' => 'total_urgente',
            'FUERA_DE_PLAZO' => 'total_fuera_de_plazo',
        ];

        foreach ($expedientes as $expediente) {
            $codigoColor = $this->colorMasUrgente($expediente);

            if ($codigoColor === null || ! isset($mapaResumen[$codigoColor])) {
                continue;
            }

            $resumen[$mapaResumen[$codigoColor]]++;
        }

        return $resumen;
    }

    /**
     * Resuelve el código de color del semáforo para un plazo vigente.
     */
    protected function resolverColor(bool $esFueraDePlazo, Plazo $plazo, int $diasRestantes): string
    {
        if ($esFueraDePlazo) {
            return 'FUERA_DE_PLAZO';
        }

        if ($diasRestantes <= 1) {
            return 'ROJO';
        }

        return $this->esAmarillo($plazo, $diasRestantes) ? 'AMARILLO' : 'VERDE';
    }

    /**
     * Regla de precaución:
     * - Plazos cortos (<= 3 días): amarillo con exactamente 2 días restantes.
     * - Plazos largos: amarillo cuando restantes <= último tercio otorgado.
     */
    protected function esAmarillo(Plazo $plazo, int $diasRestantes): bool
    {
        $totalOtorgado = max($plazo->dias_habiles_otorgados, 1);

        if ($totalOtorgado <= 3) {
            return $diasRestantes === 2;
        }

        return $diasRestantes <= (int) ceil($totalOtorgado / 3);
    }

    /**
     * Porcentaje del plazo consumido entre fecha_inicio y fecha_limite,
     * acotado al rango [0, 1].
     */
    protected function calcularPorcentajeConsumido(Plazo $plazo): float
    {
        $inicio = $plazo->fecha_inicio->copy()->startOfDay();
        $fin = $plazo->fecha_limite->copy()->endOfDay();

        $duracionTotal = $fin->diffInSeconds($inicio);

        if ($duracionTotal <= 0) {
            return 1.0;
        }

        $transcurrido = now()->diffInSeconds($inicio);

        return (float) min(max($transcurrido / $duracionTotal, 0.0), 1.0);
    }

    /**
     * Color del plazo vigente más urgente de un expediente, o null si no
     * tiene plazos vigentes.
     */
    protected function colorMasUrgente(Expediente $expediente): ?string
    {
        $plazoMasUrgente = $expediente->plazos
            ->where('estado', 'VIGENTE')
            ->sortBy('fecha_limite')
            ->first();

        if ($plazoMasUrgente === null) {
            return null;
        }

        return $this->evaluarPlazo($plazoMasUrgente)['codigo_color'];
    }
}
