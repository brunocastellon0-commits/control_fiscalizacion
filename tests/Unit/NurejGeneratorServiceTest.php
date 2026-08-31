<?php

use App\Exceptions\CannotDeriveNurejException;
use App\Models\CatalogoEstado;
use App\Models\Expediente;
use App\Models\Reglamento;
use App\Models\Usuario;
use App\Services\NurejGeneratorService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('genera códigos NUREJ padre secuenciales dentro del año', function () {
    Carbon::setTestNow('2026-08-28 10:00:00');

    try {
        $service = new NurejGeneratorService;

        expect($service->generarPadre())->toBe('2026-00001')
            ->and($service->generarPadre())->toBe('2026-00002')
            ->and($service->generarPadre())->toBe('2026-00003');
    } finally {
        Carbon::setTestNow();
    }
});

it('reinicia el correlativo en cada año', function () {
    $service = new NurejGeneratorService;

    Carbon::setTestNow('2026-12-31 23:59:00');
    $service->generarPadre();
    Carbon::setTestNow();

    Carbon::setTestNow('2027-01-01 00:00:01');
    try {
        expect($service->generarPadre())->toBe('2027-00001');
    } finally {
        Carbon::setTestNow();
    }
});

it('genera NUREJ hijo derivado correlativo del padre', function () {
    $reglamento = Reglamento::factory()->create();
    $estado = CatalogoEstado::factory()->create();
    $usuario = Usuario::factory()->create();
    $service = new NurejGeneratorService;

    Carbon::setTestNow('2026-08-28 10:00:00');
    $padreCode = $service->generarPadre();
    Carbon::setTestNow();

    $padre = Expediente::create([
        'nurej_code' => $padreCode,
        'via' => 'TECNICO',
        'reglamento_id' => $reglamento->id,
        'estado_actual_id' => $estado->id,
        'creado_por' => $usuario->id,
    ]);

    $hijo1 = $service->generarHijo($padre->id);
    expect($hijo1)->toBe($padreCode.'-1');

    Expediente::create([
        'nurej_code' => $hijo1,
        'nurej_padre_id' => $padre->id,
        'via' => 'TECNICO',
        'reglamento_id' => $reglamento->id,
        'estado_actual_id' => $estado->id,
        'creado_por' => $usuario->id,
    ]);

    expect($service->generarHijo($padre->id))->toBe($padreCode.'-2');
});

it('no permite derivar un NUREJ de un expediente ya derivado (RN-10)', function () {
    $reglamento = Reglamento::factory()->create();
    $estado = CatalogoEstado::factory()->create();
    $usuario = Usuario::factory()->create();
    $service = new NurejGeneratorService;

    Carbon::setTestNow('2026-08-28 10:00:00');
    $padreCode = $service->generarPadre();
    Carbon::setTestNow();

    $padre = Expediente::create([
        'nurej_code' => $padreCode,
        'via' => 'TECNICO',
        'reglamento_id' => $reglamento->id,
        'estado_actual_id' => $estado->id,
        'creado_por' => $usuario->id,
    ]);

    $hijo = Expediente::create([
        'nurej_code' => $service->generarHijo($padre->id),
        'nurej_padre_id' => $padre->id,
        'via' => 'TECNICO',
        'reglamento_id' => $reglamento->id,
        'estado_actual_id' => $estado->id,
        'creado_por' => $usuario->id,
    ]);

    expect(fn () => $service->generarHijo($hijo->id))
        ->toThrow(CannotDeriveNurejException::class)
        ->and(Expediente::count())->toBe(2);
});
