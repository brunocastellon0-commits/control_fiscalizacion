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
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

function loteTestCrearRol(string $codigo): Rol
{
    return Rol::factory()->create(['codigo' => $codigo]);
}

function loteTestCrearUsuario(Rol $rol, string $username, bool $activo = true): Usuario
{
    return Usuario::factory()->create([
        'username' => $username,
        'password_hash' => Hash::make('password'),
        'activo' => $activo,
        'rol_id' => $rol->id,
    ]);
}

function loteTestCrearPendiente(string $via, int $reglamentoId, int $tecnicoId): Expediente
{
    return Expediente::create([
        'nurej_code' => 'NUREJ-'.strtoupper(Str::random(10)),
        'via' => $via,
        'reglamento_id' => $reglamentoId,
        'estado_actual_id' => CatalogoEstado::where('codigo', 'PENDIENTE_SORTEO')->value('id'),
        'resumen_hechos' => 'Hechos masivos de prueba para sorteo en lote '.Str::random(6),
        'fecha_ingreso' => now(),
        'creado_por' => $tecnicoId,
    ]);
}

it('sortea todas las causas pendientes de una sola vez y mueve a EN_EVALUACION', function () {
    $reglamento = Reglamento::factory()->create();
    $rolTecnico = loteTestCrearRol(Rol::CODIGO_TECNICO);
    $tecnico = loteTestCrearUsuario($rolTecnico, 'tecnico_lote');
    $rolEncargada = loteTestCrearRol(Rol::CODIGO_ENCARGADA);
    $encargada = loteTestCrearUsuario($rolEncargada, 'encargada_lote');
    $rolAudJur = loteTestCrearRol(Rol::CODIGO_AUD_JURIDICO);
    $audJur = loteTestCrearUsuario($rolAudJur, 'aud_jur_lote');
    $rolAudFin = loteTestCrearRol(Rol::CODIGO_AUD_FINANCIERO);
    $audFin = loteTestCrearUsuario($rolAudFin, 'aud_fin_lote');

    CatalogoEstado::factory()->create(['codigo' => 'PENDIENTE_SORTEO']);
    CatalogoEstado::factory()->create(['codigo' => 'EN_EVALUACION']);

    CatalogoActuado::create([
        'codigo' => 'ACT_SORTEO_INICIAL', 'nombre' => 'Sorteo', 'fase' => 'ADM',
        'rol_id' => $rolEncargada->id,
        'estado_origen_id' => CatalogoEstado::where('codigo', 'PENDIENTE_SORTEO')->value('id'),
        'estado_destino_id' => CatalogoEstado::where('codigo', 'EN_EVALUACION')->value('id'),
        'es_automatico' => false, 'requiere_adjunto' => false,
    ]);

    $tecnicoId = $tecnico->id;
    loteTestCrearPendiente('TECNICO', $reglamento->id, $tecnicoId);
    loteTestCrearPendiente('JURIDICO', $reglamento->id, $tecnicoId);
    loteTestCrearPendiente('FINANCIERO', $reglamento->id, $tecnicoId);

    expect(Expediente::where('estado_actual_id', CatalogoEstado::where('codigo', 'PENDIENTE_SORTEO')->value('id'))->count())->toBe(3);

    Sanctum::actingAs($encargada, ['*']);

    $response = $this->postJson('/api/bandeja/sorteo/todos')->assertOk();

    $json = json_decode($response->getContent(), true);
    expect($json['total'])->toBe(3);

    expect(Expediente::where('estado_actual_id', CatalogoEstado::where('codigo', 'PENDIENTE_SORTEO')->value('id'))->count())->toBe(0)
        ->and(Expediente::where('estado_actual_id', CatalogoEstado::where('codigo', 'EN_EVALUACION')->value('id'))->count())->toBe(3)
        ->and(Asignacion::count())->toBe(3)
        ->and(Actuado::count())->toBe(3);
});

it('rechaza el sorteo en lote a un usuario sin rol ENCARGADA (403)', function () {
    CatalogoEstado::factory()->create(['codigo' => 'PENDIENTE_SORTEO']);
    $reglamento = Reglamento::factory()->create();
    $rolTecnico = loteTestCrearRol(Rol::CODIGO_TECNICO);
    $tecnico = loteTestCrearUsuario($rolTecnico, 'tecnico_lote403');

    loteTestCrearPendiente('TECNICO', $reglamento->id, $tecnico->id);

    Sanctum::actingAs($tecnico, ['*']);
    $this->postJson('/api/bandeja/sorteo/todos')->assertForbidden();
});

it('devuelve 422 cuando no hay causas pendientes de sorteo', function () {
    CatalogoEstado::factory()->create(['codigo' => 'PENDIENTE_SORTEO']);
    $rolEncargada = loteTestCrearRol(Rol::CODIGO_ENCARGADA);
    $encargada = loteTestCrearUsuario($rolEncargada, 'encargada_vacio');

    Sanctum::actingAs($encargada, ['*']);
    $this->postJson('/api/bandeja/sorteo/todos')->assertUnprocessable();
});

it('revierte todo si una vía no tiene candidatos activos (todo-o-nada)', function () {
    $reglamento = Reglamento::factory()->create();
    CatalogoEstado::factory()->create(['codigo' => 'PENDIENTE_SORTEO']);
    CatalogoEstado::factory()->create(['codigo' => 'EN_EVALUACION']);

    $rolTecnico = loteTestCrearRol(Rol::CODIGO_TECNICO);
    $tecnico = loteTestCrearUsuario($rolTecnico, 'tecnico_lote_nocand');

    $rolAudJur = loteTestCrearRol(Rol::CODIGO_AUD_JURIDICO);
    $audJurInactivo = loteTestCrearUsuario($rolAudJur, 'aud_jur_inactivo', activo: false);

    $rolEncargada = loteTestCrearRol(Rol::CODIGO_ENCARGADA);
    $encargada = loteTestCrearUsuario($rolEncargada, 'encargada_lote_nocand');

    CatalogoActuado::create([
        'codigo' => 'ACT_SORTEO_INICIAL', 'nombre' => 'Sorteo', 'fase' => 'ADM',
        'rol_id' => $rolEncargada->id,
        'estado_origen_id' => CatalogoEstado::where('codigo', 'PENDIENTE_SORTEO')->value('id'),
        'estado_destino_id' => CatalogoEstado::where('codigo', 'EN_EVALUACION')->value('id'),
        'es_automatico' => false, 'requiere_adjunto' => false,
    ]);

    loteTestCrearPendiente('TECNICO', $reglamento->id, $tecnico->id);
    loteTestCrearPendiente('JURIDICO', $reglamento->id, $tecnico->id);

    Sanctum::actingAs($encargada, ['*']);
    $this->postJson('/api/bandeja/sorteo/todos')->assertUnprocessable();

    expect(Expediente::where('estado_actual_id', CatalogoEstado::where('codigo', 'PENDIENTE_SORTEO')->value('id'))->count())->toBe(2)
        ->and(Asignacion::count())->toBe(0)
        ->and(Actuado::count())->toBe(0);
});
