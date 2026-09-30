<?php

use App\Models\Actuado;
use App\Models\Asignacion;
use App\Models\CatalogoActuado;
use App\Models\CatalogoEstado;
use App\Models\Expediente;
use App\Models\Impugnacion;
use App\Models\ParametroPlazo;
use App\Models\Reglamento;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\ActuadoService;
use App\Services\ImpugnacionService;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

function fjSemilla(): array
{
    $rolEncargada = Rol::factory()->create(['codigo' => Rol::CODIGO_ENCARGADA]);
    $rolJuridico = Rol::factory()->create(['codigo' => Rol::CODIGO_AUD_JURIDICO]);
    $rolTecnico = Rol::factory()->create(['codigo' => Rol::CODIGO_TECNICO]);

    $encargada = Usuario::factory()->create(['rol_id' => $rolEncargada->id, 'activo' => true]);
    $juridico = Usuario::factory()->create(['rol_id' => $rolJuridico->id, 'activo' => true]);
    $otroJuridico = Usuario::factory()->create(['rol_id' => $rolJuridico->id, 'activo' => true]);
    $tecnico = Usuario::factory()->create(['rol_id' => $rolTecnico->id, 'activo' => true]);

    $reglamento = Reglamento::factory()->create();

    $evaluacion = CatalogoEstado::factory()->create(['codigo' => 'EN_EVALUACION']);
    $rechazado = CatalogoEstado::factory()->create(['codigo' => 'RECHAZADO']);
    $enImpugnacion = CatalogoEstado::factory()->create(['codigo' => 'EN_IMPUGNACION']);
    $ejecucion = CatalogoEstado::factory()->create(['codigo' => 'EN_EJECUCION']);

    $actRechazo = CatalogoActuado::create([
        'codigo' => ImpugnacionService::CODIGO_ACT_RECHAZO,
        'nombre' => 'Rechazo',
        'fase' => 'ADMISIBILIDAD',
        'rol_id' => $rolJuridico->id,
        'reglamento_id' => null,
        'estado_origen_id' => $evaluacion->id,
        'estado_destino_id' => $rechazado->id,
        'es_automatico' => false,
        'requiere_adjunto' => false,
    ]);

    $actRemitir = CatalogoActuado::create([
        'codigo' => ImpugnacionService::CODIGO_ACT_REMITIR,
        'nombre' => 'Remisión de Impugnación',
        'fase' => 'ADMISIBILIDAD',
        'rol_id' => $rolJuridico->id,
        'reglamento_id' => null,
        'estado_origen_id' => $rechazado->id,
        'estado_destino_id' => $enImpugnacion->id,
        'es_automatico' => false,
        'requiere_adjunto' => false,
    ]);

    // Réplica del seeder (CatalogoActuadoSeeder:62,113-115): informe jurídico
    // con estado_destino_id NULL y pivote solo para AUD_JURIDICO.
    $actInforme = CatalogoActuado::create([
        'codigo' => 'ACT_INFORME_FINAL',
        'nombre' => 'Informe Final',
        'fase' => 'INVESTIGACION',
        'rol_id' => $rolJuridico->id,
        'reglamento_id' => null,
        'estado_origen_id' => $ejecucion->id,
        'estado_destino_id' => null,
        'es_automatico' => false,
        'requiere_adjunto' => true,
    ])->refresh();

    DB::table('catalogo_actuado_roles')->insert([
        'catalogo_actuado_id' => $actInforme->id,
        'rol_id' => $rolJuridico->id,
        'reglamento_id' => null,
    ]);

    ParametroPlazo::create([
        'reglamento_id' => $reglamento->id,
        'tipo_plazo' => 'IMPUGNACION_REMITIR',
        'subtipo' => null,
        'dias_habiles' => 1,
        'base_legal' => 'RN-08',
        'activo' => true,
    ]);

    ParametroPlazo::create([
        'reglamento_id' => $reglamento->id,
        'tipo_plazo' => 'IMPUGNACION_RESOLVER',
        'subtipo' => null,
        'dias_habiles' => 3,
        'base_legal' => 'RN-08',
        'activo' => true,
    ]);

    return compact(
        'encargada', 'juridico', 'otroJuridico', 'tecnico',
        'reglamento',
        'evaluacion', 'rechazado', 'enImpugnacion', 'ejecucion',
        'actRechazo', 'actRemitir', 'actInforme',
    );
}

function fjCrearExpediente(int $estadoId, int $reglamentoId, int $creadoPor): Expediente
{
    return Expediente::create([
        'nurej_code' => 'EXP-'.fake()->unique()->numberBetween(100000, 999999),
        'via' => 'JURIDICO',
        'reglamento_id' => $reglamentoId,
        'estado_actual_id' => $estadoId,
        'fecha_ingreso' => now(),
        'creado_por' => $creadoPor,
    ]);
}

