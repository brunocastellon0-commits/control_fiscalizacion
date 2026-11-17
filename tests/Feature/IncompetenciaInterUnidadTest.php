<?php

use App\Models\Actuado;
use App\Models\Asignacion;
use App\Models\CatalogoActuado;
use App\Models\CatalogoEstado;
use App\Models\Expediente;
use App\Models\Plazo;
use App\Models\Reglamento;
use App\Models\Rol;
use App\Models\TransferenciaUnidad;
use App\Models\Unidad;
use App\Models\Usuario;
use App\Services\PlazoCalculatorService;
use App\Services\TransferenciaUnidadService;
use App\Services\TransparenciaService;
use Database\Seeders\CatalogoActuadoSeeder;
use Database\Seeders\CatalogoEstadoSeeder;
use Database\Seeders\ReglamentoSeeder;
use Database\Seeders\RolSeeder;
use Database\Seeders\UnidadSeeder;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Semilla CTR-10: catálogos reales vía seeder (incluye el alta del par
 * Solicitud/VB y la baja de A20/A21 del flujo B1.3) y usuarios por rol.
 *
 * @return array<string, mixed>
 */
function incmpSemilla(TestCase $test): array
{
    foreach ([
        RolSeeder::class,
        CatalogoEstadoSeeder::class,
        ReglamentoSeeder::class,
        UnidadSeeder::class,
        CatalogoActuadoSeeder::class,
    ] as $seeder) {
        $test->seed($seeder);
    }

    $rolTecnico = Rol::where('codigo', Rol::CODIGO_TECNICO)->firstOrFail();
    $rolEncargada = Rol::where('codigo', Rol::CODIGO_ENCARGADA)->firstOrFail();
    $rolAdmin = Rol::where('codigo', Rol::CODIGO_ADMIN)->firstOrFail();

    $tecnico = Usuario::factory()->create(['rol_id' => $rolTecnico->id, 'activo' => true]);
    $tecnicoAjeno = Usuario::factory()->create(['rol_id' => $rolTecnico->id, 'activo' => true]);
    $encargada = Usuario::factory()->create(['rol_id' => $rolEncargada->id, 'activo' => true]);
    $admin = Usuario::factory()->create(['rol_id' => $rolAdmin->id, 'activo' => true]);

    $unidadInactiva = Unidad::create([
        'codigo' => 'UNIDAD_INACTIVA_TEST',
        'nombre' => 'Unidad inactiva para validaciones',
        'activa' => false,
    ]);

    return [
        'rolTecnico' => $rolTecnico,
        'rolEncargada' => $rolEncargada,
        'tecnico' => $tecnico,
        'tecnicoAjeno' => $tecnicoAjeno,
        'encargada' => $encargada,
        'admin' => $admin,
        'ac022' => Reglamento::where('codigo', 'AC_022_2018')->firstOrFail(),
        'evaluacion' => CatalogoEstado::where('codigo', 'EN_EVALUACION')->firstOrFail(),
        'planificacion' => CatalogoEstado::where('codigo', 'EN_PLANIFICACION')->firstOrFail(),
        'remitidoUnidad' => CatalogoEstado::where('codigo', TransferenciaUnidadService::ESTADO_REMITIDO_UNIDAD_EXTERNA)->firstOrFail(),
        'unidadDestino' => Unidad::where('codigo', 'TRANSPARENCIA')->firstOrFail(),
        'unidadInactiva' => $unidadInactiva,
    ];
}

function incmpCrearExpediente(int $estadoId, int $reglamentoId, int $creadoPor): Expediente
{
    return Expediente::create([
        'nurej_code' => 'EXP-'.fake()->unique()->numberBetween(100000, 999999),
        'via' => 'TECNICO',
        'reglamento_id' => $reglamentoId,
        'estado_actual_id' => $estadoId,
        'resumen_hechos' => 'Causa destinada al flujo de incompetencia procesal (CTR-10).',
        'fecha_ingreso' => now(),
        'creado_por' => $creadoPor,
    ]);
}

