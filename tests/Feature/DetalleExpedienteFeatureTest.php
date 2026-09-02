<?php

use App\Models\Actuado;
use App\Models\Asignacion;
use App\Models\CatalogoActuado;
use App\Models\CatalogoEstado;
use App\Models\Expediente;
use App\Models\Reglamento;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\actingAs;

function fase3WebOperador(string $username): Usuario
{
    return Usuario::factory()->create([
        'username' => $username,
        'password_hash' => Hash::make('password'),
        'activo' => true,
        'rol_id' => Rol::factory()->create(['codigo' => Rol::CODIGO_AUD_JURIDICO])->id,
    ]);
}

it('incluye los actuados con hashes en el detalle del expediente', function () {
    $rolOperador = Rol::factory()->create(['codigo' => Rol::CODIGO_AUD_JURIDICO]);
    $operador = Usuario::factory()->create(['rol_id' => $rolOperador->id]);

    $reglamento = Reglamento::factory()->create();
    $estado = CatalogoEstado::factory()->create(['codigo' => 'EN_EVALUACION']);

    $catalogo = CatalogoActuado::create([
        'codigo' => 'ACT_OBSERVACION',
        'nombre' => 'Observacion',
        'fase' => 'ADMISIBILIDAD',
        'rol_id' => $rolOperador->id,
        'estado_destino_id' => $estado->id,
        'es_automatico' => false,
        'requiere_adjunto' => false,
    ]);

    $expediente = Expediente::create([
        'nurej_code' => 'DET-'.fake()->unique()->numberBetween(10000, 99999),
        'via' => 'TECNICO',
        'reglamento_id' => $reglamento->id,
        'estado_actual_id' => $estado->id,
        'fecha_ingreso' => now(),
        'creado_por' => $operador->id,
    ]);

    // Un actuado previo para el encadenado de hashes
    $actuado1 = Actuado::create([
        'expediente_id' => $expediente->id,
        'catalogo_actuado_id' => $catalogo->id,
        'usuario_id' => $operador->id,
        'estado_nuevo_id' => $estado->id,
        'contenido' => ['descripcion' => 'Primer actuado'],
    ])->refresh();

    Asignacion::create([
        'expediente_id' => $expediente->id,
        'usuario_id' => $operador->id,
        'rol_id' => $operador->rol_id,
        'actuado_origen_id' => $actuado1->id,
        'fecha_asignacion' => now(),
        'activa' => true,
    ]);

    Sanctum::actingAs($operador, ['*']);

    $response = $this->getJson('/api/expedientes/'.$expediente->id)->assertOk();

    $actuados = $response->json('data.actuados');
    expect($actuados)->not->toBeEmpty()
        ->and($actuados[0]['hash_actuado'])->toMatch('/^[0-9a-f]{64}$/')
        ->and($actuados[0]['tipo_actuado']['codigo'])->toBe('ACT_OBSERVACION');
});

it('carga la vista de detalle por sesion web con asignacion activa', function () {
    Storage::fake('local');

    $usuario = fase3WebOperador('operador_detalle');
    $reglamento = Reglamento::factory()->create();
    $estado = CatalogoEstado::factory()->create(['codigo' => 'EN_EVALUACION']);

    $catalogo = CatalogoActuado::create([
        'codigo' => 'ACT_OBSERVACION_2',
        'nombre' => 'Observacion',
        'fase' => 'ADMISIBILIDAD',
        'rol_id' => $usuario->rol_id,
        'estado_destino_id' => $estado->id,
        'es_automatico' => false,
        'requiere_adjunto' => false,
    ]);

    $expediente = Expediente::create([
        'nurej_code' => 'WEBDET-'.fake()->unique()->numberBetween(10000, 99999),
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
        'contenido' => ['descripcion' => 'asignacion'],
    ])->refresh();

    Asignacion::create([
        'expediente_id' => $expediente->id,
        'usuario_id' => $usuario->id,
        'rol_id' => $usuario->rol_id,
        'actuado_origen_id' => $actuado->id,
        'fecha_asignacion' => now(),
        'activa' => true,
    ]);

    actingAs($usuario)
        ->get('/expedientes/'.$expediente->id)
        ->assertOk()
        ->assertSee('Cadena de Custodia');
});

