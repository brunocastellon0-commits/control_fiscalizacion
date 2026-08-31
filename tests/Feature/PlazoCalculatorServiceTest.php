<?php

use App\Models\SuspensionPlazo;
use App\Services\PlazoCalculatorService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

it('calcula el vencimiento sumando días hábiles saltando fines de semana', function () {
    $service = new PlazoCalculatorService(collect(), collect());

    $vencimiento = $service->calculateDueDate('2026-08-27', 2);

    // 27 (jue) + 1 hábil = 28 (vie) + 1 hábil = 31 (lun, sáb/dom saltados)
    expect($vencimiento->format('Y-m-d'))->toBe('2026-08-31')
        ->and($vencimiento->format('H:i:s'))->toBe('23:59:59');
});

it('salta feriados al sumar días hábiles', function () {
    $feriados = new Collection(['2026-09-01']);

    $service = new PlazoCalculatorService($feriados, collect());

    // 28 (vie) + 1 hábil = 1 (mar) es feriado -> salta a 2 (mié)
    $vencimiento = $service->calculateDueDate('2026-08-29', 2);

    expect($vencimiento->format('Y-m-d'))->toBe('2026-09-02');
});

it('salta días dentro del rango de suspensión', function () {
    $suspensiones = new Collection([
        (new SuspensionPlazo)->forceFill([
            'fecha_inicio' => '2026-09-07',
            'fecha_fin' => '2026-09-09',
        ]),
    ]);

    $service = new PlazoCalculatorService(collect(), $suspensiones);

    // 4 (vie) + 1 hábil = 7-9 suspendidos (lun-mié) -> 10 (jue) 1er hábil -> 11 (vie) 2do hábil
    $vencimiento = $service->calculateDueDate('2026-09-04', 2);

    expect($vencimiento->format('Y-m-d'))->toBe('2026-09-11');
});

it('no cuenta el día de inicio del plazo', function () {
    $service = new PlazoCalculatorService(collect(), collect());

    $vencimiento = $service->calculateDueDate('2026-08-31', 1);

    // el día de inicio (31, lun) no cuenta -> vence al día hábil siguiente (1, mar)
    expect($vencimiento->format('Y-m-d'))->toBe('2026-09-01');
});

it('devuelve 0 días restantes cuando el plazo está vencido', function () {
    $service = new PlazoCalculatorService(collect(), collect());

    $restantes = $service->daysRemaining(Carbon::yesterday());

    expect($restantes)->toBe(0);
});

it('cuenta días hábiles restantes hasta la fecha límite', function () {
    $service = new PlazoCalculatorService(collect(), collect());

    Carbon::setTestNow('2026-08-27 10:00:00');

    try {
        $restantes = $service->daysRemaining('2026-08-31');
    } finally {
        Carbon::setTestNow();
    }

    // días hábiles entre hoy (27, excluido) y el límite (31, incluido): 28 (vie) y 31 (lun) = 2
    expect($restantes)->toBe(2);
});
