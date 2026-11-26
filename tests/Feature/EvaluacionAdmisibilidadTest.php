<?php

use App\Models\Actuado;
use App\Models\Adjunto;
use App\Models\Asignacion;
use App\Models\CatalogoActuado;
use App\Models\CatalogoEstado;
use App\Models\CatalogoRequisito;
use App\Models\EvaluacionAdmisibilidad;
use App\Models\Expediente;
use App\Models\Feriado;
use App\Models\ParametroPlazo;
use App\Models\Plazo;
use App\Models\Reglamento;
use App\Models\Rol;
use App\Models\Usuario;
use App\Notifications\AvisoParaRevision;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

function evaluacionSemilla(): array
{
    $rolTecnico = Rol::factory()->create(['codigo' => Rol::CODIGO_TECNICO]);
    $tecnico = Usuario::factory()->create(['rol_id' => $rolTecnico->id, 'activo' => true]);

    $rolAuditor = Rol::factory()->create(['codigo' => Rol::CODIGO_AUD_JURIDICO]);
    $auditor = Usuario::factory()->create(['rol_id' => $rolAuditor->id, 'activo' => true]);

    $reglamento = Reglamento::factory()->create(['codigo' => 'AC_022_2018']);

    $evaluacion = CatalogoEstado::factory()->create(['codigo' => 'EN_EVALUACION']);
    $planificacion = CatalogoEstado::factory()->create(['codigo' => 'EN_PLANIFICACION']);
    $subsanacion = CatalogoEstado::factory()->create(['codigo' => 'EN_SUBSANACION']);
    $rechazado = CatalogoEstado::factory()->create(['codigo' => 'RECHAZADO']);

    $catalogoAdmision = evaluacionCrearCatalogo('ACT_ADMISION', $rolAuditor, $evaluacion, $planificacion);
    $catalogoObservacion = evaluacionCrearCatalogo('ACT_OBSERVACION', $rolAuditor, $evaluacion, $subsanacion);
    $catalogoRechazo = evaluacionCrearCatalogo('ACT_RECHAZO', $rolAuditor, $evaluacion, $rechazado);

    $critico = CatalogoRequisito::factory()->create([
        'reglamento_id' => $reglamento->id,
        'es_critico' => true,
        'activo' => true,
        'orden' => 1,
    ]);

    $noCritico = CatalogoRequisito::factory()->create([
        'reglamento_id' => $reglamento->id,
        'es_critico' => false,
        'activo' => true,
        'orden' => 2,
    ]);

    return compact(
        'tecnico', 'auditor',
        'reglamento',
        'evaluacion', 'planificacion', 'subsanacion', 'rechazado',
        'catalogoAdmision', 'catalogoObservacion', 'catalogoRechazo',
        'critico', 'noCritico',
    );
}

function evaluacionCrearCatalogo(string $codigo, Rol $rol, CatalogoEstado $origen, CatalogoEstado $destino): CatalogoActuado
{
    return CatalogoActuado::create([
        'codigo' => $codigo,
        'nombre' => $codigo,
        'fase' => 'ADMISIBILIDAD',
        'rol_id' => $rol->id,
        'reglamento_id' => null,
        'estado_origen_id' => $origen->id,
        'estado_destino_id' => $destino->id,
        'es_automatico' => false,
        'requiere_adjunto' => false,
    ]);
}

function evaluacionCrearExpediente(int $estadoId, int $creadorId, int $reglamentoId): Expediente
{
    return Expediente::create([
        'nurej_code' => 'EXP-'.fake()->unique()->numberBetween(10000, 99999),
        'via' => 'INGRESO_VERBAL',
        'reglamento_id' => $reglamentoId,
        'estado_actual_id' => $estadoId,
        'fecha_ingreso' => now(),
        'creado_por' => $creadorId,
    ]);
}

function evaluacionAsignarBandeja(Expediente $expediente, Usuario $usuario, int $estadoId, int $catalogoId): void
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
}

function evaluacionPdf(string $nombre = 'documento.pdf'): UploadedFile
{
    return UploadedFile::fake()->create($nombre, 32, 'application/pdf');
}

