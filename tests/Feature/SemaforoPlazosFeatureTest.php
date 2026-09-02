<?php

use App\Models\Actuado;
use App\Models\Asignacion;
use App\Models\CatalogoActuado;
use App\Models\CatalogoEstado;
use App\Models\Expediente;
use App\Models\Feriado;
use App\Models\Plazo;
use App\Models\Reglamento;
use App\Models\Rol;
use App\Models\Usuario;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function paso9SemillaSemaforo(): array
{
    $rolTecnico = Rol::factory()->create(['codigo' => Rol::CODIGO_TECNICO]);
    $tecnico = Usuario::factory()->create(['rol_id' => $rolTecnico->id]);

    $reglamento = Reglamento::factory()->create();
    $estado = CatalogoEstado::factory()->create(['codigo' => 'EN_EVALUACION']);

    $catalogo = CatalogoActuado::create([
        'codigo' => 'ACT_SEMA_'.fake()->unique()->numberBetween(1000, 9999),
        'nombre' => 'Actuado de semaforo',
        'fase' => 'REGISTRO',
        'rol_id' => $rolTecnico->id,
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
        'creado_por' => $tecnico->id,
    ]);

    $actuado = Actuado::create([
        'expediente_id' => $expediente->id,
        'catalogo_actuado_id' => $catalogo->id,
        'usuario_id' => $tecnico->id,
        'estado_nuevo_id' => $estado->id,
        'contenido' => ['tipo' => 'ASIGNACION'],
    ]);

    Asignacion::create([
        'expediente_id' => $expediente->id,
        'usuario_id' => $tecnico->id,
        'rol_id' => $rolTecnico->id,
        'actuado_origen_id' => $actuado->id,
        'fecha_asignacion' => now(),
        'activa' => true,
    ]);

    return compact('tecnico', 'expediente', 'actuado');
}

function paso9CrearPlazoConSemaforo(Expediente $expediente, Actuado $actuado, array $overrides): Plazo
{
    return Plazo::create(array_merge([
        'expediente_id' => $expediente->id,
        'tipo_plazo' => 'EVALUACION',
        'dias_habiles_otorgados' => 5,
        'fecha_inicio' => '2026-08-27',
        'fecha_limite' => '2026-09-02',
        'estado' => 'VIGENTE',
        'actuado_disparador_id' => $actuado->id,
    ], $overrides));
}

it('expone sem_plazo VERDE en la bandeja del operador', function () {
    Carbon::setTestNow('2026-08-28 10:00:00');

    ['tecnico' => $tecnico, 'expediente' => $expediente, 'actuado' => $actuado] = paso9SemillaSemaforo();
    paso9CrearPlazoConSemaforo($expediente, $actuado, ['dias_habiles_otorgados' => 10, 'fecha_limite' => '2026-09-11']);

    Sanctum::actingAs($tecnico, ['*']);
    $response = $this->getJson('/api/bandeja')->assertOk();
    $expedienteJson = $response->json('data.0');

    expect($expedienteJson)->not->toBeNull()
        ->and($expedienteJson['sem_plazo']['codigo_color'])->toBe('VERDE')
        ->and($expedienteJson['sem_plazo']['dias_restantes'])->toBeGreaterThan(2);

    Carbon::setTestNow();
});

it('expone sem_plazo AMARILLO con 2 dias habiles restantes', function () {
    Carbon::setTestNow('2026-08-28 10:00:00');

    ['tecnico' => $tecnico, 'expediente' => $expediente, 'actuado' => $actuado] = paso9SemillaSemaforo();
    paso9CrearPlazoConSemaforo($expediente, $actuado, ['dias_habiles_otorgados' => 3, 'fecha_limite' => '2026-09-01']);

    Sanctum::actingAs($tecnico, ['*']);
    $response = $this->getJson('/api/bandeja')->assertOk();
    $exp = collect($response->json('data'))->firstWhere('id', $expediente->id);

    expect($exp['sem_plazo']['codigo_color'])->toBe('AMARILLO')
        ->and($exp['sem_plazo']['dias_restantes'])->toBe(2);

    Carbon::setTestNow();
});

it('expone sem_plazo ROJO con 1 dia habil restante', function () {
    Carbon::setTestNow('2026-08-28 10:00:00');

    ['tecnico' => $tecnico, 'expediente' => $expediente, 'actuado' => $actuado] = paso9SemillaSemaforo();
    paso9CrearPlazoConSemaforo($expediente, $actuado, ['dias_habiles_otorgados' => 3, 'fecha_limite' => '2026-08-31']);

    Sanctum::actingAs($tecnico, ['*']);
    $response = $this->getJson('/api/bandeja')->assertOk();
    $exp = collect($response->json('data'))->firstWhere('id', $expediente->id);

    expect($exp['sem_plazo']['codigo_color'])->toBe('ROJO');

    Carbon::setTestNow();
});

it('expone sem_plazo FUERA_DE_PLAZO en el detalle', function () {
    Carbon::setTestNow('2026-08-28 10:00:00');

    ['tecnico' => $tecnico, 'expediente' => $expediente, 'actuado' => $actuado] = paso9SemillaSemaforo();
    paso9CrearPlazoConSemaforo($expediente, $actuado, ['dias_habiles_otorgados' => 3, 'fecha_limite' => '2026-08-27']);

    Sanctum::actingAs($tecnico, ['*']);
    $response = $this->getJson('/api/expedientes/'.$expediente->id)->assertOk();

    expect($response->json('data.sem_plazo.codigo_color'))->toBe('FUERA_DE_PLAZO')
        ->and($response->json('data.sem_plazo.es_fuera_de_plazo'))->toBeTrue();

    Carbon::setTestNow();
});

it('excluye un feriado del calculo de dias habiles restantes', function () {
    Carbon::setTestNow('2026-08-28 10:00:00');

    Feriado::create(['fecha' => '2026-08-31', 'descripcion' => 'Feriado de prueba']);

    ['tecnico' => $tecnico, 'expediente' => $expediente, 'actuado' => $actuado] = paso9SemillaSemaforo();
    paso9CrearPlazoConSemaforo($expediente, $actuado, ['dias_habiles_otorgados' => 3, 'fecha_limite' => '2026-09-01']);

    Sanctum::actingAs($tecnico, ['*']);
    $response = $this->getJson('/api/bandeja')->assertOk();
    $exp = collect($response->json('data'))->firstWhere('id', $expediente->id);

    expect($exp['sem_plazo']['dias_restantes'])->toBe(1);

    Carbon::setTestNow();
});

it('retorna sem_plazo null para un expediente sin plazos', function () {
    ['tecnico' => $tecnico, 'expediente' => $expediente] = paso9SemillaSemaforo();

    Sanctum::actingAs($tecnico, ['*']);
    $response = $this->getJson('/api/expedientes/'.$expediente->id)->assertOk();

    expect($response->json('data.sem_plazo'))->toBeNull();
});
