<?php

use App\Models\Actuado;
use App\Models\Expediente;
use App\Models\Reglamento;
use App\Models\Usuario;
use Database\Seeders\CatalogoEstadoSeeder;
use Illuminate\Support\Facades\DB;

/**
 * Re-ejecuta la lógica de la migración de B1.6 (la clase anónima que devuelve
 * el archivo). Idempotente: la columna `activo` ya existe tras las migraciones
 * de la suite, por lo que `up()` solo repite la verificación de referencias.
 */
function b16Migracion(): object
{
    return require database_path('migrations/2026_10_07_100001_inactivar_estados_huerfanos.php');
}

/**
 * Crea un estado de prueba (activo=true por default de columna) y devuelve su id.
 */
function b16CrearEstado(string $codigo): int
{
    return (int) DB::table('catalogo_estados')->insertGetId([
        'codigo' => $codigo,
        'nombre' => $codigo,
        'activo' => true,
    ]);
}

/**
 * Devuelve el estado como fila cruda para poder asertar existencia + activo.
 */
function b16Estado(string $codigo): object
{
    $estado = DB::table('catalogo_estados')->where('codigo', $codigo)->first();

    expect($estado)->not->toBeNull();

    return $estado;
}

/**
 * Expediente de referencia para la FK expedientes.estado_actual_id.
 */
function b16ExpedienteConEstado(int $estadoId): Expediente
{
    return Expediente::create([
        'nurej_code' => 'B16-'.fake()->unique()->numberBetween(100000, 999999),
        'via' => 'TECNICO',
        'reglamento_id' => Reglamento::factory()->create()->id,
        'estado_actual_id' => $estadoId,
        'fecha_ingreso' => now(),
        'creado_por' => Usuario::factory()->create()->id,
    ]);
}

/**
 * Fila de catálogo de actuados (CatalogoActuado no expone factory).
 */
function b16CatalogoActuado(array $atributos = []): int
{
    return (int) DB::table('catalogo_actuados')->insertGetId(array_merge([
        'codigo' => 'ACT_B16_'.fake()->unique()->numberBetween(10000, 99999),
        'nombre' => 'Actuado B1.6',
        'fase' => 'ADMISIBILIDAD',
    ], $atributos));
}

/**
 * Actuado de referencia para las FK actuados.estado_anterior_id / estado_nuevo_id.
 * El expediente base apunta a un estado neutral (EN_EVALUACION) para que la
 * única referencia al estado probado provenga de la columna bajo prueba.
 */
function b16ActuadoConEstados(int $estadoAnteriorId, int $estadoNuevoId): Actuado
{
    $expediente = b16ExpedienteConEstado(b16CrearEstado('EN_EVALUACION'));

    return Actuado::create([
        'expediente_id' => $expediente->id,
        'catalogo_actuado_id' => b16CatalogoActuado(),
        'usuario_id' => Usuario::factory()->create()->id,
        'estado_anterior_id' => $estadoAnteriorId,
        'estado_nuevo_id' => $estadoNuevoId,
        'contenido' => ['descripcion' => 'Actuado de referencia B1.6'],
    ])->refresh();
}

it('la migracion inactiva los estados huerfanos sin referencias operativas', function () {
    b16CrearEstado('EN_INVESTIGACION');
    b16CrearEstado('EN_DESCARGOS');

    b16Migracion()->up();

    expect((bool) b16Estado('EN_INVESTIGACION')->activo)->toBeFalse()
        ->and((bool) b16Estado('EN_DESCARGOS')->activo)->toBeFalse();
});

it('la migracion nunca toca los estados de reparto institucional y salidas firmes', function () {
    $protegidos = [
        'CONCLUIDO',
        'ARCHIVO_DEFINITIVO',
        'ARCHIVO_POR_ABANDONO',
        'LISTO_PARA_REPARTO',
        'CONCLUIDO_REMITIDO',
        'DERIVADO_TRANSPARENCIA',
    ];

    foreach ($protegidos as $codigo) {
        b16CrearEstado($codigo);
    }

    b16Migracion()->up();

    foreach ($protegidos as $codigo) {
        expect((bool) b16Estado($codigo)->activo)->toBeTrue($codigo.' debe permanecer activo');
    }
});

it('la migracion conserva activo=true si el estado tiene referencias en las cinco FK operativas', function (string $escenario) {
    $codigo = 'EN_INVESTIGACION';
    $estadoId = b16CrearEstado($codigo);

    match ($escenario) {
        'expedientes.estado_actual_id' => b16ExpedienteConEstado($estadoId),
        'actuados.estado_anterior_id' => b16ActuadoConEstados($estadoId, b16CrearEstado('ADMITIDO')),
        'actuados.estado_nuevo_id' => b16ActuadoConEstados(b16CrearEstado('ADMITIDO'), $estadoId),
        'catalogo_actuados.estado_origen_id' => b16CatalogoActuado(['estado_origen_id' => $estadoId]),
        'catalogo_actuados.estado_destino_id' => b16CatalogoActuado(['estado_destino_id' => $estadoId]),
    };

    b16Migracion()->up();

    expect((bool) b16Estado($codigo)->activo)->toBeTrue($escenario.' cuenta como uso operativo');
})->with([
    'expedientes.estado_actual_id',
    'actuados.estado_anterior_id',
    'actuados.estado_nuevo_id',
    'catalogo_actuados.estado_origen_id',
    'catalogo_actuados.estado_destino_id',
]);

it('el seeder fresh deja los dos huerfanos inactivos y el resto de estados activos', function () {
    $this->seed(CatalogoEstadoSeeder::class);

    expect((bool) b16Estado('EN_INVESTIGACION')->activo)->toBeFalse()
        ->and((bool) b16Estado('EN_DESCARGOS')->activo)->toBeFalse()
        ->and((bool) b16Estado('EN_EVALUACION')->activo)->toBeTrue()
        ->and((bool) b16Estado('CONCLUIDO')->activo)->toBeTrue();
});

it('el seeder no desactiva un estado huerfano que tiene referencias operativas', function () {
    $this->seed(CatalogoEstadoSeeder::class);

    $investigacion = b16Estado('EN_INVESTIGACION');
    expect((bool) $investigacion->activo)->toBeFalse();

    b16ExpedienteConEstado((int) $investigacion->id);

    $this->seed(CatalogoEstadoSeeder::class);

    expect((bool) b16Estado('EN_INVESTIGACION')->activo)->toBeTrue()
        ->and((bool) b16Estado('EN_DESCARGOS')->activo)->toBeFalse();
});

it('ningun estado_destino de catalogo_actuados apunta a un estado inactivo tras la migracion', function () {
    b16CrearEstado('EN_DESCARGOS');
    $investigacion = b16CrearEstado('EN_INVESTIGACION');
    b16CatalogoActuado(['estado_destino_id' => $investigacion]);

    b16Migracion()->up();

    expect((bool) b16Estado('EN_DESCARGOS')->activo)->toBeFalse()
        ->and((bool) b16Estado('EN_INVESTIGACION')->activo)->toBeTrue();

    $apuntandoAInactivo = DB::table('catalogo_actuados')
        ->join('catalogo_estados', 'catalogo_estados.id', '=', 'catalogo_actuados.estado_destino_id')
        ->where('catalogo_estados.activo', false)
        ->count();

    expect($apuntandoAInactivo)->toBe(0);
});
