<?php

use App\Http\Resources\ParteResource;
use App\Models\CatalogoEstado;
use App\Models\Expediente;
use App\Models\Parte;
use App\Models\Reglamento;
use App\Models\Rol;
use App\Models\Usuario;

function paso6Expediente(): Expediente
{
    $rol = Rol::factory()->create();
    $usuario = Usuario::factory()->create(['rol_id' => $rol->id]);
    $reglamento = Reglamento::factory()->create();
    $estado = CatalogoEstado::factory()->create();

    return Expediente::create([
        'nurej_code' => 'PRT-'.fake()->unique()->numberBetween(10000, 99999),
        'via' => 'TECNICO',
        'reglamento_id' => $reglamento->id,
        'estado_actual_id' => $estado->id,
        'fecha_ingreso' => now(),
        'creado_por' => $usuario->id,
    ]);
}

it('transforma una parte con sus datos y versionado', function () {
    $expediente = paso6Expediente();

    $parte = Parte::create([
        'expediente_id' => $expediente->id,
        'tipo' => 'DENUNCIANTE',
        'nombre_completo' => 'Juan Perez',
        'documento_identidad' => '1234567',
        'cargo_institucion' => null,
        'es_version_actual' => true,
    ])->refresh();

    $salida = (new ParteResource($parte))->resolve(request());

    expect($salida)
        ->toHaveKey('id', $parte->id)
        ->toHaveKey('tipo', 'DENUNCIANTE')
        ->toHaveKey('nombre_completo', 'Juan Perez')
        ->toHaveKey('documento_identidad', '1234567')
        ->toHaveKey('cargo_institucion', null)
        ->toHaveKey('es_version_actual', true)
        ->and($salida['vigente_desde'])->not->toBeNull();
});

it('no expone datos sensibles adicionales de la parte', function () {
    $expediente = paso6Expediente();

    $parte = Parte::create([
        'expediente_id' => $expediente->id,
        'tipo' => 'DENUNCIADO',
        'nombre_completo' => 'Autoridad Municipal',
        'cargo_institucion' => 'Direccion General',
        'es_version_actual' => true,
    ])->refresh();

    $salida = (new ParteResource($parte))->resolve(request());

    expect($salida)->toHaveKeys([
        'id', 'tipo', 'nombre_completo', 'documento_identidad',
        'cargo_institucion', 'vigente_desde', 'es_version_actual',
    ])->and($salida)->not->toHaveKeys(['expediente_id', 'actuado_origen_id']);
});
