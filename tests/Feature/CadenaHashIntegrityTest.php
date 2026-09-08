<?php

use App\Models\Actuado;
use App\Models\CatalogoActuado;
use App\Models\CatalogoEstado;
use App\Models\Expediente;
use App\Models\Reglamento;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;

function hashIntegritySemilla(): array
{
    $rol = Rol::factory()->create();
    $usuario = Usuario::factory()->create(['rol_id' => $rol->id]);
    $reglamento = Reglamento::factory()->create();
    $estado = CatalogoEstado::factory()->create();

    $catalogo = CatalogoActuado::create([
        'codigo' => 'ACT_INTEGRIDAD_'.fake()->unique()->numberBetween(1000, 9999),
        'nombre' => 'Actuado de integridad',
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

function hashIntegrityInsertarConLock(array $semilla, int $secuencia): Actuado
{
    return DB::transaction(function () use ($semilla, $secuencia) {
        DB::table('expedientes')
            ->where('id', $semilla['expediente']->id)
            ->lockForUpdate()
            ->first();

        return Actuado::create([
            'expediente_id' => $semilla['expediente']->id,
            'catalogo_actuado_id' => $semilla['catalogo']->id,
            'usuario_id' => $semilla['usuario']->id,
            'estado_nuevo_id' => $semilla['estado']->id,
            'contenido' => ['descripcion' => "Nodo {$secuencia} de la cadena"],
        ])->refresh();
    }, 3);
}

it('mantiene la cadena integral insertando bajo lockForUpdate', function () {
    $semilla = hashIntegritySemilla();

    collect(range(1, 5))->each(fn (int $i) => hashIntegrityInsertarConLock($semilla, $i));

    $cadena = Actuado::where('expediente_id', $semilla['expediente']->id)
        ->orderBy('id')
        ->get();

    expect($cadena)->toHaveCount(5);
    $hashes = $cadena->pluck('hash_actuado')->all();

    expect(count($hashes))->toBe(count(array_unique($hashes)))
        ->and($cadena->first()->hash_anterior)->toBeNull();

    $cadena->each(function (Actuado $actuado, int $indice) use ($cadena) {
        if ($indice === 0) {
            return;
        }

        expect($actuado->hash_anterior)->toBe($cadena[$indice - 1]->hash_actuado);
    });
});
