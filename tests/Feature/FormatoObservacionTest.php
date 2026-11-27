<?php

use App\Models\Actuado;
use App\Models\Asignacion;
use App\Models\CatalogoActuado;
use App\Models\CatalogoEstado;
use App\Models\CatalogoRequisito;
use App\Models\Expediente;
use App\Models\Reglamento;
use App\Models\Rol;
use App\Models\Usuario;
use Laravel\Sanctum\Sanctum;

function formatoSemilla(): array
{
    $rolTecnico = Rol::factory()->create(['codigo' => Rol::CODIGO_TECNICO]);
    $tecnico = Usuario::factory()->create(['rol_id' => $rolTecnico->id, 'activo' => true]);

    $rolVentanilla = Rol::factory()->create(['codigo' => Rol::CODIGO_VENTANILLA]);
    $ventanilla = Usuario::factory()->create(['rol_id' => $rolVentanilla->id, 'activo' => true]);

    $reglamento = Reglamento::factory()->create(['codigo' => 'AC_022_2018']);

    $evaluacion = CatalogoEstado::factory()->create(['codigo' => 'EN_EVALUACION']);
    $subsanacion = CatalogoEstado::factory()->create(['codigo' => 'EN_SUBSANACION']);

    $catalogoObservacion = CatalogoActuado::create([
        'codigo' => 'ACT_OBSERVACION',
        'nombre' => 'ACT_OBSERVACION',
        'fase' => 'ADMISIBILIDAD',
        'rol_id' => $rolTecnico->id,
        'reglamento_id' => null,
        'estado_origen_id' => $evaluacion->id,
        'estado_destino_id' => $subsanacion->id,
        'es_automatico' => false,
        'requiere_adjunto' => false,
    ]);

    $requisito = CatalogoRequisito::factory()->create([
        'reglamento_id' => $reglamento->id,
        'es_critico' => false,
        'activo' => true,
        'orden' => 1,
    ]);

    return compact(
        'tecnico', 'ventanilla',
        'reglamento', 'evaluacion', 'subsanacion',
        'catalogoObservacion', 'requisito',
    );
}

function formatoPrepararExpediente(array $semilla): Expediente
{
    $expediente = Expediente::create([
        'nurej_code' => 'EXP-'.fake()->unique()->numberBetween(10000, 99999),
        'via' => 'INGRESO_VERBAL',
        'reglamento_id' => $semilla['reglamento']->id,
        'estado_actual_id' => $semilla['evaluacion']->id,
        'fecha_ingreso' => now(),
        'creado_por' => $semilla['tecnico']->id,
    ]);

    $actuado = Actuado::create([
        'expediente_id' => $expediente->id,
        'catalogo_actuado_id' => $semilla['catalogoObservacion']->id,
        'usuario_id' => $semilla['tecnico']->id,
        'estado_nuevo_id' => $semilla['evaluacion']->id,
        'contenido' => ['tipo' => 'ASIGNACION'],
    ])->refresh();

    Asignacion::create([
        'expediente_id' => $expediente->id,
        'usuario_id' => $semilla['tecnico']->id,
        'rol_id' => $semilla['tecnico']->rol_id,
        'actuado_origen_id' => $actuado->id,
        'fecha_asignacion' => now(),
        'activa' => true,
    ]);

    return $expediente;
}

function formatoEmitirObservacion(Expediente $expediente, array $semilla): void
{
    Sanctum::actingAs($semilla['tecnico'], ['*']);

    test()->postJson('/api/expedientes/'.$expediente->id.'/evaluacion', [
        'requisitos' => [
            ['requisito_id' => $semilla['requisito']->id, 'cumple' => false],
        ],
        'detalle_observacion' => 'Falta presentar el documento de identidad del denunciante original.',
    ])->assertStatus(201);
}