function incmpAsignar(Expediente $expediente, Usuario $usuario): void
{
    $actuadoOrigen = Actuado::create([
        'expediente_id' => $expediente->id,
        'catalogo_actuado_id' => CatalogoActuado::first()->id,
        'usuario_id' => $usuario->id,
        'estado_nuevo_id' => $expediente->estado_actual_id,
        'contenido' => ['descripcion' => 'Asignación inicial de la semilla'],
    ])->refresh();

    Asignacion::create([
        'expediente_id' => $expediente->id,
        'usuario_id' => $usuario->id,
        'rol_id' => $usuario->rol_id,
        'actuado_origen_id' => $actuadoOrigen->id,
        'fecha_asignacion' => now(),
        'activa' => true,
    ]);
}

function incmpAbrirPlazo(Expediente $expediente): Plazo
{
    return Plazo::create([
        'expediente_id' => $expediente->id,
        'tipo_plazo' => 'EJECUCION',
        'dias_habiles_otorgados' => 10,
        'fecha_inicio' => now()->subDays(2),
        'fecha_limite' => now()->addWeekdays(8),
        'estado' => 'VIGENTE',
        'actuado_disparador_id' => $expediente->actuados()->latest('id')->value('id'),
    ]);
}

function incmpSolicitar(Expediente $expediente, array $payload = [])
{
    return test()->postJson("/api/expedientes/{$expediente->id}/incompetencia/solicitar", array_merge([
        'unidad_destino_id' => Unidad::where('codigo', 'TRANSPARENCIA')->firstOrFail()->id,
        'motivo' => 'Indicios de delito de corrupción que competen a otra unidad (RN-09 Fase 5).',
    ], $payload));
}

function incmpVistoBueno(Expediente $expediente, array $payload = [])
{
    return test()->postJson("/api/expedientes/{$expediente->id}/incompetencia/visto-bueno", array_merge([
        'aprobado' => true,
    ], $payload));
}

it('CTR-10 solicita incompetencia: pasa a EN_EVALUACION guardando el estado original, congela los relojes y conserva la bandeja', function () {
    $semilla = incmpSemilla($this);
    $expediente = incmpCrearExpediente($semilla['planificacion']->id, $semilla['ac022']->id, $semilla['tecnico']->id);
    incmpAsignar($expediente, $semilla['tecnico']);
    $plazo = incmpAbrirPlazo($expediente);

    Sanctum::actingAs($semilla['tecnico'], ['*']);
    $bandejaAntes = $this->getJson('/api/bandeja')->assertOk();
    expect(collect($bandejaAntes->json('data'))->pluck('id'))->toContain($expediente->id);

    incmpSolicitar($expediente)
        ->assertOk()
        ->assertJson(['estado' => 'EN_EVALUACION']);

    expect($expediente->refresh()->estado_actual_id)->toBe($semilla['evaluacion']->id);

    $solicitud = Actuado::query()
        ->where('expediente_id', $expediente->id)
        ->whereHas('tipoActuado', fn ($query) => $query->where('codigo', TransparenciaService::CODIGO_ACT_SOLICITUD))
        ->firstOrFail();

    expect($solicitud->usuario_id)->toBe($semilla['tecnico']->id)
        ->and($solicitud->estado_anterior_id)->toBe($semilla['planificacion']->id)
        ->and($solicitud->estado_nuevo_id)->toBe($semilla['evaluacion']->id)
        ->and($solicitud->contenido['unidad_destino_id'])->toBe($semilla['unidadDestino']->id)
        ->and($solicitud->contenido['plazos_suspendidos'])->toBe([$plazo->id])
        ->and($solicitud->hash_anterior)->not->toBeNull()
        ->and($solicitud->hash_actuado)->toMatch('/^[0-9a-f]{64}$/');

    $plazo->refresh();
    expect($plazo->estado)->toBe(TransferenciaUnidadService::ESTADO_PLAZO_SUSPENDIDO)
        ->and($plazo->fecha_pausa)->not->toBeNull();

    expect(TransferenciaUnidad::query()->where('expediente_id', $expediente->id)->count())->toBe(0);

    $asignacion = $expediente->asignacionActiva()->first();
    expect($asignacion)->not->toBeNull()
        ->and($asignacion->usuario_id)->toBe($semilla['tecnico']->id)
        ->and($asignacion->activa)->toBeTrue();

    $bandejaDespues = $this->getJson('/api/bandeja')->assertOk();
    expect(collect($bandejaDespues->json('data'))->pluck('id'))->toContain($expediente->id);
});

