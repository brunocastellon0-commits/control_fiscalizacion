<?php

use App\Models\Actuado;
use App\Models\Asignacion;
use App\Models\CatalogoActuado;
use App\Models\CatalogoEstado;
use App\Models\Expediente;
use App\Models\Reglamento;
use App\Models\Rol;
use App\Models\Usuario;
use Database\Seeders\CatalogoActuadoSeeder;
use Database\Seeders\CatalogoEstadoSeeder;
use Database\Seeders\ReglamentoSeeder;
use Database\Seeders\RolSeeder;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Fase B — Enmienda de un actuado: corrige la descripción de un actuado ya
 * emitido mediante un ACT_ENMIENDA append-only que lo referencia
 * (actuado_referencia_id). El original es inmutable y el estado procesal
 * de la causa no cambia.
 *
 * @return array{
 *     expediente: Expediente,
 *     tecnico: Usuario,
 *     tecnicoAjeno: Usuario,
 *     estado: CatalogoEstado,
 *     actuadoBase: Actuado,
 * }
 */
function actuadoEnmiendaSemilla(TestCase $test): array
{
    foreach ([
        RolSeeder::class,
        CatalogoEstadoSeeder::class,
        ReglamentoSeeder::class,
        CatalogoActuadoSeeder::class,
    ] as $seeder) {
        $test->seed($seeder);
    }

    $rolTecnico = Rol::where('codigo', Rol::CODIGO_TECNICO)->firstOrFail();
    $estado = CatalogoEstado::where('codigo', 'EN_EVALUACION')->firstOrFail();
    $ac022 = Reglamento::where('codigo', 'AC_022_2018')->firstOrFail();

    $tecnico = Usuario::factory()->create(['rol_id' => $rolTecnico->id, 'activo' => true]);
    $tecnicoAjeno = Usuario::factory()->create(['rol_id' => $rolTecnico->id, 'activo' => true]);

    $expediente = Expediente::create([
        'nurej_code' => 'EXP-EA-'.fake()->unique()->numberBetween(100000, 999999),
        'via' => 'INGRESO_VERBAL',
        'reglamento_id' => $ac022->id,
        'estado_actual_id' => $estado->id,
        'resumen_hechos' => 'Hechos de la causa para enmienda de actuado.',
        'fecha_ingreso' => now(),
        'creado_por' => $tecnico->id,
    ]);

    $actuadoBase = Actuado::create([
        'expediente_id' => $expediente->id,
        'catalogo_actuado_id' => CatalogoActuado::where('codigo', 'ACT_SORTEO_INICIAL')->firstOrFail()->id,
        'usuario_id' => $tecnico->id,
        'estado_nuevo_id' => $estado->id,
        'contenido' => ['descripcion' => 'Descripcion original con errata.'],
    ])->refresh();

    Asignacion::create([
        'expediente_id' => $expediente->id,
        'usuario_id' => $tecnico->id,
        'rol_id' => $rolTecnico->id,
        'actuado_origen_id' => $actuadoBase->id,
        'fecha_asignacion' => now(),
        'activa' => true,
    ]);

    return [
        'expediente' => $expediente,
        'tecnico' => $tecnico,
        'tecnicoAjeno' => $tecnicoAjeno,
        'estado' => $estado,
        'actuadoBase' => $actuadoBase,
    ];
}

it('enmienda un actuado: 200, actuado de referencia append-only y estado sin cambio', function () {
    $s = actuadoEnmiendaSemilla($this);
    Sanctum::actingAs($s['tecnico'], ['*']);

    $response = $this->postJson(
        "/api/expedientes/{$s['expediente']->id}/actuados/{$s['actuadoBase']->id}/enmienda",
        [
            'descripcion' => 'Descripcion original corregida.',
            'motivo' => 'Correccion de errata en la transcripcion.',
        ],
    );

    $response->assertStatus(200)
        ->assertJsonStructure(['actuado_enmienda_id', 'expediente_actualizado']);

    $enmienda = Actuado::findOrFail($response->json('actuado_enmienda_id'));

    expect($enmienda->tipoActuado->codigo)->toBe('ACT_ENMIENDA')
        ->and($enmienda->actuado_referencia_id)->toBe($s['actuadoBase']->id)
        ->and($enmienda->contenido['descripcion'])->toBe('Descripcion original corregida.')
        ->and($enmienda->contenido['motivo'])->toBe('Correccion de errata en la transcripcion.')
        ->and($enmienda->hash_actuado)->toMatch('/^[0-9a-f]{64}$/')
        ->and($enmienda->hash_anterior)->toBe($s['actuadoBase']->hash_actuado);

    // No-op de estado: no transiciona la causa ni toca los relojes.
    expect($enmienda->estado_anterior_id)->toBe($s['estado']->id)
        ->and($enmienda->estado_nuevo_id)->toBe($s['estado']->id)
        ->and($s['expediente']->fresh()->estado_actual_id)->toBe($s['estado']->id);

    // El actuado original permanece inmutable.
    $original = $s['actuadoBase']->fresh();
    expect($original->contenido['descripcion'])->toBe('Descripcion original con errata.')
        ->and($original->actuado_referencia_id)->toBeNull();
});

