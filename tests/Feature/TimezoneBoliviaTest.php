<?php

use App\Models\Actuado;
use App\Models\CatalogoActuado;
use App\Models\CatalogoEstado;
use App\Models\Expediente;
use App\Models\Plazo;
use App\Models\Reglamento;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\PlazoCalculatorService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

it('configura la zona horaria institucional America/La_Paz', function () {
    expect(config('app.timezone'))->toBe('America/La_Paz')
        ->and(now()->timezoneName)->toBe('America/La_Paz')
        ->and(now()->format('P'))->toBe('-04:00');
});

it('cierra los plazos a las 23:59:59 hora de Bolivia', function () {
    $service = new PlazoCalculatorService(collect(), collect());

    // 27 (jue) + 2 hábiles (vie 28, lun 31) — ver PlazoCalculatorServiceTest
    $vencimiento = $service->calculateDueDate('2026-08-27', 2);

    expect($vencimiento->format('Y-m-d'))->toBe('2026-08-31')
        ->and($vencimiento->format('H:i:s'))->toBe('23:59:59')
        ->and($vencimiento->timezoneName)->toBe('America/La_Paz');
});

it('persiste expedientes, actuados y plazos sin corrimiento horario', function () {
    $frozen = '2026-09-07 10:15:00';
    Carbon::setTestNow($frozen);

    try {
        $rol = Rol::factory()->create();
        $usuario = Usuario::factory()->create(['rol_id' => $rol->id]);
        $reglamento = Reglamento::factory()->create();
        $estado = CatalogoEstado::factory()->create();

        $catalogo = CatalogoActuado::create([
            'codigo' => 'ACT_TZ_'.fake()->unique()->numberBetween(1000, 9999),
            'nombre' => 'Actuado verificación timezone',
            'fase' => 'REGISTRO',
            'rol_id' => $rol->id,
            'estado_destino_id' => $estado->id,
            'es_automatico' => false,
            'requiere_adjunto' => false,
        ]);

        $expediente = Expediente::create([
            'nurej_code' => 'TZ-'.fake()->unique()->numberBetween(100000, 999999),
            'via' => 'TECNICO',
            'reglamento_id' => $reglamento->id,
            'estado_actual_id' => $estado->id,
            'fecha_ingreso' => now(),
            'creado_por' => $usuario->id,
        ]);

        $actuado = Actuado::create([
            'expediente_id' => $expediente->id,
            'catalogo_actuado_id' => $catalogo->id,
            'usuario_id' => $usuario->id,
            'estado_nuevo_id' => $estado->id,
            'contenido' => ['descripcion' => 'Verificación de fechas Bolivia'],
        ])->refresh();

        $plazo = Plazo::create([
            'expediente_id' => $expediente->id,
            'tipo_plazo' => 'TZ_VERIFICACION',
            'dias_habiles_otorgados' => 3,
            'fecha_inicio' => '2026-09-07',
            'fecha_limite' => '2026-09-10',
            'estado' => 'VIGENTE',
            'fuera_de_plazo' => false,
            'actuado_disparador_id' => $actuado->id,
        ]);

        // Expediente::$timestamps = false → created_at no se escribe (como en
        // producción); la fecha persistida es fecha_ingreso, escrita por Eloquent
        // con el reloj congelado en America/La_Paz: sin corrimiento.
        $expRow = DB::table('expedientes')->where('id', $expediente->id)->first();
        $plazoRow = DB::table('plazos')->where('id', $plazo->id)->first();

        expect($expRow->fecha_ingreso)->toBe($frozen)
            ->and($expRow->created_at)->toBeNull()
            ->and($plazoRow->fecha_inicio)->toBe('2026-09-07')
            ->and($plazoRow->fecha_limite)->toBe('2026-09-10');
    } finally {
        Carbon::setTestNow();
    }

    // actuados.fecha_hora usa el reloj por defecto de MySQL (fuera del congelado):
    // debe coincidir con el reloj real de La Paz (tolerancia de 10 s, sin desfase de horas).
    $fechaHora = DB::table('actuados')->where('id', $actuado->id)->value('fecha_hora');
    $desfase = Carbon::parse($fechaHora)->diffInSeconds(now());

    expect($fechaHora)->toMatch('/^2026-09-07|^20\d\d-\d\d-\d\d \d\d:\d\d:\d\d$/')
        ->and($desfase)->toBeLessThanOrEqual(10);
});

it('ro la fecha recién al pasar la medianoche de La Paz (AUD-0043)', function () {
    try {
        // 03:59:59 UTC = 23:59:59 de La Paz: el día aún no rueda.
        Carbon::setTestNow(Carbon::parse('2026-09-08 03:59:59', 'UTC'));

        expect(now()->toDateString())->toBe('2026-09-07')
            ->and(now()->format('H:i:s'))->toBe('23:59:59');

        // 04:00:00 UTC = 00:00:00 de La Paz: recién ahí rueda al día siguiente.
        Carbon::setTestNow(Carbon::parse('2026-09-08 04:00:00', 'UTC'));

        expect(now()->toDateString())->toBe('2026-09-08')
            ->and(now()->format('H:i:s'))->toBe('00:00:00');
    } finally {
        Carbon::setTestNow();
    }
});

it('mantiene el cálculo de días restantes consistente en la zona institucional', function () {
    $service = new PlazoCalculatorService(collect(), collect());

    Carbon::setTestNow('2026-08-27 10:00:00');

    try {
        $restantes = $service->daysRemaining('2026-08-31');
        $vencimiento = $service->calculateDueDate('2026-08-27', 2);
    } finally {
        Carbon::setTestNow();
    }

    // 28 (vie) y 31 (lun) = 2 hábiles restantes; el vencimiento cierra en La Paz.
    expect($restantes)->toBe(2)
        ->and($vencimiento->format('Y-m-d H:i:s'))->toBe('2026-08-31 23:59:59')
        ->and($vencimiento->timezoneName)->toBe('America/La_Paz');
});