it('CTR-10 con VB aprobado congela los relojes, remite a la unidad externa con el estado previo original y saca la causa de la bandeja', function () {
    $semilla = incmpSemilla($this);
    $expediente = incmpCrearExpediente($semilla['planificacion']->id, $semilla['ac022']->id, $semilla['tecnico']->id);
    incmpAsignar($expediente, $semilla['tecnico']);
    $plazo = incmpAbrirPlazo($expediente);

    Sanctum::actingAs($semilla['tecnico'], ['*']);
    incmpSolicitar($expediente)->assertOk();

    $plazo->refresh();
    expect($plazo->estado)->toBe(TransferenciaUnidadService::ESTADO_PLAZO_SUSPENDIDO)
        ->and($plazo->fecha_pausa)->not->toBeNull();

    $solicitud = Actuado::query()
        ->where('expediente_id', $expediente->id)
        ->whereHas('tipoActuado', fn ($query) => $query->where('codigo', TransparenciaService::CODIGO_ACT_SOLICITUD))
        ->firstOrFail();

    Sanctum::actingAs($semilla['encargada'], ['*']);
    incmpVistoBueno($expediente, ['aprobado' => true, 'observaciones' => 'Visto bueno conforme con la competencia de la unidad destino.'])
        ->assertOk()
        ->assertJson(['estado' => TransferenciaUnidadService::ESTADO_REMITIDO_UNIDAD_EXTERNA]);

    expect($expediente->refresh()->estado_actual_id)->toBe($semilla['remitidoUnidad']->id);

    $transferencia = TransferenciaUnidad::query()
        ->where('expediente_id', $expediente->id)
        ->latest('id')
        ->firstOrFail();
    expect($transferencia->tipo)->toBe('REMISION')
        ->and($transferencia->unidad_id)->toBe($semilla['unidadDestino']->id)
        ->and($transferencia->estado_previo_id)->toBe($semilla['planificacion']->id)
        ->and($transferencia->actuado_id)->not->toBeNull();

    $actuadoVb = $transferencia->actuado;
    expect($actuadoVb)->not->toBeNull()
        ->and($actuadoVb->tipoActuado->codigo)->toBe(TransparenciaService::CODIGO_ACT_VB)
        ->and($actuadoVb->usuario_id)->toBe($semilla['encargada']->id)
        ->and($actuadoVb->estado_anterior_id)->toBe($semilla['evaluacion']->id)
        ->and($actuadoVb->estado_nuevo_id)->toBe($semilla['remitidoUnidad']->id)
        ->and($actuadoVb->hash_anterior)->toBe($solicitud->hash_actuado)
        ->and($actuadoVb->contenido['via'])->toBe('INCOMPETENCIA')
        ->and($actuadoVb->contenido['solicitud_actuado_id'])->toBe($solicitud->id)
        ->and($actuadoVb->contenido['aprobado'])->toBeTrue()
        ->and($actuadoVb->contenido['plazos_suspendidos'])->toBe([$plazo->id]);

    $plazo->refresh();
    expect($plazo->estado)->toBe(TransferenciaUnidadService::ESTADO_PLAZO_SUSPENDIDO)
        ->and($plazo->fecha_pausa)->not->toBeNull()
        ->and($plazo->fecha_reanudacion)->toBeNull();

    expect($expediente->asignacionActiva()->first()?->usuario_id)->toBe($semilla['tecnico']->id);

    Sanctum::actingAs($semilla['tecnico'], ['*']);
    $bandeja = $this->getJson('/api/bandeja')->assertOk();
    expect(collect($bandeja->json('data'))->pluck('id'))->not->toContain($expediente->id);
});

