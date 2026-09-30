<?php

use App\Models\Actuado;
use App\Models\Asignacion;
use App\Models\CatalogoActuado;
use App\Models\CatalogoEstado;
use App\Models\Expediente;
use App\Models\Plazo;
use App\Models\Reglamento;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\ArchivoPorAbandonoService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;

function fvSemilla(): array
{
    $rolAdmin = Rol::factory()->create(['codigo' => Rol::CODIGO_ADMIN]);
    $admin = Usuario::factory()->create(['rol_id' => $rolAdmin->id, 'activo' => true]);

    $rolTecnico = Rol::factory()->create(['codigo' => Rol::CODIGO_TECNICO]);
    $tecnico = Usuario::factory()->create(['rol_id' => $rolTecnico->id, 'activo' => true]);

    $reglamento = Reglamento::factory()->create();

    $subsanacion = CatalogoEstado::factory()->create(['codigo' => 'EN_SUBSANACION']);
    $archivoAbandono = CatalogoEstado::factory()->create(['codigo' => 'ARCHIVO_POR_ABANDONO', 'es_final' => true]);

    $catalogoDisparador = CatalogoActuado::create([
        'codigo' => 'ACT_OBSERVACION',
        'nombre' => 'Observación',
        'fase' => 'ADMISIBILIDAD',
        'rol_id' => $rolTecnico->id,
        'estado_origen_id' => $subsanacion->id,
        'estado_destino_id' => $subsanacion->id,
        'es_automatico' => false,
        'requiere_adjunto' => false,
    ]);

    $catalogoArchivo = CatalogoActuado::create([
        'codigo' => ArchivoPorAbandonoService::CODIGO_CATALOGO_ARCHIVO,
        'nombre' => 'Archivo por Abandono',
        'fase' => 'ADMISIBILIDAD',
        'rol_id' => $rolAdmin->id,
        'estado_origen_id' => $subsanacion->id,
        'estado_destino_id' => $archivoAbandono->id,
        'es_automatico' => true,
        'requiere_adjunto' => false,
    ]);

    return compact(
        'admin', 'tecnico', 'reglamento',
        'subsanacion', 'archivoAbandono',
        'catalogoDisparador', 'catalogoArchivo',
    );
}

function fvExpediente(array $semilla): Expediente
{
    return Expediente::create([
        'nurej_code' => 'EXP-'.fake()->unique()->numberBetween(100000, 999999),
        'via' => 'JURIDICO',
        'reglamento_id' => $semilla['reglamento']->id,
        'estado_actual_id' => $semilla['subsanacion']->id,
        'fecha_ingreso' => now(),
        'creado_por' => $semilla['tecnico']->id,
    ]);
}

function fvDisparador(Expediente $expediente, array $semilla): Actuado
{
    return Actuado::create([
        'expediente_id' => $expediente->id,
        'catalogo_actuado_id' => $semilla['catalogoDisparador']->id,
        'usuario_id' => $semilla['tecnico']->id,
        'estado_nuevo_id' => $semilla['subsanacion']->id,
        'contenido' => ['descripcion' => 'Observación: apertura de subsanación'],
    ])->refresh();
}

function fvAsignar(Expediente $expediente, Actuado $disparador, array $semilla): void
{
    Asignacion::create([
        'expediente_id' => $expediente->id,
        'usuario_id' => $semilla['tecnico']->id,
        'rol_id' => $semilla['tecnico']->rol_id,
        'actuado_origen_id' => $disparador->id,
        'fecha_asignacion' => now(),
        'activa' => true,
    ]);
}

function fvPlazoSubsanacion(Expediente $expediente, Actuado $disparador, string $fechaLimite): Plazo
{
    return Plazo::create([
        'expediente_id' => $expediente->id,
        'tipo_plazo' => ArchivoPorAbandonoService::TIPO_PLAZO_SUBSANACION,
        'dias_habiles_otorgados' => 3,
        'fecha_inicio' => '2026-08-28',
        'fecha_limite' => $fechaLimite,
        'estado' => ArchivoPorAbandonoService::ESTADO_PLAZO_VIGENTE,
        'fuera_de_plazo' => false,
        'actuado_disparador_id' => $disparador->id,
    ]);
}

function fvPlazoInternoVencido(Expediente $expediente, Actuado $disparador, string $fechaLimite): Plazo
{
    return Plazo::create([
        'expediente_id' => $expediente->id,
        'tipo_plazo' => 'EJECUCION',
        'dias_habiles_otorgados' => 10,
        'fecha_inicio' => '2026-08-20',
        'fecha_limite' => $fechaLimite,
        'estado' => ArchivoPorAbandonoService::ESTADO_PLAZO_VIGENTE,
        'fuera_de_plazo' => false,
        'actuado_disparador_id' => $disparador->id,
    ]);
}

