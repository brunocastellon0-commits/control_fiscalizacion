<?php

use App\Models\Actuado;
use App\Models\Asignacion;
use App\Models\CatalogoActuado;
use App\Models\CatalogoEstado;
use App\Models\Expediente;
use App\Models\Feriado;
use App\Models\ParametroPlazo;
use App\Models\Plazo;
use App\Models\Reglamento;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\PlanificacionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

function feSemilla(): array
{
    $rolEncargada = Rol::factory()->create(['codigo' => Rol::CODIGO_ENCARGADA, 'nombre' => 'Encargada']);
    $rolTecnico = Rol::factory()->create(['codigo' => Rol::CODIGO_TECNICO, 'nombre' => 'Técnico']);

    $encargada = Usuario::factory()->create(['rol_id' => $rolEncargada->id, 'activo' => true]);
    $tecnico = Usuario::factory()->create(['rol_id' => $rolTecnico->id, 'activo' => true]);

    $ac022 = Reglamento::factory()->create(['codigo' => 'AC_022_2018']);

    $planificacion = CatalogoEstado::factory()->create(['codigo' => 'EN_PLANIFICACION']);
    $pendienteVb = CatalogoEstado::factory()->create(['codigo' => 'PENDIENTE_VISTO_BUENO']);
    $ejecucion = CatalogoEstado::factory()->create(['codigo' => 'EN_EJECUCION']);
    $evaluacion = CatalogoEstado::factory()->create(['codigo' => 'EN_EVALUACION']);

    $actCronograma = CatalogoActuado::create([
        'codigo' => PlanificacionService::CODIGO_ACT_CRONOGRAMA,
        'nombre' => 'Cronograma de Trabajo',
        'fase' => 'PLANIFICACION',
        'rol_id' => $rolTecnico->id,
        'reglamento_id' => null,
        'estado_origen_id' => $planificacion->id,
        'estado_destino_id' => $pendienteVb->id,
        'es_automatico' => false,
        'requiere_adjunto' => true,
    ]);

    $actVb = CatalogoActuado::create([
        'codigo' => PlanificacionService::CODIGO_ACT_VISTO_BUENO,
        'nombre' => 'Visto Bueno a Planificación',
        'fase' => 'PLANIFICACION',
        'rol_id' => $rolEncargada->id,
        'reglamento_id' => null,
        'estado_origen_id' => $pendienteVb->id,
        'estado_destino_id' => $ejecucion->id,
        'es_automatico' => false,
        'requiere_adjunto' => false,
    ]);

    DB::table('catalogo_actuado_roles')->insert([
        ['catalogo_actuado_id' => $actCronograma->id, 'rol_id' => $rolTecnico->id, 'reglamento_id' => $ac022->id],
        ['catalogo_actuado_id' => $actVb->id, 'rol_id' => $rolEncargada->id, 'reglamento_id' => null],
    ]);

    $paramEjecucion = ParametroPlazo::create([
        'reglamento_id' => $ac022->id,
        'tipo_plazo' => 'EJECUCION',
        'subtipo' => 'JURISDICCIONAL',
        'dias_habiles' => 10,
        'base_legal' => 'AC_022_2018',
        'activo' => true,
    ]);

    return compact(
        'encargada', 'tecnico', 'ac022',
        'planificacion', 'pendienteVb', 'ejecucion', 'evaluacion',
        'actCronograma', 'actVb', 'paramEjecucion',
    );
}

function feCrearExpediente(int $estadoId, int $reglamentoId, int $creadoPor): Expediente
{
    return Expediente::create([
        'nurej_code' => 'EXP-'.fake()->unique()->numberBetween(100000, 999999),
        'via' => 'TECNICO',
        'reglamento_id' => $reglamentoId,
        'estado_actual_id' => $estadoId,
        'resumen_hechos' => 'Expediente en trámite para el tablero de la Encargada (test).',
        'fecha_ingreso' => now(),
        'creado_por' => $creadoPor,
    ]);
}

function feAsignar(Expediente $expediente, Usuario $usuario): void
{
    $actuadoOrigen = Actuado::create([
        'expediente_id' => $expediente->id,
        'catalogo_actuado_id' => CatalogoActuado::orderBy('id')->value('id'),
        'usuario_id' => $usuario->id,
        'estado_nuevo_id' => $expediente->estado_actual_id,
        'contenido' => ['descripcion' => 'Asignación inicial de bandeja (test)'],
    ])->refresh();

    Asignacion::create([
        'expediente_id' => $expediente->id,
        'usuario_id' => $usuario->id,
        'rol_id' => $usuario->rol_id,
        'actuado_origen_id' => $actuadoOrigen->id,
        'fecha_asignacion' => now(),
        'activa' => true,
    ]);
}