it('CTR-05 tras la incompetencia restaura el estado original pre-solicitud, reanuda los relojes y devuelve la causa a la bandeja', function () {
    $semilla = incmpSemilla($this);
    $expediente = incmpCrearExpediente($semilla['planificacion']->id, $semilla['ac022']->id, $semilla['tecnico']->id);
    incmpAsignar($expediente, $semilla['tecnico']);
    $plazo = incmpAbrirPlazo($expediente);

    Sanctum::actingAs($semilla['tecnico'], ['*']);
    incmpSolicitar($expediente)->assertOk();

    Sanctum::actingAs($semilla['encargada'], ['*']);
    incmpVistoBueno($expediente, ['aprobado' => true])->assertOk();

    $plazo->refresh();
    $calculadora = app(PlazoCalculatorService::class);
    $diasRestantes = $calculadora->businessDaysBetween($plazo->fecha_pausa, $plazo->fecha_limite);
    $fechaLimiteEsperada = $calculadora->calculateDueDate(now(), $diasRestantes);

    $this->postJson("/api/expedientes/{$expediente->id}/registrar-recepcion", [
        'unidad_origen_id' => $semilla['unidadDestino']->id,
        'motivo' => 'Devolución desde la unidad externa por falta de competencia (CTR-05).',
    ])
        ->assertOk()
        ->assertJson(['estado' => 'EN_PLANIFICACION']);

    expect($expediente->refresh()->estado_actual_id)->toBe($semilla['planificacion']->id);

    $recepcion = TransferenciaUnidad::query()
        ->where('expediente_id', $expediente->id)
        ->latest('id')
        ->firstOrFail();
    expect($recepcion->tipo)->toBe('RECEPCION');

    $actuadoRecepcion = $recepcion->actuado;
    expect($actuadoRecepcion)->not->toBeNull()
        ->and($actuadoRecepcion->contenido['plazos_reanudados'])->toBe([$plazo->id]);

    $plazo->refresh();
    expect($plazo->estado)->toBe(TransferenciaUnidadService::ESTADO_PLAZO_VIGENTE)
        ->and($plazo->fecha_reanudacion)->not->toBeNull()
        ->and($plazo->fecha_pausa)->not->toBeNull()
        ->and($plazo->fecha_limite->format('Y-m-d'))->toBe($fechaLimiteEsperada->format('Y-m-d'));

    Sanctum::actingAs($semilla['tecnico'], ['*']);
    $bandeja = $this->getJson('/api/bandeja')->assertOk();
    expect(collect($bandeja->json('data'))->pluck('id'))->toContain($expediente->id);
});

it('CTR-10 con VB rechazado restaura el estado original, reanuda exactamente los relojes congelados y permite re-solicitar', function () {
    $semilla = incmpSemilla($this);
    $expediente = incmpCrearExpediente($semilla['planificacion']->id, $semilla['ac022']->id, $semilla['tecnico']->id);
    incmpAsignar($expediente, $semilla['tecnico']);
    $plazo = incmpAbrirPlazo($expediente);

    Sanctum::actingAs($semilla['tecnico'], ['*']);
    incmpSolicitar($expediente)->assertOk();

    $plazo->refresh();
    expect($plazo->estado)->toBe(TransferenciaUnidadService::ESTADO_PLAZO_SUSPENDIDO)
        ->and($plazo->fecha_pausa)->not->toBeNull();

    $calculadora = app(PlazoCalculatorService::class);
    $diasRestantes = $calculadora->businessDaysBetween($plazo->fecha_pausa, $plazo->fecha_limite);
    $fechaLimiteEsperada = $calculadora->calculateDueDate(now(), $diasRestantes);

    Sanctum::actingAs($semilla['encargada'], ['*']);
    incmpVistoBueno($expediente, [
        'aprobado' => false,
        'observaciones' => 'No se observa incompetencia; continúe la sustanciación ordinaria.',
    ])
        ->assertOk()
        ->assertJson(['estado' => 'EN_PLANIFICACION']);

    expect($expediente->refresh()->estado_actual_id)->toBe($semilla['planificacion']->id);

    $vb = Actuado::query()
        ->where('expediente_id', $expediente->id)
        ->whereHas('tipoActuado', fn ($query) => $query->where('codigo', TransparenciaService::CODIGO_ACT_VB))
        ->firstOrFail();
    expect($vb->estado_anterior_id)->toBe($semilla['evaluacion']->id)
        ->and($vb->estado_nuevo_id)->toBe($semilla['planificacion']->id)
        ->and($vb->contenido['aprobado'])->toBeFalse();

    expect(TransferenciaUnidad::query()->where('expediente_id', $expediente->id)->count())->toBe(0);

    $plazo->refresh();
    expect($plazo->estado)->toBe(TransferenciaUnidadService::ESTADO_PLAZO_VIGENTE)
        ->and($plazo->fecha_reanudacion)->not->toBeNull()
        ->and($plazo->fecha_pausa)->not->toBeNull()
        ->and($plazo->fecha_limite->format('Y-m-d'))->toBe($fechaLimiteEsperada->format('Y-m-d'));

    $pausaPrevia = $plazo->fecha_pausa;

    Sanctum::actingAs($semilla['tecnico'], ['*']);
    incmpSolicitar($expediente)->assertOk()->assertJson(['estado' => 'EN_EVALUACION']);

    $plazo->refresh();
    expect($plazo->estado)->toBe(TransferenciaUnidadService::ESTADO_PLAZO_SUSPENDIDO)
        ->and($plazo->fecha_pausa->greaterThanOrEqualTo($pausaPrevia))->toBeTrue();
});