function fjAsignar(Expediente $expediente, Usuario $usuario): void
{
    $actuadoOrigen = Actuado::create([
        'expediente_id' => $expediente->id,
        'catalogo_actuado_id' => CatalogoActuado::orderBy('id')->value('id'),
        'usuario_id' => $usuario->id,
        'estado_nuevo_id' => $expediente->estado_actual_id,
        'contenido' => ['descripcion' => 'Asignación inicial de la semilla (test jurídico)'],
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

function fjEmitirRechazo(array $semilla, Expediente $expediente, Usuario $emisor): void
{
    app(ActuadoService::class)->registerActuado(
        expediente: $expediente,
        catalogoActuado: $semilla['actRechazo'],
        emisor: $emisor,
        descripcion: 'Rechazo por requisito crítico ausente (test jurídico).',
        metadatos: ['tipo' => 'ACTUADO'],
    );
}

it('permite al Auditor Jurídico rechazar y remitir la impugnación a la Encargada (RN-08)', function () {
    $s = fjSemilla();
    $expediente = fjCrearExpediente($s['evaluacion']->id, $s['reglamento']->id, $s['juridico']->id);
    fjAsignar($expediente, $s['juridico']);
    fjEmitirRechazo($s, $expediente, $s['juridico']);

    expect($expediente->refresh()->estado_actual_id)->toBe($s['rechazado']->id);

    Sanctum::actingAs($s['juridico'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/impugnacion/remitir", [
        'descripcion' => 'Remisión a la Encargada para resolución de la impugnación (test).',
    ])->assertCreated();

    $expediente->refresh();

    expect($expediente->estado_actual_id)->toBe($s['enImpugnacion']->id);

    $asignacionActiva = $expediente->asignacionActiva()->first();
    expect($asignacionActiva)->not->toBeNull()
        ->and($asignacionActiva->usuario_id)->toBe($s['encargada']->id);

    $plazoResolver = $expediente->plazos()->where('tipo_plazo', 'IMPUGNACION_RESOLVER')->first();
    expect($plazoResolver)->not->toBeNull()
        ->and($plazoResolver->estado)->toBe('VIGENTE')
        ->and($plazoResolver->dias_habiles_otorgados)->toBe(3);

    $impugnacion = $expediente->impugnaciones()->first();
    expect($impugnacion)->not->toBeNull()
        ->and($impugnacion->resultado)->toBe(ImpugnacionService::RESULTADO_PENDIENTE);
});

it('no permite emitir el informe jurídico: actuados.estado_nuevo_id es NOT NULL y el catálogo declara destino null', function () {
    Storage::fake('local');

    $s = fjSemilla();
    $expediente = fjCrearExpediente($s['ejecucion']->id, $s['reglamento']->id, $s['juridico']->id);
    fjAsignar($expediente, $s['juridico']);

    expect($s['actInforme']->estado_destino_id)->toBeNull();

    Sanctum::actingAs($s['juridico'], ['*']);

    $actuadosPrevios = Actuado::where('expediente_id', $expediente->id)->count();

    $response = $this->postJson("/api/expedientes/{$expediente->id}/actuados", [
        'catalogo_actuado_id' => $s['actInforme']->id,
        'descripcion' => 'Informe de Auditoría Jurídica con responsabilidad (test).',
        'adjunto' => UploadedFile::fake()->create('informe_juridico.pdf', 100, 'application/pdf'),
    ]);

    $response->assertStatus(500);

    expect($response->exception)->toBeInstanceOf(QueryException::class)
        ->and($response->exception->getMessage())->toContain('estado_nuevo_id');

    expect(Actuado::where('expediente_id', $expediente->id)->count())->toBe($actuadosPrevios)
        ->and($expediente->refresh()->estado_actual_id)->toBe($s['ejecucion']->id);
});

it('bloquea con 403 al Técnico emitir el informe jurídico (catálogo exclusivo del Auditor Jurídico)', function () {
    Storage::fake('local');

    $s = fjSemilla();
    $expediente = fjCrearExpediente($s['ejecucion']->id, $s['reglamento']->id, $s['tecnico']->id);
    fjAsignar($expediente, $s['tecnico']);

    Sanctum::actingAs($s['tecnico'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/actuados", [
        'catalogo_actuado_id' => $s['actInforme']->id,
        'descripcion' => 'Intento de informe jurídico por un Técnico (test).',
        'adjunto' => UploadedFile::fake()->create('informe_juridico.pdf', 100, 'application/pdf'),
    ])->assertForbidden();
});

it('bloquea con 403 al Auditor Jurídico emitir el informe en un expediente ajeno (RF-03)', function () {
    Storage::fake('local');

    $s = fjSemilla();

    $propio = fjCrearExpediente($s['ejecucion']->id, $s['reglamento']->id, $s['juridico']->id);
    fjAsignar($propio, $s['juridico']);

    $ajeno = fjCrearExpediente($s['ejecucion']->id, $s['reglamento']->id, $s['otroJuridico']->id);
    fjAsignar($ajeno, $s['otroJuridico']);

    Sanctum::actingAs($s['juridico'], ['*']);

    $this->postJson("/api/expedientes/{$ajeno->id}/actuados", [
        'catalogo_actuado_id' => $s['actInforme']->id,
        'descripcion' => 'Intento de informe sobre un expediente ajeno (test).',
        'adjunto' => UploadedFile::fake()->create('informe_juridico.pdf', 100, 'application/pdf'),
    ])->assertForbidden();

    $this->postJson("/api/expedientes/{$propio->id}/actuados", [
        'catalogo_actuado_id' => $s['actInforme']->id,
        'descripcion' => 'Confirmación de que el propio sí está en su bandeja (test).',
        'adjunto' => UploadedFile::fake()->create('informe_juridico.pdf', 100, 'application/pdf'),
    ])->assertStatus(500);
});

it('bloquea con 403 al Auditor Jurídico sin asignación activa emitir el informe', function () {
    Storage::fake('local');

    $s = fjSemilla();
    $expediente = fjCrearExpediente($s['ejecucion']->id, $s['reglamento']->id, $s['juridico']->id);

    Sanctum::actingAs($s['juridico'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/actuados", [
        'catalogo_actuado_id' => $s['actInforme']->id,
        'descripcion' => 'Intento de informe sin asignación en la bandeja (test).',
        'adjunto' => UploadedFile::fake()->create('informe_juridico.pdf', 100, 'application/pdf'),
    ])->assertForbidden();
});

it('valida 422 cuando el informe jurídico llega sin el adjunto obligatorio', function () {
    $s = fjSemilla();
    $expediente = fjCrearExpediente($s['ejecucion']->id, $s['reglamento']->id, $s['juridico']->id);
    fjAsignar($expediente, $s['juridico']);

    Sanctum::actingAs($s['juridico'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/actuados", [
        'catalogo_actuado_id' => $s['actInforme']->id,
        'descripcion' => 'Informe jurídico sin el documento requerido (test).',
    ])->assertStatus(422)->assertJsonValidationErrors('adjunto');
});

it('valida 422 cuando el adjunto del informe jurídico no es PDF', function () {
    $s = fjSemilla();
    $expediente = fjCrearExpediente($s['ejecucion']->id, $s['reglamento']->id, $s['juridico']->id);
    fjAsignar($expediente, $s['juridico']);

    Sanctum::actingAs($s['juridico'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/actuados", [
        'catalogo_actuado_id' => $s['actInforme']->id,
        'descripcion' => 'Informe jurídico con adjunto de tipo no permitido (test).',
        'adjunto' => UploadedFile::fake()->create('informe_juridico.txt', 10),
    ])->assertStatus(422)->assertJsonValidationErrors('adjunto');
});

it('bloquea con 403 la doble remisión de impugnación: el segundo envío queda fuera de estado y solo se crea una impugnación', function () {
    $s = fjSemilla();
    $expediente = fjCrearExpediente($s['evaluacion']->id, $s['reglamento']->id, $s['juridico']->id);
    fjAsignar($expediente, $s['juridico']);
    fjEmitirRechazo($s, $expediente, $s['juridico']);

    Sanctum::actingAs($s['juridico'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/impugnacion/remitir", [
        'descripcion' => 'Primer envío de la impugnación a la Encargada (test).',
    ])->assertCreated();

    $this->postJson("/api/expedientes/{$expediente->id}/impugnacion/remitir", [
        'descripcion' => 'Segundo envío duplicado de la impugnación (test).',
    ])->assertForbidden();

    expect(Impugnacion::where('expediente_id', $expediente->id)->count())->toBe(1)
        ->and($expediente->refresh()->estado_actual_id)->toBe($s['enImpugnacion']->id);
});

it('bloquea con 403 remitir la impugnación cuando el expediente no está en RECHAZADO (estado inválido)', function () {
    $s = fjSemilla();
    $expediente = fjCrearExpediente($s['evaluacion']->id, $s['reglamento']->id, $s['juridico']->id);
    fjAsignar($expediente, $s['juridico']);

    Sanctum::actingAs($s['juridico'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/impugnacion/remitir", [
        'descripcion' => 'Remisión intentada antes de que exista un rechazo (test).',
    ])->assertForbidden();

    expect(Impugnacion::where('expediente_id', $expediente->id)->count())->toBe(0);
});
