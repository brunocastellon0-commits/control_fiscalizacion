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
use App\Services\MarcarPlazosVencidosService;
use App\Services\SemaforoPlazoService;
use Carbon\Carbon;

function semaforoPenalizacionSemilla(): array
{
    $rolAdmin = Rol::factory()->create(['codigo' => Rol::CODIGO_ADMIN]);
    $admin = Usuario::factory()->create(['rol_id' => $rolAdmin->id, 'activo' => true]);

    $rolTecnico = Rol::factory()->create(['codigo' => Rol::CODIGO_TECNICO]);
    $tecnico = Usuario::factory()->create(['rol_id' => $rolTecnico->id, 'activo' => true]);

    $reglamento = Reglamento::factory()->create();

    $ejecucion = CatalogoEstado::factory()->create(['codigo' => 'EN_EJECUCION']);
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
        'ejecucion', 'subsanacion', 'archivoAbandono',
        'catalogoDisparador', 'catalogoArchivo',
    );
}

function semaforoPenalizacionCrearExpediente(int $estadoId, int $creadorId, int $reglamentoId): Expediente
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

function semaforoPenalizacionAsignarActivamente(Expediente $expediente, Usuario $usuario, int $estadoId, int $catalogoId): Actuado
{
    $actuado = Actuado::create([
        'expediente_id' => $expediente->id,
        'catalogo_actuado_id' => $catalogoId,
        'usuario_id' => $usuario->id,
        'estado_nuevo_id' => $estadoId,
        'contenido' => ['tipo' => 'ASIGNACION'],
    ])->refresh();

    Asignacion::create([
        'expediente_id' => $expediente->id,
        'usuario_id' => $usuario->id,
        'rol_id' => $usuario->rol_id,
        'actuado_origen_id' => $actuado->id,
        'fecha_asignacion' => now(),
        'activa' => true,
    ]);

    return $actuado;
}

function semaforoPenalizacionCrearPlazo(
    Expediente $expediente,
    Actuado $actuadoDisparador,
    string $tipoPlazo,
    string $fechaLimite,
): Plazo {
    return Plazo::create([
        'expediente_id' => $expediente->id,
        'tipo_plazo' => $tipoPlazo,
        'dias_habiles_otorgados' => 10,
        'fecha_inicio' => '2026-08-28',
        'fecha_limite' => $fechaLimite,
        'estado' => ArchivoPorAbandonoService::ESTADO_PLAZO_VIGENTE,
        'fuera_de_plazo' => false,
        'actuado_disparador_id' => $actuadoDisparador->id,
    ]);
}

it('marca fuera_de_plazo un plazo de EJECUCION vencido sin archivar ni cerrar la bandeja', function () {
    $semilla = semaforoPenalizacionSemilla();
    $expediente = semaforoPenalizacionCrearExpediente(
        $semilla['ejecucion']->id,
        $semilla['tecnico']->id,
        $semilla['reglamento']->id,
    );
    $actuado = semaforoPenalizacionAsignarActivamente(
        $expediente,
        $semilla['tecnico'],
        $semilla['ejecucion']->id,
        $semilla['catalogoDisparador']->id,
    );
    semaforoPenalizacionCrearPlazo($expediente, $actuado, 'EJECUCION', '2026-09-05');

    Carbon::setTestNow('2026-09-08 10:00:00');

    try {
        $this->artisan('plazos:verificar-vencidos')->assertExitCode(0);

        $plazo = $expediente->plazos()->first();
        $expediente->refresh();
        $asignacion = $expediente->asignacionActiva()->first();

        $semaforo = app(SemaforoPlazoService::class)->evaluarPlazo($plazo);

        expect($plazo->fuera_de_plazo)->toBeTrue()
            ->and($plazo->estado)->toBe(ArchivoPorAbandonoService::ESTADO_PLAZO_VIGENTE)
            ->and($expediente->estado_actual_id)->toBe($semilla['ejecucion']->id)
            ->and($asignacion)->not->toBeNull()
            ->and($asignacion->activa)->toBeTrue()
            ->and($semaforo['codigo_color'])->toBe('FUERA_DE_PLAZO')
            ->and($semaforo['es_fuera_de_plazo'])->toBeTrue();
    } finally {
        Carbon::setTestNow();
    }
});

