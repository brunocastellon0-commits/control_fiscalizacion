<?php

use App\Models\Actuado;
use App\Models\Asignacion;
use App\Models\CatalogoActuado;
use App\Models\CatalogoEstado;
use App\Models\Expediente;
use App\Models\Reglamento;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\CierreExpedienteService;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

function cierreSemilla(): array
{
    $rolEncargada = Rol::factory()->create(['codigo' => Rol::CODIGO_ENCARGADA]);
    $rolTecnico = Rol::factory()->create(['codigo' => Rol::CODIGO_TECNICO]);
    $rolAudJuridico = Rol::factory()->create(['codigo' => Rol::CODIGO_AUD_JURIDICO]);

    $encargada = Usuario::factory()->create(['rol_id' => $rolEncargada->id, 'activo' => true]);
    $tecnico = Usuario::factory()->create(['rol_id' => $rolTecnico->id, 'activo' => true]);
    $audJuridico = Usuario::factory()->create(['rol_id' => $rolAudJuridico->id, 'activo' => true]);

    $ac022 = Reglamento::factory()->create(['codigo' => 'AC_022_2018']);

    $ejecucion = CatalogoEstado::factory()->create(['codigo' => 'EN_EJECUCION']);
    $pendienteVbFinal = CatalogoEstado::factory()->create(['codigo' => 'PENDIENTE_VISTO_BUENO_FINAL']);
    $listoParaReparto = CatalogoEstado::factory()->create(['codigo' => 'LISTO_PARA_REPARTO']);
    $concluidoRemitido = CatalogoEstado::factory()->create(['codigo' => 'CONCLUIDO_REMITIDO']);

    $actVbFinal = CatalogoActuado::create([
        'codigo' => CierreExpedienteService::CODIGO_ACT_VISTO_BUENO_FINAL,
        'nombre' => 'Visto Bueno Final',
        'fase' => 'INVESTIGACION',
        'rol_id' => $rolEncargada->id,
        'reglamento_id' => null,
        'estado_origen_id' => $pendienteVbFinal->id,
        'estado_destino_id' => $listoParaReparto->id,
        'es_automatico' => false,
        'requiere_adjunto' => false,
    ]);

    $actReparto = CatalogoActuado::create([
        'codigo' => CierreExpedienteService::CODIGO_ACT_REPARTO_INSTITUCIONAL,
        'nombre' => 'Reparto Institucional',
        'fase' => 'INVESTIGACION',
        'rol_id' => $rolEncargada->id,
        'reglamento_id' => null,
        'estado_origen_id' => $listoParaReparto->id,
        'estado_destino_id' => $concluidoRemitido->id,
        'es_automatico' => false,
        'requiere_adjunto' => false,
    ]);

    DB::table('catalogo_actuado_roles')->insert([
        ['catalogo_actuado_id' => $actVbFinal->id, 'rol_id' => $rolEncargada->id, 'reglamento_id' => null],
        ['catalogo_actuado_id' => $actReparto->id, 'rol_id' => $rolEncargada->id, 'reglamento_id' => null],
    ]);

    return compact(
        'encargada', 'tecnico', 'audJuridico',
        'ac022',
        'ejecucion', 'pendienteVbFinal', 'listoParaReparto', 'concluidoRemitido',
        'actVbFinal', 'actReparto',
    );
}

function cierreCrearExpediente(int $estadoId, int $reglamentoId, int $creadoPor): Expediente
{
    return Expediente::create([
        'nurej_code' => 'EXP-'.fake()->unique()->numberBetween(100000, 999999),
        'via' => 'TECNICO',
        'reglamento_id' => $reglamentoId,
        'estado_actual_id' => $estadoId,
        'resumen_hechos' => 'Hechos de la causa en etapa de cierre.',
        'fecha_ingreso' => now(),
        'creado_por' => $creadoPor,
    ]);
}

