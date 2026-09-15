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
use App\Services\AmpliacionService;
use App\Services\PlazoCalculatorService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

function ampliacionSemilla(): array
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

    $ejecucion = CatalogoEstado::factory()->create(['codigo' => 'EN_EJECUCION']);
    $pendienteAmpliacion = CatalogoEstado::factory()->create(['codigo' => 'PENDIENTE_APROBACION_AMPLIACION']);

    $actSolicitar = CatalogoActuado::create([
        'codigo' => AmpliacionService::CODIGO_ACT_SOLICITAR_AMPLIACION,
        'nombre' => 'Solicitud de Ampliación de Plazo',
        'fase' => 'INVESTIGACION',
        'rol_id' => $rolTecnico->id,
        'reglamento_id' => null,
        'estado_origen_id' => $ejecucion->id,
        'estado_destino_id' => $pendienteAmpliacion->id,
        'es_automatico' => false,
        'requiere_adjunto' => false,
    ]);

    $actAprobar = CatalogoActuado::create([
        'codigo' => AmpliacionService::CODIGO_ACT_APROBAR_AMPLIACION,
        'nombre' => 'Aprobación de Ampliación de Plazo',
        'fase' => 'INVESTIGACION',
        'rol_id' => $rolEncargada->id,
        'reglamento_id' => null,
        'estado_origen_id' => $pendienteAmpliacion->id,
        'estado_destino_id' => $ejecucion->id,
        'es_automatico' => false,
        'requiere_adjunto' => false,
    ]);

    DB::table('catalogo_actuado_roles')->insert([
        ['catalogo_actuado_id' => $actSolicitar->id, 'rol_id' => $rolTecnico->id, 'reglamento_id' => $ac022->id],
        ['catalogo_actuado_id' => $actAprobar->id, 'rol_id' => $rolEncargada->id, 'reglamento_id' => null],
    ]);

    $paramEjecucion = ParametroPlazo::create([
        'reglamento_id' => $ac022->id,
        'tipo_plazo' => 'EJECUCION',
        'subtipo' => 'JURISDICCIONAL',
        'dias_habiles' => 10,
        'base_legal' => 'AC_022_2018',
        'activo' => true,
    ]);

    $paramAmpliacion = ParametroPlazo::create([
        'reglamento_id' => $ac022->id,
        'tipo_plazo' => AmpliacionService::TIPO_PLAZO_EJECUCION_AMPLIADA,
        'subtipo' => null,
        'dias_habiles' => 5,
        'base_legal' => 'AC_022_2018',
        'activo' => true,
    ]);

    return compact(
        'encargada', 'tecnico', 'otroTecnico', 'audJuridico',
        'ac022', 'ac054',
        'ejecucion', 'pendienteAmpliacion',
        'actSolicitar', 'actAprobar',
        'paramEjecucion', 'paramAmpliacion',
    );
}

function ampliacionCrearExpediente(int $estadoId, int $reglamentoId, string $via, int $creadoPor): Expediente
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

function ampliacionAsignar(Expediente $expediente, Usuario $operador): void
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

function ampliacionPlazoEjecucion(Expediente $expediente, ParametroPlazo $parametro): Plazo
{
    $fechaLimite = app(PlazoCalculatorService::class)->calculateDueDate(now(), $parametro->dias_habiles);

    return Plazo::create([
        'expediente_id' => $expediente->id,
        'tipo_plazo' => $parametro->tipo_plazo,
        'parametro_plazo_id' => $parametro->id,
        'dias_habiles_otorgados' => $parametro->dias_habiles,
        'fecha_inicio' => now(),
        'fecha_limite' => $fechaLimite,
        'estado' => 'VIGENTE',
        'actuado_disparador_id' => $expediente->actuados()->value('id'),
    ]);
}

