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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

function unidadExternaSemilla(): array
{
    $rolEncargada = Rol::factory()->create(['codigo' => Rol::CODIGO_ENCARGADA]);
    $rolTecnico = Rol::factory()->create(['codigo' => Rol::CODIGO_TECNICO]);
    $rolAdmin = Rol::factory()->create(['codigo' => Rol::CODIGO_ADMIN]);

    $encargada = Usuario::factory()->create(['rol_id' => $rolEncargada->id, 'activo' => true]);
    $tecnico = Usuario::factory()->create(['rol_id' => $rolTecnico->id, 'activo' => true]);
    $admin = Usuario::factory()->create(['rol_id' => $rolAdmin->id, 'activo' => true]);

    $ac022 = Reglamento::factory()->create(['codigo' => 'AC_022_2018']);

    $evaluacion = CatalogoEstado::factory()->create(['codigo' => 'EN_EVALUACION']);
    $planificacion = CatalogoEstado::factory()->create(['codigo' => 'EN_PLANIFICACION']);
    $remitidoUnidad = CatalogoEstado::factory()->create([
        'codigo' => TransferenciaUnidadService::ESTADO_REMITIDO_UNIDAD_EXTERNA,
    ]);

    $unidadDestino = Unidad::create(['codigo' => 'TRANSPARENCIA', 'nombre' => 'Unidad de Transparencia (Ley N° 974)']);
    $unidadOtra = Unidad::create(['codigo' => 'MINISTERIO_PUBLICO', 'nombre' => 'Ministerio Público']);
    $unidadInactiva = Unidad::create(['codigo' => 'REGIMEN_DISCIPLINARIO', 'nombre' => 'Régimen Disciplinario', 'activa' => false]);

    $actBase = CatalogoActuado::create([
        'codigo' => 'ACT_REGISTRO_DIGITALIZACION',
        'nombre' => 'Registro y Digitalización',
        'fase' => 'REGISTRO',
        'rol_id' => $rolTecnico->id,
        'reglamento_id' => null,
        'estado_origen_id' => null,
        'estado_destino_id' => null,
        'es_automatico' => false,
        'requiere_adjunto' => false,
    ]);

    $actRemision = CatalogoActuado::create([
        'codigo' => TransferenciaUnidadService::CODIGO_ACT_REMISION_UNIDAD,
        'nombre' => 'Remisión a Unidad Externa',
        'fase' => 'INVESTIGACION',
        'rol_id' => $rolEncargada->id,
        'reglamento_id' => null,
        'estado_origen_id' => null,
        'estado_destino_id' => $remitidoUnidad->id,
        'es_automatico' => false,
        'requiere_adjunto' => false,
    ]);

    $actRecepcion = CatalogoActuado::create([
        'codigo' => TransferenciaUnidadService::CODIGO_ACT_RECEPCION_UNIDAD,
        'nombre' => 'Recepción de Unidad Externa',
        'fase' => 'INVESTIGACION',
        'rol_id' => $rolEncargada->id,
        'reglamento_id' => null,
        'estado_origen_id' => $remitidoUnidad->id,
        'estado_destino_id' => null,
        'es_automatico' => false,
        'requiere_adjunto' => false,
    ]);

    // Pivote como lo deja CatalogoActuadoSeeder: solo la Encargada.
    foreach ([$actRemision, $actRecepcion] as $actuado) {
        DB::table('catalogo_actuado_roles')->insert([
            'catalogo_actuado_id' => $actuado->id,
            'rol_id' => $rolEncargada->id,
            'reglamento_id' => null,
        ]);
    }

    return compact(
        'rolEncargada', 'encargada', 'tecnico', 'admin', 'ac022',
        'evaluacion', 'planificacion', 'remitidoUnidad',
        'unidadDestino', 'unidadOtra', 'unidadInactiva',
        'actBase', 'actRemision', 'actRecepcion',
    );
}

function unidadCrearExpediente(int $estadoId, int $reglamentoId, int $creadoPor): Expediente
{
    return Expediente::create([
        'nurej_code' => 'EXP-'.fake()->unique()->numberBetween(100000, 999999),
        'via' => 'TECNICO',
        'reglamento_id' => $reglamentoId,
        'estado_actual_id' => $estadoId,
        'resumen_hechos' => 'Causa destinada al flujo de unidades inter-unidad.',
        'fecha_ingreso' => now(),
        'creado_por' => $creadoPor,
    ]);
}

function unidadAsignar(Expediente $expediente, Usuario $usuario): void
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

function unidadAbrirPlazo(Expediente $expediente): Plazo
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

