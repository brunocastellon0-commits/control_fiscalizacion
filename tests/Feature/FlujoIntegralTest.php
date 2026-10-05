<?php

use App\Models\Actuado;
use App\Models\Asignacion;
use App\Models\CatalogoActuado;
use App\Models\CatalogoEstado;
use App\Models\CatalogoRequisito;
use App\Models\Expediente;
use App\Models\Feriado;
use App\Models\Parte;
use App\Models\Plazo;
use App\Models\Reglamento;
use App\Models\Rol;
use App\Models\Usuario;
use Carbon\Carbon;
use Database\Seeders\CatalogoActuadoSeeder;
use Database\Seeders\CatalogoEstadoSeeder;
use Database\Seeders\CatalogoRequisitoSeeder;
use Database\Seeders\FeriadoSeeder;
use Database\Seeders\ParametroPlazoSeeder;
use Database\Seeders\ReglamentoSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Semilla integral (F16): catálogos reales del proyecto y los tres operadores
 * que participan del ciclo AC055 (Encargada, Técnico y Auditor Financiero).
 *
 * @return array{encargada: Usuario, tecnico: Usuario, auditor: Usuario, ac055: Reglamento, rolAuditor: Rol}
 */
function fiSemilla(TestCase $test): array
{
    foreach ([
        RolSeeder::class,
        CatalogoEstadoSeeder::class,
        ReglamentoSeeder::class,
        CatalogoRequisitoSeeder::class,
        ParametroPlazoSeeder::class,
        FeriadoSeeder::class,
        CatalogoActuadoSeeder::class,
    ] as $seeder) {
        $test->seed($seeder);
    }

    $rolEncargada = Rol::where('codigo', Rol::CODIGO_ENCARGADA)->firstOrFail();
    $rolTecnico = Rol::where('codigo', Rol::CODIGO_TECNICO)->firstOrFail();
    $rolAuditor = Rol::where('codigo', Rol::CODIGO_AUD_FINANCIERO)->firstOrFail();

    return [
        'encargada' => Usuario::factory()->create(['rol_id' => $rolEncargada->id, 'activo' => true]),
        'tecnico' => Usuario::factory()->create(['rol_id' => $rolTecnico->id, 'activo' => true]),
        'auditor' => Usuario::factory()->create(['rol_id' => $rolAuditor->id, 'activo' => true]),
        'ac055' => Reglamento::where('codigo', 'AC_055_2018')->firstOrFail(),
        'rolAuditor' => $rolAuditor,
    ];
}

/**
 * Feriado local insertado sobre la semilla: el 2026-10-07 cae miércoles y
 * desplaza los límites de 5 días hábiles calculados desde el lunes 2026-10-05.
 */
function fiInsertarFeriadoDePrueba(): void
{
    Feriado::create([
        'fecha' => '2026-10-07',
        'descripcion' => 'Feriado puente (flujo integral F16)',
        'ambito' => 'LOCAL',
    ]);
}

/**
 * @return array<string, mixed>
 */
function fiPayloadApertura(int $reglamentoId): array
{
    return [
        'via' => 'FINANCIERO',
        'reglamento_id' => $reglamentoId,
        'resumen_hechos' => 'Desvío de fondos denunciado en la unidad de fiscalización.',
        'partes' => [
            ['tipo' => 'DENUNCIANTE', 'nombre_completo' => 'María Fernanda Rojas', 'documento_identidad' => '6543210'],
            ['tipo' => 'DENUNCIADO', 'nombre_completo' => 'Unidad Administrativa', 'cargo_institucion' => 'Director Administrativo'],
        ],
    ];
}

/**
 * @return array<int, array{requisito_id: int, cumple: bool}>
 */
function fiRequisitosAprobatorios(int $reglamentoId): array
{
    return CatalogoRequisito::where('reglamento_id', $reglamentoId)
        ->where('activo', true)
        ->orderBy('orden')
        ->get()
        ->map(fn (CatalogoRequisito $requisito) => [
            'requisito_id' => $requisito->id,
            'cumple' => true,
        ])
        ->all();
}