it('no vuelve a marcar un plazo que ya quedó fuera de plazo', function () {
    $semilla = semaforoPenalizacionSemilla();
    $expediente = semaforoPenalizacionCrearExpediente(
        $semilla['ejecucion']->id,
        $semilla['tecnico']->id,
        $semilla['reglamento']->id,
    );
    $actuado = semaforoPenalizacionAsignarActivamente(
        $expediente,
        $semilla['tecnico'],
        $semilla['ejecucion']->id,
        $semilla['catalogoDisparador']->id,
    );
    $plazo = semaforoPenalizacionCrearPlazo($expediente, $actuado, 'EJECUCION', '2026-09-05');
    $plazo->update(['fuera_de_plazo' => true]);

    Carbon::setTestNow('2026-09-08 10:00:00');

    try {
        $marcados = app(MarcarPlazosVencidosService::class)->marcarVencidos();

        expect($marcados)->toBe(0)
            ->and($plazo->fresh()->fuera_de_plazo)->toBeTrue();
    } finally {
        Carbon::setTestNow();
    }
});

it('archiva por SUBSANACION vencida y solo penaliza la EJECUCION en la misma corrida', function () {
    $semilla = semaforoPenalizacionSemilla();

    $expEjecucion = semaforoPenalizacionCrearExpediente(
        $semilla['ejecucion']->id,
        $semilla['tecnico']->id,
        $semilla['reglamento']->id,
    );
    $actuadoEjecucion = semaforoPenalizacionAsignarActivamente(
        $expEjecucion,
        $semilla['tecnico'],
        $semilla['ejecucion']->id,
        $semilla['catalogoDisparador']->id,
    );
    semaforoPenalizacionCrearPlazo($expEjecucion, $actuadoEjecucion, 'EJECUCION', '2026-09-05');

    $expSubsanacion = semaforoPenalizacionCrearExpediente(
        $semilla['subsanacion']->id,
        $semilla['tecnico']->id,
        $semilla['reglamento']->id,
    );
    $actuadoSubsanacion = semaforoPenalizacionAsignarActivamente(
        $expSubsanacion,
        $semilla['tecnico'],
        $semilla['subsanacion']->id,
        $semilla['catalogoDisparador']->id,
    );
    semaforoPenalizacionCrearPlazo($expSubsanacion, $actuadoSubsanacion, 'SUBSANACION', '2026-09-07');

    Carbon::setTestNow('2026-09-08 00:05:00');

    try {
        $this->artisan('plazos:verificar-vencidos')->assertExitCode(0);

        $plazoEjecucion = $expEjecucion->plazos()->first();
        $plazoSubsanacion = $expSubsanacion->plazos()->first();
        $expEjecucion->refresh();
        $expSubsanacion->refresh();

        expect($plazoEjecucion->fuera_de_plazo)->toBeTrue()
            ->and($plazoEjecucion->estado)->toBe(ArchivoPorAbandonoService::ESTADO_PLAZO_VIGENTE)
            ->and($expEjecucion->estado_actual_id)->toBe($semilla['ejecucion']->id)
            ->and($expEjecucion->asignacionActiva()->first()->activa)->toBeTrue();

        expect($plazoSubsanacion->estado)->toBe(ArchivoPorAbandonoService::ESTADO_PLAZO_VENCIDO)
            ->and($plazoSubsanacion->fuera_de_plazo)->toBeTrue()
            ->and($expSubsanacion->estado_actual_id)->toBe($semilla['archivoAbandono']->id)
            ->and($expSubsanacion->asignacionActiva()->first())->toBeNull();
    } finally {
        Carbon::setTestNow();
    }
});

it('preserva el flag fuera_de_plazo cuando el plazo se cierra por la entrega tardía', function () {
    $semilla = semaforoPenalizacionSemilla();
    $expediente = semaforoPenalizacionCrearExpediente(
        $semilla['ejecucion']->id,
        $semilla['tecnico']->id,
        $semilla['reglamento']->id,
    );
    $actuado = semaforoPenalizacionAsignarActivamente(
        $expediente,
        $semilla['tecnico'],
        $semilla['ejecucion']->id,
        $semilla['catalogoDisparador']->id,
    );
    $plazo = semaforoPenalizacionCrearPlazo($expediente, $actuado, 'EJECUCION', '2026-09-05');

    Carbon::setTestNow('2026-09-08 10:00:00');

    try {
        $this->artisan('plazos:verificar-vencidos')->assertExitCode(0);

        $plazo->refresh();
        expect($plazo->fuera_de_plazo)->toBeTrue();

        Plazo::where('expediente_id', $expediente->id)
            ->where('estado', ArchivoPorAbandonoService::ESTADO_PLAZO_VIGENTE)
            ->update(['estado' => 'CERRADO']);

        $plazo->refresh();
        $semaforo = app(SemaforoPlazoService::class)->evaluarPlazo($plazo);

        expect($plazo->estado)->toBe('CERRADO')
            ->and($plazo->fuera_de_plazo)->toBeTrue()
            ->and($semaforo['codigo_color'])->toBe('CERRADO')
            ->and($semaforo['es_fuera_de_plazo'])->toBeTrue();
    } finally {
        Carbon::setTestNow();
    }
});
