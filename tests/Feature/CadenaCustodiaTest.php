<?php

use App\Models\Actuado;
use App\Models\CatalogoActuado;
use App\Models\CatalogoEstado;
use App\Models\Expediente;
use App\Models\Reglamento;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

function paso9SemillaExpedienteYActuado(): array
{
    $rol = Rol::factory()->create();
    $usuario = Usuario::factory()->create(['rol_id' => $rol->id]);
    $reglamento = Reglamento::factory()->create();
    $estado = CatalogoEstado::factory()->create();

    $catalogo = CatalogoActuado::create([
        'codigo' => 'ACT_CADENA_'.fake()->unique()->numberBetween(1000, 9999),
        'nombre' => 'Actuado de cadena',
        'fase' => 'REGISTRO',
        'rol_id' => $rol->id,
        'estado_destino_id' => $estado->id,
        'es_automatico' => false,
        'requiere_adjunto' => false,
    ]);

    $expediente = Expediente::create([
        'nurej_code' => '2026-'.fake()->unique()->numberBetween(10000, 99999),
        'via' => 'TECNICO',
        'reglamento_id' => $reglamento->id,
        'estado_actual_id' => $estado->id,
        'fecha_ingreso' => now(),
        'creado_por' => $usuario->id,
    ]);

    return compact('expediente', 'usuario', 'estado', 'catalogo');
}

function paso9CrearActuado(Expediente $expediente, Usuario $usuario, CatalogoEstado $estado, CatalogoActuado $catalogo): Actuado
{
    return Actuado::create([
        'expediente_id' => $expediente->id,
        'catalogo_actuado_id' => $catalogo->id,
        'usuario_id' => $usuario->id,
        'estado_nuevo_id' => $estado->id,
        'contenido' => ['descripcion' => 'Nodo de la cadena de custodia'],
    ])->refresh();
}

function paso9HashEsperado(?string $hashAnterior, Actuado $actuado): string
{
    $fila = DB::table('actuados')->where('id', $actuado->id)->first();

    return hash('sha256', implode('', [
        $hashAnterior ?? '',
        (string) $fila->expediente_id,
        (string) $fila->catalogo_actuado_id,
        $fila->usuario_id !== null ? (string) $fila->usuario_id : 'SYSTEM',
        (string) $fila->fecha_hora,
        (string) $fila->contenido,
    ]));
}

it('inicia la cadena con el primer actuado sin hash_anterior', function () {
    $semilla = paso9SemillaExpedienteYActuado();

    $actuado = paso9CrearActuado(
        $semilla['expediente'],
        $semilla['usuario'],
        $semilla['estado'],
        $semilla['catalogo'],
    );

    expect($actuado->hash_anterior)->toBeNull()
        ->and($actuado->hash_actuado)->toMatch('/^[0-9a-f]{64}$/');
});

it('encadena el segundo actuado con el hash del primero', function () {
    $semilla = paso9SemillaExpedienteYActuado();

    $primero = paso9CrearActuado(
        $semilla['expediente'],
        $semilla['usuario'],
        $semilla['estado'],
        $semilla['catalogo'],
    );

    $segundo = paso9CrearActuado(
        $semilla['expediente'],
        $semilla['usuario'],
        $semilla['estado'],
        $semilla['catalogo'],
    );

    expect($segundo->hash_anterior)->toBe($primero->hash_actuado);
});

it('mantiene la cadena sobre tres actuados consecutivos', function () {
    $semilla = paso9SemillaExpedienteYActuado();

    $actuados = collect(range(1, 3))
        ->map(fn () => paso9CrearActuado(
            $semilla['expediente'],
            $semilla['usuario'],
            $semilla['estado'],
            $semilla['catalogo'],
        ));

    expect($actuados[1]->hash_anterior)->toBe($actuados[0]->hash_actuado)
        ->and($actuados[2]->hash_anterior)->toBe($actuados[1]->hash_actuado);
});

it('reproduce fielmente el hash sha256 calculado por el trigger', function () {
    $semilla = paso9SemillaExpedienteYActuado();

    $primero = paso9CrearActuado(
        $semilla['expediente'],
        $semilla['usuario'],
        $semilla['estado'],
        $semilla['catalogo'],
    );

    $segundo = paso9CrearActuado(
        $semilla['expediente'],
        $semilla['usuario'],
        $semilla['estado'],
        $semilla['catalogo'],
    );

    expect($primero->hash_actuado)->toBe(paso9HashEsperado(null, $primero))
        ->and($segundo->hash_actuado)->toBe(paso9HashEsperado($primero->hash_actuado, $segundo));
});

it('bloquea el UPDATE directo sobre un actuado por inmutabilidad', function () {
    $semilla = paso9SemillaExpedienteYActuado();
    $actuado = paso9CrearActuado(
        $semilla['expediente'],
        $semilla['usuario'],
        $semilla['estado'],
        $semilla['catalogo'],
    );

    expect(fn () => $actuado->update(['contenido' => ['descripcion' => 'manipulado']]))
        ->toThrow(QueryException::class);

    $actuado->refresh();
    expect($actuado->contenido)->toBe(['descripcion' => 'Nodo de la cadena de custodia']);
});

it('bloquea el DELETE directo sobre un actuado por inmutabilidad', function () {
    $semilla = paso9SemillaExpedienteYActuado();
    $actuado = paso9CrearActuado(
        $semilla['expediente'],
        $semilla['usuario'],
        $semilla['estado'],
        $semilla['catalogo'],
    );

    expect(fn () => $actuado->delete())
        ->toThrow(QueryException::class);

    expect(Actuado::find($actuado->id))->not->toBeNull();
});
