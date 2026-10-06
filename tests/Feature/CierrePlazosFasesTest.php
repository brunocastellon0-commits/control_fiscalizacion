<?php

use App\Models\Actuado;
use App\Models\CatalogoActuado;
use App\Models\CatalogoEstado;
use App\Models\Expediente;
use App\Models\ParametroPlazo;
use App\Models\Plazo;
use App\Models\Reglamento;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\ActuadoService;
use App\Services\CierreExpedienteService;
use App\Services\DescargoFinancieroService;
use App\Services\MarcarPlazosVencidosService;
use App\Services\SemaforoPlazoService;
use Database\Seeders\CatalogoActuadoSeeder;
use Database\Seeders\CatalogoEstadoSeeder;
use Database\Seeders\FeriadoSeeder;
use Database\Seeders\ParametroPlazoSeeder;
use Database\Seeders\ReglamentoSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * B1.3 — Cierre formal de plazos al transicionar entre fases (AUD-0030).
 * Semilla con los catálogos reales del proyecto y operadores por rol.
 *
 * @return array{encargada: Usuario, tecnico: Usuario, auditor: Usuario, auditorFinanciero: Usuario, ac022: Reglamento, ac055: Reglamento, estados: array<string, CatalogoEstado>}
 */
function cierrePlazoSemilla(TestCase $test): array
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
        'PENDIENTE_VISTO_BUENO',
        'EN_EJECUCION',
        'PENDIENTE_APROBACION_AMPLIACION',
        'PENDIENTE_VISTO_BUENO_FINAL',
        'LISTO_PARA_REPARTO',
        'CONCLUIDO_REMITIDO',
    ] as $codigo) {
        $estados[$codigo] = CatalogoEstado::where('codigo', $codigo)->firstOrFail();
    }

    $rolEncargada = Rol::where('codigo', Rol::CODIGO_ENCARGADA)->firstOrFail();
    $rolTecnico = Rol::where('codigo', Rol::CODIGO_TECNICO)->firstOrFail();
    $rolJuridico = Rol::where('codigo', Rol::CODIGO_AUD_JURIDICO)->firstOrFail();
    $rolFinanciero = Rol::where('codigo', Rol::CODIGO_AUD_FINANCIERO)->firstOrFail();

    return [
        'encargada' => Usuario::factory()->create(['rol_id' => $rolEncargada->id, 'activo' => true]),
        'tecnico' => Usuario::factory()->create(['rol_id' => $rolTecnico->id, 'activo' => true]),
        'auditor' => Usuario::factory()->create(['rol_id' => $rolJuridico->id, 'activo' => true]),
        'auditorFinanciero' => Usuario::factory()->create(['rol_id' => $rolFinanciero->id, 'activo' => true]),
        'ac022' => Reglamento::where('codigo', 'AC_022_2018')->firstOrFail(),
        'ac055' => Reglamento::where('codigo', 'AC_055_2018')->firstOrFail(),
        'estados' => $estados,
    ];
}

function cierrePlazoCrearExpediente(int $estadoId, int $reglamentoId, int $creadorId): Expediente
{
    return Expediente::create([
        'nurej_code' => 'EXP-CIERRE-'.fake()->unique()->numberBetween(10000, 99999),
        'via' => 'TECNICO',
        'reglamento_id' => $reglamentoId,
        'estado_actual_id' => $estadoId,
        'fecha_ingreso' => now(),
        'creado_por' => $creadorId,
    ]);
}

/**
 * Actuado base (append-only) para satisfacer la FK NOT NULL de
 * `plazos.actuado_disparador_id` cuando un reloj se crea manualmente.
 */
function cierrePlazoActuadoBase(Expediente $expediente, Usuario $usuario): Actuado
{
    $catalogo = CatalogoActuado::where('codigo', 'ACT_SORTEO_INICIAL')->firstOrFail();

    return Actuado::create([
        'expediente_id' => $expediente->id,
        'catalogo_actuado_id' => $catalogo->id,
        'usuario_id' => $usuario->id,
        'estado_nuevo_id' => $expediente->estado_actual_id,
        'contenido' => ['descripcion' => 'Actuado base de la semilla B1.3'],
    ])->refresh();
}