function fiPayloadComunicar(): array
{
    return [
        'descripcion' => 'Comunicación formal de hallazgos a los auditados.',
        'adjunto' => UploadedFile::fake()->create('oficio_hallazgos.pdf', 100, 'application/pdf'),
    ];
}

function fiPayloadRecibir(): array
{
    return [
        'descripcion' => 'Escrito de descargos presentado por los auditados.',
        'adjunto' => UploadedFile::fake()->create('escrito_descargos.pdf', 100, 'application/pdf'),
    ];
}

/**
 * @return array<string, mixed>
 */
function fiPayloadInforme(): array
{
    return [
        'catalogo_actuado_id' => CatalogoActuado::where(
            'codigo',
            'ACT_INFORME_AUDITORIA_FINANCIERA_SIN_RESPONSABILIDAD',
        )->value('id'),
        'descripcion' => 'Informe final de auditoría financiera sin responsabilidad.',
        'adjunto' => UploadedFile::fake()->create('informe_final.pdf', 100, 'application/pdf'),
    ];
}

/**
 * @return array<string, mixed>
 */
function fiPayloadVistoBuenoFinal(): array
{
    return [
        'descripcion' => 'Visto bueno final del informe de auditoría financiera.',
    ];
}

/**
 * @return array<string, mixed>
 */
function fiPayloadReparto(): array
{
    return [
        'destino' => 'Juzgado Disciplinario',
        'justificacion' => 'Remisión institucional del NUREJ concluido al destino previsto por la norma.',
    ];
}

function fiEstado(Expediente $expediente): string
{
    return CatalogoEstado::findOrFail($expediente->fresh()->estado_actual_id)->codigo;
}

function fiPlazo(int $expedienteId, string $tipoPlazo): ?Plazo
{
    return Plazo::where('expediente_id', $expedienteId)
        ->where('tipo_plazo', $tipoPlazo)
        ->latest('id')
        ->first();
}

function fiBandejaActiva(int $expedienteId): ?int
{
    return Asignacion::where('expediente_id', $expedienteId)
        ->where('activa', true)
        ->value('usuario_id');
}

/**
 * Segundo expediente (EN_EVALUACION) con banda propia, construido directamente
 * sobre el modelo para verificar el IDOR entre causas.
 */
function fiCrearExpedienteAsignado(array $s, Usuario $operador): Expediente
{
    $expediente = Expediente::create([
        'nurej_code' => 'EXP-'.fake()->unique()->numberBetween(100000, 999999),
        'via' => 'FINANCIERO',
        'reglamento_id' => $s['ac055']->id,
        'estado_actual_id' => CatalogoEstado::where('codigo', 'EN_EVALUACION')->value('id'),
        'resumen_hechos' => 'Expediente ajeno usado para verificar el compartimento entre causas.',
        'fecha_ingreso' => now(),
        'creado_por' => $s['tecnico']->id,
    ]);

    $actuado = Actuado::create([
        'expediente_id' => $expediente->id,
        'catalogo_actuado_id' => CatalogoActuado::where('codigo', 'ACT_SORTEO_INICIAL')->value('id'),
        'usuario_id' => $s['encargada']->id,
        'estado_nuevo_id' => $expediente->estado_actual_id,
        'contenido' => ['tipo' => 'SORTEO_INICIAL', 'descripcion' => 'Sorteo inicial de la causa ajena.'],
    ])->refresh();

    Asignacion::create([
        'expediente_id' => $expediente->id,
        'usuario_id' => $operador->id,
        'rol_id' => $operador->rol_id,
        'actuado_origen_id' => $actuado->id,
        'fecha_asignacion' => now(),
        'activa' => true,
    ]);

    return $expediente;
}

