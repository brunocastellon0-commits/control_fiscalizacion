<?php

use App\Models\Actuado;
use App\Models\CatalogoActuado;
use App\Models\CatalogoEstado;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

function paso7SemillaActuadoController(): array
{
    $rolOperador = Rol::factory()->create(['codigo' => Rol::CODIGO_AUD_JURIDICO]);
    $operador = Usuario::factory()->create(['rol_id' => $rolOperador->id]);

    $rolTecnico = Rol::factory()->create(['codigo' => Rol::CODIGO_TECNICO]);
    $tecnico = Usuario::factory()->create(['rol_id' => $rolTecnico->id]);

    $evaluacion = CatalogoEstado::factory()->create(['codigo' => 'EN_EVALUACION']);
    $observado = CatalogoEstado::factory()->create(['codigo' => 'OBSERVADO']);

    $catalogoObservacion = CatalogoActuado::create([
        'codigo' => 'ACT_OBSERVACION',
        'nombre' => 'Observacion',
        'fase' => 'ADMISIBILIDAD',
        'rol_id' => $rolOperador->id,
        'estado_origen_id' => $evaluacion->id,
        'estado_destino_id' => $observado->id,
        'es_automatico' => false,
        'requiere_adjunto' => false,
    ]);

    $catalogoRegistro = CatalogoActuado::create([
        'codigo' => 'ACT_REGISTRO_DIGITALIZACION',
        'nombre' => 'Registro y Digitalizacion',
        'fase' => 'REGISTRO',
        'rol_id' => $rolTecnico->id,
        'estado_origen_id' => null,
        'estado_destino_id' => $evaluacion->id,
        'es_automatico' => false,
        'requiere_adjunto' => true,
    ]);

    return compact('rolOperador', 'operador', 'rolTecnico', 'tecnico', 'evaluacion', 'observado', 'catalogoObservacion', 'catalogoRegistro');
}

it('permite a un operador con asignacion activa emitir un actuado de su rol', function () {
    [
        'operador' => $operador,
        'evaluacion' => $evaluacion,
        'catalogoObservacion' => $catalogoObservacion,
    ] = paso7SemillaActuadoController();

    $expediente = paso7CrearExpedienteConEstado($evaluacion->id, $operador->id);
    paso7AsignarActivamente($expediente, $operador, $evaluacion->id, $catalogoObservacion->id);

    Sanctum::actingAs($operador, ['*']);

    $response = $this->postJson('/api/expedientes/'.$expediente->id.'/actuados', [
        'catalogo_actuado_id' => $catalogoObservacion->id,
        'descripcion' => 'Observacion de requisitos al expediente.',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.tipo_actuado.codigo', 'ACT_OBSERVACION')
        ->assertJsonPath('data.descripcion', 'Observacion de requisitos al expediente.')
        ->assertJsonPath('data.estado_nuevo.codigo', 'OBSERVADO');

    expect(Actuado::where('expediente_id', $expediente->id)->count())->toBe(2);
});

it('bloquea a un operador sin asignacion activa emitir actuados (RF-03)', function () {
    [
        'operador' => $operador,
        'evaluacion' => $evaluacion,
        'catalogoObservacion' => $catalogoObservacion,
    ] = paso7SemillaActuadoController();

    $expediente = paso7CrearExpedienteConEstado($evaluacion->id, $operador->id);

    Sanctum::actingAs($operador, ['*']);

    $this->postJson('/api/expedientes/'.$expediente->id.'/actuados', [
        'catalogo_actuado_id' => $catalogoObservacion->id,
        'descripcion' => 'Intento de observacion sin asignacion.',
    ])->assertForbidden();
});

it('exige adjunto cuando el catalogo del actuado lo requiere', function () {
    Storage::fake('local');
    [
        'tecnico' => $tecnico,
        'evaluacion' => $evaluacion,
        'catalogoRegistro' => $catalogoRegistro,
    ] = paso7SemillaActuadoController();

    $expediente = paso7CrearExpedienteConEstado($evaluacion->id, $tecnico->id);
    paso7AsignarActivamente($expediente, $tecnico, $evaluacion->id, $catalogoRegistro->id);

    Sanctum::actingAs($tecnico, ['*']);

    $this->postJson('/api/expedientes/'.$expediente->id.'/actuados', [
        'catalogo_actuado_id' => $catalogoRegistro->id,
        'descripcion' => 'Registro y digitalizacion del expediente.',
    ])->assertStatus(422)->assertJsonValidationErrors('adjunto');
});
