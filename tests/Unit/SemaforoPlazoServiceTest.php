<?php

use App\Models\Actuado;
use App\Models\CatalogoActuado;
use App\Models\CatalogoEstado;
use App\Models\Expediente;
use App\Models\Plazo;
use App\Models\Reglamento;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\SemaforoPlazoService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * Hoy = viernes 2026-08-28. daysRemaining() cuenta los días hábiles desde el
 * día siguiente a hoy hasta la fecha límite (inclusive). Días restantes:
 *   límite vie 28 -> 0 · lun 31 -> 1 · mar 01 -> 2 · mié 02 -> 3 · jue 03 -> 4 · lun 07 -> 6
 */
const HOY_SEMAFORO = '2026-08-28 10:00:00';

function crearPlazoSemaforo(array $atributos = []): array
{
    $rol = Rol::factory()->create();
    $usuario = Usuario::factory()->create(['rol_id' => $rol->id]);
    $reglamento = Reglamento::factory()->create();
    $estado = CatalogoEstado::factory()->create();
    $catalogoActuado = CatalogoActuado::create([
        'codigo' => 'ACT_TEST_'.fake()->unique()->numberBetween(1000, 9999),
        'nombre' => 'Actuado de test',
        'fase' => 'TEST',
        'estado_destino_id' => $estado->id,
        'es_automatico' => false,
        'requiere_adjunto' => false,
    ]);

    $expediente = Expediente::create([
        'nurej_code' => '2026-'.fake()->unique()->numberBetween(10000, 99999),
        'via' => 'TECNICO',
        'reglamento_id' => $reglamento->id,
        'estado_actual_id' => $estado->id,
        'fecha_ingreso' => now(),
        'creado_por' => $usuario->id,
    ]);

    $actuado = Actuado::create([
        'expediente_id' => $expediente->id,
        'catalogo_actuado_id' => $catalogoActuado->id,
        'usuario_id' => $usuario->id,
        'estado_nuevo_id' => $estado->id,
        'contenido' => ['descripcion' => 'apertura'],
    ]);

    $plazo = Plazo::create(array_merge([
        'expediente_id' => $expediente->id,
        'tipo_plazo' => 'EVALUACION',
        'dias_habiles_otorgados' => 3,
        'fecha_inicio' => '2026-08-27',
        'fecha_limite' => '2026-09-02',
        'estado' => 'VIGENTE',
        'actuado_disparador_id' => $actuado->id,
    ], $atributos));

    return [$expediente, $plazo];
}

it('marca FUERA_DE_PLAZO cuando el vencimiento ya fue superado', function () {
    Carbon::setTestNow(HOY_SEMAFORO);

    try {
        [, $plazo] = crearPlazoSemaforo(['fecha_limite' => '2026-08-27']);

        $resumen = app(SemaforoPlazoService::class)->evaluarPlazo($plazo);

        expect($resumen['codigo_color'])->toBe('FUERA_DE_PLAZO')
            ->and($resumen['es_fuera_de_plazo'])->toBeTrue()
            ->and($resumen['dias_restantes'])->toBe(0)
            ->and($resumen['fecha_limite'])->toBe('2026-08-27');
    } finally {
        Carbon::setTestNow();
    }
});

it('marca ROJO cuando quedan 0 o 1 días hábiles', function () {
    Carbon::setTestNow(HOY_SEMAFORO);

    try {
        [, $plazoHoy] = crearPlazoSemaforo(['fecha_limite' => '2026-08-28']);
        [, $plazoProximo] = crearPlazoSemaforo(['fecha_limite' => '2026-08-31']);
        $evaluar = fn (Plazo $p) => app(SemaforoPlazoService::class)->evaluarPlazo($p);

        expect($evaluar($plazoHoy)['codigo_color'])->toBe('ROJO')
            ->and($evaluar($plazoProximo)['codigo_color'])->toBe('ROJO');
    } finally {
        Carbon::setTestNow();
    }
});

it('marca AMARILLO en plazos cortos cuando quedan 2 días', function () {
    Carbon::setTestNow(HOY_SEMAFORO);

    try {
        [, $plazo] = crearPlazoSemaforo(['dias_habiles_otorgados' => 3, 'fecha_limite' => '2026-09-01']);

        $resumen = app(SemaforoPlazoService::class)->evaluarPlazo($plazo);

        expect($resumen['codigo_color'])->toBe('AMARILLO')
            ->and($resumen['dias_restantes'])->toBe(2);
    } finally {
        Carbon::setTestNow();
    }
});