function fiHashEsperado(?string $hashAnterior, Actuado $actuado): string
{
    $fila = DB::table('actuados')->where('id', $actuado->id)->first();

    return hash('sha256', implode('', [
        $hashAnterior ?? '',
        (string) $fila->expediente_id,
        (string) $fila->catalogo_actuado_id,
        $fila->usuario_id !== null ? (string) $fila->usuario_id : 'SYSTEM',
        (string) $fila->fecha_hora,
        (string) $fila->contenido,
    ]));
}

/**
 * Recorrido 1→5: apertura, sorteo, admisión, MPA y visto bueno a la
 * planificación. El expediente queda EN_EJECUCION en la bandeja del auditor.
 */
function fiLlevarAEjecucion(TestCase $test, array $s): Expediente
{
    Sanctum::actingAs($s['tecnico'], ['*']);

    $test->postJson('/api/expedientes', array_merge(
        fiPayloadApertura($s['ac055']->id),
        ['adjunto' => UploadedFile::fake()->create('denuncia_integral.pdf', 100, 'application/pdf')],
    ))->assertCreated();

    $expediente = Expediente::firstOrFail();

    Sanctum::actingAs($s['encargada'], ['*']);

    $test->postJson("/api/expedientes/{$expediente->id}/sortear", [
        'descripcion' => 'Sorteo inicial del flujo integral (F16).',
    ])->assertCreated();

    Sanctum::actingAs($s['auditor'], ['*']);

    $test->postJson("/api/expedientes/{$expediente->id}/evaluacion", [
        'requisitos' => fiRequisitosAprobatorios($s['ac055']->id),
    ])->assertCreated();

    $test->postJson("/api/expedientes/{$expediente->id}/planificacion", [
        'descripcion' => 'MPA de auditoría financiera: alcance, muestra y cronograma propuesto.',
        'fecha_limite_propuesta' => '2026-10-16',
        'adjunto' => UploadedFile::fake()->create('mpa_integral.pdf', 100, 'application/pdf'),
    ])->assertCreated();

    Sanctum::actingAs($s['encargada'], ['*']);

    $test->postJson("/api/expedientes/{$expediente->id}/planificacion/visto-bueno", [
        'descripcion' => 'Visto bueno al MPA cargado por el auditor asignado.',
    ])->assertCreated();

    return $expediente->fresh();
}