function unidadRemitir(array $semilla, Expediente $expediente, ?int $unidadId = null, ?string $motivo = null, ?UploadedFile $adjunto = null)
{
    return test()->postJson("/api/expedientes/{$expediente->id}/remitir-unidad", [
        'unidad_id' => $unidadId ?? $semilla['unidadDestino']->id,
        'motivo' => $motivo ?? 'Remisión física a la unidad externa para trámite competente (RF-05).',
        'adjunto' => $adjunto,
    ]);
}

it('CTR-04 remite la causa: guarda estado_previo_id, congela relojes y la saca de la bandeja del operador', function () {
    Storage::fake('local');

    $semilla = unidadExternaSemilla();
    $expediente = unidadCrearExpediente($semilla['evaluacion']->id, $semilla['ac022']->id, $semilla['tecnico']->id);
    unidadAsignar($expediente, $semilla['tecnico']);
    $plazo = unidadAbrirPlazo($expediente);

    Sanctum::actingAs($semilla['tecnico'], ['*']);
    $bandejaAntes = $this->getJson('/api/bandeja')->assertOk();
    $idsAntes = collect($bandejaAntes->json('data'))->pluck('id')->all();
    expect($idsAntes)->toContain($expediente->id);

    Sanctum::actingAs($semilla['encargada'], ['*']);
    unidadRemitir(
        $semilla,
        $expediente,
        adjunto: UploadedFile::fake()->create('oficio_remision.pdf', 100, 'application/pdf'),
    )
        ->assertCreated()
        ->assertJsonStructure(['transferencia_id', 'estado'])
        ->assertJsonPath('estado', TransferenciaUnidadService::ESTADO_REMITIDO_UNIDAD_EXTERNA);

    $expediente->refresh();
    expect($expediente->estado_actual_id)->toBe($semilla['remitidoUnidad']->id);

    $transferencia = TransferenciaUnidad::query()
        ->where('expediente_id', $expediente->id)
        ->latest('id')
        ->first();
    expect($transferencia)->not->toBeNull()
        ->and($transferencia->tipo)->toBe('REMISION')
        ->and($transferencia->unidad_id)->toBe($semilla['unidadDestino']->id)
        ->and($transferencia->estado_previo_id)->toBe($semilla['evaluacion']->id)
        ->and($transferencia->actuado_id)->not->toBeNull()
        ->and($transferencia->motivo)->toBe('Remisión física a la unidad externa para trámite competente (RF-05).');

    $actuado = $transferencia->actuado;
    expect($actuado)->not->toBeNull()
        ->and($actuado->tipoActuado->codigo)->toBe(TransferenciaUnidadService::CODIGO_ACT_REMISION_UNIDAD)
        ->and($actuado->usuario_id)->toBe($semilla['encargada']->id)
        ->and($actuado->estado_anterior_id)->toBe($semilla['evaluacion']->id)
        ->and($actuado->estado_nuevo_id)->toBe($semilla['remitidoUnidad']->id)
        ->and($actuado->hash_anterior)->not->toBeNull()
        ->and($actuado->hash_actuado)->toMatch('/^[0-9a-f]{64}$/')
        ->and($actuado->contenido['plazos_suspendidos'])->toBe([$plazo->id])
        ->and($actuado->adjuntos()->count())->toBe(1);

    $plazo->refresh();
    expect($plazo->estado)->toBe(TransferenciaUnidadService::ESTADO_PLAZO_SUSPENDIDO)
        ->and($plazo->fecha_pausa)->not->toBeNull()
        ->and($plazo->fecha_reanudacion)->toBeNull()
        ->and($plazo->actuado_cierre_id)->toBeNull();

    $asignacion = $expediente->asignacionActiva()->first();
    expect($asignacion)->not->toBeNull()
        ->and($asignacion->usuario_id)->toBe($semilla['tecnico']->id)
        ->and($asignacion->activa)->toBeTrue();

    Sanctum::actingAs($semilla['tecnico'], ['*']);
    $bandejaDespues = $this->getJson('/api/bandeja')->assertOk();
    $idsDespues = collect($bandejaDespues->json('data'))->pluck('id')->all();
    expect($idsDespues)->not->toContain($expediente->id);
});