it('devuelve 401 si el usuario no está autenticado', function () {
    $semilla = evaluacionSemilla();
    $expediente = evaluacionCrearExpediente($semilla['evaluacion']->id, $semilla['tecnico']->id, $semilla['reglamento']->id);

    $this->postJson('/api/expedientes/'.$expediente->id.'/evaluacion', [])
        ->assertUnauthorized();
});

it('devuelve 403 si el operador no tiene asignación activa (RF-03)', function () {
    $semilla = evaluacionSemilla();
    $expediente = evaluacionCrearExpediente($semilla['evaluacion']->id, $semilla['tecnico']->id, $semilla['reglamento']->id);

    Sanctum::actingAs($semilla['tecnico'], ['*']);

    $this->postJson('/api/expedientes/'.$expediente->id.'/evaluacion', [
        'requisitos' => [
            ['requisito_id' => $semilla['critico']->id, 'cumple' => true],
            ['requisito_id' => $semilla['noCritico']->id, 'cumple' => true],
        ],
    ])->assertForbidden();
});

it('devuelve 403 si el operador está asignado a otro expediente (compartimento estanco)', function () {
    $semilla = evaluacionSemilla();

    $expedienteAjeno = evaluacionCrearExpediente($semilla['evaluacion']->id, $semilla['tecnico']->id, $semilla['reglamento']->id);
    $expedienteAsignado = evaluacionCrearExpediente($semilla['evaluacion']->id, $semilla['tecnico']->id, $semilla['reglamento']->id);

    evaluacionAsignarBandeja($expedienteAsignado, $semilla['tecnico'], $semilla['evaluacion']->id, $semilla['catalogoAdmision']->id);

    Sanctum::actingAs($semilla['tecnico'], ['*']);

    $this->postJson('/api/expedientes/'.$expedienteAjeno->id.'/evaluacion', [
        'requisitos' => [
            ['requisito_id' => $semilla['critico']->id, 'cumple' => true],
            ['requisito_id' => $semilla['noCritico']->id, 'cumple' => true],
        ],
    ])->assertForbidden();
});

it('devuelve 422 si el expediente no está en EN_EVALUACION', function () {
    $semilla = evaluacionSemilla();

    $expediente = evaluacionCrearExpediente($semilla['subsanacion']->id, $semilla['tecnico']->id, $semilla['reglamento']->id);
    evaluacionAsignarBandeja($expediente, $semilla['tecnico'], $semilla['subsanacion']->id, $semilla['catalogoObservacion']->id);

    Sanctum::actingAs($semilla['tecnico'], ['*']);

    $this->postJson('/api/expedientes/'.$expediente->id.'/evaluacion', [
        'requisitos' => [
            ['requisito_id' => $semilla['critico']->id, 'cumple' => true],
            ['requisito_id' => $semilla['noCritico']->id, 'cumple' => true],
        ],
    ])->assertStatus(422)
        ->assertJsonValidationErrors('expediente');
});

it('devuelve 422 si el checklist no cubre todos los requisitos activos', function () {
    $semilla = evaluacionSemilla();

    $expediente = evaluacionCrearExpediente($semilla['evaluacion']->id, $semilla['tecnico']->id, $semilla['reglamento']->id);
    evaluacionAsignarBandeja($expediente, $semilla['tecnico'], $semilla['evaluacion']->id, $semilla['catalogoAdmision']->id);

    Sanctum::actingAs($semilla['tecnico'], ['*']);

    $this->postJson('/api/expedientes/'.$expediente->id.'/evaluacion', [
        'requisitos' => [
            ['requisito_id' => $semilla['critico']->id, 'cumple' => true],
        ],
    ])->assertStatus(422)
        ->assertJsonValidationErrors('requisitos');
});

