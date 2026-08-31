<?php

use App\Http\Resources\ActuadoResource;
use App\Models\Actuado;
use App\Models\CatalogoActuado;
use App\Models\CatalogoEstado;
use App\Models\Expediente;
use App\Models\Reglamento;
use App\Models\Rol;
use App\Models\Usuario;

function paso6Actuado(array $contenido = ['descripcion' => 'Se notifica descargo']): Actuado
{
    $rolActuado = Rol::factory()->create();
    $usuarioActuado = Usuario::factory()->create(['rol_id' => $rolActuado->id]);
    $reglamento = Reglamento::factory()->create();
    $estadoOrigen = CatalogoEstado::factory()->create();
    $estadoDestino = CatalogoEstado::factory()->create();
    $catalogoActuado = CatalogoActuado::create([
        'codigo' => 'ACT_ACTUADO_'.fake()->unique()->numberBetween(1000, 9999),
        'nombre' => 'Actuado de prueba',
        'fase' => 'TEST',
        'rol_id' => $rolActuado->id,
        'estado_origen_id' => $estadoOrigen->id,
        'estado_destino_id' => $estadoDestino->id,
        'es_automatico' => false,
        'requiere_adjunto' => false,
    ]);

    $expediente = Expediente::create([
        'nurej_code' => 'ACT-'.fake()->unique()->numberBetween(10000, 99999),
        'via' => 'TECNICO',
        'reglamento_id' => $reglamento->id,
        'estado_actual_id' => $estadoOrigen->id,
        'fecha_ingreso' => now(),
        'creado_por' => $usuarioActuado->id,
    ]);

    $actuado = Actuado::create([
        'expediente_id' => $expediente->id,
        'catalogo_actuado_id' => $catalogoActuado->id,
        'usuario_id' => $usuarioActuado->id,
        'estado_anterior_id' => $estadoOrigen->id,
        'estado_nuevo_id' => $estadoDestino->id,
        'contenido' => $contenido,
    ]);

    return $actuado->refresh();
}

it('transforma un actuado con sus hashes, estado y descripcion', function () {
    $actuado = paso6Actuado();
    $actuado->load(['tipoActuado', 'estadoAnterior', 'estadoNuevo', 'usuario', 'adjuntos']);

    $salida = (new ActuadoResource($actuado))->resolve(request());

    expect($salida)
        ->toHaveKey('id', $actuado->id)
        ->toHaveKey('fecha_hora')
        ->toHaveKey('descripcion', 'Se notifica descargo')
        ->toHaveKey('hash_anterior')
        ->toHaveKey('hash_actuado')
        ->and($salida['hash_actuado'])->toBeString()->toHaveLength(64)
        ->and($salida['tipo_actuado']['codigo'])->toBe($actuado->tipoActuado->codigo)
        ->and($salida['estado_anterior']['codigo'])->toBe($actuado->estadoAnterior->codigo)
        ->and($salida['estado_nuevo']['codigo'])->toBe($actuado->estadoNuevo->codigo)
        ->and($salida['usuario']['nombres'])->toBe($actuado->usuario->nombres)
        ->and($salida['adjuntos'])->toBe([]);
});

it('omite relaciones anidadas cuando no estan cargadas', function () {
    $actuado = paso6Actuado();

    $salida = (new ActuadoResource($actuado))->resolve(request());

    expect($salida)
        ->not->toHaveKey('tipo_actuado')
        ->not->toHaveKey('estado_anterior')
        ->not->toHaveKey('estado_nuevo')
        ->not->toHaveKey('usuario')
        ->not->toHaveKey('adjuntos');
});

it('devuelve descripcion nula cuando el contenido no la define', function () {
    $actuado = paso6Actuado(contenido: ['otro_campo' => 'x']);

    $salida = (new ActuadoResource($actuado))->resolve(request());

    expect($salida['descripcion'])->toBeNull();
});
