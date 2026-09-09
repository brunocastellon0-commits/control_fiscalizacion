<?php

use App\Models\Actuado;
use App\Models\Asignacion;
use App\Models\CatalogoActuado;
use App\Models\CatalogoEstado;
use App\Models\Expediente;
use App\Models\ParametroPlazo;
use App\Models\Plazo;
use App\Models\Reglamento;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\PlanificacionService;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

function planificacionSemilla(): array
{
    $rolEncargada = Rol::factory()->create(['codigo' => Rol::CODIGO_ENCARGADA]);
    $rolTecnico = Rol::factory()->create(['codigo' => Rol::CODIGO_TECNICO]);
    $rolAudJuridico = Rol::factory()->create(['codigo' => Rol::CODIGO_AUD_JURIDICO]);

    $encargada = Usuario::factory()->create(['rol_id' => $rolEncargada->id, 'activo' => true]);
    $tecnico = Usuario::factory()->create(['rol_id' => $rolTecnico->id, 'activo' => true]);
    $otroTecnico = Usuario::factory()->create(['rol_id' => $rolTecnico->id, 'activo' => true]);
    $audJuridico = Usuario::factory()->create(['rol_id' => $rolAudJuridico->id, 'activo' => true]);

    $ac022 = Reglamento::factory()->create(['codigo' => 'AC_022_2018']);
    $ac054 = Reglamento::factory()->create(['codigo' => 'AC_054_2018']);

    $planificacion = CatalogoEstado::factory()->create(['codigo' => 'EN_PLANIFICACION']);
    $pendienteVb = CatalogoEstado::factory()->create(['codigo' => 'PENDIENTE_VISTO_BUENO']);
    $ejecucion = CatalogoEstado::factory()->create(['codigo' => 'EN_EJECUCION']);

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

    $actMpa = CatalogoActuado::create([
        'codigo' => PlanificacionService::CODIGO_ACT_MPA,
        'nombre' => 'MPA (Programa de Auditoría)',
        'fase' => 'PLANIFICACION',
        'rol_id' => $rolAudJuridico->id,
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
        ['catalogo_actuado_id' => $actMpa->id, 'rol_id' => $rolAudJuridico->id, 'reglamento_id' => $ac054->id],
        ['catalogo_actuado_id' => $actVb->id, 'rol_id' => $rolEncargada->id, 'reglamento_id' => null],
    ]);

    $paramPlanificacion = ParametroPlazo::create([
        'reglamento_id' => $ac022->id,
        'tipo_plazo' => 'PLANIFICACION',
        'subtipo' => null,
        'dias_habiles' => 2,
        'base_legal' => 'AC_022_2018',
        'activo' => true,
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
        'encargada', 'tecnico', 'otroTecnico', 'audJuridico',
        'ac022', 'ac054',
        'planificacion', 'pendienteVb', 'ejecucion',
        'actCronograma', 'actMpa', 'actVb',
        'paramPlanificacion', 'paramEjecucion',
    );
}

function planificacionCrearExpediente(int $estadoId, int $reglamentoId, string $via, int $creadoPor): Expediente
{
    return Expediente::create([
        'nurej_code' => 'EXP-'.fake()->unique()->numberBetween(100000, 999999),
        'via' => $via,
        'reglamento_id' => $reglamentoId,
        'estado_actual_id' => $estadoId,
        'fecha_ingreso' => now(),
        'creado_por' => $creadoPor,
    ]);
}

function planificacionAsignar(Expediente $expediente, Usuario $operador): void
{
    $actuadoOrigen = Actuado::create([
        'expediente_id' => $expediente->id,
        'catalogo_actuado_id' => CatalogoActuado::first()->id,
        'usuario_id' => $operador->id,
        'estado_nuevo_id' => $expediente->estado_actual_id,
        'contenido' => ['descripcion' => 'Asignación inicial de la semilla'],
    ])->refresh();

    Asignacion::create([
        'expediente_id' => $expediente->id,
        'usuario_id' => $operador->id,
        'rol_id' => $operador->rol_id,
        'actuado_origen_id' => $actuadoOrigen->id,
        'fecha_asignacion' => now(),
        'activa' => true,
    ]);
}

function planificacionPlazoVigente(Expediente $expediente, ParametroPlazo $parametro): Plazo
{
    return Plazo::create([
        'expediente_id' => $expediente->id,
        'tipo_plazo' => $parametro->tipo_plazo,
        'parametro_plazo_id' => $parametro->id,
        'dias_habiles_otorgados' => $parametro->dias_habiles,
        'fecha_inicio' => now(),
        'fecha_limite' => now()->addDays(2),
        'estado' => 'VIGENTE',
        'actuado_disparador_id' => $expediente->actuados()->value('id'),
    ]);
}

it('el Técnico de AC022 carga el Cronograma y pasa a PENDIENTE_VISTO_BUENO, cerrando el plazo de planificación', function () {
    Carbon::setTestNow('2026-09-10 10:00:00');
    Storage::fake('local');

    $semilla = planificacionSemilla();
    $expediente = planificacionCrearExpediente($semilla['planificacion']->id, $semilla['ac022']->id, 'TECNICO', $semilla['tecnico']->id);
    planificacionAsignar($expediente, $semilla['tecnico']);
    planificacionPlazoVigente($expediente, $semilla['paramPlanificacion']);

    Sanctum::actingAs($semilla['tecnico'], ['*']);

    $response = $this->postJson("/api/expedientes/{$expediente->id}/planificacion", [
        'descripcion' => 'Cronograma de investigacion con las etapas previstas (test).',
        'adjunto' => UploadedFile::fake()->create('cronograma.pdf', 100, 'application/pdf'),
    ])->assertCreated()
        ->assertJsonPath('data.tipo_actuado.codigo', PlanificacionService::CODIGO_ACT_CRONOGRAMA)
        ->assertJsonPath('data.estado_nuevo.codigo', PlanificacionService::ESTADO_PENDIENTE_VISTO_BUENO);

    $actuado = Actuado::findOrFail((int) $response->json('data.id'));

    $expediente->refresh();

    expect($expediente->estado_actual_id)->toBe($semilla['pendienteVb']->id);

    $asignacionActiva = $expediente->asignacionActiva()->first();
    expect($asignacionActiva)->not->toBeNull()
        ->and($asignacionActiva->usuario_id)->toBe($semilla['encargada']->id);

    expect($expediente->plazos()->where('tipo_plazo', 'PLANIFICACION')->where('estado', 'VIGENTE')->count())->toBe(0);

    expect($actuado->contenido['tipo'])->toBe('PLANIFICACION')
        ->and($actuado->contenido)->not->toHaveKey('fecha_limite');

    $adjunto = $actuado->adjuntos()->first();
    expect($adjunto)->not->toBeNull()
        ->and($adjunto->mime_type)->toBe('application/pdf');
    Storage::disk('local')->assertExists($adjunto->ruta_almacenamiento);

    Carbon::setTestNow();
});

it('el Auditor Jurídico de AC054 carga el MPA con la fecha límite dinámica en metadatos', function () {
    Storage::fake('local');

    $semilla = planificacionSemilla();
    $expediente = planificacionCrearExpediente($semilla['planificacion']->id, $semilla['ac054']->id, 'JURIDICO', $semilla['audJuridico']->id);
    planificacionAsignar($expediente, $semilla['audJuridico']);

    Sanctum::actingAs($semilla['audJuridico'], ['*']);

    $response = $this->postJson("/api/expedientes/{$expediente->id}/planificacion", [
        'descripcion' => 'MPA con el alcance de la auditoria y su cronograma (test).',
        'fecha_limite_propuesta' => '2026-10-15',
        'adjunto' => UploadedFile::fake()->create('mpa.pdf', 100, 'application/pdf'),
    ])->assertCreated()
        ->assertJsonPath('data.tipo_actuado.codigo', PlanificacionService::CODIGO_ACT_MPA);

    $actuado = Actuado::findOrFail((int) $response->json('data.id'));

    $expediente->refresh();

    expect($expediente->estado_actual_id)->toBe($semilla['pendienteVb']->id);

    $asignacionActiva = $expediente->asignacionActiva()->first();
    expect($asignacionActiva)->not->toBeNull()
        ->and($asignacionActiva->usuario_id)->toBe($semilla['encargada']->id);

    expect($actuado->contenido[PlanificacionService::METADATO_FECHA_LIMITE])->toBe('2026-10-15');
});

it('devuelve 403 si un operador sin bandeja activa intenta cargar la planificación', function () {
    $semilla = planificacionSemilla();
    $expediente = planificacionCrearExpediente($semilla['planificacion']->id, $semilla['ac022']->id, 'TECNICO', $semilla['tecnico']->id);
    planificacionAsignar($expediente, $semilla['tecnico']);

    Sanctum::actingAs($semilla['otroTecnico'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/planificacion", [
        'descripcion' => 'Intento de carga por un operador sin bandeja activa (test).',
        'adjunto' => UploadedFile::fake()->create('plan.pdf', 100, 'application/pdf'),
    ])->assertForbidden();
});

it('devuelve 403 si el rol no corresponde al reglamento (Técnico intenta cargar un MPA de AC054)', function () {
    $semilla = planificacionSemilla();
    $expediente = planificacionCrearExpediente($semilla['planificacion']->id, $semilla['ac054']->id, 'JURIDICO', $semilla['audJuridico']->id);
    planificacionAsignar($expediente, $semilla['tecnico']);

    Sanctum::actingAs($semilla['tecnico'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/planificacion", [
        'descripcion' => 'Intento de carga de MPA por un rol no habilitado (test).',
        'fecha_limite_propuesta' => '2026-10-15',
        'adjunto' => UploadedFile::fake()->create('plan.pdf', 100, 'application/pdf'),
    ])->assertForbidden();
});

it('devuelve 403 si la Encargada intenta cargar la planificación', function () {
    $semilla = planificacionSemilla();
    $expediente = planificacionCrearExpediente($semilla['planificacion']->id, $semilla['ac022']->id, 'TECNICO', $semilla['tecnico']->id);
    planificacionAsignar($expediente, $semilla['tecnico']);

    Sanctum::actingAs($semilla['encargada'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/planificacion", [
        'descripcion' => 'Intento de carga de planificación por la Encargada (test).',
        'adjunto' => UploadedFile::fake()->create('plan.pdf', 100, 'application/pdf'),
    ])->assertForbidden();
});

it('devuelve 422 si el MPA llega sin fecha_limite_propuesta', function () {
    $semilla = planificacionSemilla();
    $expediente = planificacionCrearExpediente($semilla['planificacion']->id, $semilla['ac054']->id, 'JURIDICO', $semilla['audJuridico']->id);
    planificacionAsignar($expediente, $semilla['audJuridico']);

    Sanctum::actingAs($semilla['audJuridico'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/planificacion", [
        'descripcion' => 'MPA sin fecha limite propuesta (test).',
        'adjunto' => UploadedFile::fake()->create('mpa.pdf', 100, 'application/pdf'),
    ])->assertStatus(422)
        ->assertJsonValidationErrors('fecha_limite_propuesta');
});

it('devuelve 422 si la fecha_limite_propuesta es anterior a hoy', function () {
    $semilla = planificacionSemilla();
    $expediente = planificacionCrearExpediente($semilla['planificacion']->id, $semilla['ac054']->id, 'JURIDICO', $semilla['audJuridico']->id);
    planificacionAsignar($expediente, $semilla['audJuridico']);

    Sanctum::actingAs($semilla['audJuridico'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/planificacion", [
        'descripcion' => 'MPA con fecha limite en el pasado (test).',
        'fecha_limite_propuesta' => '2026-01-01',
        'adjunto' => UploadedFile::fake()->create('mpa.pdf', 100, 'application/pdf'),
    ])->assertStatus(422)
        ->assertJsonValidationErrors('fecha_limite_propuesta');
});

it('devuelve 422 si la carga del Cronograma llega sin adjunto', function () {
    $semilla = planificacionSemilla();
    $expediente = planificacionCrearExpediente($semilla['planificacion']->id, $semilla['ac022']->id, 'TECNICO', $semilla['tecnico']->id);
    planificacionAsignar($expediente, $semilla['tecnico']);

    Sanctum::actingAs($semilla['tecnico'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/planificacion", [
        'descripcion' => 'Cronograma sin adjuntar el documento (test).',
    ])->assertStatus(422)
        ->assertJsonValidationErrors('adjunto');
});

it('el Visto Bueno sobre Cronograma pasa a EN_EJECUCION con 10 días hábiles y devuelve la bandeja al Técnico', function () {
    Carbon::setTestNow('2026-09-10 10:00:00');
    Storage::fake('local');

    $semilla = planificacionSemilla();
    $expediente = planificacionCrearExpediente($semilla['planificacion']->id, $semilla['ac022']->id, 'TECNICO', $semilla['tecnico']->id);
    planificacionAsignar($expediente, $semilla['tecnico']);

    Sanctum::actingAs($semilla['tecnico'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/planificacion", [
        'descripcion' => 'Cronograma de investigacion con las etapas previstas (test).',
        'adjunto' => UploadedFile::fake()->create('cronograma.pdf', 100, 'application/pdf'),
    ])->assertCreated();

    Sanctum::actingAs($semilla['encargada'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/planificacion/visto-bueno", [
        'descripcion' => 'Se aprueba el Cronograma presentado; inicia la ejecución.',
    ])->assertCreated()
        ->assertJsonPath('data.tipo_actuado.codigo', PlanificacionService::CODIGO_ACT_VISTO_BUENO)
        ->assertJsonPath('data.estado_nuevo.codigo', 'EN_EJECUCION');

    $expediente->refresh();

    expect($expediente->estado_actual_id)->toBe($semilla['ejecucion']->id);

    $asignacionActiva = $expediente->asignacionActiva()->first();
    expect($asignacionActiva->usuario_id)->toBe($semilla['tecnico']->id);

    $plazoEjecucion = $expediente->plazos()->where('tipo_plazo', 'EJECUCION')->first();
    expect($plazoEjecucion)->not->toBeNull()
        ->and($plazoEjecucion->estado)->toBe('VIGENTE')
        ->and($plazoEjecucion->dias_habiles_otorgados)->toBe(10)
        ->and($plazoEjecucion->parametro_plazo_id)->toBe($semilla['paramEjecucion']->id)
        ->and($plazoEjecucion->fecha_limite->toDateString())->toBe('2026-09-24');

    $actuadoVb = $expediente->actuados()
        ->whereHas('tipoActuado', fn ($query) => $query->where('codigo', PlanificacionService::CODIGO_ACT_VISTO_BUENO))
        ->first();

    expect($actuadoVb)->not->toBeNull()
        ->and($actuadoVb->usuario_id)->toBe($semilla['encargada']->id);

    Carbon::setTestNow();
});

it('el Visto Bueno sobre MPA abre EJECUCION con la fecha límite dinámica exacta', function () {
    Storage::fake('local');

    $semilla = planificacionSemilla();
    $expediente = planificacionCrearExpediente($semilla['planificacion']->id, $semilla['ac054']->id, 'JURIDICO', $semilla['audJuridico']->id);
    planificacionAsignar($expediente, $semilla['audJuridico']);

    Sanctum::actingAs($semilla['audJuridico'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/planificacion", [
        'descripcion' => 'MPA con el alcance de la auditoria y su cronograma (test).',
        'fecha_limite_propuesta' => '2026-10-15',
        'adjunto' => UploadedFile::fake()->create('mpa.pdf', 100, 'application/pdf'),
    ])->assertCreated();

    Sanctum::actingAs($semilla['encargada'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/planificacion/visto-bueno", [
        'descripcion' => 'Se aprueba el MPA; el reloj corre hasta la fecha límite propuesta.',
    ])->assertCreated()
        ->assertJsonPath('data.estado_nuevo.codigo', 'EN_EJECUCION');

    $expediente->refresh();

    expect($expediente->estado_actual_id)->toBe($semilla['ejecucion']->id);

    $asignacionActiva = $expediente->asignacionActiva()->first();
    expect($asignacionActiva->usuario_id)->toBe($semilla['audJuridico']->id);

    $plazoEjecucion = $expediente->plazos()->where('tipo_plazo', 'EJECUCION')->first();
    expect($plazoEjecucion)->not->toBeNull()
        ->and($plazoEjecucion->estado)->toBe('VIGENTE')
        ->and($plazoEjecucion->fecha_limite->toDateString())->toBe('2026-10-15')
        ->and($plazoEjecucion->dias_habiles_otorgados)->toBe(0)
        ->and($plazoEjecucion->parametro_plazo_id)->toBeNull();
});

it('devuelve 403 si un operador intenta emitir el Visto Bueno', function () {
    Storage::fake('local');

    $semilla = planificacionSemilla();
    $expediente = planificacionCrearExpediente($semilla['planificacion']->id, $semilla['ac022']->id, 'TECNICO', $semilla['tecnico']->id);
    planificacionAsignar($expediente, $semilla['tecnico']);

    Sanctum::actingAs($semilla['tecnico'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/planificacion", [
        'descripcion' => 'Cronograma de investigacion con las etapas previstas (test).',
        'adjunto' => UploadedFile::fake()->create('cronograma.pdf', 100, 'application/pdf'),
    ])->assertCreated();

    $this->postJson("/api/expedientes/{$expediente->id}/planificacion/visto-bueno", [
        'descripcion' => 'Intento de visto bueno por un operador (test).',
    ])->assertForbidden();
});

it('devuelve 403 si se intenta cargar planificación fuera del estado EN_PLANIFICACION', function () {
    $semilla = planificacionSemilla();
    $expediente = planificacionCrearExpediente($semilla['ejecucion']->id, $semilla['ac022']->id, 'TECNICO', $semilla['tecnico']->id);
    planificacionAsignar($expediente, $semilla['tecnico']);

    Sanctum::actingAs($semilla['tecnico'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/planificacion", [
        'descripcion' => 'Carga de planificación fuera de estado (test).',
        'adjunto' => UploadedFile::fake()->create('plan.pdf', 100, 'application/pdf'),
    ])->assertForbidden();
});
