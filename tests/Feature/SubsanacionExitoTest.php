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
use App\Services\ActuadoService;
use App\Services\ArchivoPorAbandonoService;
use Database\Seeders\CatalogoActuadoSeeder;
use Database\Seeders\CatalogoEstadoSeeder;
use Database\Seeders\FeriadoSeeder;
use Database\Seeders\ParametroPlazoSeeder;
use Database\Seeders\ReglamentoSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * B1.4 — Flujo de subsanación (AUD-0032) y protección transaccional del
 * archivo por abandono (AUD-0031). Semilla con los catálogos reales.
 *
 * @return array{encargada: Usuario, tecnico: Usuario, otroTecnico: Usuario, admin: Usuario, ac022: Reglamento, estados: array<string, CatalogoEstado>}
 */
function subsanacionExitoSemilla(TestCase $test): array
{
    foreach ([
        RolSeeder::class,
        CatalogoEstadoSeeder::class,
        ReglamentoSeeder::class,
        ParametroPlazoSeeder::class,
        FeriadoSeeder::class,
        CatalogoActuadoSeeder::class,
    ] as $seeder) {
        $test->seed($seeder);
    }

    $estados = [];
    foreach ([
        'PENDIENTE_SORTEO',
        'EN_EVALUACION',
        'EN_SUBSANACION',
        'EN_PLANIFICACION',
        'ARCHIVO_POR_ABANDONO',
    ] as $codigo) {
        $estados[$codigo] = CatalogoEstado::where('codigo', $codigo)->firstOrFail();
    }

    $rolEncargada = Rol::where('codigo', Rol::CODIGO_ENCARGADA)->firstOrFail();
    $rolTecnico = Rol::where('codigo', Rol::CODIGO_TECNICO)->firstOrFail();
    $rolAdmin = Rol::where('codigo', Rol::CODIGO_ADMIN)->firstOrFail();

    return [
        'encargada' => Usuario::factory()->create(['rol_id' => $rolEncargada->id, 'activo' => true]),
        'tecnico' => Usuario::factory()->create(['rol_id' => $rolTecnico->id, 'activo' => true]),
        'otroTecnico' => Usuario::factory()->create(['rol_id' => $rolTecnico->id, 'activo' => true]),
        'admin' => Usuario::factory()->create(['rol_id' => $rolAdmin->id, 'activo' => true]),
        'ac022' => Reglamento::where('codigo', 'AC_022_2018')->firstOrFail(),
        'estados' => $estados,
    ];
}

function subsanacionExitoCrearExpediente(int $estadoId, int $reglamentoId, int $creadorId): Expediente
{
    return Expediente::create([
        'nurej_code' => 'EXP-SUBS-'.fake()->unique()->numberBetween(100000, 999999),
        'via' => 'TECNICO',
        'reglamento_id' => $reglamentoId,
        'estado_actual_id' => $estadoId,
        'fecha_ingreso' => now(),
        'creado_por' => $creadorId,
    ]);
}

function subsanacionExitoActuadoBase(Expediente $expediente, Usuario $usuario, int $estadoNuevoId): Actuado
{
    $catalogo = CatalogoActuado::where('codigo', 'ACT_OBSERVACION')->firstOrFail();

    return Actuado::create([
        'expediente_id' => $expediente->id,
        'catalogo_actuado_id' => $catalogo->id,
        'usuario_id' => $usuario->id,
        'estado_nuevo_id' => $estadoNuevoId,
        'contenido' => ['descripcion' => 'Actuado base de la semilla B1.4'],
    ])->refresh();
}

function subsanacionExitoAsignar(Expediente $expediente, Usuario $usuario, Actuado $actuadoOrigen): void
{
    Asignacion::create([
        'expediente_id' => $expediente->id,
        'usuario_id' => $usuario->id,
        'rol_id' => $usuario->rol_id,
        'actuado_origen_id' => $actuadoOrigen->id,
        'fecha_asignacion' => now(),
        'activa' => true,
    ]);
}

function subsanacionExitoPlazo(
    Expediente $expediente,
    Actuado $disparador,
    string $fechaLimite,
    string $estado = 'VIGENTE',
): Plazo {
    return Plazo::create([
        'expediente_id' => $expediente->id,
        'tipo_plazo' => ArchivoPorAbandonoService::TIPO_PLAZO_SUBSANACION,
        'dias_habiles_otorgados' => 3,
        'fecha_inicio' => now()->subDays(3)->toDateString(),
        'fecha_limite' => $fechaLimite,
        'estado' => $estado,
        'fuera_de_plazo' => $estado === 'VENCIDO',
        'actuado_disparador_id' => $disparador->id,
    ]);
}

