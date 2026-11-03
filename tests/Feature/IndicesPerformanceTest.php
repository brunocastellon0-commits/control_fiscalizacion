<?php

use Illuminate\Support\Facades\DB;

function indiceExiste(string $tabla, string $indice): bool
{
    $fila = DB::selectOne(
        'SELECT COUNT(*) AS total FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?',
        [DB::connection()->getDatabaseName(), $tabla, $indice]
    );

    return $fila->total > 0;
}

/**
 * @return array<int, string>
 */
function columnasDelIndice(string $tabla, string $indice): array
{
    $filas = DB::select(
        'SELECT COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ? ORDER BY SEQ_IN_INDEX',
        [DB::connection()->getDatabaseName(), $tabla, $indice]
    );

    return array_map(fn ($fila) => $fila->COLUMN_NAME, $filas);
}

/**
 * @return array<int, string>
 */
function clavesDelPlan(string $sql): array
{
    $claves = [];

    foreach (DB::select('EXPLAIN '.$sql) as $fila) {
        if (! empty($fila->key)) {
            $claves[] = $fila->key;
        }
    }

    return array_values(array_unique($claves));
}

it('crea los indices compuestos de rendimiento con el orden correcto', function () {
    expect(indiceExiste('expedientes', 'idx_expedientes_estado_ingreso'))->toBeTrue()
        ->and(columnasDelIndice('expedientes', 'idx_expedientes_estado_ingreso'))->toBe(['estado_actual_id', 'fecha_ingreso'])
        ->and(indiceExiste('asignaciones', 'idx_asignaciones_activa_usuario'))->toBeTrue()
        ->and(columnasDelIndice('asignaciones', 'idx_asignaciones_activa_usuario'))->toBe(['usuario_id', 'activa', 'fecha_asignacion'])
        ->and(indiceExiste('plazos', 'idx_plazos_expediente_estado'))->toBeTrue()
        ->and(columnasDelIndice('plazos', 'idx_plazos_expediente_estado'))->toBe(['expediente_id', 'estado', 'fecha_limite']);
});

it('no duplica el indice existente de actuados', function () {
    expect(indiceExiste('actuados', 'idx_actuados_exp_fecha'))->toBeTrue()
        ->and(indiceExiste('actuados', 'idx_actuados_expediente_fecha'))->toBeFalse();
});

it('permite al optimizador usar los indices nuevos', function () {
    expect(clavesDelPlan('SELECT id FROM expedientes FORCE INDEX (idx_expedientes_estado_ingreso) WHERE estado_actual_id = 1 ORDER BY fecha_ingreso'))
        ->toContain('idx_expedientes_estado_ingreso')
        ->and(clavesDelPlan('SELECT id FROM asignaciones FORCE INDEX (idx_asignaciones_activa_usuario) WHERE usuario_id = 1 AND activa = 1 ORDER BY fecha_asignacion'))
        ->toContain('idx_asignaciones_activa_usuario')
        ->and(clavesDelPlan("SELECT id FROM plazos FORCE INDEX (idx_plazos_expediente_estado) WHERE expediente_id = 1 AND estado = 'VIGENTE' ORDER BY fecha_limite"))
        ->toContain('idx_plazos_expediente_estado');
});

it('revierte y reaplica la migracion sin errores', function () {
    $migracion = require database_path('migrations/2026_11_03_200000_add_performance_indexes.php');

    $migracion->down();

    expect(indiceExiste('expedientes', 'idx_expedientes_estado_ingreso'))->toBeFalse()
        ->and(indiceExiste('asignaciones', 'idx_asignaciones_activa_usuario'))->toBeFalse()
        ->and(indiceExiste('plazos', 'idx_plazos_expediente_estado'))->toBeFalse();

    $migracion->up();

    expect(indiceExiste('expedientes', 'idx_expedientes_estado_ingreso'))->toBeTrue()
        ->and(indiceExiste('asignaciones', 'idx_asignaciones_activa_usuario'))->toBeTrue()
        ->and(indiceExiste('plazos', 'idx_plazos_expediente_estado'))->toBeTrue();
});