it('devuelve 422 si el checklist trae formatos o IDs inválidos', function () {
    $semilla = evaluacionSemilla();
    $reglamentoOtro = Reglamento::factory()->create();
    $requisitoDeOtro = CatalogoRequisito::factory()->create(['reglamento_id' => $reglamentoOtro->id, 'es_critico' => true]);

    $expediente = evaluacionCrearExpediente($semilla['evaluacion']->id, $semilla['tecnico']->id, $semilla['reglamento']->id);
    evaluacionAsignarBandeja($expediente, $semilla['tecnico'], $semilla['evaluacion']->id, $semilla['catalogoAdmision']->id);

    Sanctum::actingAs($semilla['tecnico'], ['*']);

    $this->postJson('/api/expedientes/'.$expediente->id.'/evaluacion', [
        'requisitos' => [
            ['requisito_id' => $semilla['critico']->id, 'cumple' => 'no'],
            ['requisito_id' => $requisitoDeOtro->id, 'cumple' => true],
        ],
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['requisitos.0.cumple', 'requisitos']);
});

it('emite ACT_ADMISION, pasa a EN_PLANIFICACION y abre el plazo cuando todo cumple', function () {
    $semilla = evaluacionSemilla();

    ParametroPlazo::create([
        'reglamento_id' => $semilla['reglamento']->id,
        'tipo_plazo' => 'PLANIFICACION',
        'subtipo' => null,
        'dias_habiles' => 2,
        'base_legal' => 'AC_022_2018',
        'activo' => true,
    ]);

    $expediente = evaluacionCrearExpediente($semilla['evaluacion']->id, $semilla['tecnico']->id, $semilla['reglamento']->id);
    evaluacionAsignarBandeja($expediente, $semilla['tecnico'], $semilla['evaluacion']->id, $semilla['catalogoAdmision']->id);

    Sanctum::actingAs($semilla['tecnico'], ['*']);

    $this->postJson('/api/expedientes/'.$expediente->id.'/evaluacion', [
        'requisitos' => [
            ['requisito_id' => $semilla['critico']->id, 'cumple' => true],
            ['requisito_id' => $semilla['noCritico']->id, 'cumple' => true],
        ],
    ])->assertStatus(201)
        ->assertJsonPath('resumen.resultado', 'ACT_ADMISION')
        ->assertJsonPath('resumen.requisitos_faltantes', 0)
        ->assertJsonCount(2, 'evaluaciones');

    $expediente->refresh();

    expect($expediente->estado_actual_id)->toBe($semilla['planificacion']->id)
        ->and($expediente->asignacionActiva->usuario_id)->toBe($semilla['tecnico']->id);

    $plazo = Plazo::where('expediente_id', $expediente->id)
        ->where('tipo_plazo', 'PLANIFICACION')
        ->first();

    expect($plazo)->not->toBeNull()
        ->and($plazo->dias_habiles_otorgados)->toBe(2)
        ->and($plazo->estado)->toBe('VIGENTE');

    $actuado = Actuado::where('expediente_id', $expediente->id)
        ->where('catalogo_actuado_id', $semilla['catalogoAdmision']->id)
        ->where('estado_nuevo_id', $semilla['planificacion']->id)
        ->first();

    expect($actuado)->not->toBeNull()
        ->and($actuado->hash_actuado)->not->toBeNull();

    expect(EvaluacionAdmisibilidad::where('expediente_id', $expediente->id)->count())->toBe(2)
        ->and(EvaluacionAdmisibilidad::where('expediente_id', $expediente->id)->where('operador_id', $semilla['tecnico']->id)->count())->toBe(2)
        ->and(EvaluacionAdmisibilidad::where('expediente_id', $expediente->id)
            ->whereNotNull('actuado_id')->count())->toBe(2);
});

it('conserva el checklist guardado y rechaza una nueva evaluación cuando el paso ya está completado', function () {
    $semilla = evaluacionSemilla();

    ParametroPlazo::create([
        'reglamento_id' => $semilla['reglamento']->id,
        'tipo_plazo' => 'PLANIFICACION',
        'subtipo' => null,
        'dias_habiles' => 2,
        'base_legal' => 'AC_022_2018',
        'activo' => true,
    ]);

    $expediente = evaluacionCrearExpediente($semilla['evaluacion']->id, $semilla['tecnico']->id, $semilla['reglamento']->id);
    evaluacionAsignarBandeja($expediente, $semilla['tecnico'], $semilla['evaluacion']->id, $semilla['catalogoAdmision']->id);

    Sanctum::actingAs($semilla['tecnico'], ['*']);

    $this->postJson('/api/expedientes/'.$expediente->id.'/evaluacion', [
        'requisitos' => [
            ['requisito_id' => $semilla['critico']->id, 'cumple' => true],
            ['requisito_id' => $semilla['noCritico']->id, 'cumple' => true],
        ],
    ])->assertStatus(201);

    $this->getJson('/api/expedientes/'.$expediente->id.'/requisitos')
        ->assertOk()
        ->assertJsonPath('data.0.cumple', true)
        ->assertJsonPath('data.1.cumple', true);

    $evaluacionesAntes = EvaluacionAdmisibilidad::where('expediente_id', $expediente->id)->count();
    $actuadosAntes = Actuado::where('expediente_id', $expediente->id)->count();

    $this->post('/api/expedientes/'.$expediente->id.'/evaluacion', [
        'requisitos' => [
            ['requisito_id' => $semilla['critico']->id, 'cumple' => false],
            ['requisito_id' => $semilla['noCritico']->id, 'cumple' => false],
        ],
        'documentos' => [
            $semilla['critico']->id => [
                'requisito_id' => $semilla['critico']->id,
                'finalidad' => 'IMPUGNACION',
                'archivo' => evaluacionPdf('impugnacion.pdf'),
            ],
            $semilla['noCritico']->id => [
                'requisito_id' => $semilla['noCritico']->id,
                'finalidad' => 'SUBSANACION',
                'archivo' => evaluacionPdf('subsanacion.pdf'),
            ],
        ],
    ])->assertStatus(422)
        ->assertJsonValidationErrors('expediente');

    expect(EvaluacionAdmisibilidad::where('expediente_id', $expediente->id)->count())->toBe($evaluacionesAntes)
        ->and(Actuado::where('expediente_id', $expediente->id)->count())->toBe($actuadosAntes);
});

it('emite ACT_OBSERVACION hacia EN_SUBSANACION con 3 días hábiles reales (técnico / AC 022)', function () {
    $semilla = evaluacionSemilla();

    ParametroPlazo::create([
        'reglamento_id' => $semilla['reglamento']->id,
        'tipo_plazo' => 'SUBSANACION',
        'subtipo' => null,
        'dias_habiles' => 3,
        'base_legal' => 'AC_022_2018',
        'activo' => true,
    ]);

    CarbonImmutable::setTestNow('2026-09-14 10:00:00');

    Feriado::create(['fecha' => '2026-09-15', 'descripcion' => 'Feriado martes', 'ambito' => 'NACIONAL']);
    Feriado::create(['fecha' => '2026-09-16', 'descripcion' => 'Feriado miércoles', 'ambito' => 'NACIONAL']);

    $expediente = evaluacionCrearExpediente($semilla['evaluacion']->id, $semilla['tecnico']->id, $semilla['reglamento']->id);
    evaluacionAsignarBandeja($expediente, $semilla['tecnico'], $semilla['evaluacion']->id, $semilla['catalogoObservacion']->id);

    Sanctum::actingAs($semilla['tecnico'], ['*']);

    $this->post('/api/expedientes/'.$expediente->id.'/evaluacion', [
        'requisitos' => [
            ['requisito_id' => $semilla['critico']->id, 'cumple' => true],
            ['requisito_id' => $semilla['noCritico']->id, 'cumple' => false],
        ],
        'documentos' => [
            $semilla['noCritico']->id => [
                'requisito_id' => $semilla['noCritico']->id,
                'finalidad' => 'SUBSANACION',
                'archivo' => evaluacionPdf('subsanacion.pdf'),
            ],
        ],
    ])->assertStatus(201)
        ->assertJsonPath('resumen.resultado', 'ACT_OBSERVACION')
        ->assertJsonPath('resumen.requisitos_faltantes', 1);

    $plazo = Plazo::where('expediente_id', $expediente->id)
        ->where('tipo_plazo', 'SUBSANACION')
        ->first();

    expect($plazo)->not->toBeNull()
        ->and($plazo->dias_habiles_otorgados)->toBe(3)
        ->and($plazo->estado)->toBe('VIGENTE')
        ->and($plazo->fecha_limite->toDateString())->toBe('2026-09-21');

    $expediente->refresh();
    expect($expediente->estado_actual_id)->toBe($semilla['subsanacion']->id);

    CarbonImmutable::setTestNow();
});

it('emite ACT_OBSERVACION sin plazo cuando el reglamento no parametriza SUBSANACION', function () {
    $semilla = evaluacionSemilla();
    $reglamentoSinPlazo = Reglamento::factory()->create(['codigo' => 'AC_054_2018']);

    $critico = CatalogoRequisito::factory()->create(['reglamento_id' => $reglamentoSinPlazo->id, 'es_critico' => true, 'activo' => true]);
    $noCritico = CatalogoRequisito::factory()->create(['reglamento_id' => $reglamentoSinPlazo->id, 'es_critico' => false, 'activo' => true]);

    $expediente = evaluacionCrearExpediente($semilla['evaluacion']->id, $semilla['auditor']->id, $reglamentoSinPlazo->id);
    evaluacionAsignarBandeja($expediente, $semilla['auditor'], $semilla['evaluacion']->id, $semilla['catalogoObservacion']->id);

    Sanctum::actingAs($semilla['auditor'], ['*']);

    $this->post('/api/expedientes/'.$expediente->id.'/evaluacion', [
        'requisitos' => [
            ['requisito_id' => $critico->id, 'cumple' => true],
            ['requisito_id' => $noCritico->id, 'cumple' => false],
        ],
        'documentos' => [
            $noCritico->id => [
                'requisito_id' => $noCritico->id,
                'finalidad' => 'SUBSANACION',
                'archivo' => evaluacionPdf('subsanacion.pdf'),
            ],
        ],
    ])->assertStatus(201)
        ->assertJsonPath('resumen.resultado', 'ACT_OBSERVACION');

    expect(Plazo::where('expediente_id', $expediente->id)->exists())->toBeFalse();
});

it('emite ACT_RECHAZO, pasa a RECHAZADO y conserva 1 día para remitir la impugnación', function () {
    $semilla = evaluacionSemilla();

    $expediente = evaluacionCrearExpediente($semilla['evaluacion']->id, $semilla['tecnico']->id, $semilla['reglamento']->id);
    evaluacionAsignarBandeja($expediente, $semilla['tecnico'], $semilla['evaluacion']->id, $semilla['catalogoRechazo']->id);

    ParametroPlazo::create([
        'reglamento_id' => $semilla['reglamento']->id,
        'tipo_plazo' => 'IMPUGNACION_REMITIR',
        'subtipo' => null,
        'dias_habiles' => 1,
        'base_legal' => 'RN-08',
        'activo' => true,
    ]);

    Plazo::create([
        'expediente_id' => $expediente->id,
        'tipo_plazo' => 'SUBSANACION',
        'dias_habiles_otorgados' => 3,
        'fecha_inicio' => now(),
        'fecha_limite' => now()->addDays(3),
        'estado' => 'VIGENTE',
        'actuado_disparador_id' => Actuado::latest('id')->first()->id,
    ]);

    Sanctum::actingAs($semilla['tecnico'], ['*']);

    $this->post('/api/expedientes/'.$expediente->id.'/evaluacion', [
        'requisitos' => [
            ['requisito_id' => $semilla['critico']->id, 'cumple' => false],
            ['requisito_id' => $semilla['noCritico']->id, 'cumple' => true],
        ],
        'documentos' => [
            $semilla['critico']->id => [
                'requisito_id' => $semilla['critico']->id,
                'finalidad' => 'IMPUGNACION',
                'archivo' => evaluacionPdf('impugnacion.pdf'),
            ],
        ],
    ])->assertStatus(201)
        ->assertJsonPath('resumen.resultado', 'ACT_RECHAZO')
        ->assertJsonPath('resumen.faltantes_criticos', [$semilla['critico']->id]);

    $expediente->refresh();

    expect($expediente->estado_actual_id)->toBe($semilla['rechazado']->id);

    $plazoRemitir = Plazo::where('expediente_id', $expediente->id)
        ->where('tipo_plazo', 'IMPUGNACION_REMITIR')
        ->where('estado', 'VIGENTE')
        ->first();
    $cerrado = Plazo::where('expediente_id', $expediente->id)
        ->where('tipo_plazo', 'SUBSANACION')
        ->where('estado', 'CERRADO')
        ->first();

    expect($plazoRemitir)->not->toBeNull()
        ->and($plazoRemitir->dias_habiles_otorgados)->toBe(1)
        ->and($cerrado)->not->toBeNull();

    $actuadoRechazo = Actuado::where('expediente_id', $expediente->id)
        ->where('catalogo_actuado_id', $semilla['catalogoRechazo']->id)
        ->where('estado_nuevo_id', $semilla['rechazado']->id)
        ->latest('id')
        ->first();

    $aviso = Adjunto::where('actuado_id', $actuadoRechazo->id)
        ->where('nombre_original', 'aviso-rechazo.pdf')
        ->first();

    expect($actuadoRechazo)->not->toBeNull()
        ->and($actuadoRechazo->estado_nuevo_id)->toBe($semilla['rechazado']->id)
        ->and($aviso)->not->toBeNull();
});

it('lista solo los requisitos activos del reglamento para el operador asignado (RF-04)', function () {
    $semilla = evaluacionSemilla();

    CatalogoRequisito::factory()->create([
        'reglamento_id' => $semilla['reglamento']->id,
        'es_critico' => false,
        'activo' => false,
        'orden' => 3,
    ]);

    $expediente = evaluacionCrearExpediente($semilla['evaluacion']->id, $semilla['tecnico']->id, $semilla['reglamento']->id);
    evaluacionAsignarBandeja($expediente, $semilla['tecnico'], $semilla['evaluacion']->id, $semilla['catalogoAdmision']->id);

    Sanctum::actingAs($semilla['tecnico'], ['*']);

    $this->getJson('/api/expedientes/'.$expediente->id.'/requisitos')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.es_critico', true)
        ->assertJsonPath('data.1.es_critico', false);
});

it('exige autorización RF-03 también en el catálogo de requisitos', function () {
    $semilla = evaluacionSemilla();

    $otroCreador = Usuario::factory()->create(['rol_id' => $semilla['auditor']->rol_id, 'activo' => true]);

    $expediente = evaluacionCrearExpediente($semilla['evaluacion']->id, $otroCreador->id, $semilla['reglamento']->id);

    Sanctum::actingAs($semilla['tecnico'], ['*']);

    $this->getJson('/api/expedientes/'.$expediente->id.'/requisitos')
        ->assertForbidden();
});

it('exige un PDF junto a cada requisito no cumplido para guardar la evaluación', function () {
    $semilla = evaluacionSemilla();
    $expediente = evaluacionCrearExpediente($semilla['evaluacion']->id, $semilla['tecnico']->id, $semilla['reglamento']->id);
    evaluacionAsignarBandeja($expediente, $semilla['tecnico'], $semilla['evaluacion']->id, $semilla['catalogoObservacion']->id);

    Sanctum::actingAs($semilla['tecnico'], ['*']);

    $this->postJson('/api/expedientes/'.$expediente->id.'/evaluacion', [
        'requisitos' => [
            ['requisito_id' => $semilla['critico']->id, 'cumple' => true],
            ['requisito_id' => $semilla['noCritico']->id, 'cumple' => false],
        ],
    ])->assertStatus(422)
        ->assertJsonValidationErrors('documentos');
});

it('genera el aviso PDF del ciudadano y notifica a la Encargada solo para revisado', function () {
    Storage::fake('local');
    Notification::fake();

    $semilla = evaluacionSemilla();

    $rolEncargada = Rol::factory()->create(['codigo' => Rol::CODIGO_ENCARGADA]);
    $encargada = Usuario::factory()->create(['rol_id' => $rolEncargada->id, 'activo' => true]);

    ParametroPlazo::create([
        'reglamento_id' => $semilla['reglamento']->id,
        'tipo_plazo' => 'SUBSANACION',
        'subtipo' => null,
        'dias_habiles' => 3,
        'base_legal' => 'AC_022_2018',
        'activo' => true,
    ]);

    $expediente = evaluacionCrearExpediente($semilla['evaluacion']->id, $semilla['tecnico']->id, $semilla['reglamento']->id);
    evaluacionAsignarBandeja($expediente, $semilla['tecnico'], $semilla['evaluacion']->id, $semilla['catalogoObservacion']->id);

    Sanctum::actingAs($semilla['tecnico'], ['*']);

    $this->post('/api/expedientes/'.$expediente->id.'/evaluacion', [
        'requisitos' => [
            ['requisito_id' => $semilla['critico']->id, 'cumple' => true],
            ['requisito_id' => $semilla['noCritico']->id, 'cumple' => false],
        ],
        'documentos' => [
            $semilla['noCritico']->id => [
                'requisito_id' => $semilla['noCritico']->id,
                'finalidad' => 'SUBSANACION',
                'archivo' => evaluacionPdf('subsanacion.pdf'),
            ],
        ],
    ])->assertCreated();

    $actuado = Actuado::where('expediente_id', $expediente->id)
        ->where('catalogo_actuado_id', $semilla['catalogoObservacion']->id)
        ->latest('id')
        ->first();

    $aviso = Adjunto::where('actuado_id', $actuado->id)
        ->where('nombre_original', 'aviso-observacion.pdf')
        ->first();

    expect($aviso)->not->toBeNull()
        ->and($aviso->finalidad)->toBeNull()
        ->and($aviso->requisito_id)->toBeNull();

    Storage::disk('local')->assertExists($aviso->ruta_almacenamiento);

    Notification::assertSentTo(
        $encargada,
        AvisoParaRevision::class,
        function (AvisoParaRevision $notification) use ($aviso) {
            $datos = $notification->toArray($notification);

            return $notification->categoria === 'observacion'
                && $notification->adjunto?->id === $aviso->id
                && $datos['requiere_accion'] === false;
        },
    );

    $expediente->refresh();
    expect($expediente->estado_actual_id)->toBe($semilla['subsanacion']->id);
});

it('acepta la subsanación con un PDF por requisito y abre 2 días de planificación', function () {
    $semilla = evaluacionSemilla();

    $catalogoSubsanacion = evaluacionCrearCatalogo(
        'ACT_SUBSANACION_ACEPTADA',
        $semilla['tecnico']->rol,
        $semilla['subsanacion'],
        $semilla['planificacion'],
    );

    ParametroPlazo::create([
        'reglamento_id' => $semilla['reglamento']->id,
        'tipo_plazo' => 'SUBSANACION',
        'subtipo' => null,
        'dias_habiles' => 3,
        'base_legal' => 'AC_022_2018',
        'activo' => true,
    ]);

    ParametroPlazo::create([
        'reglamento_id' => $semilla['reglamento']->id,
        'tipo_plazo' => 'PLANIFICACION',
        'subtipo' => null,
        'dias_habiles' => 2,
        'base_legal' => 'AC_022_2018',
        'activo' => true,
    ]);

    $expediente = evaluacionCrearExpediente($semilla['subsanacion']->id, $semilla['tecnico']->id, $semilla['reglamento']->id);
    evaluacionAsignarBandeja($expediente, $semilla['tecnico'], $semilla['subsanacion']->id, $semilla['catalogoObservacion']->id);

    Plazo::create([
        'expediente_id' => $expediente->id,
        'tipo_plazo' => 'SUBSANACION',
        'dias_habiles_otorgados' => 3,
        'fecha_inicio' => now(),
        'fecha_limite' => now()->addDays(3),
        'estado' => 'VIGENTE',
        'actuado_disparador_id' => Actuado::latest('id')->first()->id,
    ]);

    Sanctum::actingAs($semilla['tecnico'], ['*']);

    $this->post('/api/expedientes/'.$expediente->id.'/subsanacion/aceptar', [
        'descripcion' => 'Subsanación presentada por el ciudadano y cargada por el operador asignado.',
        'documentos' => [
            $semilla['noCritico']->id => [
                'requisito_id' => $semilla['noCritico']->id,
                'archivo' => evaluacionPdf('subsanacion.pdf'),
            ],
        ],
    ])->assertCreated();

    $expediente->refresh();
    expect($expediente->estado_actual_id)->toBe($semilla['planificacion']->id);

    $actuado = Actuado::where('expediente_id', $expediente->id)
        ->where('catalogo_actuado_id', $catalogoSubsanacion->id)
        ->first();

    $adjunto = Adjunto::where('actuado_id', $actuado->id)->first();
    expect($adjunto)->not->toBeNull()
        ->and($adjunto->finalidad)->toBe('SUBSANACION')
        ->and($adjunto->requisito_id)->toBe($semilla['noCritico']->id);

    expect($expediente->plazos()->where('tipo_plazo', 'SUBSANACION')->where('estado', 'CERRADO')->exists())->toBeTrue();

    $plazoPlanificacion = $expediente->plazos()->where('tipo_plazo', 'PLANIFICACION')->first();
    expect($plazoPlanificacion)->not->toBeNull()
        ->and($plazoPlanificacion->dias_habiles_otorgados)->toBe(2)
        ->and($plazoPlanificacion->estado)->toBe('VIGENTE');
});