it('CTR-04 responde 422 mientras la solicitud de incompetencia siga abierta y vuelve a ser remitible tras el VB', function () {
    $semilla = incmpSemilla($this);
    $expediente = incmpCrearExpediente($semilla['planificacion']->id, $semilla['ac022']->id, $semilla['tecnico']->id);
    incmpAsignar($expediente, $semilla['tecnico']);
    $plazo = incmpAbrirPlazo($expediente);

    Sanctum::actingAs($semilla['tecnico'], ['*']);
    incmpSolicitar($expediente)->assertOk();

    Sanctum::actingAs($semilla['encargada'], ['*']);
    $this->postJson("/api/expedientes/{$expediente->id}/remitir-unidad", [
        'unidad_id' => $semilla['unidadDestino']->id,
        'motivo' => 'Remisión directa mientras la solicitud de incompetencia aguarda visto bueno.',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('expediente');

    expect(TransferenciaUnidad::query()->where('expediente_id', $expediente->id)->count())->toBe(0);

    $plazo->refresh();
    expect($plazo->estado)->toBe(TransferenciaUnidadService::ESTADO_PLAZO_SUSPENDIDO);

    incmpVistoBueno($expediente, ['aprobado' => false])->assertOk();

    $this->postJson("/api/expedientes/{$expediente->id}/remitir-unidad", [
        'unidad_id' => $semilla['unidadDestino']->id,
        'motivo' => 'Remisión directa tras resolverse la solicitud de incompetencia por rechazo.',
    ])->assertCreated();

    expect($expediente->refresh()->estado_actual_id)->toBe($semilla['remitidoUnidad']->id);
});

it('CTR-10 devuelve 403 según rol y estado: solicitar es del operador asignado y el VB solo de la Encargada', function () {
    $semilla = incmpSemilla($this);
    $expediente = incmpCrearExpediente($semilla['evaluacion']->id, $semilla['ac022']->id, $semilla['tecnico']->id);
    incmpAsignar($expediente, $semilla['tecnico']);

    // La Encargada no solicita y el operador ajeno no tiene la bandeja.
    Sanctum::actingAs($semilla['encargada'], ['*']);
    incmpSolicitar($expediente)->assertForbidden();

    Sanctum::actingAs($semilla['admin'], ['*']);
    incmpSolicitar($expediente)->assertForbidden();

    Sanctum::actingAs($semilla['tecnicoAjeno'], ['*']);
    incmpSolicitar($expediente)->assertForbidden();

    // Ningún operativo ni el ADMIN resuelven el visto bueno.
    Sanctum::actingAs($semilla['tecnico'], ['*']);
    incmpVistoBueno($expediente, ['aprobado' => true])->assertForbidden();

    Sanctum::actingAs($semilla['admin'], ['*']);
    incmpVistoBueno($expediente, ['aprobado' => true])->assertForbidden();

    // Causa en estado final: fuera de la solicitud.
    $estadoFinal = CatalogoEstado::factory()->create(['codigo' => 'ESTADO_FINAL_CTR10_TEST', 'es_final' => true]);
    $expedienteFinal = incmpCrearExpediente($estadoFinal->id, $semilla['ac022']->id, $semilla['tecnico']->id);
    incmpAsignar($expedienteFinal, $semilla['tecnico']);

    Sanctum::actingAs($semilla['tecnico'], ['*']);
    incmpSolicitar($expedienteFinal)->assertForbidden();

    expect($expediente->refresh()->estado_actual_id)->toBe($semilla['evaluacion']->id);
});

it('CTR-10 no admite re-solicitar con una solicitud abierta, el VB exige solicitud y valida unidades/motivo/observaciones (422)', function () {
    $semilla = incmpSemilla($this);
    $expediente = incmpCrearExpediente($semilla['evaluacion']->id, $semilla['ac022']->id, $semilla['tecnico']->id);
    incmpAsignar($expediente, $semilla['tecnico']);

    Sanctum::actingAs($semilla['tecnico'], ['*']);

    incmpSolicitar($expediente, ['unidad_destino_id' => 999999])->assertUnprocessable()
        ->assertJsonValidationErrors('unidad_destino_id');
    incmpSolicitar($expediente, ['unidad_destino_id' => $semilla['unidadInactiva']->id])->assertUnprocessable()
        ->assertJsonValidationErrors('unidad_destino_id');
    incmpSolicitar($expediente, ['motivo' => 'corto'])->assertUnprocessable()
        ->assertJsonValidationErrors('motivo');

    incmpSolicitar($expediente)->assertOk();
    incmpSolicitar($expediente)->assertUnprocessable()
        ->assertJsonValidationErrors('expediente');

    Sanctum::actingAs($semilla['encargada'], ['*']);
    incmpVistoBueno($expediente, ['aprobado' => true, 'observaciones' => 'corto'])->assertUnprocessable()
        ->assertJsonValidationErrors('observaciones');

    // Visto bueno sin ninguna solicitud abierta: 422 de negocio.
    $expedienteSinSolicitud = incmpCrearExpediente($semilla['evaluacion']->id, $semilla['ac022']->id, $semilla['tecnico']->id);
    incmpAsignar($expedienteSinSolicitud, $semilla['tecnico']);
    incmpVistoBueno($expedienteSinSolicitud, ['aprobado' => true])->assertUnprocessable()
        ->assertJsonValidationErrors('aprobado');

    expect($expediente->refresh()->estado_actual_id)->toBe($semilla['evaluacion']->id);
});

it('CTR-10 exige autenticación en ambos endpoints', function () {
    $semilla = incmpSemilla($this);
    $expediente = incmpCrearExpediente($semilla['evaluacion']->id, $semilla['ac022']->id, $semilla['tecnico']->id);
    incmpAsignar($expediente, $semilla['tecnico']);

    $this->postJson("/api/expedientes/{$expediente->id}/incompetencia/solicitar", [
        'unidad_destino_id' => $semilla['unidadDestino']->id,
        'motivo' => 'Intento de solicitud sin sesión autenticada para el flujo CTR-10.',
    ])->assertUnauthorized();

    $this->postJson("/api/expedientes/{$expediente->id}/incompetencia/visto-bueno", [
        'aprobado' => true,
    ])->assertUnauthorized();
});

it('el seeder da de baja los tipos del flujo B1.3 y registra el par CTR-10 con sus pivotes', function () {
    $semilla = incmpSemilla($this);

    expect(CatalogoActuado::where('codigo', 'ACT_DERIVACION_INCOMPETENCIA')->exists())->toBeFalse()
        ->and(CatalogoActuado::where('codigo', 'ACT_REMISION_TRANSPARENCIA')->exists())->toBeFalse();

    $solicitud = CatalogoActuado::where('codigo', TransparenciaService::CODIGO_ACT_SOLICITUD)->firstOrFail();
    expect($solicitud->estado_destino_id)->toBeNull()
        ->and($solicitud->requiere_adjunto)->toBeFalse();

    $vb = CatalogoActuado::where('codigo', TransparenciaService::CODIGO_ACT_VB)->firstOrFail();
    expect($vb->estado_destino_id)->toBeNull()
        ->and($vb->requiere_adjunto)->toBeFalse();

    $pivotesSolicitud = DB::table('catalogo_actuado_roles')
        ->where('catalogo_actuado_id', $solicitud->id)
        ->get();
    expect($pivotesSolicitud->count())->toBe(3)
        ->and($pivotesSolicitud->pluck('reglamento_id')->filter()->count())->toBe(0);

    $pivotesVb = DB::table('catalogo_actuado_roles')
        ->where('catalogo_actuado_id', $vb->id)
        ->get();
    expect($pivotesVb->count())->toBe(1)
        ->and($pivotesVb->first()->rol_id)->toBe($semilla['rolEncargada']->id)
        ->and($pivotesVb->first()->reglamento_id)->toBeNull();
});
