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

function archivoAbandonoSemilla(): array
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
        'admin', 'tecnico',
        'reglamento',
        'subsanacion', 'archivoAbandono',
        'catalogoDisparador', 'catalogoArchivo',
    );
}

function archivoAbandonoCrearExpediente(int $estadoId, int $creadorId, int $reglamentoId): Expediente
{
    return Expediente::create([
        'nurej_code' => 'EXP-'.fake()->unique()->numberBetween(100000, 999999),
        'via' => 'JURIDICO',
        'reglamento_id' => $reglamentoId,
        'estado_actual_id' => $estadoId,
        'fecha_ingreso' => now(),
        'creado_por' => $creadorId,
    ]);
}

function archivoAbandonoCrearPlazoVigente(
    Expediente $expediente,
    Actuado $actuadoDisparador,
    string $fechaLimite,
    array $overrides = [],
): Plazo {
    return Plazo::create(array_merge([
        'expediente_id' => $expediente->id,
        'tipo_plazo' => ArchivoPorAbandonoService::TIPO_PLAZO_SUBSANACION,
        'dias_habiles_otorgados' => 3,
        'fecha_inicio' => '2026-08-28',
        'fecha_limite' => $fechaLimite,
        'estado' => ArchivoPorAbandonoService::ESTADO_PLAZO_VIGENTE,
        'fuera_de_plazo' => false,
        'actuado_disparador_id' => $actuadoDisparador->id,
    ], $overrides));
}

it('archiva por abandono el expediente en EN_SUBSANACION cuyo plazo caducó (RN-03)', function () {
    $semilla = archivoAbandonoSemilla();
    $expediente = archivoAbandonoCrearExpediente(
        $semilla['subsanacion']->id,
        $semilla['tecnico']->id,
        $semilla['reglamento']->id,
    );

    $actuadoDisparador = Actuado::create([
        'expediente_id' => $expediente->id,
        'catalogo_actuado_id' => $semilla['catalogoDisparador']->id,
        'usuario_id' => $semilla['tecnico']->id,
        'estado_nuevo_id' => $semilla['subsanacion']->id,
        'contenido' => ['descripcion' => 'Observación: apertura de subsanación'],
    ])->refresh();

    Asignacion::create([
        'expediente_id' => $expediente->id,
        'usuario_id' => $semilla['tecnico']->id,
        'rol_id' => $semilla['tecnico']->rol_id,
        'actuado_origen_id' => $actuadoDisparador->id,
        'fecha_asignacion' => now(),
        'activa' => true,
    ]);

    archivoAbandonoCrearPlazoVigente($expediente, $actuadoDisparador, '2026-09-07');

    Carbon::setTestNow('2026-09-08 00:05:00');

    $this->artisan('plazos:verificar-vencidos')->assertExitCode(0);

    $plazo = $expediente->plazos()->first();
    $expediente->refresh();

    expect($plazo->estado)->toBe(ArchivoPorAbandonoService::ESTADO_PLAZO_VENCIDO)
        ->and($plazo->fuera_de_plazo)->toBeTrue()
        ->and($expediente->estado_actual_id)->toBe($semilla['archivoAbandono']->id);

    $actuado = Actuado::where('expediente_id', $expediente->id)
        ->where('catalogo_actuado_id', $semilla['catalogoArchivo']->id)
        ->first();

    expect($actuado)->not->toBeNull()
        ->and($actuado->usuario_id)->toBe($semilla['admin']->id)
        ->and($actuado->estado_anterior_id)->toBe($semilla['subsanacion']->id)
        ->and($actuado->estado_nuevo_id)->toBe($semilla['archivoAbandono']->id)
        ->and($actuado->contenido['tipo'])->toBe('AUTOMATICO')
        ->and($actuado->contenido['plazo_id'])->toBe($plazo->id)
        ->and($actuado->hash_actuado)->toMatch('/^[0-9a-f]{64}$/')
        ->and($actuado->hash_anterior)->toBe($actuadoDisparador->hash_actuado);

    expect(Asignacion::where('expediente_id', $expediente->id)->where('activa', true)->count())->toBe(0);

    Carbon::setTestNow();
});