function cierrePlazoManual(
    Expediente $expediente,
    int $disparadorId,
    string $tipoPlazo,
    string $estado,
    string $fechaLimite,
): Plazo {
    return Plazo::create([
        'expediente_id' => $expediente->id,
        'tipo_plazo' => $tipoPlazo,
        'dias_habiles_otorgados' => 5,
        'fecha_inicio' => now(),
        'fecha_limite' => $fechaLimite,
        'estado' => $estado,
        'actuado_disparador_id' => $disparadorId,
    ]);
}

it('cierra el reloj de EVALUACION al admitir y deja el de PLANIFICACION vigente', function () {
    $semilla = cierrePlazoSemilla($this);
    $servicio = app(ActuadoService::class);

    $expediente = cierrePlazoCrearExpediente(
        $semilla['estados']['PENDIENTE_SORTEO']->id,
        $semilla['ac022']->id,
        $semilla['encargada']->id,
    );

    $servicio->registerActuado(
        expediente: $expediente,
        catalogoActuado: CatalogoActuado::where('codigo', 'ACT_SORTEO_INICIAL')->firstOrFail(),
        emisor: $semilla['encargada'],
        descripcion: 'Sorteo inicial: abre el reloj de EVALUACION.',
    );

    $plazoEvaluacion = Plazo::where('expediente_id', $expediente->id)
        ->where('tipo_plazo', 'EVALUACION')
        ->firstOrFail();
    expect($plazoEvaluacion->estado)->toBe('VIGENTE');

    $actuadoAdmision = $servicio->registerActuado(
        expediente: $expediente,
        catalogoActuado: CatalogoActuado::where('codigo', 'ACT_ADMISION')->firstOrFail(),
        emisor: $semilla['auditor'],
        descripcion: 'Admisión tras revisar requisitos.',
    );

    $plazoEvaluacion->refresh();
    expect($plazoEvaluacion->estado)->toBe('CERRADO')
        ->and($plazoEvaluacion->actuado_cierre_id)->toBe($actuadoAdmision->id)
        ->and($plazoEvaluacion->fuera_de_plazo)->toBeFalse();

    expect(Plazo::where('expediente_id', $expediente->id)
        ->where('tipo_plazo', 'EVALUACION')
        ->where('estado', 'VIGENTE')->count())->toBe(0);

    $plazoPlanificacion = Plazo::where('expediente_id', $expediente->id)
        ->where('tipo_plazo', 'PLANIFICACION')
        ->firstOrFail();
    expect($plazoPlanificacion->estado)->toBe('VIGENTE')
        ->and($plazoPlanificacion->actuado_disparador_id)->toBe($actuadoAdmision->id);

    // El semáforo no penaliza un reloj cerrado: se retorna sin cálculo.
    $color = app(SemaforoPlazoService::class)->evaluarPlazo($plazoEvaluacion);
    expect($color['codigo_color'])->toBe('CERRADO');
});

it('cierra el reloj de EVALUACION al observar y abre el de SUBSANACION', function () {
    $semilla = cierrePlazoSemilla($this);
    $servicio = app(ActuadoService::class);

    $expediente = cierrePlazoCrearExpediente(
        $semilla['estados']['PENDIENTE_SORTEO']->id,
        $semilla['ac022']->id,
        $semilla['encargada']->id,
    );

    $servicio->registerActuado(
        expediente: $expediente,
        catalogoActuado: CatalogoActuado::where('codigo', 'ACT_SORTEO_INICIAL')->firstOrFail(),
        emisor: $semilla['encargada'],
        descripcion: 'Sorteo inicial.',
    );

    $actuadoObservacion = $servicio->registerActuado(
        expediente: $expediente,
        catalogoActuado: CatalogoActuado::where('codigo', 'ACT_OBSERVACION')->firstOrFail(),
        emisor: $semilla['auditor'],
        descripcion: 'Observación de requisitos no críticos.',
    );

    $plazoEvaluacion = Plazo::where('expediente_id', $expediente->id)
        ->where('tipo_plazo', 'EVALUACION')
        ->firstOrFail();
    $plazoSubsanacion = Plazo::where('expediente_id', $expediente->id)
        ->where('tipo_plazo', 'SUBSANACION')
        ->firstOrFail();

    expect($plazoEvaluacion->estado)->toBe('CERRADO')
        ->and($plazoEvaluacion->actuado_cierre_id)->toBe($actuadoObservacion->id)
        ->and($plazoSubsanacion->estado)->toBe('VIGENTE')
        ->and($plazoSubsanacion->dias_habiles_otorgados)->toBe(3);

    $expediente->refresh();
    expect($expediente->estado_actual_id)->toBe($semilla['estados']['EN_SUBSANACION']->id);
});