function subsanacionExitoEmitirAceptacion(Expediente $expediente): mixed
{
    $catalogo = CatalogoActuado::where('codigo', 'ACT_SUBSANACION_ACEPTADA')->firstOrFail();

    return test()->postJson("/api/expedientes/{$expediente->id}/actuados", [
        'catalogo_actuado_id' => $catalogo->id,
        'descripcion' => 'Subsanación presentada por el interesado y aceptada por el operador.',
    ]);
}

it('acepta la subsanación: cierra el reloj, retorna a evaluación y habilita la admisión (RN-03)', function () {
    $s = subsanacionExitoSemilla($this);
    $servicio = app(ActuadoService::class);

    $expediente = subsanacionExitoCrearExpediente(
        $s['estados']['PENDIENTE_SORTEO']->id,
        $s['ac022']->id,
        $s['encargada']->id,
    );

    $servicio->registerActuado(
        expediente: $expediente,
        catalogoActuado: CatalogoActuado::where('codigo', 'ACT_SORTEO_INICIAL')->firstOrFail(),
        emisor: $s['encargada'],
        descripcion: 'Sorteo inicial: abre el reloj de EVALUACION.',
    );

    $plazoEvaluacion = Plazo::where('expediente_id', $expediente->id)
        ->where('tipo_plazo', 'EVALUACION')
        ->firstOrFail();

    $actuadoObservacion = $servicio->registerActuado(
        expediente: $expediente,
        catalogoActuado: CatalogoActuado::where('codigo', 'ACT_OBSERVACION')->firstOrFail(),
        emisor: $s['tecnico'],
        descripcion: 'Observación de requisito no crítico: abre subsanación.',
    );

    subsanacionExitoAsignar($expediente, $s['tecnico'], $actuadoObservacion);

    $plazoSubsanacion = Plazo::where('expediente_id', $expediente->id)
        ->where('tipo_plazo', 'SUBSANACION')
        ->firstOrFail();

    expect($plazoSubsanacion->estado)->toBe('VIGENTE')
        ->and($plazoEvaluacion->refresh()->estado)->toBe('CERRADO')
        ->and($expediente->refresh()->estado_actual_id)->toBe($s['estados']['EN_SUBSANACION']->id);

    Sanctum::actingAs($s['tecnico'], ['*']);

    subsanacionExitoEmitirAceptacion($expediente)
        ->assertCreated()
        ->assertJsonPath('data.tipo_actuado.codigo', 'ACT_SUBSANACION_ACEPTADA')
        ->assertJsonPath('data.estado_nuevo.codigo', 'EN_EVALUACION');

    $expediente->refresh();

    expect($expediente->estado_actual_id)->toBe($s['estados']['EN_EVALUACION']->id)
        ->and($plazoSubsanacion->refresh()->estado)->toBe('CERRADO');

    $actuadoAceptacion = Actuado::where('expediente_id', $expediente->id)
        ->whereHas('tipoActuado', fn ($query) => $query->where('codigo', 'ACT_SUBSANACION_ACEPTADA'))
        ->firstOrFail();

    expect($plazoSubsanacion->actuado_cierre_id)->toBe($actuadoAceptacion->id)
        ->and($plazoSubsanacion->fuera_de_plazo)->toBeFalse();

    // Decisión B1.4: al volver a evaluación NO se reabre un reloj EVALUACION
    // (el cerrado por la observación sigue cerrado y no nace uno nuevo).
    expect(Plazo::where('expediente_id', $expediente->id)
        ->where('tipo_plazo', 'EVALUACION')
        ->where('estado', 'VIGENTE')->count())->toBe(0)
        ->and($plazoEvaluacion->refresh()->estado)->toBe('CERRADO');

    // La evaluación de admisibilidad vuelve a ser operable desde EN_EVALUACION.
    $servicio->registerActuado(
        expediente: $expediente,
        catalogoActuado: CatalogoActuado::where('codigo', 'ACT_ADMISION')->firstOrFail(),
        emisor: $s['tecnico'],
        descripcion: 'Admisión tras la subsanación aceptada.',
    );

    expect($expediente->refresh()->estado_actual_id)->toBe($s['estados']['EN_PLANIFICACION']->id);
});

it('rechaza con 403 la aceptación sin pivot de rol o sin asignación activa', function () {
    $s = subsanacionExitoSemilla($this);

    $expediente = subsanacionExitoCrearExpediente(
        $s['estados']['EN_SUBSANACION']->id,
        $s['ac022']->id,
        $s['tecnico']->id,
    );

    // La Encargada no figura en el pivote del actuado (RF-03).
    Sanctum::actingAs($s['encargada'], ['*']);
    subsanacionExitoEmitirAceptacion($expediente)->assertForbidden();

    // Técnico con rol habilitado pero sin asignación activa del expediente.
    Sanctum::actingAs($s['otroTecnico'], ['*']);
    subsanacionExitoEmitirAceptacion($expediente)->assertForbidden();

    $expediente->refresh();
    expect($expediente->estado_actual_id)->toBe($s['estados']['EN_SUBSANACION']->id)
        ->and(Actuado::where('expediente_id', $expediente->id)
            ->whereHas('tipoActuado', fn ($query) => $query->where('codigo', 'ACT_SUBSANACION_ACEPTADA'))
            ->exists())->toBeFalse();
});

