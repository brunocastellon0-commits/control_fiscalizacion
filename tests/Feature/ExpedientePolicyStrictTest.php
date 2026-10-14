<?php

use App\Models\Actuado;
use App\Models\Asignacion;
use App\Models\CatalogoActuado;
use App\Models\CatalogoEstado;
use App\Models\Expediente;
use App\Models\Reglamento;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\EnmiendaService;
use App\Services\TransferenciaUnidadService;
use Laravel\Sanctum\Sanctum;

function b23SemillaStrict(): array
{
    $rolOperador = Rol::factory()->create(['codigo' => Rol::CODIGO_AUD_JURIDICO]);
    $operador = Usuario::factory()->create(['rol_id' => $rolOperador->id]);

    $rolEncargada = Rol::factory()->create(['codigo' => Rol::CODIGO_ENCARGADA]);
    $encargada = Usuario::factory()->create(['rol_id' => $rolEncargada->id]);

    $rolAdmin = Rol::factory()->create(['codigo' => Rol::CODIGO_ADMIN]);
    $admin = Usuario::factory()->create(['rol_id' => $rolAdmin->id]);

    $evaluacion = CatalogoEstado::factory()->create(['codigo' => 'EN_EVALUACION']);
    $observado = CatalogoEstado::factory()->create(['codigo' => 'OBSERVADO']);
    $remitido = CatalogoEstado::factory()->create([
        'codigo' => TransferenciaUnidadService::ESTADO_REMITIDO_UNIDAD_EXTERNA,
    ]);

    $catalogoObservacion = CatalogoActuado::create([
        'codigo' => 'ACT_OBS_B23',
        'nombre' => 'Observacion B2.3',
        'fase' => 'ADMISIBILIDAD',
        'rol_id' => $rolOperador->id,
        'estado_origen_id' => $evaluacion->id,
        'estado_destino_id' => $observado->id,
        'es_automatico' => false,
        'requiere_adjunto' => false,
    ]);

    $catalogoObservacionEncargada = CatalogoActuado::create([
        'codigo' => 'ACT_OBS_B23_ENC',
        'nombre' => 'Observacion Encargada B2.3',
        'fase' => 'ADMISIBILIDAD',
        'rol_id' => $rolEncargada->id,
        'estado_origen_id' => $evaluacion->id,
        'estado_destino_id' => $observado->id,
        'es_automatico' => false,
        'requiere_adjunto' => false,
    ]);

    $catalogoEnmienda = CatalogoActuado::create([
        'codigo' => EnmiendaService::CODIGO_ACTUADO,
        'nombre' => 'Enmienda B2.3',
        'fase' => 'ADMISIBILIDAD',
        'rol_id' => $rolOperador->id,
        'estado_origen_id' => null,
        'estado_destino_id' => null,
        'es_automatico' => false,
        'requiere_adjunto' => false,
    ]);

    return compact(
        'rolOperador', 'operador',
        'rolEncargada', 'encargada',
        'rolAdmin', 'admin',
        'evaluacion', 'observado', 'remitido',
        'catalogoObservacion', 'catalogoObservacionEncargada', 'catalogoEnmienda',
    );
}

function b23CrearExpedienteStrict(int $estadoId, int $creadorId): Expediente
{
    return Expediente::create([
        'nurej_code' => 'EXP-'.fake()->unique()->numberBetween(10000, 99999),
        'via' => 'TECNICO',
        'reglamento_id' => Reglamento::factory()->create()->id,
        'estado_actual_id' => $estadoId,
        'fecha_ingreso' => now(),
        'creado_por' => $creadorId,
    ]);
}

function b23AsignarStrict(Expediente $expediente, Usuario $usuario, int $estadoId, int $catalogoId): void
{
    $actuado = Actuado::create([
        'expediente_id' => $expediente->id,
        'catalogo_actuado_id' => $catalogoId,
        'usuario_id' => $usuario->id,
        'estado_nuevo_id' => $estadoId,
        'contenido' => ['tipo' => 'ASIGNACION'],
    ])->refresh();

    Asignacion::create([
        'expediente_id' => $expediente->id,
        'usuario_id' => $usuario->id,
        'rol_id' => $usuario->rol_id,
        'actuado_origen_id' => $actuado->id,
        'fecha_asignacion' => now(),
        'activa' => true,
    ]);
}

it('bloquea emitir actuados en causa remitida a unidad externa aunque el operador la tenga asignada', function () {
    [
        'operador' => $operador,
        'remitido' => $remitido,
        'catalogoObservacion' => $catalogoObservacion,
    ] = b23SemillaStrict();

    $expediente = b23CrearExpedienteStrict($remitido->id, $operador->id);
    b23AsignarStrict($expediente, $operador, $remitido->id, $catalogoObservacion->id);

    Sanctum::actingAs($operador, ['*']);

    $this->postJson('/api/expedientes/'.$expediente->id.'/actuados', [
        'catalogo_actuado_id' => $catalogoObservacion->id,
        'descripcion' => 'Intento de emision sobre causa remitida a unidad externa.',
    ])->assertForbidden();
});

it('bloquea la emision de actuados de la Encargada sobre una causa remitida', function () {
    [
        'encargada' => $encargada,
        'remitido' => $remitido,
        'catalogoObservacionEncargada' => $catalogoObservacion,
    ] = b23SemillaStrict();

    $expediente = b23CrearExpedienteStrict($remitido->id, $encargada->id);

    Sanctum::actingAs($encargada, ['*']);

    $this->postJson('/api/expedientes/'.$expediente->id.'/actuados', [
        'catalogo_actuado_id' => $catalogoObservacion->id,
        'descripcion' => 'Intento de emision de la Encargada sobre causa remitida.',
    ])->assertForbidden();
});