it('cierra los relojes EJECUCION y EJECUCION_AMPLIADA al emitir el informe final', function () {
    $semilla = cierrePlazoSemilla($this);
    $servicio = app(ActuadoService::class);

    $expediente = cierrePlazoCrearExpediente(
        $semilla['estados']['PENDIENTE_VISTO_BUENO']->id,
        $semilla['ac022']->id,
        $semilla['encargada']->id,
    );

    $actuadoVistoBueno = $servicio->registerActuado(
        expediente: $expediente,
        catalogoActuado: CatalogoActuado::where('codigo', 'ACT_VISTO_BUENO_PLANIFICACION')->firstOrFail(),
        emisor: $semilla['encargada'],
        descripcion: 'Visto bueno al cronograma: inicia la investigación.',
    );

    $plazoEjecucion = Plazo::where('expediente_id', $expediente->id)
        ->where('tipo_plazo', 'EJECUCION')
        ->firstOrFail();
    expect($plazoEjecucion->estado)->toBe('VIGENTE');

    // Reloj de una ampliación aprobada previamente (semilla manual).
    $parametroAmpliada = ParametroPlazo::where('reglamento_id', $semilla['ac022']->id)
        ->where('tipo_plazo', 'EJECUCION_AMPLIADA')
        ->firstOrFail();
    $plazoAmpliada = Plazo::create([
        'expediente_id' => $expediente->id,
        'tipo_plazo' => 'EJECUCION_AMPLIADA',
        'parametro_plazo_id' => $parametroAmpliada->id,
        'dias_habiles_otorgados' => 5,
        'fecha_inicio' => now(),
        'fecha_limite' => now()->addDays(7)->toDateString(),
        'estado' => 'VIGENTE',
        'actuado_disparador_id' => $actuadoVistoBueno->id,
    ]);

    $actuadoInforme = $servicio->registerActuado(
        expediente: $expediente,
        catalogoActuado: CatalogoActuado::where('codigo', 'ACT_INFORME_TECNICO_CON_RESPONSABILIDAD')->firstOrFail(),
        emisor: $semilla['tecnico'],
        descripcion: 'Informe final técnico con responsabilidad.',
        adjunto: UploadedFile::fake()->create('informe-tecnico.pdf', 100, 'application/pdf'),
    );

    $plazoEjecucion->refresh();
    $plazoAmpliada->refresh();

    expect($plazoEjecucion->estado)->toBe('CERRADO')
        ->and($plazoEjecucion->actuado_cierre_id)->toBe($actuadoInforme->id)
        ->and($plazoAmpliada->estado)->toBe('CERRADO')
        ->and($plazoAmpliada->actuado_cierre_id)->toBe($actuadoInforme->id);

    expect(Plazo::where('expediente_id', $expediente->id)
        ->whereIn('tipo_plazo', ['EJECUCION', 'EJECUCION_AMPLIADA'])
        ->where('estado', 'VIGENTE')->count())->toBe(0);

    $expediente->refresh();
    expect($expediente->estado_actual_id)->toBe($semilla['estados']['PENDIENTE_VISTO_BUENO_FINAL']->id);
});