it('rechaza con 422 la aceptación fuera de EN_SUBSANACION (D-6g)', function () {
    $s = subsanacionExitoSemilla($this);

    $expediente = subsanacionExitoCrearExpediente(
        $s['estados']['EN_EVALUACION']->id,
        $s['ac022']->id,
        $s['tecnico']->id,
    );

    $disparador = subsanacionExitoActuadoBase($expediente, $s['tecnico'], $s['estados']['EN_EVALUACION']->id);
    subsanacionExitoAsignar($expediente, $s['tecnico'], $disparador);

    Sanctum::actingAs($s['tecnico'], ['*']);

    subsanacionExitoEmitirAceptacion($expediente)
        ->assertStatus(422)
        ->assertJsonValidationErrors('estado_origen_id');

    expect($expediente->refresh()->estado_actual_id)->toBe($s['estados']['EN_EVALUACION']->id);
});

it('rechaza con 422 la aceptación cuando la fecha límite del plazo ya superó (vencimiento independiente del CRON)', function () {
    $s = subsanacionExitoSemilla($this);

    $expediente = subsanacionExitoCrearExpediente(
        $s['estados']['EN_SUBSANACION']->id,
        $s['ac022']->id,
        $s['tecnico']->id,
    );

    $disparador = subsanacionExitoActuadoBase($expediente, $s['tecnico'], $s['estados']['EN_SUBSANACION']->id);
    subsanacionExitoAsignar($expediente, $s['tecnico'], $disparador);

    // Vencido por calendario pero el CRON todavía no corrió: sigue VIGENTE.
    $plazo = subsanacionExitoPlazo($expediente, $disparador, now()->subDay()->toDateString());

    Sanctum::actingAs($s['tecnico'], ['*']);

    subsanacionExitoEmitirAceptacion($expediente)
        ->assertStatus(422)
        ->assertJsonValidationErrors('plazo');

    $expediente->refresh();
    expect($expediente->estado_actual_id)->toBe($s['estados']['EN_SUBSANACION']->id)
        ->and($plazo->refresh()->estado)->toBe('VIGENTE')
        ->and(Actuado::where('expediente_id', $expediente->id)
            ->whereHas('tipoActuado', fn ($query) => $query->where('codigo', 'ACT_SUBSANACION_ACEPTADA'))
            ->exists())->toBeFalse();
});

it('rechaza con 422 la aceptación cuando el plazo de subsanación ya está VENCIDO', function () {
    $s = subsanacionExitoSemilla($this);

    $expediente = subsanacionExitoCrearExpediente(
        $s['estados']['EN_SUBSANACION']->id,
        $s['ac022']->id,
        $s['tecnico']->id,
    );

    $disparador = subsanacionExitoActuadoBase($expediente, $s['tecnico'], $s['estados']['EN_SUBSANACION']->id);
    subsanacionExitoAsignar($expediente, $s['tecnico'], $disparador);

    // Marca VENCIDO estampada por una corrida previa del CRON.
    $plazo = subsanacionExitoPlazo(
        $expediente,
        $disparador,
        now()->addDays(2)->toDateString(),
        'VENCIDO',
    );

    Sanctum::actingAs($s['tecnico'], ['*']);

    subsanacionExitoEmitirAceptacion($expediente)
        ->assertStatus(422)
        ->assertJsonValidationErrors('plazo');

    expect($expediente->refresh()->estado_actual_id)->toBe($s['estados']['EN_SUBSANACION']->id)
        ->and($plazo->refresh()->estado)->toBe('VENCIDO');
});

it('permite la aceptación cuando no existe plazo de subsanación parametrizado', function () {
    $s = subsanacionExitoSemilla($this);

    $expediente = subsanacionExitoCrearExpediente(
        $s['estados']['EN_SUBSANACION']->id,
        $s['ac022']->id,
        $s['tecnico']->id,
    );

    $disparador = subsanacionExitoActuadoBase($expediente, $s['tecnico'], $s['estados']['EN_SUBSANACION']->id);
    subsanacionExitoAsignar($expediente, $s['tecnico'], $disparador);

    Sanctum::actingAs($s['tecnico'], ['*']);

    subsanacionExitoEmitirAceptacion($expediente)
        ->assertCreated()
        ->assertJsonPath('data.estado_nuevo.codigo', 'EN_EVALUACION');

    expect($expediente->refresh()->estado_actual_id)->toBe($s['estados']['EN_EVALUACION']->id)
        ->and($expediente->plazos()->where('tipo_plazo', 'SUBSANACION')->count())->toBe(0);
});