it('marca AMARILLO en plazos largos al entrar al último tercio', function () {
    Carbon::setTestNow(HOY_SEMAFORO);

    try {
        [, $plazoTercio] = crearPlazoSemaforo(['dias_habiles_otorgados' => 10, 'fecha_limite' => '2026-09-03']);
        [, $plazoInterior] = crearPlazoSemaforo(['dias_habiles_otorgados' => 10, 'fecha_limite' => '2026-09-02']);
        $evaluar = fn (Plazo $p) => app(SemaforoPlazoService::class)->evaluarPlazo($p);

        expect($evaluar($plazoTercio)['codigo_color'])->toBe('AMARILLO')
            ->and($evaluar($plazoInterior)['codigo_color'])->toBe('AMARILLO');
    } finally {
        Carbon::setTestNow();
    }
});

it('marca VERDE con margen operativo holgado', function () {
    Carbon::setTestNow(HOY_SEMAFORO);

    try {
        [, $plazoCorto] = crearPlazoSemaforo(['dias_habiles_otorgados' => 3, 'fecha_limite' => '2026-09-02']);
        [, $plazoLargo] = crearPlazoSemaforo(['dias_habiles_otorgados' => 10, 'fecha_limite' => '2026-09-07']);
        $evaluar = fn (Plazo $p) => app(SemaforoPlazoService::class)->evaluarPlazo($p);

        expect($evaluar($plazoCorto)['codigo_color'])->toBe('VERDE')
            ->and($evaluar($plazoLargo)['codigo_color'])->toBe('VERDE');
    } finally {
        Carbon::setTestNow();
    }
});

it('retorna el estado sin cálculo para plazos no vigentes', function () {
    Carbon::setTestNow(HOY_SEMAFORO);

    try {
        [, $plazoSuspendido] = crearPlazoSemaforo(['estado' => 'SUSPENDIDO', 'fecha_limite' => '2026-08-27']);

        $resumen = app(SemaforoPlazoService::class)->evaluarPlazo($plazoSuspendido);

        expect($resumen['codigo_color'])->toBe('SUSPENDIDO')
            ->and($resumen['dias_restantes'])->toBeNull()
            ->and($resumen['porcentaje_consumido'])->toBeNull()
            ->and($resumen['es_fuera_de_plazo'])->toBeFalse();
    } finally {
        Carbon::setTestNow();
    }
});

it('incluye el porcentaje consumido acotado a [0, 1]', function () {
    Carbon::setTestNow(HOY_SEMAFORO);

    try {
        [, $plazo] = crearPlazoSemaforo(['fecha_limite' => '2026-09-02']);

        $porcentaje = app(SemaforoPlazoService::class)->evaluarPlazo($plazo)['porcentaje_consumido'];

        expect($porcentaje)->toBeGreaterThan(0.0)
            ->and($porcentaje)->toBeLessThanOrEqual(1.0);
    } finally {
        Carbon::setTestNow();
    }
});

it('consolida las métricas de la bandeja por color de urgencia', function () {
    Carbon::setTestNow(HOY_SEMAFORO);

    try {
        [$verde] = crearPlazoSemaforo(['dias_habiles_otorgados' => 3, 'fecha_limite' => '2026-09-03']);
        [$precaucion] = crearPlazoSemaforo(['dias_habiles_otorgados' => 3, 'fecha_limite' => '2026-09-01']);
        [$urgente] = crearPlazoSemaforo(['fecha_limite' => '2026-08-31']);

        // Expediente con dos plazos vigentes: prevalece el más urgente.
        [$urgenteDoble] = crearPlazoSemaforo(['fecha_limite' => '2026-08-31']);
        crearPlazoSemaforo([
            'expediente_id' => $urgenteDoble->id,
            'fecha_limite' => '2026-09-11',
        ]);

        [$fuera] = crearPlazoSemaforo(['fecha_limite' => '2026-08-27']);

        $resumen = app(SemaforoPlazoService::class)->resumenBandeja([
            $verde, $precaucion, $urgente, $urgenteDoble, $fuera,
        ]);

        expect($resumen)->toBe([
            'total_en_plazo' => 1,
            'total_precaucion' => 1,
            'total_urgente' => 2,
            'total_fuera_de_plazo' => 1,
        ]);
    } finally {
        Carbon::setTestNow();
    }
});