it('cierra el reloj vigente en el Visto Bueno Final y respeta los SUSPENDIDOS (CierreExpedienteService)', function () {
    $semilla = cierrePlazoSemilla($this);

    $expediente = cierrePlazoCrearExpediente(
        $semilla['estados']['PENDIENTE_VISTO_BUENO_FINAL']->id,
        $semilla['ac022']->id,
        $semilla['encargada']->id,
    );

    $disparador = cierrePlazoActuadoBase($expediente, $semilla['encargada']);

    // Residual que la fase no debió dejar: VIGENTE → debe cerrar.
    $plazoVigente = cierrePlazoManual(
        $expediente,
        $disparador->id,
        'EJECUCION',
        'VIGENTE',
        now()->addDays(3)->toDateString(),
    );
    // Reloj congelado por transparencia/descargos: NO debe tocarse.
    $plazoSuspendido = cierrePlazoManual(
        $expediente,
        $disparador->id,
        'EJECUCION_AMPLIADA',
        'SUSPENDIDO',
        now()->addDays(2)->toDateString(),
    );

    $cierre = app(CierreExpedienteService::class);

    $actuadoVistoBueno = $cierre->aprobarVistoBueno(
        $expediente,
        $semilla['encargada'],
        'Visto bueno final del informe aprobado.',
    );

    $plazoVigente->refresh();
    $plazoSuspendido->refresh();

    expect($plazoVigente->estado)->toBe('CERRADO')
        ->and($plazoVigente->actuado_cierre_id)->toBe($actuadoVistoBueno->id)
        ->and($plazoSuspendido->estado)->toBe('SUSPENDIDO')
        ->and($plazoSuspendido->actuado_cierre_id)->toBeNull();

    // En HTTP cada request reconsulta el expediente; aquí se refresca la
    // instancia para que la validación de estado del reparto vea el cambio.
    $expediente->refresh();

    $cierre->ejecutarReparto(
        $expediente,
        $semilla['encargada'],
        'Juzgado Disciplinario',
        'Remisión conforme RN-09.',
    );

    expect(Plazo::where('expediente_id', $expediente->id)
        ->whereIn('tipo_plazo', ['EJECUCION', 'EJECUCION_AMPLIADA'])
        ->where('estado', 'VIGENTE')->count())->toBe(0);

    $expediente->refresh();
    expect($expediente->estado_actual_id)->toBe($semilla['estados']['CONCLUIDO_REMITIDO']->id);
});

it('no cierra el reloj de EJECUCION al solicitar ampliación (la fase sigue viva)', function () {
    $semilla = cierrePlazoSemilla($this);
    $servicio = app(ActuadoService::class);

    $expediente = cierrePlazoCrearExpediente(
        $semilla['estados']['EN_EJECUCION']->id,
        $semilla['ac022']->id,
        $semilla['tecnico']->id,
    );

    $disparador = cierrePlazoActuadoBase($expediente, $semilla['tecnico']);
    $plazoEjecucion = cierrePlazoManual(
        $expediente,
        $disparador->id,
        'EJECUCION',
        'VIGENTE',
        now()->addDays(5)->toDateString(),
    );

    $servicio->registerActuado(
        expediente: $expediente,
        catalogoActuado: CatalogoActuado::where('codigo', 'ACT_SOLICITAR_AMPLIACION')->firstOrFail(),
        emisor: $semilla['tecnico'],
        descripcion: 'Solicitud de ampliación de plazo.',
    );

    $plazoEjecucion->refresh();
    expect($plazoEjecucion->estado)->toBe('VIGENTE')
        ->and($plazoEjecucion->actuado_cierre_id)->toBeNull();

    $expediente->refresh();
    expect($expediente->estado_actual_id)
        ->toBe($semilla['estados']['PENDIENTE_APROBACION_AMPLIACION']->id);
});

