<?php

namespace App\Services;

use App\Models\Feriado;
use App\Models\SuspensionPlazo;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PlazoCalculatorService
{
    private Collection $feriados;

    private Collection $suspensiones;

    /**
     * Los feriados y suspensiones se cargan de la base de datos por defecto.
     * Aceptan inyección (colecciones) para pruebas deterministas.
     *
     * @param  Collection<int, mixed>|null  $feriados
     * @param  Collection<int, mixed>|null  $suspensiones
     */
    public function __construct(?Collection $feriados = null, ?Collection $suspensiones = null)
    {
        $this->feriados = $feriados ?? $this->loadFeriados();
        $this->suspensiones = $suspensiones ?? $this->loadSuspensiones();
    }

    /**
     * Calcula la fecha de vencimiento sumando N días hábiles, sin contar el
     * día de inicio (el plazo corre desde el día hábil siguiente).
     *
     * @param  Carbon|string  $fechaInicio  Fecha desde la que empieza a correr el plazo
     * @param  int  $diasHabiles  Cantidad de días hábiles normativos
     * @return Carbon Fecha límite procesal (23:59:59 del día de vencimiento)
     */
    public function calculateDueDate(Carbon|string $fechaInicio, int $diasHabiles): Carbon
    {
        $fecha = $fechaInicio instanceof Carbon ? $fechaInicio->copy() : Carbon::parse($fechaInicio);
        $feriados = $this->feriados->map(fn ($f) => Carbon::parse($f)->format('Y-m-d'))->toArray();

        $diasContados = 0;

        while ($diasContados < $diasHabiles) {
            $fecha->addDay();

            if ($fecha->isWeekend()) {
                continue;
            }

            if (in_array($fecha->format('Y-m-d'), $feriados, true)) {
                continue;
            }

            if ($this->isSuspendedDate($fecha)) {
                continue;
            }

            $diasContados++;
        }

        return $fecha->endOfDay();
    }

    /**
     * Días hábiles restantes desde hoy hasta la fecha de vencimiento (sin
     * incluir el día de vencimiento).
     */
    public function daysRemaining(Carbon|string $fechaVencimiento): int
    {
        $hoy = Carbon::today();
        $limite = $fechaVencimiento instanceof Carbon
            ? $fechaVencimiento->copy()->startOfDay()
            : Carbon::parse($fechaVencimiento)->startOfDay();

        if ($hoy->greaterThan($limite)) {
            return 0;
        }

        $feriados = $this->feriados->map(fn ($f) => Carbon::parse($f)->format('Y-m-d'))->toArray();

        $diasRestantes = 0;
        $cursor = $hoy->copy();

        while ($cursor->lessThan($limite)) {
            $cursor->addDay();

            if ($cursor->isWeekend()
                || in_array($cursor->format('Y-m-d'), $feriados, true)
                || $this->isSuspendedDate($cursor)) {
                continue;
            }

            $diasRestantes++;
        }

        return $diasRestantes;
    }

    /**
     * Indica si la fecha cae dentro de un rango de suspensión (inclusivo).
     */
    protected function isSuspendedDate(Carbon $fecha): bool
    {
        $fechaStr = $fecha->format('Y-m-d');

        foreach ($this->suspensiones as $suspension) {
            $inicio = Carbon::parse($suspension->fecha_inicio)->format('Y-m-d');
            $fin = Carbon::parse($suspension->fecha_fin)->format('Y-m-d');

            if ($fechaStr >= $inicio && $fechaStr <= $fin) {
                return true;
            }
        }

        return false;
    }

    /**
     * Carga los feriados vigentes de la base de datos.
     */
    protected function loadFeriados(): Collection
    {
        return collect(Feriado::pluck('fecha'));
    }

    /**
     * Carga las suspensiones de plazos vigentes de la base de datos.
     */
    protected function loadSuspensiones(): Collection
    {
        return collect(SuspensionPlazo::all(['fecha_inicio', 'fecha_fin']));
    }
}