it('responde 404 al enmiendar un actuado de otro expediente', function () {
    $s = actuadoEnmiendaSemilla($this);
    Sanctum::actingAs($s['tecnico'], ['*']);

    $ac022 = Reglamento::where('codigo', 'AC_022_2018')->firstOrFail();
    $otroExpediente = Expediente::create([
        'nurej_code' => 'EXP-EA2-'.fake()->unique()->numberBetween(100000, 999999),
        'via' => 'INGRESO_VERBAL',
        'reglamento_id' => $ac022->id,
        'estado_actual_id' => $s['estado']->id,
        'fecha_ingreso' => now(),
        'creado_por' => $s['tecnico']->id,
    ]);
    $actuadoAjeno = Actuado::create([
        'expediente_id' => $otroExpediente->id,
        'catalogo_actuado_id' => CatalogoActuado::where('codigo', 'ACT_SORTEO_INICIAL')->firstOrFail()->id,
        'usuario_id' => $s['tecnico']->id,
        'estado_nuevo_id' => $s['estado']->id,
        'contenido' => ['descripcion' => 'Actuado de otro expediente.'],
    ]);

    $this->postJson(
        "/api/expedientes/{$s['expediente']->id}/actuados/{$actuadoAjeno->id}/enmienda",
        ['descripcion' => 'Descripcion valida.', 'motivo' => 'Motivo valido para el 404.'],
    )->assertStatus(404);
});

it('rechaza con 422 una segunda enmienda sobre el mismo actuado', function () {
    $s = actuadoEnmiendaSemilla($this);
    Sanctum::actingAs($s['tecnico'], ['*']);

    $ruta = "/api/expedientes/{$s['expediente']->id}/actuados/{$s['actuadoBase']->id}/enmienda";
    $datos = ['descripcion' => 'Primera descripcion corregida.', 'motivo' => 'Motivo de la primera enmienda.'];

    $this->postJson($ruta, $datos)->assertStatus(200);

    $this->postJson($ruta, [
        'descripcion' => 'Segunda descripcion corregida.',
        'motivo' => 'Motivo de la segunda enmienda.',
    ])->assertStatus(422)->assertJsonValidationErrors(['actuado']);
});

it('rechaza con 422 enmiendar un actuado que ya es una enmienda', function () {
    $s = actuadoEnmiendaSemilla($this);
    Sanctum::actingAs($s['tecnico'], ['*']);

    $primera = $this->postJson(
        "/api/expedientes/{$s['expediente']->id}/actuados/{$s['actuadoBase']->id}/enmienda",
        ['descripcion' => 'Descripcion corregida unica.', 'motivo' => 'Motivo valido de la enmienda.'],
    )->assertStatus(200);

    $this->postJson(
        "/api/expedientes/{$s['expediente']->id}/actuados/{$primera->json('actuado_enmienda_id')}/enmienda",
        ['descripcion' => 'Descripcion de la enmienda.', 'motivo' => 'Motivo sobre una enmienda.'],
    )->assertStatus(422)->assertJsonValidationErrors(['actuado']);
});

it('valida motivo obligatorio de al menos 10 caracteres', function () {
    $s = actuadoEnmiendaSemilla($this);
    Sanctum::actingAs($s['tecnico'], ['*']);

    $this->postJson(
        "/api/expedientes/{$s['expediente']->id}/actuados/{$s['actuadoBase']->id}/enmienda",
        ['descripcion' => 'Descripcion corregida.', 'motivo' => 'corto'],
    )->assertStatus(422)->assertJsonValidationErrors(['motivo']);
});

it('prohíbe la enmienda al operador sin asignación activa del expediente', function () {
    $s = actuadoEnmiendaSemilla($this);
    Sanctum::actingAs($s['tecnicoAjeno'], ['*']);

    $this->postJson(
        "/api/expedientes/{$s['expediente']->id}/actuados/{$s['actuadoBase']->id}/enmienda",
        ['descripcion' => 'Descripcion corregida.', 'motivo' => 'Motivo sin autorizacion.'],
    )->assertStatus(403);
});
