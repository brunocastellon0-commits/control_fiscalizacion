<?php

use App\Models\Rol;
use Database\Seeders\RolSeeder;

/**
 * B0.3 — correspondencia entre las constantes Rol::CODIGO_* definidas en el
 * modelo y los códigos reales de la tabla roles tras ejecutar RolSeeder.
 *
 * @return array<string, string>
 */
function rolConstantesCodigos(): array
{
    $codigos = [];

    foreach ((new ReflectionClass(Rol::class))->getConstants() as $nombre => $valor) {
        if (str_starts_with($nombre, 'CODIGO_')) {
            $codigos[$nombre] = $valor;
        }
    }

    return $codigos;
}

it('define al menos una constante Rol::CODIGO_', function () {
    expect(rolConstantesCodigos())->not->toBeEmpty();
});

it('cada Rol::CODIGO_* existe en la tabla roles tras el seed', function () {
    $this->seed(RolSeeder::class);

    foreach (rolConstantesCodigos() as $nombre => $valor) {
        expect(Rol::where('codigo', $valor)->exists())
            ->toBeTrue("La constante Rol::{$nombre} = '{$valor}' no tiene correspondencia en la tabla roles");
    }
});

it('la tabla roles contiene exactamente los códigos de las constantes', function () {
    $this->seed(RolSeeder::class);

    $esperados = array_values(rolConstantesCodigos());
    sort($esperados);

    $enBD = Rol::pluck('codigo')->sort()->values()->all();

    expect($enBD)->toBe($esperados);
});
