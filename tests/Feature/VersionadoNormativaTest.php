<?php

use App\Models\CatalogoActuado;
use App\Models\CatalogoEstado;
use App\Models\Expediente;
use App\Models\ParametroPlazo;
use App\Models\Reglamento;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\ExpedienteService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * RN-06 — Inmutabilidad normativa: la causa nace y concluye bajo el mismo
 * reglamento. Ningún endpoint ni servicio puede actualizar reglamento_id de
 * un expediente existente; el guard de Expediente::updating() lo bloquea.
 */
function versionadoSemillaNormativa(): array
{
    $rol = Rol::factory()->create();
    $tecnico = Usuario::factory()->create(['rol_id' => $rol->id]);
    $estado = CatalogoEstado::factory()->create(['codigo' => 'PENDIENTE_SORTEO']);
    $catalogo = CatalogoActuado::create([
        'codigo' => 'ACT_REGISTRO_DIGITALIZACION',
        'nombre' => 'Registro y Digitalización',
        'fase' => 'REGISTRO',
        'rol_id' => $rol->id,
        'estado_destino_id' => $estado->id,
        'es_automatico' => false,
        'requiere_adjunto' => false,
        'descripcion' => 'Registro y digitalización del expediente',
    ]);

    $reglamentoOrigen = Reglamento::factory()->create();
    $reglamentoDestino = Reglamento::factory()->create();

    // Dos parámetros de plazo incompatibles: el downstream solo debe poder
    // resolver el del reglamento de origen (3 días), jamás el de destino (7).
    ParametroPlazo::create([
        'reglamento_id' => $reglamentoOrigen->id,
        'tipo_plazo' => 'SUBSANACION',
        'subtipo' => null,
        'dias_habiles' => 3,
        'base_legal' => 'RN-06-ORIGEN',
        'activo' => true,
    ]);
    ParametroPlazo::create([
        'reglamento_id' => $reglamentoDestino->id,
        'tipo_plazo' => 'SUBSANACION',
        'subtipo' => null,
        'dias_habiles' => 7,
        'base_legal' => 'RN-06-DESTINO',
        'activo' => true,
    ]);

    $expediente = Expediente::create([
        'nurej_code' => 'RN06-'.fake()->unique()->numberBetween(100000, 999999),
        'via' => 'TECNICO',
        'reglamento_id' => $reglamentoOrigen->id,
        'estado_actual_id' => $estado->id,
        'resumen_hechos' => 'Causa bajo verificación de inmutabilidad normativa.',
        'fecha_ingreso' => now(),
        'creado_por' => $tecnico->id,
    ]);

    return compact(
        'rol', 'tecnico', 'estado', 'catalogo',
        'reglamentoOrigen', 'reglamentoDestino', 'expediente',
    );
}

it('no expone rutas PUT/PATCH/DELETE sobre expedientes (RN-06)', function () {
    $violaciones = collect(Route::getRoutes())
        ->filter(fn ($ruta) => str_contains($ruta->uri(), 'expediente'))
        ->filter(fn ($ruta) => array_intersect(['PUT', 'PATCH', 'DELETE'], $ruta->methods()) !== [])
        ->map(fn ($ruta) => implode('|', $ruta->methods()).' '.$ruta->uri())
        ->all();

    expect($violaciones)->toBe([]);
});

it('bloquea update() de reglamento_id y la BD conserva el valor original', function () {
    $semilla = versionadoSemillaNormativa();
    $expediente = $semilla['expediente'];

    expect(fn () => $expediente->update([
        'reglamento_id' => $semilla['reglamentoDestino']->id,
    ]))->toThrow(DomainException::class);

    $enBase = DB::table('expedientes')->where('id', $expediente->id)->first();
    expect((int) $enBase->reglamento_id)->toBe($semilla['reglamentoOrigen']->id);
});

it('bloquea forceFill()->save() y asignación directa + save()', function () {
    $semilla = versionadoSemillaNormativa();

    $conForceFill = Expediente::findOrFail($semilla['expediente']->id);
    expect(fn () => $conForceFill->forceFill([
        'reglamento_id' => $semilla['reglamentoDestino']->id,
    ])->save())->toThrow(DomainException::class);

    $conAsignacionDirecta = Expediente::findOrFail($semilla['expediente']->id);
    $conAsignacionDirecta->reglamento_id = $semilla['reglamentoDestino']->id;
    expect(fn () => $conAsignacionDirecta->save())->toThrow(DomainException::class);

    $enBase = DB::table('expedientes')->where('id', $semilla['expediente']->id)->first();
    expect((int) $enBase->reglamento_id)->toBe($semilla['reglamentoOrigen']->id);
});

it('la creación de expedientes e hijos sigue funcionando con reglamento_id', function () {
    $semilla = versionadoSemillaNormativa();

    $apertura = app(ExpedienteService::class)->aperturaCausa([
        'via' => 'TECNICO',
        'reglamento_id' => $semilla['reglamentoOrigen']->id,
        'resumen_hechos' => 'Apertura bajo RN-06.',
    ], $semilla['tecnico']);

    expect($apertura->reglamento_id)->toBe($semilla['reglamentoOrigen']->id);

    // Mismo mecanismo de creación de hijos (NurejHijoService usa create()).
    $hijo = Expediente::create([
        'nurej_code' => '2026-'.fake()->unique()->numberBetween(10000, 99999).'-1',
        'nurej_padre_id' => $apertura->id,
        'via' => $apertura->via,
        'reglamento_id' => $apertura->reglamento_id,
        'estado_actual_id' => $semilla['estado']->id,
        'fecha_ingreso' => now(),
        'creado_por' => $semilla['tecnico']->id,
    ]);

    expect($hijo->reglamento_id)->toBe($semilla['reglamentoOrigen']->id);

    $enBase = DB::table('expedientes')->where('id', $hijo->id)->first();
    expect((int) $enBase->reglamento_id)->toBe($semilla['reglamentoOrigen']->id);
});

it('tras el intento fallido el downstream sigue resolviendo parámetros con el reglamento original', function () {
    $semilla = versionadoSemillaNormativa();
    $expediente = $semilla['expediente'];

    expect(fn () => $expediente->update([
        'reglamento_id' => $semilla['reglamentoDestino']->id,
    ]))->toThrow(DomainException::class);

    // Misma resolución que ActuadoService@abrirPlazoSiAplica sobre el
    // reglamento del expediente persistido.
    $fresco = Expediente::findOrFail($expediente->id);
    $parametro = ParametroPlazo::where('reglamento_id', $fresco->reglamento_id)
        ->where('tipo_plazo', 'SUBSANACION')
        ->whereNull('subtipo')
        ->where('activo', true)
        ->firstOrFail();

    expect($parametro->reglamento_id)->toBe($semilla['reglamentoOrigen']->id)
        ->and($parametro->dias_habiles)->toBe(3);
});