it('CTR-05 restaura el estado exacto, reanuda los relojes sin pérdida de días hábiles y devuelve la causa a la bandeja', function () {
    Storage::fake('local');

    $semilla = unidadExternaSemilla();
    $expediente = unidadCrearExpediente($semilla['evaluacion']->id, $semilla['ac022']->id, $semilla['tecnico']->id);
    unidadAsignar($expediente, $semilla['tecnico']);
    $plazo = unidadAbrirPlazo($expediente);

    Sanctum::actingAs($semilla['encargada'], ['*']);
    unidadRemitir($semilla, $expediente)->assertCreated();

    $plazo->refresh();
    $calculadora = app(PlazoCalculatorService::class);
    $diasRestantes = $calculadora->businessDaysBetween($plazo->fecha_pausa, $plazo->fecha_limite);
    $fechaLimiteEsperada = $calculadora->calculateDueDate(now(), $diasRestantes);

    $this->postJson("/api/expedientes/{$expediente->id}/registrar-recepcion", [
        'unidad_origen_id' => $semilla['unidadDestino']->id,
        'motivo' => 'Recepción física desde la unidad externa con el trámite cumplido (RF-05).',
    ])
        ->assertOk()
        ->assertJsonPath('estado', 'EN_EVALUACION');

    $expediente->refresh();
    expect($expediente->estado_actual_id)->toBe($semilla['evaluacion']->id);

    $recepcion = TransferenciaUnidad::query()
        ->where('expediente_id', $expediente->id)
        ->latest('id')
        ->first();
    expect($recepcion->tipo)->toBe('RECEPCION')
        ->and($recepcion->unidad_id)->toBe($semilla['unidadDestino']->id)
        ->and($recepcion->estado_previo_id)->toBeNull()
        ->and($recepcion->actuado_id)->not->toBeNull();

    $actuadoRecepcion = $recepcion->actuado;
    $remision = TransferenciaUnidad::query()
        ->where('expediente_id', $expediente->id)
        ->where('tipo', 'REMISION')
        ->firstOrFail();
    $actuadoRemision = Actuado::find($remision->actuado_id);

    expect($actuadoRecepcion)->not->toBeNull()
        ->and($actuadoRecepcion->tipoActuado->codigo)->toBe(TransferenciaUnidadService::CODIGO_ACT_RECEPCION_UNIDAD)
        ->and($actuadoRecepcion->estado_anterior_id)->toBe($semilla['remitidoUnidad']->id)
        ->and($actuadoRecepcion->estado_nuevo_id)->toBe($semilla['evaluacion']->id)
        ->and($actuadoRecepcion->hash_anterior)->toBe($actuadoRemision->hash_actuado)
        ->and($actuadoRecepcion->contenido['plazos_reanudados'])->toBe([$plazo->id]);

    $plazo->refresh();
    expect($plazo->estado)->toBe(TransferenciaUnidadService::ESTADO_PLAZO_VIGENTE)
        ->and($plazo->fecha_reanudacion)->not->toBeNull()
        ->and($plazo->fecha_pausa)->not->toBeNull()
        ->and($plazo->fecha_limite->format('Y-m-d'))->toBe($fechaLimiteEsperada->format('Y-m-d'));

    Sanctum::actingAs($semilla['tecnico'], ['*']);
    $bandeja = $this->getJson('/api/bandeja')->assertOk();
    $ids = collect($bandeja->json('data'))->pluck('id')->all();
    expect($ids)->toContain($expediente->id);
});

it('la recepción restaura la fase previa exacta de la remisión abierta sin asumir un estado fijo', function () {
    Storage::fake('local');

    $semilla = unidadExternaSemilla();
    $expediente = unidadCrearExpediente($semilla['planificacion']->id, $semilla['ac022']->id, $semilla['tecnico']->id);
    unidadAsignar($expediente, $semilla['tecnico']);

    Sanctum::actingAs($semilla['encargada'], ['*']);
    unidadRemitir($semilla, $expediente)->assertCreated();

    $this->postJson("/api/expedientes/{$expediente->id}/registrar-recepcion", [
        'unidad_origen_id' => $semilla['unidadDestino']->id,
        'motivo' => 'Devolución desde la unidad externa sin observaciones (RF-05).',
    ])
        ->assertOk()
        ->assertJsonPath('estado', 'EN_PLANIFICACION');

    expect($expediente->refresh()->estado_actual_id)->toBe($semilla['planificacion']->id);
});