it('no archiva el plazo de subsanación que vence hoy (comparación estricta)', function () {
    $semilla = archivoAbandonoSemilla();
    $expediente = archivoAbandonoCrearExpediente(
        $semilla['subsanacion']->id,
        $semilla['tecnico']->id,
        $semilla['reglamento']->id,
    );

    $actuadoDisparador = Actuado::create([
        'expediente_id' => $expediente->id,
        'catalogo_actuado_id' => $semilla['catalogoDisparador']->id,
        'usuario_id' => $semilla['tecnico']->id,
        'estado_nuevo_id' => $semilla['subsanacion']->id,
        'contenido' => ['descripcion' => 'Observación: apertura de subsanación'],
    ])->refresh();

    archivoAbandonoCrearPlazoVigente($expediente, $actuadoDisparador, '2026-09-08');

    Carbon::setTestNow('2026-09-08 00:05:00');

    $this->artisan('plazos:verificar-vencidos')->assertExitCode(0);

    $expediente->refresh();

    expect($expediente->plazos()->first()->estado)->toBe(ArchivoPorAbandonoService::ESTADO_PLAZO_VIGENTE)
        ->and($expediente->estado_actual_id)->toBe($semilla['subsanacion']->id)
        ->and(Actuado::where('catalogo_actuado_id', $semilla['catalogoArchivo']->id)->exists())->toBeFalse();

    Carbon::setTestNow();
});

it('no archiva plazos de subsanación futuros ni los ya cerrados', function () {
    $semilla = archivoAbandonoSemilla();

    $futuro = archivoAbandonoCrearExpediente(
        $semilla['subsanacion']->id,
        $semilla['tecnico']->id,
        $semilla['reglamento']->id,
    );

    $disparadorFuturo = Actuado::create([
        'expediente_id' => $futuro->id,
        'catalogo_actuado_id' => $semilla['catalogoDisparador']->id,
        'usuario_id' => $semilla['tecnico']->id,
        'estado_nuevo_id' => $semilla['subsanacion']->id,
        'contenido' => ['descripcion' => 'Observación: apertura de subsanación'],
    ])->refresh();

    archivoAbandonoCrearPlazoVigente($futuro, $disparadorFuturo, '2026-09-15');

    $cerrado = archivoAbandonoCrearExpediente(
        $semilla['subsanacion']->id,
        $semilla['tecnico']->id,
        $semilla['reglamento']->id,
    );

    $disparadorCerrado = Actuado::create([
        'expediente_id' => $cerrado->id,
        'catalogo_actuado_id' => $semilla['catalogoDisparador']->id,
        'usuario_id' => $semilla['tecnico']->id,
        'estado_nuevo_id' => $semilla['subsanacion']->id,
        'contenido' => ['descripcion' => 'Observación: apertura de subsanación'],
    ])->refresh();

    archivoAbandonoCrearPlazoVigente($cerrado, $disparadorCerrado, '2026-09-07', [
        'estado' => 'CERRADO',
    ]);

    Carbon::setTestNow('2026-09-08 00:05:00');

    $this->artisan('plazos:verificar-vencidos')->assertExitCode(0);

    expect($futuro->fresh()->estado_actual_id)->toBe($semilla['subsanacion']->id)
        ->and($futuro->plazos()->first()->estado)->toBe(ArchivoPorAbandonoService::ESTADO_PLAZO_VIGENTE)
        ->and($cerrado->fresh()->estado_actual_id)->toBe($semilla['subsanacion']->id)
        ->and($cerrado->plazos()->first()->estado)->toBe('CERRADO');

    Carbon::setTestNow();
});