it('la API del dashboard exige autenticación: 401 sin sesión', function () {
    $this->getJson('/api/encargada/dashboard')->assertUnauthorized();
});

it('expone el contexto completo del tablero: semáforo, vencimientos, feriados, por_estado y carga', function () {
    Storage::fake('local');

    $semilla = feSemilla();
    $expediente = feCrearExpediente($semilla['evaluacion']->id, $semilla['ac022']->id, $semilla['tecnico']->id);
    feAsignar($expediente, $semilla['tecnico']);

    Plazo::create([
        'expediente_id' => $expediente->id,
        'tipo_plazo' => 'EVALUACION',
        'dias_habiles_otorgados' => 5,
        'fecha_inicio' => now()->subDays(7),
        'fecha_limite' => now()->subDay(),
        'estado' => 'VIGENTE',
        'actuado_disparador_id' => $expediente->actuados()->latest('id')->value('id'),
    ]);

    Feriado::create([
        'fecha' => now()->addDays(10)->toDateString(),
        'descripcion' => 'Feriado de prueba para el tablero (test)',
        'ambito' => 'NACIONAL',
    ]);

    Sanctum::actingAs($semilla['encargada'], ['*']);

    $this->getJson('/api/encargada/dashboard')
        ->assertOk()
        ->assertJsonPath('expedientes.total', 1)
        ->assertJsonPath('expedientes.sin_asignar', 0)
        ->assertJsonPath('asignaciones.activas', 1)
        ->assertJsonPath('asignaciones.carga_operadores.0.total', 1)
        ->assertJsonPath('semaforo.total_fuera_de_plazo', 1)
        ->assertJsonPath('vencimientos.fuera_de_plazo.0.nurej_code', $expediente->nurej_code)
        ->assertJsonPath('vencimientos.fuera_de_plazo.0.asignado_a', trim($semilla['tecnico']->nombres.' '.$semilla['tecnico']->apellidos))
        ->assertJsonPath('feriados_proximos.0.fecha', now()->addDays(10)->toDateString())
        ->assertJsonPath('expedientes.ultimos.0.nurej_code', $expediente->nurej_code);

    $porEstado = collect($this->getJson('/api/encargada/dashboard')->json('expedientes.por_estado'));
    expect($porEstado->firstWhere('codigo', 'EN_EVALUACION')['total'] ?? null)->toBe(1);
});

it('el segundo Visto Bueno tras la aprobación y la devolución posterior devuelven 403 (estado inválido)', function () {
    Storage::fake('local');

    $semilla = feSemilla();
    $expediente = feCrearExpediente($semilla['planificacion']->id, $semilla['ac022']->id, $semilla['tecnico']->id);
    feAsignar($expediente, $semilla['tecnico']);

    Sanctum::actingAs($semilla['tecnico'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/planificacion", [
        'descripcion' => 'Cronograma de investigación con las etapas previstas (test).',
        'adjunto' => UploadedFile::fake()->create('cronograma.pdf', 100, 'application/pdf'),
    ])->assertCreated();

    Sanctum::actingAs($semilla['encargada'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/planificacion/visto-bueno", [
        'descripcion' => 'Aprobación del cronograma; arranca el reloj de ejecución (test).',
    ])->assertCreated();

    $this->postJson("/api/expedientes/{$expediente->id}/planificacion/visto-bueno", [
        'descripcion' => 'Segundo visto bueno sobre un expediente ya en ejecución (test).',
    ])->assertForbidden();

    $this->postJson("/api/expedientes/{$expediente->id}/planificacion/devolver", [
        'justificacion' => 'Devolución intentada tras la aprobación definitiva (test).',
    ])->assertForbidden();

    $vbEmitidos = Actuado::where('expediente_id', $expediente->id)
        ->whereHas('tipoActuado', fn ($query) => $query->where('codigo', PlanificacionService::CODIGO_ACT_VISTO_BUENO))
        ->count();

    $expediente->refresh();

    expect($vbEmitidos)->toBe(1)
        ->and($expediente->estado_actual_id)->toBe($semilla['ejecucion']->id);
});