it('respeta la pausa y reanudación de descargos (AC055) y cierra EJECUCION recién con el informe', function () {
    $semilla = cierrePlazoSemilla($this);
    $servicio = app(ActuadoService::class);
    $descargos = app(DescargoFinancieroService::class);
    $auditor = $semilla['auditorFinanciero'];

    $expediente = cierrePlazoCrearExpediente(
        $semilla['estados']['EN_EJECUCION']->id,
        $semilla['ac055']->id,
        $auditor->id,
    );

    $disparador = cierrePlazoActuadoBase($expediente, $auditor);
    $plazoEjecucion = cierrePlazoManual(
        $expediente,
        $disparador->id,
        'EJECUCION',
        'VIGENTE',
        now()->addDays(6)->toDateString(),
    );

    $descargos->comunicarHallazgos(
        $expediente,
        $auditor,
        'Comunicación de hallazgos (AC055).',
        UploadedFile::fake()->create('oficio-hallazgos.pdf', 100, 'application/pdf'),
    );

    $plazoEjecucion->refresh();
    $plazoDescargos = Plazo::where('expediente_id', $expediente->id)
        ->where('tipo_plazo', 'DESCARGOS')
        ->firstOrFail();

    // La comunicación congela (SUSPENDIDO), no cierra.
    expect($plazoEjecucion->estado)->toBe('SUSPENDIDO')
        ->and($plazoEjecucion->actuado_cierre_id)->toBeNull()
        ->and($plazoEjecucion->fecha_pausa)->not->toBeNull()
        ->and($plazoDescargos->estado)->toBe('VIGENTE');

    $descargos->recibirDescargos(
        $expediente,
        $auditor,
        'Recepción de descargos presentados.',
        UploadedFile::fake()->create('descargos.pdf', 100, 'application/pdf'),
    );

    $plazoEjecucion->refresh();
    $plazoDescargos->refresh();
    expect($plazoEjecucion->estado)->toBe('VIGENTE')
        ->and($plazoDescargos->estado)->toBe('CUMPLIDO')
        ->and($plazoDescargos->actuado_cierre_id)->not->toBeNull();

    $actuadoInforme = $servicio->registerActuado(
        expediente: $expediente,
        catalogoActuado: CatalogoActuado::where('codigo', 'ACT_INFORME_AUDITORIA_FINANCIERA_CON_RESPONSABILIDAD')->firstOrFail(),
        emisor: $auditor,
        descripcion: 'Informe final financiero con responsabilidad.',
        adjunto: UploadedFile::fake()->create('informe-financiero.pdf', 100, 'application/pdf'),
    );

    $plazoEjecucion->refresh();
    $plazoDescargos->refresh();

    expect($plazoEjecucion->estado)->toBe('CERRADO')
        ->and($plazoEjecucion->actuado_cierre_id)->toBe($actuadoInforme->id)
        ->and($plazoDescargos->estado)->toBe('CUMPLIDO');

    expect(Plazo::where('expediente_id', $expediente->id)
        ->whereIn('tipo_plazo', ['EJECUCION', 'EJECUCION_AMPLIADA', 'DESCARGOS'])
        ->where('estado', 'VIGENTE')->count())->toBe(0);
});

it('el CRON diario no estampa fuera_de_plazo en relojes ya cerrados (impacto AUD-0030)', function () {
    $semilla = cierrePlazoSemilla($this);
    $servicio = app(ActuadoService::class);

    $expediente = cierrePlazoCrearExpediente(
        $semilla['estados']['PENDIENTE_SORTEO']->id,
        $semilla['ac022']->id,
        $semilla['encargada']->id,
    );

    $servicio->registerActuado(
        expediente: $expediente,
        catalogoActuado: CatalogoActuado::where('codigo', 'ACT_SORTEO_INICIAL')->firstOrFail(),
        emisor: $semilla['encargada'],
        descripcion: 'Sorteo inicial.',
    );
    $servicio->registerActuado(
        expediente: $expediente,
        catalogoActuado: CatalogoActuado::where('codigo', 'ACT_ADMISION')->firstOrFail(),
        emisor: $semilla['auditor'],
        descripcion: 'Admisión: cierra EVALUACION y abre PLANIFICACION.',
    );

    // Simula el paso del tiempo: ambos vencimientos ya superados.
    Plazo::where('expediente_id', $expediente->id)
        ->update(['fecha_limite' => now()->subDay()->toDateString()]);

    $marcados = app(MarcarPlazosVencidosService::class)->marcarVencidos();

    $plazoEvaluacion = Plazo::where('expediente_id', $expediente->id)
        ->where('tipo_plazo', 'EVALUACION')
        ->firstOrFail();
    $plazoPlanificacion = Plazo::where('expediente_id', $expediente->id)
        ->where('tipo_plazo', 'PLANIFICACION')
        ->firstOrFail();

    // El cerrado no se marca (ya no corre); el vigente sí (control: el CRON corrió).
    expect($plazoEvaluacion->fuera_de_plazo)->toBeFalse()
        ->and($plazoPlanificacion->fuera_de_plazo)->toBeTrue()
        ->and($marcados)->toBeGreaterThanOrEqual(1);
});