function formatoActuadoObservacion(Expediente $expediente, array $semilla): Actuado
{
    return Actuado::where('expediente_id', $expediente->id)
        ->where('catalogo_actuado_id', $semilla['catalogoObservacion']->id)
        ->where('estado_nuevo_id', $semilla['subsanacion']->id)
        ->firstOrFail();
}

it('descarga el formato de observación en PDF', function () {
    $semilla = formatoSemilla();
    $expediente = formatoPrepararExpediente($semilla);
    formatoEmitirObservacion($expediente, $semilla);
    $actuado = formatoActuadoObservacion($expediente, $semilla);

    Sanctum::actingAs($semilla['tecnico'], ['*']);

    $this->get('/api/expedientes/'.$expediente->id.'/actuados/'.$actuado->id.'/formato-observacion')
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('devuelve el HTML de impresión con el NUREJ y el detalle de la observación', function () {
    $semilla = formatoSemilla();
    $expediente = formatoPrepararExpediente($semilla);
    formatoEmitirObservacion($expediente, $semilla);
    $actuado = formatoActuadoObservacion($expediente, $semilla);

    Sanctum::actingAs($semilla['tecnico'], ['*']);

    $this->get('/api/expedientes/'.$expediente->id.'/actuados/'.$actuado->id.'/formato-observacion?impresion=1')
        ->assertOk()
        ->assertSee($expediente->nurej_code)
        ->assertSee('Falta presentar el documento de identidad del denunciante original.');
});

it('devuelve 404 si el actuado es ajeno al expediente o no es una observación', function () {
    $semilla = formatoSemilla();
    $expedienteA = formatoPrepararExpediente($semilla);
    formatoEmitirObservacion($expedienteA, $semilla);
    $actuadoA = formatoActuadoObservacion($expedienteA, $semilla);

    $expedienteB = formatoPrepararExpediente($semilla);

    $catalogoOtro = CatalogoActuado::create([
        'codigo' => 'ACT_OTRO_FORMATO',
        'nombre' => 'ACT_OTRO_FORMATO',
        'fase' => 'ADMISIBILIDAD',
        'rol_id' => $semilla['tecnico']->rol_id,
        'reglamento_id' => null,
        'estado_origen_id' => $semilla['evaluacion']->id,
        'estado_destino_id' => $semilla['evaluacion']->id,
        'es_automatico' => false,
        'requiere_adjunto' => false,
    ]);

    $actuadoNoObservacion = Actuado::create([
        'expediente_id' => $expedienteB->id,
        'catalogo_actuado_id' => $catalogoOtro->id,
        'usuario_id' => $semilla['tecnico']->id,
        'estado_nuevo_id' => $semilla['evaluacion']->id,
        'contenido' => ['tipo' => 'OTRO'],
    ]);

    Sanctum::actingAs($semilla['tecnico'], ['*']);

    $this->get('/api/expedientes/'.$expedienteB->id.'/actuados/'.$actuadoA->id.'/formato-observacion')
        ->assertNotFound();

    $this->get('/api/expedientes/'.$expedienteB->id.'/actuados/'.$actuadoNoObservacion->id.'/formato-observacion')
        ->assertNotFound();
});

it('devuelve 403 a la Ventanilla y a un operador sin asignación', function () {
    $semilla = formatoSemilla();
    $expediente = formatoPrepararExpediente($semilla);
    formatoEmitirObservacion($expediente, $semilla);
    $actuado = formatoActuadoObservacion($expediente, $semilla);

    $url = '/api/expedientes/'.$expediente->id.'/actuados/'.$actuado->id.'/formato-observacion';

    Sanctum::actingAs($semilla['ventanilla'], ['*']);
    $this->get($url)->assertForbidden();

    $tecnicoSinAsignacion = Usuario::factory()->create([
        'rol_id' => $semilla['tecnico']->rol_id,
        'activo' => true,
    ]);
    Sanctum::actingAs($tecnicoSinAsignacion, ['*']);
    $this->get($url)->assertForbidden();
});