it('CTR-04 y CTR-05 responden 403 para operador y ADMIN; la recepción exige causa remitida', function () {
    $semilla = unidadExternaSemilla();
    $expediente = unidadCrearExpediente($semilla['evaluacion']->id, $semilla['ac022']->id, $semilla['tecnico']->id);
    unidadAsignar($expediente, $semilla['tecnico']);

    $payload = [
        'unidad_id' => $semilla['unidadDestino']->id,
        'unidad_origen_id' => $semilla['unidadDestino']->id,
        'motivo' => 'Intento de remisión sin facultad de la Encargada (RF-05).',
    ];

    Sanctum::actingAs($semilla['tecnico'], ['*']);
    $this->postJson("/api/expedientes/{$expediente->id}/remitir-unidad", $payload)->assertForbidden();
    $this->postJson("/api/expedientes/{$expediente->id}/registrar-recepcion", $payload)->assertForbidden();

    Sanctum::actingAs($semilla['admin'], ['*']);
    $this->postJson("/api/expedientes/{$expediente->id}/remitir-unidad", $payload)->assertForbidden();
    $this->postJson("/api/expedientes/{$expediente->id}/registrar-recepcion", $payload)->assertForbidden();

    expect($expediente->refresh()->estado_actual_id)->toBe($semilla['evaluacion']->id);
});

it('no permite remitir una causa ya remitida (política remitirUnidad) ni recibir sin remisión abierta', function () {
    Storage::fake('local');

    $semilla = unidadExternaSemilla();
    $expediente = unidadCrearExpediente($semilla['evaluacion']->id, $semilla['ac022']->id, $semilla['tecnico']->id);
    unidadAsignar($expediente, $semilla['tecnico']);

    Sanctum::actingAs($semilla['encargada'], ['*']);

    // Recibir sin que la causa esté remitida: política registrarRecepcion -> 403.
    $this->postJson("/api/expedientes/{$expediente->id}/registrar-recepcion", [
        'unidad_origen_id' => $semilla['unidadDestino']->id,
        'motivo' => 'Recepción indebida sin remisión previa (RF-05).',
    ])->assertForbidden();

    unidadRemitir($semilla, $expediente)->assertCreated();

    // Ya remitida: remitir de nuevo -> 403.
    unidadRemitir($semilla, $expediente)->assertForbidden();

    expect($expediente->refresh()->estado_actual_id)->toBe($semilla['remitidoUnidad']->id);
    expect(TransferenciaUnidad::query()->where('expediente_id', $expediente->id)->count())->toBe(1);
});

it('valida unidad inexistente/inactiva, motivo corto y bloquea la recepción cruzada por unidad', function () {
    Storage::fake('local');

    $semilla = unidadExternaSemilla();
    $expediente = unidadCrearExpediente($semilla['evaluacion']->id, $semilla['ac022']->id, $semilla['tecnico']->id);
    unidadAsignar($expediente, $semilla['tecnico']);

    Sanctum::actingAs($semilla['encargada'], ['*']);

    unidadRemitir($semilla, $expediente, unidadId: 999999)->assertUnprocessable()
        ->assertJsonValidationErrors('unidad_id');
    unidadRemitir($semilla, $expediente, unidadId: $semilla['unidadInactiva']->id)->assertUnprocessable()
        ->assertJsonValidationErrors('unidad_id');
    unidadRemitir($semilla, $expediente, motivo: 'motivo corto')->assertUnprocessable()
        ->assertJsonValidationErrors('motivo');

    unidadRemitir($semilla, $expediente)->assertCreated();

    // Recepción cruzada: otra unidad distinta de la remisión abierta -> 422.
    $this->postJson("/api/expedientes/{$expediente->id}/registrar-recepcion", [
        'unidad_origen_id' => $semilla['unidadOtra']->id,
        'motivo' => 'Recepción cruzada con una unidad distinta a la remitida (RF-05).',
    ])->assertUnprocessable()->assertJsonValidationErrors('unidad_origen_id');

    expect($expediente->refresh()->estado_actual_id)->toBe($semilla['remitidoUnidad']->id);

    // Con la unidad correcta la recepción procede.
    $this->postJson("/api/expedientes/{$expediente->id}/registrar-recepcion", [
        'unidad_origen_id' => $semilla['unidadDestino']->id,
        'motivo' => 'Recepción conforme con la unidad destino de la remisión (RF-05).',
    ])->assertOk()->assertJsonPath('estado', 'EN_EVALUACION');
});

it('el catálogo habilita el par Remisión/Recepción exclusivamente para el rol Encargada', function () {
    $semilla = unidadExternaSemilla();

    foreach ([TransferenciaUnidadService::CODIGO_ACT_REMISION_UNIDAD, TransferenciaUnidadService::CODIGO_ACT_RECEPCION_UNIDAD] as $codigo) {
        $actuado = CatalogoActuado::where('codigo', $codigo)->firstOrFail();

        $pivotes = DB::table('catalogo_actuado_roles')
            ->where('catalogo_actuado_id', $actuado->id)
            ->get();

        expect($pivotes->count())->toBe(1)
            ->and($pivotes->first()->rol_id)->toBe($semilla['rolEncargada']->id)
            ->and($pivotes->first()->reglamento_id)->toBeNull();
    }
});
