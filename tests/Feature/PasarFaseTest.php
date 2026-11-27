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
use Database\Seeders\ParametroPlazoSeeder;
use Database\Seeders\ReglamentoSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Semilla para pruebas de transición diferida (cambiar_fase = false) y
 * posterior ejecución de POST /api/expedientes/{expediente}/pasar-fase.
 *
 * @return array{
 *     expediente: Expediente,
 *     tecnico: Usuario,
 *     tecnicoAjeno: Usuario,
 *     catalogoTransicion: CatalogoActuado,
 *     estadoOrigen: CatalogoEstado,
 *     estadoDestino: CatalogoEstado,
 * }
 */
function pasarFaseSemilla(TestCase $test): array
{
    foreach ([
        RolSeeder::class,
        CatalogoEstadoSeeder::class,
        ReglamentoSeeder::class,
        CatalogoActuadoSeeder::class,
        ParametroPlazoSeeder::class,
    ] as $seeder) {
        $test->seed($seeder);
    }

    $rolTecnico = Rol::where('codigo', Rol::CODIGO_TECNICO)->firstOrFail();
    $ac022 = Reglamento::where('codigo', 'AC_022_2018')->firstOrFail();

    $catalogoTransicion = CatalogoActuado::query()
        ->where('es_automatico', false)
        ->whereNotNull('estado_origen_id')
        ->whereNotNull('estado_destino_id')
        ->whereColumn('estado_origen_id', '!=', 'estado_destino_id')
        ->whereHas('roles', fn ($q) => $q->where('roles.id', $rolTecnico->id))
        ->with(['estadoOrigen', 'estadoDestino'])
        ->firstOrFail();

    $tecnico = Usuario::factory()->create(['rol_id' => $rolTecnico->id, 'activo' => true]);
    $tecnicoAjeno = Usuario::factory()->create(['rol_id' => $rolTecnico->id, 'activo' => true]);

    $expediente = Expediente::create([
        'nurej_code' => 'EXP-PF-'.fake()->unique()->numberBetween(100000, 999999),
        'via' => 'INGRESO_VERBAL',
        'reglamento_id' => $ac022->id,
        'estado_actual_id' => $catalogoTransicion->estado_origen_id,
        'resumen_hechos' => 'Hechos para probar transición diferida de fase.',
        'fecha_ingreso' => now(),
        'creado_por' => $tecnico->id,
    ]);

    $actuadoInicial = Actuado::create([
        'expediente_id' => $expediente->id,
        'catalogo_actuado_id' => CatalogoActuado::where('codigo', 'ACT_SORTEO_INICIAL')->firstOrFail()->id,
        'usuario_id' => $tecnico->id,
        'estado_nuevo_id' => $catalogoTransicion->estado_origen_id,
        'contenido' => ['descripcion' => 'Inicio de fase.'],
    ])->refresh();

    Asignacion::create([
        'expediente_id' => $expediente->id,
        'usuario_id' => $tecnico->id,
        'rol_id' => $rolTecnico->id,
        'actuado_origen_id' => $actuadoInicial->id,
        'fecha_asignacion' => now(),
        'activa' => true,
    ]);

    return [
        'expediente' => $expediente,
        'tecnico' => $tecnico,
        'tecnicoAjeno' => $tecnicoAjeno,
        'catalogoTransicion' => $catalogoTransicion,
        'estadoOrigen' => $catalogoTransicion->estadoOrigen,
        'estadoDestino' => $catalogoTransicion->estadoDestino,
    ];
}

it('registra actuado con cambiar_fase=false sin cambiar estado y luego pasar-fase aplica la transicion', function () {
    Storage::fake('local');
    $s = pasarFaseSemilla($this);
    Sanctum::actingAs($s['tecnico'], ['*']);

    $payload = [
        'catalogo_actuado_id' => $s['catalogoTransicion']->id,
        'descripcion' => 'Actuado emitido permaneciendo en la fase actual.',
        'cambiar_fase' => false,
    ];

    if ($s['catalogoTransicion']->requiere_adjunto) {
        $payload['adjunto'] = UploadedFile::fake()->create('respaldo.pdf', 100, 'application/pdf');
    }

    $storeResponse = $this->post("/api/expedientes/{$s['expediente']->id}/actuados", $payload, [
        'Accept' => 'application/json',
    ]);

    $storeResponse->assertCreated()
        ->assertJsonPath('data.cambiar_fase', false);

    $s['expediente']->refresh();
    expect($s['expediente']->estado_actual_id)->toBe($s['estadoOrigen']->id);

    // Impide un segundo actuado que cambie de fase mientras exista uno pendiente.
    $segundoResponse = $this->post("/api/expedientes/{$s['expediente']->id}/actuados", $payload, [
        'Accept' => 'application/json',
    ]);
    $segundoResponse->assertUnprocessable();

    // Aplica la transición pendiente con pasar-fase.
    $pasarResponse = $this->postJson("/api/expedientes/{$s['expediente']->id}/pasar-fase");
    $pasarResponse->assertSuccessful();

    $s['expediente']->refresh();
    expect($s['expediente']->estado_actual_id)->toBe($s['estadoDestino']->id);

    // Una segunda llamada ya no tiene pendiente por aplicar.
    $this->postJson("/api/expedientes/{$s['expediente']->id}/pasar-fase")
        ->assertUnprocessable();
});

it('rechaza pasar-fase con 403 para usuario sin asignacion activa', function () {
    Storage::fake('local');
    $s = pasarFaseSemilla($this);
    Sanctum::actingAs($s['tecnico'], ['*']);

    $payload = [
        'catalogo_actuado_id' => $s['catalogoTransicion']->id,
        'descripcion' => 'Actuado diferido para prueba de autorizacion.',
        'cambiar_fase' => false,
    ];

    if ($s['catalogoTransicion']->requiere_adjunto) {
        $payload['adjunto'] = UploadedFile::fake()->create('respaldo.pdf', 100, 'application/pdf');
    }

    $this->post("/api/expedientes/{$s['expediente']->id}/actuados", $payload, [
        'Accept' => 'application/json',
    ])->assertCreated();

    Sanctum::actingAs($s['tecnicoAjeno'], ['*']);

    $this->postJson("/api/expedientes/{$s['expediente']->id}/pasar-fase")
        ->assertForbidden();
});