it('control positivo: el mismo operador emite el actuado cuando la causa no esta remitida', function () {
    [
        'operador' => $operador,
        'evaluacion' => $evaluacion,
        'catalogoObservacion' => $catalogoObservacion,
    ] = b23SemillaStrict();

    $expediente = b23CrearExpedienteStrict($evaluacion->id, $operador->id);
    b23AsignarStrict($expediente, $operador, $evaluacion->id, $catalogoObservacion->id);

    Sanctum::actingAs($operador, ['*']);

    $this->postJson('/api/expedientes/'.$expediente->id.'/actuados', [
        'catalogo_actuado_id' => $catalogoObservacion->id,
        'descripcion' => 'Observacion valida sobre causa en evaluacion.',
    ])->assertStatus(201);
});

it('bloquea la enmienda en causa remitida aunque el operador tenga asignacion activa', function () {
    [
        'operador' => $operador,
        'remitido' => $remitido,
        'catalogoObservacion' => $catalogoObservacion,
    ] = b23SemillaStrict();

    $expediente = b23CrearExpedienteStrict($remitido->id, $operador->id);
    b23AsignarStrict($expediente, $operador, $remitido->id, $catalogoObservacion->id);

    Sanctum::actingAs($operador, ['*']);

    $this->postJson('/api/expedientes/'.$expediente->id.'/enmienda', [
        'campo_modificado' => 'resumen_hechos',
        'valor_nuevo' => 'Correccion del resumen de hechos.',
        'motivo' => 'Error material detectado.',
    ])->assertForbidden();
});

it('mantiene la consulta de solo lectura de una causa remitida para el operador asignado', function () {
    [
        'operador' => $operador,
        'remitido' => $remitido,
        'catalogoObservacion' => $catalogoObservacion,
    ] = b23SemillaStrict();

    $expediente = b23CrearExpedienteStrict($remitido->id, $operador->id);
    b23AsignarStrict($expediente, $operador, $remitido->id, $catalogoObservacion->id);

    Sanctum::actingAs($operador, ['*']);

    $this->getJson('/api/expedientes/'.$expediente->id)
        ->assertOk()
        ->assertJsonPath('data.nurej_code', $expediente->nurej_code);
});

it('conserva el acceso de solo lectura de ADMIN a los catalogos y bloquea su emision sobre causas', function () {
    [
        'operador' => $operador,
        'admin' => $admin,
        'evaluacion' => $evaluacion,
        'remitido' => $remitido,
        'catalogoObservacion' => $catalogoObservacion,
    ] = b23SemillaStrict();

    $expediente = b23CrearExpedienteStrict($evaluacion->id, $operador->id);
    b23AsignarStrict($expediente, $operador, $evaluacion->id, $catalogoObservacion->id);

    Sanctum::actingAs($admin, ['*']);

    $this->getJson('/api/catalogo/actuados')->assertOk();

    $this->postJson('/api/expedientes/'.$expediente->id.'/actuados', [
        'catalogo_actuado_id' => $catalogoObservacion->id,
        'descripcion' => 'Intento de emision administrativa sobre causa asignada.',
    ])->assertForbidden();

    $remitida = b23CrearExpedienteStrict($remitido->id, $operador->id);
    b23AsignarStrict($remitida, $operador, $remitido->id, $catalogoObservacion->id);

    $this->getJson('/api/expedientes/'.$remitida->id)->assertForbidden();
});

it('protege la bandeja del operador con la policy operadorBandeja: 403 para ADMIN y Encargada', function () {
    [
        'operador' => $operador,
        'encargada' => $encargada,
        'admin' => $admin,
        'evaluacion' => $evaluacion,
        'catalogoObservacion' => $catalogoObservacion,
    ] = b23SemillaStrict();

    $expediente = b23CrearExpedienteStrict($evaluacion->id, $operador->id);
    b23AsignarStrict($expediente, $operador, $evaluacion->id, $catalogoObservacion->id);

    Sanctum::actingAs($operador, ['*']);
    $this->getJson('/api/bandeja')->assertOk();

    Sanctum::actingAs($encargada, ['*']);
    $this->getJson('/api/bandeja')->assertForbidden();

    Sanctum::actingAs($admin, ['*']);
    $this->getJson('/api/bandeja')->assertForbidden();
});

it('delega la lectura del actuado en la politica del expediente contenedor sin bypass para ADMIN', function () {
    [
        'operador' => $operador,
        'encargada' => $encargada,
        'admin' => $admin,
        'evaluacion' => $evaluacion,
        'catalogoObservacion' => $catalogoObservacion,
    ] = b23SemillaStrict();

    $rolAjeno = Rol::factory()->create(['codigo' => Rol::CODIGO_TECNICO]);
    $operadorAjeno = Usuario::factory()->create(['rol_id' => $rolAjeno->id]);

    $expediente = b23CrearExpedienteStrict($evaluacion->id, $operador->id);
    b23AsignarStrict($expediente, $operador, $evaluacion->id, $catalogoObservacion->id);

    $actuado = Actuado::create([
        'expediente_id' => $expediente->id,
        'catalogo_actuado_id' => $catalogoObservacion->id,
        'usuario_id' => $operador->id,
        'estado_nuevo_id' => $evaluacion->id,
        'contenido' => ['tipo' => 'OBSERVACION'],
    ]);

    expect($operador->can('view', $actuado))->toBeTrue()
        ->and($encargada->can('view', $actuado))->toBeTrue()
        ->and($admin->can('view', $actuado))->toBeFalse()
        ->and($operadorAjeno->can('view', $actuado))->toBeFalse();
});