it('el creador sin asignacion puede ver su expediente antes del sorteo', function () {
    $creador = Usuario::factory()->create(['rol_id' => Rol::factory()->create(['codigo' => Rol::CODIGO_TECNICO])->id]);
    $reglamento = Reglamento::factory()->create();
    $estado = CatalogoEstado::factory()->create(['codigo' => 'PENDIENTE_SORTEO']);

    $expediente = Expediente::create([
        'nurej_code' => 'CREADOR-'.fake()->unique()->numberBetween(10000, 99999),
        'via' => 'TECNICO',
        'reglamento_id' => $reglamento->id,
        'estado_actual_id' => $estado->id,
        'fecha_ingreso' => now(),
        'creado_por' => $creador->id,
    ]);

    Sanctum::actingAs($creador, ['*']);

    $this->getJson('/api/expedientes/'.$expediente->id)->assertOk();
});

it('el creador pierde acceso una vez el expediente ya fue sorteado', function () {
    $creador = Usuario::factory()->create(['rol_id' => Rol::factory()->create(['codigo' => Rol::CODIGO_TECNICO])->id]);
    $asignado = Usuario::factory()->create(['rol_id' => Rol::factory()->create(['codigo' => Rol::CODIGO_AUD_JURIDICO])->id]);
    $reglamento = Reglamento::factory()->create();
    $estado = CatalogoEstado::factory()->create(['codigo' => 'EN_EVALUACION']);

    $catalogo = CatalogoActuado::create([
        'codigo' => 'ACT_SORTEO_CREADOR_TEST',
        'nombre' => 'Sorteo Test',
        'fase' => 'ADMISIBILIDAD',
        'rol_id' => $creador->rol_id,
        'estado_origen_id' => $estado->id,
        'es_automatico' => false,
        'requiere_adjunto' => false,
    ]);

    $expediente = Expediente::create([
        'nurej_code' => 'CREADOR-'.fake()->unique()->numberBetween(10000, 99999),
        'via' => 'TECNICO',
        'reglamento_id' => $reglamento->id,
        'estado_actual_id' => $estado->id,
        'fecha_ingreso' => now(),
        'creado_por' => $creador->id,
    ]);

    $actuado = Actuado::create([
        'expediente_id' => $expediente->id,
        'catalogo_actuado_id' => $catalogo->id,
        'usuario_id' => $creador->id,
        'estado_nuevo_id' => $estado->id,
        'contenido' => ['descripcion' => 'Sorteo'],
    ])->refresh();

    Asignacion::create([
        'expediente_id' => $expediente->id,
        'usuario_id' => $asignado->id,
        'rol_id' => $asignado->rol_id,
        'actuado_origen_id' => $actuado->id,
        'fecha_asignacion' => now(),
        'activa' => true,
    ]);

    $this->withHeader('Accept', 'application/json');
    Sanctum::actingAs($creador, ['*']);

    $this->getJson('/api/expedientes/'.$expediente->id)->assertForbidden();
});

it('deniega 403 por sesion web a un operador sin asignacion', function () {
    $ajeno = fase3WebOperador('operador_ajeno');
    $dueno = Usuario::factory()->create(['rol_id' => $ajeno->rol_id]);

    $reglamento = Reglamento::factory()->create();
    $estado = CatalogoEstado::factory()->create();

    $expediente = Expediente::create([
        'nurej_code' => 'WEB403-'.fake()->unique()->numberBetween(10000, 99999),
        'via' => 'TECNICO',
        'reglamento_id' => $reglamento->id,
        'estado_actual_id' => $estado->id,
        'fecha_ingreso' => now(),
        'creado_por' => $dueno->id,
    ]);

    $this->withHeader('Accept', 'application/json');
    actingAs($ajeno)
        ->get('/expedientes/'.$expediente->id)
        ->assertForbidden();
});