it('es idempotente: la segunda corrida del comando no archiva ni marca nada de nuevo', function () {
    $semilla = fvSemilla();

    $expediente = fvExpediente($semilla);
    $disparador = fvDisparador($expediente, $semilla);
    fvAsignar($expediente, $disparador, $semilla);
    fvPlazoSubsanacion($expediente, $disparador, '2026-09-07');
    fvPlazoInternoVencido($expediente, $disparador, '2026-09-07');

    Carbon::setTestNow('2026-09-08 00:05:00');

    $this->artisan('plazos:verificar-vencidos')->assertExitCode(0);

    $actuadosTrasPrimera = Actuado::where('expediente_id', $expediente->id)->count();
    $internaMarcada = $expediente->plazos()->where('tipo_plazo', 'EJECUCION')->first();

    expect($actuadosTrasPrimera)->toBe(2)
        ->and($internaMarcada->fuera_de_plazo)->toBeTrue();

    $this->artisan('plazos:verificar-vencidos')->assertExitCode(0);

    expect(Actuado::where('expediente_id', $expediente->id)->count())->toBe($actuadosTrasPrimera)
        ->and($expediente->plazos()->where('tipo_plazo', 'SUBSANACION')->first()->estado)
        ->toBe(ArchivoPorAbandonoService::ESTADO_PLAZO_VENCIDO)
        ->and($expediente->plazos()->where('tipo_plazo', 'EJECUCION')->first()->fuera_de_plazo)->toBeTrue()
        ->and(Actuado::where('catalogo_actuado_id', $semilla['catalogoArchivo']->id)->count())->toBe(1);

    Carbon::setTestNow();
});

it('archiva con la fecha UTC ya cambiada pero aún es el día de vencimiento en Argentina (corte UTC prematuro, AUD-0043)', function () {
    $semilla = fvSemilla();

    $expediente = fvExpediente($semilla);
    $disparador = fvDisparador($expediente, $semilla);
    fvAsignar($expediente, $disparador, $semilla);
    fvPlazoSubsanacion($expediente, $disparador, '2026-09-07');

    // 2026-09-08 00:05 UTC = 2026-09-07 21:05 en Argentina (UTC-3): aún
    // falta la medianoche local del día de vencimiento, pero el job ya
    // archiva porque compara contra la fecha UTC.
    Carbon::setTestNow('2026-09-08 00:05:00');

    $this->artisan('plazos:verificar-vencidos')->assertExitCode(0);

    $plazo = $expediente->plazos()->first();
    $expediente->refresh();

    expect($plazo->estado)->toBe(ArchivoPorAbandonoService::ESTADO_PLAZO_VENCIDO)
        ->and($expediente->estado_actual_id)->toBe($semilla['archivoAbandono']->id)
        ->and(Actuado::where('catalogo_actuado_id', $semilla['catalogoArchivo']->id)->exists())->toBeTrue();

    Carbon::setTestNow();
});

it('falla sin ejecutar nada si falta el catálogo ACT_ARCHIVO_POR_ABANDONO (AUD-0042)', function () {
    $semilla = fvSemilla();
    $semilla['catalogoArchivo']->delete();

    $expediente = fvExpediente($semilla);
    $disparador = fvDisparador($expediente, $semilla);
    fvAsignar($expediente, $disparador, $semilla);
    fvPlazoSubsanacion($expediente, $disparador, '2026-09-07');
    fvPlazoInternoVencido($expediente, $disparador, '2026-09-07');

    Carbon::setTestNow('2026-09-08 00:05:00');

    expect(fn () => $this->artisan('plazos:verificar-vencidos')->run())
        ->toThrow(ModelNotFoundException::class);

    expect($expediente->plazos()->where('tipo_plazo', 'SUBSANACION')->first()->estado)
        ->toBe(ArchivoPorAbandonoService::ESTADO_PLAZO_VIGENTE)
        ->and($expediente->plazos()->where('tipo_plazo', 'EJECUCION')->first()->fuera_de_plazo)->toBeFalse()
        ->and($expediente->fresh()->estado_actual_id)->toBe($semilla['subsanacion']->id)
        ->and(Actuado::where('expediente_id', $expediente->id)->count())->toBe(1);

    Carbon::setTestNow();
});