it('el Técnico de AC022 solicita la ampliación: PENDIENTE_APROBACION_AMPLIACION, bandeja a la Encargada y reloj vigente', function () {
    Carbon::setTestNow('2026-09-10 10:00:00');

    $semilla = ampliacionSemilla();
    $expediente = ampliacionCrearExpediente($semilla['ejecucion']->id, $semilla['ac022']->id, 'TECNICO', $semilla['tecnico']->id);
    ampliacionAsignar($expediente, $semilla['tecnico']);
    $plazoEjecucion = ampliacionPlazoEjecucion($expediente, $semilla['paramEjecucion']);

    Sanctum::actingAs($semilla['tecnico'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/ampliacion", [
        'justificacion' => 'La investigacion requiere inspeccion en sitio y mas pruebas de campo (test).',
    ])->assertCreated()
        ->assertJsonPath('data.tipo_actuado.codigo', AmpliacionService::CODIGO_ACT_SOLICITAR_AMPLIACION)
        ->assertJsonPath('data.estado_nuevo.codigo', AmpliacionService::ESTADO_PENDIENTE_APROBACION_AMPLIACION);

    $expediente->refresh();

    expect($expediente->estado_actual_id)->toBe($semilla['pendienteAmpliacion']->id);

    $asignacionActiva = $expediente->asignacionActiva()->first();
    expect($asignacionActiva)->not->toBeNull()
        ->and($asignacionActiva->usuario_id)->toBe($semilla['encargada']->id);

    $plazoEjecucion->refresh();
    expect($plazoEjecucion->estado)->toBe('VIGENTE')
        ->and($expediente->plazos()->where('tipo_plazo', 'EJECUCION')->where('estado', 'VIGENTE')->count())->toBe(1);

    $actuadoSolicitud = $expediente->actuados()
        ->whereHas('tipoActuado', fn ($query) => $query->where('codigo', AmpliacionService::CODIGO_ACT_SOLICITAR_AMPLIACION))
        ->latest('id')
        ->first();

    expect($actuadoSolicitud)->not->toBeNull()
        ->and($actuadoSolicitud->usuario_id)->toBe($semilla['tecnico']->id)
        ->and($actuadoSolicitud->contenido['descripcion'])->toBe('La investigacion requiere inspeccion en sitio y mas pruebas de campo (test).')
        ->and($actuadoSolicitud->contenido['tipo'])->toBe('AMPLIACION')
        ->and($actuadoSolicitud->contenido['usuario_destino_id'])->toBe($semilla['encargada']->id);

    Carbon::setTestNow();
});