it('no archiva por abandono un plazo de subsanación de un expediente fuera de EN_SUBSANACION (AUD-0031)', function () {
    $s = subsanacionExitoSemilla($this);

    $expediente = subsanacionExitoCrearExpediente(
        $s['estados']['EN_PLANIFICACION']->id,
        $s['ac022']->id,
        $s['tecnico']->id,
    );

    $disparador = subsanacionExitoActuadoBase($expediente, $s['tecnico'], $s['estados']['EN_PLANIFICACION']->id);
    $plazo = subsanacionExitoPlazo($expediente, $disparador, now()->subDay()->toDateString());

    $archivados = app(ArchivoPorAbandonoService::class)->archivarVencidos();

    expect($archivados)->toBe(0)
        ->and($plazo->refresh()->estado)->toBe('VIGENTE')
        ->and($expediente->refresh()->estado_actual_id)->toBe($s['estados']['EN_PLANIFICACION']->id)
        ->and(Actuado::where('expediente_id', $expediente->id)
            ->whereHas('tipoActuado', fn ($query) => $query->where('codigo', 'ACT_ARCHIVO_POR_ABANDONO'))
            ->exists())->toBeFalse();
});

it('archiva solo los expedientes en EN_SUBSANACION en una corrida mixta (AUD-0031)', function () {
    $s = subsanacionExitoSemilla($this);

    $huerfano = subsanacionExitoCrearExpediente(
        $s['estados']['EN_PLANIFICACION']->id,
        $s['ac022']->id,
        $s['tecnico']->id,
    );
    $disparadorHuerfano = subsanacionExitoActuadoBase($huerfano, $s['tecnico'], $s['estados']['EN_PLANIFICACION']->id);
    $plazoHuerfano = subsanacionExitoPlazo($huerfano, $disparadorHuerfano, now()->subDay()->toDateString());

    $valido = subsanacionExitoCrearExpediente(
        $s['estados']['EN_SUBSANACION']->id,
        $s['ac022']->id,
        $s['tecnico']->id,
    );
    $disparadorValido = subsanacionExitoActuadoBase($valido, $s['tecnico'], $s['estados']['EN_SUBSANACION']->id);
    subsanacionExitoAsignar($valido, $s['tecnico'], $disparadorValido);
    $plazoValido = subsanacionExitoPlazo($valido, $disparadorValido, now()->subDay()->toDateString());

    $archivados = app(ArchivoPorAbandonoService::class)->archivarVencidos();

    expect($archivados)->toBe(1)
        ->and($plazoValido->refresh()->estado)->toBe('VENCIDO')
        ->and($valido->refresh()->estado_actual_id)->toBe($s['estados']['ARCHIVO_POR_ABANDONO']->id)
        ->and($plazoHuerfano->refresh()->estado)->toBe('VIGENTE')
        ->and($huerfano->refresh()->estado_actual_id)->toBe($s['estados']['EN_PLANIFICACION']->id);
});

it('un fallo en un expediente no cancela el archivo de los demás (try/catch por expediente)', function () {
    $s = subsanacionExitoSemilla($this);

    $expFallido = subsanacionExitoCrearExpediente(
        $s['estados']['EN_SUBSANACION']->id,
        $s['ac022']->id,
        $s['tecnico']->id,
    );
    $disparadorFallido = subsanacionExitoActuadoBase($expFallido, $s['tecnico'], $s['estados']['EN_SUBSANACION']->id);
    $plazoFallido = subsanacionExitoPlazo($expFallido, $disparadorFallido, now()->subDay()->toDateString());

    $expOk = subsanacionExitoCrearExpediente(
        $s['estados']['EN_SUBSANACION']->id,
        $s['ac022']->id,
        $s['tecnico']->id,
    );
    $disparadorOk = subsanacionExitoActuadoBase($expOk, $s['tecnico'], $s['estados']['EN_SUBSANACION']->id);
    $plazoOk = subsanacionExitoPlazo($expOk, $disparadorOk, now()->subDay()->toDateString());

    Log::spy();

    $this->mock(ActuadoService::class, function ($mock) use ($expFallido) {
        $mock->shouldReceive('registerActuado')->andReturnUsing(
            function (Expediente $expediente) use ($expFallido) {
                if ($expediente->id === $expFallido->id) {
                    throw new RuntimeException('Fallo simulado en un expediente de la corrida (B1.4).');
                }

                return Mockery::mock(Actuado::class);
            }
        );
    });

    $archivados = app(ArchivoPorAbandonoService::class)->archivarVencidos();

    expect($archivados)->toBe(1);
    Log::shouldHaveReceived('error')->atLeast()->once();
});