function cierreAsignar(Expediente $expediente, Usuario $usuario): void
{
    $actuadoOrigen = Actuado::create([
        'expediente_id' => $expediente->id,
        'catalogo_actuado_id' => CatalogoActuado::first()->id,
        'usuario_id' => $usuario->id,
        'estado_nuevo_id' => $expediente->estado_actual_id,
        'contenido' => ['descripcion' => 'Asignación inicial de la semilla'],
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

it('la Encargada aprueba el Visto Bueno Final: LISTO_PARA_REPARTO y bandeja propia', function () {
    $semilla = cierreSemilla();
    $expediente = cierreCrearExpediente($semilla['pendienteVbFinal']->id, $semilla['ac022']->id, $semilla['tecnico']->id);
    cierreAsignar($expediente, $semilla['tecnico']);

    Sanctum::actingAs($semilla['encargada'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/cierre/visto-bueno", [
        'descripcion' => 'Informe final revisado; se otorga el visto bueno jerarquico del cierre (test).',
    ])->assertCreated()
        ->assertJsonPath('data.tipo_actuado.codigo', CierreExpedienteService::CODIGO_ACT_VISTO_BUENO_FINAL)
        ->assertJsonPath('data.estado_anterior.codigo', 'PENDIENTE_VISTO_BUENO_FINAL')
        ->assertJsonPath('data.estado_nuevo.codigo', 'LISTO_PARA_REPARTO');

    $expediente->refresh();

    expect($expediente->estado_actual_id)->toBe($semilla['listoParaReparto']->id);

    $asignacionActiva = $expediente->asignacionActiva()->first();
    expect($asignacionActiva)->not->toBeNull()
        ->and($asignacionActiva->usuario_id)->toBe($semilla['encargada']->id);

    $actuadoVb = $expediente->actuados()
        ->whereHas('tipoActuado', fn ($query) => $query->where('codigo', CierreExpedienteService::CODIGO_ACT_VISTO_BUENO_FINAL))
        ->latest('id')
        ->first();

    expect($actuadoVb)->not->toBeNull()
        ->and($actuadoVb->usuario_id)->toBe($semilla['encargada']->id)
        ->and($actuadoVb->estado_anterior_id)->toBe($semilla['pendienteVbFinal']->id)
        ->and($actuadoVb->estado_nuevo_id)->toBe($semilla['listoParaReparto']->id)
        ->and($actuadoVb->contenido['tipo'])->toBe('VISTO_BUENO_FINAL')
        ->and($actuadoVb->contenido['usuario_destino_id'])->toBe($semilla['encargada']->id);
});

it('la Encargada ejecuta el Reparto Institucional: CONCLUIDO_REMITIDO y bandeja vacía', function () {
    $semilla = cierreSemilla();
    $expediente = cierreCrearExpediente($semilla['listoParaReparto']->id, $semilla['ac022']->id, $semilla['tecnico']->id);
    cierreAsignar($expediente, $semilla['encargada']);

    Sanctum::actingAs($semilla['encargada'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/cierre/reparto", [
        'destino' => 'Juzgado Disciplinario',
        'justificacion' => 'Remision de la causa por competencia disciplinaria demostrada en el informe (test).',
    ])->assertCreated()
        ->assertJsonPath('data.tipo_actuado.codigo', CierreExpedienteService::CODIGO_ACT_REPARTO_INSTITUCIONAL)
        ->assertJsonPath('data.estado_anterior.codigo', 'LISTO_PARA_REPARTO')
        ->assertJsonPath('data.estado_nuevo.codigo', 'CONCLUIDO_REMITIDO');

    $expediente->refresh();

    expect($expediente->estado_actual_id)->toBe($semilla['concluidoRemitido']->id);

    $asignacionActiva = $expediente->asignacionActiva()->first();
    expect($asignacionActiva)->toBeNull()
        ->and($expediente->asignaciones()->where('activa', true)->count())->toBe(0);

    $actuadoReparto = $expediente->actuados()
        ->whereHas('tipoActuado', fn ($query) => $query->where('codigo', CierreExpedienteService::CODIGO_ACT_REPARTO_INSTITUCIONAL))
        ->latest('id')
        ->first();

    expect($actuadoReparto)->not->toBeNull()
        ->and($actuadoReparto->usuario_id)->toBe($semilla['encargada']->id)
        ->and($actuadoReparto->estado_anterior_id)->toBe($semilla['listoParaReparto']->id)
        ->and($actuadoReparto->estado_nuevo_id)->toBe($semilla['concluidoRemitido']->id)
        ->and($actuadoReparto->contenido['tipo'])->toBe('REPARTO')
        ->and($actuadoReparto->contenido['destino_reparto'])->toBe('Juzgado Disciplinario')
        ->and($actuadoReparto->contenido['descripcion'])->toBe('Remision de la causa por competencia disciplinaria demostrada en el informe (test).');
});

it('cierra el ciclo completo: VB Final + Reparto dejan el NUREJ en CONCLUIDO_REMITIDO sin bandejas', function () {
    $semilla = cierreSemilla();
    $expediente = cierreCrearExpediente($semilla['pendienteVbFinal']->id, $semilla['ac022']->id, $semilla['tecnico']->id);
    cierreAsignar($expediente, $semilla['tecnico']);

    Sanctum::actingAs($semilla['encargada'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/cierre/visto-bueno", [
        'descripcion' => 'Aprobacion jerarquica del informe final para la salida institucional (test).',
    ])->assertCreated();

    $expediente->refresh();
    expect($expediente->estado_actual_id)->toBe($semilla['listoParaReparto']->id);

    $this->postJson("/api/expedientes/{$expediente->id}/cierre/reparto", [
        'destino' => 'Sumariante',
        'justificacion' => 'La causa pasa al sumariante por hallazgos de responsabilidad (test).',
    ])->assertCreated();

    $expediente->refresh();

    expect($expediente->estado_actual_id)->toBe($semilla['concluidoRemitido']->id);

    $asignacionActiva = $expediente->asignacionActiva()->first();
    expect($asignacionActiva)->toBeNull()
        ->and($expediente->asignaciones()->where('activa', true)->count())->toBe(0);

    $actuadosCierre = $expediente->actuados()
        ->where('usuario_id', $semilla['encargada']->id)
        ->count();

    expect($actuadosCierre)->toBe(2);
});

it('devuelve 422 si el destino del reparto no está contemplado en la norma', function () {
    $semilla = cierreSemilla();
    $expediente = cierreCrearExpediente($semilla['listoParaReparto']->id, $semilla['ac022']->id, $semilla['tecnico']->id);
    cierreAsignar($expediente, $semilla['encargada']);

    Sanctum::actingAs($semilla['encargada'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/cierre/reparto", [
        'destino' => 'Procuraduría General',
        'justificacion' => 'Destino no contemplado en la norma vigente (test).',
    ])->assertStatus(422)
        ->assertJsonValidationErrors('destino');
});

it('devuelve 422 si la nota de remisión es demasiado corta', function () {
    $semilla = cierreSemilla();
    $expediente = cierreCrearExpediente($semilla['listoParaReparto']->id, $semilla['ac022']->id, $semilla['tecnico']->id);
    cierreAsignar($expediente, $semilla['encargada']);

    Sanctum::actingAs($semilla['encargada'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/cierre/reparto", [
        'destino' => 'Sumariante',
        'justificacion' => 'Corta.',
    ])->assertStatus(422)
        ->assertJsonValidationErrors('justificacion');
});

it('devuelve 403 si un Técnico intenta emitir el Visto Bueno Final', function () {
    $semilla = cierreSemilla();
    $expediente = cierreCrearExpediente($semilla['pendienteVbFinal']->id, $semilla['ac022']->id, $semilla['tecnico']->id);
    cierreAsignar($expediente, $semilla['tecnico']);

    Sanctum::actingAs($semilla['tecnico'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/cierre/visto-bueno", [
        'descripcion' => 'Intento de aprobación por un operador sin atribución jerárquica (test).',
    ])->assertForbidden();
});

it('devuelve 403 si un Técnico intenta ejecutar el Reparto Institucional', function () {
    $semilla = cierreSemilla();
    $expediente = cierreCrearExpediente($semilla['listoParaReparto']->id, $semilla['ac022']->id, $semilla['tecnico']->id);
    cierreAsignar($expediente, $semilla['encargada']);

    Sanctum::actingAs($semilla['tecnico'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/cierre/reparto", [
        'destino' => 'Sumariante',
        'justificacion' => 'Intento de reparto por un operador sin atribución jerárquica (test).',
    ])->assertForbidden();
});

it('devuelve 403 si un Auditor Jurídico intenta emitir Visto Bueno Final o Reparto', function () {
    $semilla = cierreSemilla();
    $expediente = cierreCrearExpediente($semilla['pendienteVbFinal']->id, $semilla['ac022']->id, $semilla['tecnico']->id);
    cierreAsignar($expediente, $semilla['audJuridico']);

    Sanctum::actingAs($semilla['audJuridico'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/cierre/visto-bueno", [
        'descripcion' => 'Intento de aprobación por un auditor sin atribución jerárquica (test).',
    ])->assertForbidden();

    $expediente->update(['estado_actual_id' => $semilla['listoParaReparto']->id]);

    $this->postJson("/api/expedientes/{$expediente->id}/cierre/reparto", [
        'destino' => 'Juzgado Disciplinario',
        'justificacion' => 'Intento de reparto por un auditor sin atribución jerárquica (test).',
    ])->assertForbidden();
});

it('devuelve 403 si la Encargada emite Visto Bueno Final fuera de PENDIENTE_VISTO_BUENO_FINAL', function () {
    $semilla = cierreSemilla();
    $expediente = cierreCrearExpediente($semilla['ejecucion']->id, $semilla['ac022']->id, $semilla['tecnico']->id);
    cierreAsignar($expediente, $semilla['tecnico']);

    Sanctum::actingAs($semilla['encargada'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/cierre/visto-bueno", [
        'descripcion' => 'Aprobación sobre un expediente fuera del estado de cierre (test).',
    ])->assertForbidden();
});

it('devuelve 403 si la Encargada ejecuta Reparto fuera de LISTO_PARA_REPARTO', function () {
    $semilla = cierreSemilla();
    $expediente = cierreCrearExpediente($semilla['pendienteVbFinal']->id, $semilla['ac022']->id, $semilla['tecnico']->id);
    cierreAsignar($expediente, $semilla['tecnico']);

    Sanctum::actingAs($semilla['encargada'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/cierre/reparto", [
        'destino' => 'Asesoría Jurídica',
        'justificacion' => 'Reparto sobre un expediente que aún no tiene visto bueno final (test).',
    ])->assertForbidden();
});