it('la Encargada aprueba la ampliación: EJECUCION cerrado y EJECUCION_AMPLIADA de 5 días desde el vencimiento original', function () {
    Carbon::setTestNow('2026-09-10 10:00:00');

    $semilla = ampliacionSemilla();
    $expediente = ampliacionCrearExpediente($semilla['ejecucion']->id, $semilla['ac022']->id, 'TECNICO', $semilla['tecnico']->id);
    ampliacionAsignar($expediente, $semilla['tecnico']);
    $plazoEjecucion = ampliacionPlazoEjecucion($expediente, $semilla['paramEjecucion']);

    Sanctum::actingAs($semilla['tecnico'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/ampliacion", [
        'justificacion' => 'La investigacion requiere inspeccion en sitio y mas pruebas de campo (test).',
    ])->assertCreated();

    Sanctum::actingAs($semilla['encargada'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/ampliacion/aprobar", [])->assertCreated()
        ->assertJsonPath('data.tipo_actuado.codigo', AmpliacionService::CODIGO_ACT_APROBAR_AMPLIACION)
        ->assertJsonPath('data.estado_nuevo.codigo', AmpliacionService::ESTADO_EJECUCION);

    $expediente->refresh();

    expect($expediente->estado_actual_id)->toBe($semilla['ejecucion']->id);

    $asignacionActiva = $expediente->asignacionActiva()->first();
    expect($asignacionActiva)->not->toBeNull()
        ->and($asignacionActiva->usuario_id)->toBe($semilla['tecnico']->id);

    $plazoEjecucion->refresh();
    expect($plazoEjecucion->estado)->toBe('CERRADO')
        ->and($plazoEjecucion->dias_habiles_otorgados)->toBe(10);

    $plazoAmpliado = $expediente->plazos()->where('tipo_plazo', AmpliacionService::TIPO_PLAZO_EJECUCION_AMPLIADA)->first();
    expect($plazoAmpliado)->not->toBeNull()
        ->and($plazoAmpliado->estado)->toBe('VIGENTE')
        ->and($plazoAmpliado->dias_habiles_otorgados)->toBe(5)
        ->and($plazoAmpliado->parametro_plazo_id)->toBe($semilla['paramAmpliacion']->id)
        ->and($plazoAmpliado->fecha_inicio->toDateString())->toBe('2026-09-10');

    $fechaLimiteEsperada = app(PlazoCalculatorService::class)->calculateDueDate($plazoEjecucion->fecha_limite, 5);
    expect($plazoAmpliado->fecha_limite->toDateString())->toBe($fechaLimiteEsperada->toDateString())
        ->and($plazoAmpliado->fecha_limite->toDateString())->toBe('2026-10-01');

    $actuadoAprobacion = $expediente->actuados()
        ->whereHas('tipoActuado', fn ($query) => $query->where('codigo', AmpliacionService::CODIGO_ACT_APROBAR_AMPLIACION))
        ->latest('id')
        ->first();

    expect($actuadoAprobacion)->not->toBeNull()
        ->and($actuadoAprobacion->usuario_id)->toBe($semilla['encargada']->id)
        ->and($actuadoAprobacion->estado_anterior_id)->toBe($semilla['pendienteAmpliacion']->id)
        ->and($actuadoAprobacion->estado_nuevo_id)->toBe($semilla['ejecucion']->id)
        ->and($actuadoAprobacion->contenido['tipo'])->toBe('AMPLIACION')
        ->and($actuadoAprobacion->contenido['dias_habiles_otorgados'])->toBe(5)
        ->and($actuadoAprobacion->contenido['fecha_limite_anterior'])->toBe('2026-09-24')
        ->and($actuadoAprobacion->contenido['fecha_limite_ampliada'])->toBe('2026-10-01')
        ->and($actuadoAprobacion->contenido['usuario_destino_id'])->toBe($semilla['tecnico']->id);

    expect($plazoAmpliado->actuado_disparador_id)->toBe($actuadoAprobacion->id);

    Carbon::setTestNow();
});

it('devuelve 403 si un Técnico sin bandeja activa solicita la ampliación', function () {
    $semilla = ampliacionSemilla();
    $expediente = ampliacionCrearExpediente($semilla['ejecucion']->id, $semilla['ac022']->id, 'TECNICO', $semilla['tecnico']->id);
    ampliacionAsignar($expediente, $semilla['tecnico']);
    ampliacionPlazoEjecucion($expediente, $semilla['paramEjecucion']);

    Sanctum::actingAs($semilla['otroTecnico'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/ampliacion", [
        'justificacion' => 'Intento de ampliación por un operador sin bandeja activa (test).',
    ])->assertForbidden();
});

it('devuelve 403 si un Auditor Jurídico intenta solicitar ampliación (solo AC022)', function () {
    $semilla = ampliacionSemilla();
    $expediente = ampliacionCrearExpediente($semilla['ejecucion']->id, $semilla['ac054']->id, 'JURIDICO', $semilla['audJuridico']->id);
    ampliacionAsignar($expediente, $semilla['audJuridico']);

    Sanctum::actingAs($semilla['audJuridico'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/ampliacion", [
        'justificacion' => 'Intento de ampliación en un reglamento no habilitado (test).',
    ])->assertForbidden();
});

it('devuelve 403 si la Encargada intenta solicitar la ampliación', function () {
    $semilla = ampliacionSemilla();
    $expediente = ampliacionCrearExpediente($semilla['ejecucion']->id, $semilla['ac022']->id, 'TECNICO', $semilla['tecnico']->id);
    ampliacionAsignar($expediente, $semilla['tecnico']);

    Sanctum::actingAs($semilla['encargada'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/ampliacion", [
        'justificacion' => 'Intento de solicitud por parte de la Encargada (test).',
    ])->assertForbidden();
});

it('devuelve 403 si un operador intenta aprobar la ampliación', function () {
    $semilla = ampliacionSemilla();
    $expediente = ampliacionCrearExpediente($semilla['ejecucion']->id, $semilla['ac022']->id, 'TECNICO', $semilla['tecnico']->id);
    ampliacionAsignar($expediente, $semilla['tecnico']);
    ampliacionPlazoEjecucion($expediente, $semilla['paramEjecucion']);

    Sanctum::actingAs($semilla['tecnico'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/ampliacion", [
        'justificacion' => 'El Técnico solicita la ampliación (test).',
    ])->assertCreated();

    $this->postJson("/api/expedientes/{$expediente->id}/ampliacion/aprobar", [])->assertForbidden();
});

it('devuelve 403 si la Encargada aprueba fuera de PENDIENTE_APROBACION_AMPLIACION', function () {
    $semilla = ampliacionSemilla();
    $expediente = ampliacionCrearExpediente($semilla['ejecucion']->id, $semilla['ac022']->id, 'TECNICO', $semilla['tecnico']->id);
    ampliacionAsignar($expediente, $semilla['tecnico']);
    ampliacionPlazoEjecucion($expediente, $semilla['paramEjecucion']);

    Sanctum::actingAs($semilla['encargada'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/ampliacion/aprobar", [])->assertForbidden();
});

it('devuelve 422 si la justificación de la ampliación es demasiado corta', function () {
    $semilla = ampliacionSemilla();
    $expediente = ampliacionCrearExpediente($semilla['ejecucion']->id, $semilla['ac022']->id, 'TECNICO', $semilla['tecnico']->id);
    ampliacionAsignar($expediente, $semilla['tecnico']);

    Sanctum::actingAs($semilla['tecnico'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/ampliacion", [
        'justificacion' => 'Corta.',
    ])->assertStatus(422)
        ->assertJsonValidationErrors('justificacion');
});

it('devuelve 422 si no hay plazo EJECUCION vigente al aprobar la ampliación', function () {
    $semilla = ampliacionSemilla();
    $expediente = ampliacionCrearExpediente($semilla['pendienteAmpliacion']->id, $semilla['ac022']->id, 'TECNICO', $semilla['tecnico']->id);
    ampliacionAsignar($expediente, $semilla['encargada']);

    Sanctum::actingAs($semilla['encargada'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/ampliacion/aprobar", [])
        ->assertStatus(422)
        ->assertJsonValidationErrors('expediente');
});

it('devuelve 422 si el expediente ya tiene una ampliación otorgada (única por causa)', function () {
    Carbon::setTestNow('2026-09-10 10:00:00');

    $semilla = ampliacionSemilla();
    $expediente = ampliacionCrearExpediente($semilla['ejecucion']->id, $semilla['ac022']->id, 'TECNICO', $semilla['tecnico']->id);
    ampliacionAsignar($expediente, $semilla['tecnico']);
    ampliacionPlazoEjecucion($expediente, $semilla['paramEjecucion']);

    Plazo::create([
        'expediente_id' => $expediente->id,
        'tipo_plazo' => AmpliacionService::TIPO_PLAZO_EJECUCION_AMPLIADA,
        'parametro_plazo_id' => $semilla['paramAmpliacion']->id,
        'dias_habiles_otorgados' => 5,
        'fecha_inicio' => now(),
        'fecha_limite' => now()->addDays(20),
        'estado' => 'VIGENTE',
        'actuado_disparador_id' => $expediente->actuados()->value('id'),
    ]);

    Sanctum::actingAs($semilla['tecnico'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/ampliacion", [
        'justificacion' => 'Intento de una segunda ampliación sobre la misma causa (test).',
    ])->assertStatus(422)
        ->assertJsonValidationErrors('expediente');

    Carbon::setTestNow();
});