it('recorre el ciclo completo AC055: sorteo, admisión, MPA, visto bueno, descargos, informe y reparto', function () {
    Storage::fake('local');
    Carbon::setTestNow('2026-10-05 10:00:00');

    $s = fiSemilla($this);
    fiInsertarFeriadoDePrueba();

    // ---- 1. Apertura (Técnico) -------------------------------------------
    Sanctum::actingAs($s['tecnico'], ['*']);

    $this->postJson('/api/expedientes', array_merge(
        fiPayloadApertura($s['ac055']->id),
        ['adjunto' => UploadedFile::fake()->create('denuncia_integral.pdf', 100, 'application/pdf')],
    ))
        ->assertCreated()
        ->assertJsonPath('data.estado_actual.codigo', 'PENDIENTE_SORTEO');

    $expediente = Expediente::firstOrFail();

    expect(fiEstado($expediente))->toBe('PENDIENTE_SORTEO')
        ->and($expediente->via)->toBe('FINANCIERO')
        ->and(Parte::where('expediente_id', $expediente->id)->count())->toBe(2)
        ->and(fiBandejaActiva($expediente->id))->toBeNull()
        ->and(Actuado::where('expediente_id', $expediente->id)->count())->toBe(1);

    // ---- 2. Sorteo ciego (Encargada) -------------------------------------
    Sanctum::actingAs($s['encargada'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/sortear", [
        'descripcion' => 'Sorteo inicial del flujo integral (F16).',
    ])
        ->assertCreated()
        ->assertJsonPath('data.asignacion_activa.usuario.id', $s['auditor']->id);

    expect(fiEstado($expediente))->toBe('EN_EVALUACION')
        ->and(fiBandejaActiva($expediente->id))->toBe($s['auditor']->id);

    $plazoEvaluacion = fiPlazo($expediente->id, 'EVALUACION');

    expect($plazoEvaluacion->estado)->toBe('VIGENTE')
        ->and($plazoEvaluacion->dias_habiles_otorgados)->toBe(5)
        ->and($plazoEvaluacion->fecha_limite->format('Y-m-d'))->toBe('2026-10-13');

    // ---- 3. Evaluación de admisibilidad (Auditor asignado) ---------------
    Sanctum::actingAs($s['auditor'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/evaluacion", [
        'requisitos' => fiRequisitosAprobatorios($s['ac055']->id),
    ])
        ->assertCreated()
        ->assertJsonPath('resumen.resultado', 'ACT_ADMISION')
        ->assertJsonPath('resumen.requisitos_faltantes', 0);

    expect(fiEstado($expediente))->toBe('EN_PLANIFICACION')
        ->and(fiBandejaActiva($expediente->id))->toBe($s['auditor']->id);

    $plazoPlanificacion = fiPlazo($expediente->id, 'PLANIFICACION');

    expect($plazoPlanificacion->estado)->toBe('VIGENTE')
        ->and($plazoPlanificacion->dias_habiles_otorgados)->toBe(2)
        ->and($plazoPlanificacion->fecha_limite->format('Y-m-d'))->toBe('2026-10-08')
        // Evidencia de AUD-0030: la admisión NO cierra el plazo de EVALUACION.
        ->and($plazoEvaluacion->refresh()->estado)->toBe('VIGENTE');

    // ---- 4. Carga del MPA (Auditor asignado) -----------------------------
    $this->postJson("/api/expedientes/{$expediente->id}/planificacion", [
        'descripcion' => 'MPA de auditoría financiera: alcance, muestra y cronograma propuesto.',
        'fecha_limite_propuesta' => '2026-10-16',
        'adjunto' => UploadedFile::fake()->create('mpa_integral.pdf', 100, 'application/pdf'),
    ])
        ->assertCreated()
        ->assertJsonPath('data.tipo_actuado.codigo', 'ACT_MPA')
        ->assertJsonPath('data.estado_nuevo.codigo', 'PENDIENTE_VISTO_BUENO');

    expect(fiEstado($expediente))->toBe('PENDIENTE_VISTO_BUENO')
        ->and(fiBandejaActiva($expediente->id))->toBe($s['encargada']->id)
        ->and(fiPlazo($expediente->id, 'PLANIFICACION')->estado)->toBe('CERRADO');

    // ---- 5. Visto bueno a la planificación (Encargada) -------------------
    Sanctum::actingAs($s['encargada'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/planificacion/visto-bueno", [
        'descripcion' => 'Visto bueno al MPA cargado por el auditor asignado.',
    ])
        ->assertCreated()
        ->assertJsonPath('data.tipo_actuado.codigo', 'ACT_VISTO_BUENO_PLANIFICACION')
        ->assertJsonPath('data.estado_nuevo.codigo', 'EN_EJECUCION');

    expect(fiEstado($expediente))->toBe('EN_EJECUCION')
        ->and(fiBandejaActiva($expediente->id))->toBe($s['auditor']->id);

    $plazoEjecucion = fiPlazo($expediente->id, 'EJECUCION');

    expect($plazoEjecucion->estado)->toBe('VIGENTE')
        ->and($plazoEjecucion->fecha_limite->format('Y-m-d'))->toBe('2026-10-16')
        ->and($plazoEjecucion->parametro_plazo_id)->toBeNull()
        ->and($plazoEjecucion->dias_habiles_otorgados)->toBe(0);

    // ---- 6. Comunicación de hallazgos (Auditor asignado) -----------------
    Sanctum::actingAs($s['auditor'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/descargos/comunicar", fiPayloadComunicar())
        ->assertCreated()
        ->assertJsonPath('data.tipo_actuado.codigo', 'ACT_COMUNICACION_HALLAZGOS');

    expect(fiEstado($expediente))->toBe('EN_EJECUCION');

    $plazoEjecucion = fiPlazo($expediente->id, 'EJECUCION');

    expect($plazoEjecucion->estado)->toBe('SUSPENDIDO')
        ->and($plazoEjecucion->fecha_pausa)->not->toBeNull();

    $plazoDescargos = fiPlazo($expediente->id, 'DESCARGOS');

    expect($plazoDescargos->estado)->toBe('VIGENTE')
        ->and($plazoDescargos->dias_habiles_otorgados)->toBe(5)
        ->and($plazoDescargos->fecha_limite->format('Y-m-d'))->toBe('2026-10-13');

    // ---- 7. Recepción de descargos (Auditor asignado) --------------------
    $this->postJson("/api/expedientes/{$expediente->id}/descargos/recibir", fiPayloadRecibir())
        ->assertCreated()
        ->assertJsonPath('data.tipo_actuado.codigo', 'ACT_RECEPCION_DESCARGOS');

    $plazoDescargos = fiPlazo($expediente->id, 'DESCARGOS');

    expect($plazoDescargos->estado)->toBe('CUMPLIDO')
        ->and($plazoDescargos->parametro_plazo_id)->not->toBeNull()
        ->and($plazoDescargos->actuado_cierre_id)->not->toBeNull();

    $plazoEjecucion = fiPlazo($expediente->id, 'EJECUCION');

    expect($plazoEjecucion->estado)->toBe('VIGENTE')
        ->and($plazoEjecucion->fecha_reanudacion)->not->toBeNull();

    // ---- 8. Informe final sin responsabilidad (Auditor asignado) ---------
    $this->postJson("/api/expedientes/{$expediente->id}/actuados", fiPayloadInforme())
        ->assertCreated()
        ->assertJsonPath('data.tipo_actuado.codigo', 'ACT_INFORME_AUDITORIA_FINANCIERA_SIN_RESPONSABILIDAD')
        ->assertJsonPath('data.estado_nuevo.codigo', 'PENDIENTE_VISTO_BUENO_FINAL');

    expect(fiEstado($expediente))->toBe('PENDIENTE_VISTO_BUENO_FINAL')
        ->and(fiBandejaActiva($expediente->id))->toBe($s['auditor']->id);

    // ---- 9. Visto bueno final (Encargada) --------------------------------
    Sanctum::actingAs($s['encargada'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/cierre/visto-bueno", fiPayloadVistoBuenoFinal())
        ->assertCreated()
        ->assertJsonPath('data.tipo_actuado.codigo', 'ACT_VISTO_BUENO_FINAL')
        ->assertJsonPath('data.estado_nuevo.codigo', 'LISTO_PARA_REPARTO');

    expect(fiEstado($expediente))->toBe('LISTO_PARA_REPARTO')
        ->and(fiBandejaActiva($expediente->id))->toBe($s['encargada']->id);

    // ---- 10. Reparto institucional (Encargada) ---------------------------
    $this->postJson("/api/expedientes/{$expediente->id}/cierre/reparto", fiPayloadReparto())
        ->assertCreated()
        ->assertJsonPath('data.tipo_actuado.codigo', 'ACT_REPARTO_INSTITUCIONAL')
        ->assertJsonPath('data.estado_nuevo.codigo', 'CONCLUIDO_REMITIDO');

    expect(fiEstado($expediente))->toBe('CONCLUIDO_REMITIDO')
        ->and(fiBandejaActiva($expediente->id))->toBeNull();

    // ---- Cadena de custodia y trazabilidad -------------------------------
    $actuados = Actuado::with('tipoActuado')
        ->where('expediente_id', $expediente->id)
        ->orderBy('id')
        ->get();

    expect($actuados)->toHaveCount(10)
        ->and($actuados->pluck('tipoActuado.codigo')->all())->toBe([
            'ACT_REGISTRO_DIGITALIZACION',
            'ACT_SORTEO_INICIAL',
            'ACT_ADMISION',
            'ACT_MPA',
            'ACT_VISTO_BUENO_PLANIFICACION',
            'ACT_COMUNICACION_HALLAZGOS',
            'ACT_RECEPCION_DESCARGOS',
            'ACT_INFORME_AUDITORIA_FINANCIERA_SIN_RESPONSABILIDAD',
            'ACT_VISTO_BUENO_FINAL',
            'ACT_REPARTO_INSTITUCIONAL',
        ])
        ->and($actuados->first()->hash_anterior)->toBeNull()
        ->and($actuados->first()->hash_actuado)->toBe(fiHashEsperado(null, $actuados->first()));

    for ($i = 1; $i < $actuados->count(); $i++) {
        expect($actuados[$i]->hash_anterior)->toBe($actuados[$i - 1]->hash_actuado)
            ->and($actuados[$i]->hash_actuado)
            ->toBe(fiHashEsperado($actuados[$i - 1]->hash_actuado, $actuados[$i]));
    }

    $ultimoActuado = $actuados->last();

    expect($ultimoActuado->contenido['destino_reparto'])->toBe('Juzgado Disciplinario')
        ->and($ultimoActuado->usuario_id)->toBe($s['encargada']->id);

    Carbon::setTestNow();
});

it('mantiene el compartimento estanco y los roles del recorrido (RF-03)', function () {
    Storage::fake('local');
    Carbon::setTestNow('2026-10-05 10:00:00');

    $s = fiSemilla($this);
    fiInsertarFeriadoDePrueba();

    $expediente = fiLlevarAEjecucion($this, $s);

    // Un segundo Auditor Financiero del mismo rol, creado después del sorteo
    // (por lo tanto sin banda del expediente y sin participar del sorteo).
    $otroAuditor = Usuario::factory()->create([
        'rol_id' => $s['rolAuditor']->id,
        'activo' => true,
    ]);

    // RF-03: el operador sin asignación no ve ni tramita el expediente ajeno.
    Sanctum::actingAs($otroAuditor, ['*']);

    $this->getJson("/api/expedientes/{$expediente->id}")->assertForbidden();

    $this->postJson("/api/expedientes/{$expediente->id}/evaluacion", [
        'requisitos' => fiRequisitosAprobatorios($s['ac055']->id),
    ])->assertForbidden();

    $this->postJson("/api/expedientes/{$expediente->id}/descargos/comunicar", fiPayloadComunicar())
        ->assertForbidden();

    // El Técnico que abrió la causa pierde el acceso al ser sorteada.
    Sanctum::actingAs($s['tecnico'], ['*']);

    $this->getJson("/api/expedientes/{$expediente->id}")->assertForbidden();

    $this->postJson("/api/expedientes/{$expediente->id}/planificacion/visto-bueno", [
        'descripcion' => 'Visto bueno emitido por un rol no autorizado.',
    ])->assertForbidden();

    // La Encargada no participa de la fase de descargos de AC055.
    Sanctum::actingAs($s['encargada'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/descargos/comunicar", fiPayloadComunicar())
        ->assertForbidden();

    // IDOR entre expedientes: una causa ajena asignada al segundo auditor.
    $expedienteAjeno = fiCrearExpedienteAsignado($s, $otroAuditor);

    Sanctum::actingAs($s['auditor'], ['*']);

    $this->getJson("/api/expedientes/{$expedienteAjeno->id}")->assertForbidden();

    $this->postJson("/api/expedientes/{$expedienteAjeno->id}/evaluacion", [
        'requisitos' => fiRequisitosAprobatorios($s['ac055']->id),
    ])->assertForbidden();

    // El expediente propio sigue operable por su asignatario.
    $this->postJson("/api/expedientes/{$expediente->id}/descargos/comunicar", fiPayloadComunicar())
        ->assertCreated();

    $this->postJson("/api/expedientes/{$expediente->id}/descargos/recibir", fiPayloadRecibir())
        ->assertCreated();

    $this->postJson("/api/expedientes/{$expediente->id}/actuados", fiPayloadInforme())
        ->assertCreated();

    expect(fiEstado($expediente))->toBe('PENDIENTE_VISTO_BUENO_FINAL');

    // Visto bueno final: solo la Encargada activa, jamás el Técnico creador.
    Sanctum::actingAs($s['tecnico'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/cierre/visto-bueno", fiPayloadVistoBuenoFinal())
        ->assertForbidden();

    Sanctum::actingAs($otroAuditor, ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/cierre/visto-bueno", fiPayloadVistoBuenoFinal())
        ->assertForbidden();

    Sanctum::actingAs($s['encargada'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/cierre/visto-bueno", fiPayloadVistoBuenoFinal())
        ->assertCreated();

    expect(fiEstado($expediente))->toBe('LISTO_PARA_REPARTO');

    Carbon::setTestNow();
});

it('bloquea las acciones emitidas fuera de orden o por el rol equivocado', function () {
    Storage::fake('local');
    Carbon::setTestNow('2026-10-05 10:00:00');

    $s = fiSemilla($this);
    fiInsertarFeriadoDePrueba();

    $expediente = fiLlevarAEjecucion($this, $s);
    Sanctum::actingAs($s['auditor'], ['*']);

    // 1. Informe sin haber recepcionado descargos (Bloqueo de Salida RN-09).
    $this->postJson("/api/expedientes/{$expediente->id}/actuados", fiPayloadInforme())
        ->assertStatus(422)
        ->assertJsonValidationErrors('catalogo_actuado_id');

    expect(Actuado::where('expediente_id', $expediente->id)->count())->toBe(5);

    // 2. Una segunda comunicación con el sub-reloj ya cerrado.
    $this->postJson("/api/expedientes/{$expediente->id}/descargos/comunicar", fiPayloadComunicar())
        ->assertCreated();

    $this->postJson("/api/expedientes/{$expediente->id}/descargos/comunicar", fiPayloadComunicar())
        ->assertStatus(422)
        ->assertJsonValidationErrors('expediente');

    // 3. Roles fuera de su ámbito.
    Sanctum::actingAs($s['tecnico'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/actuados", fiPayloadInforme())
        ->assertForbidden();

    Sanctum::actingAs($s['auditor'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/cierre/visto-bueno", fiPayloadVistoBuenoFinal())
        ->assertForbidden();

    // 4. Reparto fuera de estado (el expediente aún no terminó la investigación).
    Sanctum::actingAs($s['encargada'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/cierre/reparto", fiPayloadReparto())
        ->assertForbidden();

    // 5. Cierre correcto: recepción, informe y visto bueno final.
    Sanctum::actingAs($s['auditor'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/descargos/recibir", fiPayloadRecibir())
        ->assertCreated();

    $this->postJson("/api/expedientes/{$expediente->id}/actuados", fiPayloadInforme())
        ->assertCreated()
        ->assertJsonPath('data.estado_nuevo.codigo', 'PENDIENTE_VISTO_BUENO_FINAL');

    expect(fiEstado($expediente))->toBe('PENDIENTE_VISTO_BUENO_FINAL');

    // 6. El reparto exige el Visto Bueno previo (fuera de estado).
    Sanctum::actingAs($s['encargada'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/cierre/reparto", fiPayloadReparto())
        ->assertForbidden();

    $this->postJson("/api/expedientes/{$expediente->id}/cierre/visto-bueno", fiPayloadVistoBuenoFinal())
        ->assertCreated();

    expect(fiEstado($expediente))->toBe('LISTO_PARA_REPARTO');

    $this->postJson("/api/expedientes/{$expediente->id}/cierre/reparto", fiPayloadReparto())
        ->assertCreated();

    expect(fiEstado($expediente))->toBe('CONCLUIDO_REMITIDO');

    // 7. Un NUREJ concluido no admite nuevas acciones de cierre.
    $this->postJson("/api/expedientes/{$expediente->id}/cierre/reparto", fiPayloadReparto())
        ->assertForbidden();

    $this->postJson("/api/expedientes/{$expediente->id}/cierre/visto-bueno", fiPayloadVistoBuenoFinal())
        ->assertForbidden();

    Carbon::setTestNow();
});
