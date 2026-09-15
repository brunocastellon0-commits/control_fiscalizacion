<?php

use App\Models\Actuado;
use App\Models\Asignacion;
use App\Models\CatalogoActuado;
use App\Models\CatalogoEstado;
use App\Models\Expediente;
use App\Models\Feriado;
use App\Models\ParametroPlazo;
use App\Models\Plazo;
use App\Models\Reglamento;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\DescargoFinancieroService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

function descargoSemilla(): array
{
    $rolAudFinanciero = Rol::factory()->create(['codigo' => Rol::CODIGO_AUD_FINANCIERO]);
    $rolTecnico = Rol::factory()->create(['codigo' => Rol::CODIGO_TECNICO]);

    $auditor = Usuario::factory()->create(['rol_id' => $rolAudFinanciero->id, 'activo' => true]);
    $otroAuditor = Usuario::factory()->create(['rol_id' => $rolAudFinanciero->id, 'activo' => true]);
    $tecnico = Usuario::factory()->create(['rol_id' => $rolTecnico->id, 'activo' => true]);

    $ac055 = Reglamento::factory()->create(['codigo' => 'AC_055_2018']);
    $ac022 = Reglamento::factory()->create(['codigo' => 'AC_022_2018']);

    $ejecucion = CatalogoEstado::factory()->create(['codigo' => 'EN_EJECUCION']);
    $pendienteVbFinal = CatalogoEstado::factory()->create(['codigo' => 'PENDIENTE_VISTO_BUENO_FINAL']);

    $actComunicar = CatalogoActuado::create([
        'codigo' => DescargoFinancieroService::CODIGO_ACT_COMUNICACION,
        'nombre' => 'Comunicación de Hallazgos',
        'fase' => 'INVESTIGACION',
        'rol_id' => $rolAudFinanciero->id,
        'reglamento_id' => null,
        'estado_origen_id' => null,
        'estado_destino_id' => null,
        'es_automatico' => false,
        'requiere_adjunto' => true,
    ]);

    $actRecibir = CatalogoActuado::create([
        'codigo' => DescargoFinancieroService::CODIGO_ACT_RECEPCION,
        'nombre' => 'Recepción de Descargos',
        'fase' => 'INVESTIGACION',
        'rol_id' => $rolAudFinanciero->id,
        'reglamento_id' => null,
        'estado_origen_id' => null,
        'estado_destino_id' => null,
        'es_automatico' => false,
        'requiere_adjunto' => true,
    ]);

    $actInforme = CatalogoActuado::create([
        'codigo' => DescargoFinancieroService::CODIGO_INFORME_FINANCIERO_CON_RESPONSABILIDAD,
        'nombre' => 'Informe Final con Responsabilidad',
        'fase' => 'INVESTIGACION',
        'rol_id' => $rolAudFinanciero->id,
        'reglamento_id' => null,
        'estado_origen_id' => $ejecucion->id,
        'estado_destino_id' => $pendienteVbFinal->id,
        'es_automatico' => false,
        'requiere_adjunto' => true,
    ])->refresh();

    DB::table('catalogo_actuado_roles')->insert([
        'catalogo_actuado_id' => $actComunicar->id,
        'rol_id' => $rolAudFinanciero->id,
        'reglamento_id' => $ac055->id,
    ]);
    DB::table('catalogo_actuado_roles')->insert([
        'catalogo_actuado_id' => $actRecibir->id,
        'rol_id' => $rolAudFinanciero->id,
        'reglamento_id' => $ac055->id,
    ]);
    DB::table('catalogo_actuado_roles')->insert([
        'catalogo_actuado_id' => $actInforme->id,
        'rol_id' => $rolAudFinanciero->id,
        'reglamento_id' => $ac055->id,
    ]);

    $paramDescargos = ParametroPlazo::create([
        'reglamento_id' => $ac055->id,
        'tipo_plazo' => 'DESCARGOS',
        'subtipo' => null,
        'dias_habiles' => 5,
        'base_legal' => 'AC_055_2018',
        'activo' => true,
    ]);

    return compact(
        'auditor', 'otroAuditor', 'tecnico',
        'ac055', 'ac022',
        'ejecucion', 'pendienteVbFinal',
        'actComunicar', 'actRecibir', 'actInforme',
        'paramDescargos',
    );
}

function descargoCrearExpediente(int $estadoId, int $reglamentoId, int $creadoPor): Expediente
{
    return Expediente::create([
        'nurej_code' => 'EXP-'.fake()->unique()->numberBetween(100000, 999999),
        'via' => 'TECNICO',
        'reglamento_id' => $reglamentoId,
        'estado_actual_id' => $estadoId,
        'resumen_hechos' => 'Auditoría financiera en plena ejecución con hallazgos detectados.',
        'fecha_ingreso' => now(),
        'creado_por' => $creadoPor,
    ]);
}

function descargoAsignar(Expediente $expediente, Usuario $usuario): void
{
    $actuadoOrigen = Actuado::create([
        'expediente_id' => $expediente->id,
        'catalogo_actuado_id' => CatalogoActuado::orderBy('id')->value('id'),
        'usuario_id' => $usuario->id,
        'estado_nuevo_id' => $expediente->estado_actual_id,
        'contenido' => ['descripcion' => 'Asignación inicial de auditoría financiera'],
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

function descargoAbrirPlazo(Expediente $expediente, ?string $fechaLimite = null): Plazo
{
    return Plazo::create([
        'expediente_id' => $expediente->id,
        'tipo_plazo' => 'EJECUCION',
        'dias_habiles_otorgados' => 10,
        'fecha_inicio' => now()->subDay(),
        'fecha_limite' => $fechaLimite ?? now()->addDays(10),
        'estado' => 'VIGENTE',
        'actuado_disparador_id' => $expediente->actuados()->latest('id')->value('id'),
    ]);
}

function descargoHabilitarFeriadosProcesales(): void
{
    Feriado::create(['fecha' => '2026-09-15', 'descripcion' => 'Feriado martes (test)', 'ambito' => 'NACIONAL']);
    Feriado::create(['fecha' => '2026-09-16', 'descripcion' => 'Feriado miércoles (test)', 'ambito' => 'NACIONAL']);
}

it('ciclo completo: comunicar pausa EJECUCION y abre el sub-reloj de 5 días hábiles; recibir lo cierra y reanuda el reloj', function () {
    Storage::fake('local');
    descargoHabilitarFeriadosProcesales();

    $semilla = descargoSemilla();
    $expediente = descargoCrearExpediente($semilla['ejecucion']->id, $semilla['ac055']->id, $semilla['tecnico']->id);
    descargoAsignar($expediente, $semilla['auditor']);
    $plazoEjecucion = descargoAbrirPlazo($expediente);

    Sanctum::actingAs($semilla['auditor'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/descargos/comunicar", [
        'descripcion' => 'Se comunican los hallazgos detectados en la auditoría financiera conforme al AC055 (test).',
        'adjunto' => UploadedFile::fake()->create('oficio_hallazgos.pdf', 100, 'application/pdf'),
    ])->assertCreated()
        ->assertJsonPath('data.tipo_actuado.codigo', DescargoFinancieroService::CODIGO_ACT_COMUNICACION)
        ->assertJsonPath('data.estado_anterior.codigo', 'EN_EJECUCION')
        ->assertJsonPath('data.estado_nuevo.codigo', 'EN_EJECUCION');

    $expediente->refresh();

    expect($expediente->estado_actual_id)->toBe($semilla['ejecucion']->id);

    $plazoEjecucion->refresh();
    expect($plazoEjecucion->estado)->toBe(DescargoFinancieroService::ESTADO_PLAZO_SUSPENDIDO)
        ->and($plazoEjecucion->fecha_pausa)->not->toBeNull();

    $subReloj = Plazo::where('expediente_id', $expediente->id)->where('tipo_plazo', 'DESCARGOS')->first();
    expect($subReloj)->not->toBeNull()
        ->and($subReloj->estado)->toBe(DescargoFinancieroService::ESTADO_PLAZO_VIGENTE)
        ->and($subReloj->dias_habiles_otorgados)->toBe(5)
        ->and($subReloj->fecha_limite->format('Y-m-d'))->toBe('2026-09-23');

    $this->postJson("/api/expedientes/{$expediente->id}/descargos/recibir", [
        'descripcion' => 'Se reciben los descargos presentados por los auditados dentro del término normativo (test).',
        'adjunto' => UploadedFile::fake()->create('escrito_descargos.pdf', 100, 'application/pdf'),
    ])->assertCreated()
        ->assertJsonPath('data.tipo_actuado.codigo', DescargoFinancieroService::CODIGO_ACT_RECEPCION);

    $actuadoRecibir = $expediente->actuados()
        ->whereHas('tipoActuado', fn ($query) => $query->where('codigo', DescargoFinancieroService::CODIGO_ACT_RECEPCION))
        ->latest('id')
        ->first();

    $subReloj->refresh();
    $plazoEjecucion->refresh();

    expect($subReloj->estado)->toBe(DescargoFinancieroService::ESTADO_PLAZO_CUMPLIDO)
        ->and($subReloj->actuado_cierre_id)->toBe($actuadoRecibir->id)
        ->and($plazoEjecucion->estado)->toBe(DescargoFinancieroService::ESTADO_PLAZO_VIGENTE)
        ->and($plazoEjecucion->fecha_reanudacion)->not->toBeNull()
        ->and($plazoEjecucion->fecha_limite->format('Y-m-d'))->toBe('2026-09-25');
});

it('el informe final financiero exige la fase de descargos previa y se emite tras recibirlos (Bloqueo de Salida)', function () {
    Storage::fake('local');
    descargoHabilitarFeriadosProcesales();

    $semilla = descargoSemilla();
    $expediente = descargoCrearExpediente($semilla['ejecucion']->id, $semilla['ac055']->id, $semilla['tecnico']->id);
    descargoAsignar($expediente, $semilla['auditor']);
    descargoAbrirPlazo($expediente);

    Sanctum::actingAs($semilla['auditor'], ['*']);

    $payload = [
        'catalogo_actuado_id' => $semilla['actInforme']->id,
        'descripcion' => 'Informe final de auditoría financiera con responsabilidad, listo para visto bueno (test).',
        'adjunto' => UploadedFile::fake()->create('informe_final.pdf', 100, 'application/pdf'),
    ];

    $this->postJson("/api/expedientes/{$expediente->id}/actuados", $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors('catalogo_actuado_id');

    $this->postJson("/api/expedientes/{$expediente->id}/descargos/comunicar", [
        'descripcion' => 'Comunicación previa de hallazgos antes de la emisión del informe final (test).',
        'adjunto' => UploadedFile::fake()->create('oficio_hallazgos.pdf', 100, 'application/pdf'),
    ])->assertCreated();

    $this->postJson("/api/expedientes/{$expediente->id}/descargos/recibir", [
        'descripcion' => 'Descargos recibidos y valorados por el auditor responsable (test).',
        'adjunto' => UploadedFile::fake()->create('escrito_descargos.pdf', 100, 'application/pdf'),
    ])->assertCreated();

    $this->postJson("/api/expedientes/{$expediente->id}/actuados", $payload)
        ->assertCreated()
        ->assertJsonPath('data.tipo_actuado.codigo', DescargoFinancieroService::CODIGO_INFORME_FINANCIERO_CON_RESPONSABILIDAD)
        ->assertJsonPath('data.estado_anterior.codigo', 'EN_EJECUCION')
        ->assertJsonPath('data.estado_nuevo.codigo', 'PENDIENTE_VISTO_BUENO_FINAL');

    $expediente->refresh();
    expect($expediente->estado_actual_id)->toBe($semilla['pendienteVbFinal']->id);
});

it('devuelve 403 al operador sin rol financiero, sin bandeja o fuera del AC055', function () {
    Storage::fake('local');

    $semilla = descargoSemilla();

    $sinRolFinanciero = descargoCrearExpediente($semilla['ejecucion']->id, $semilla['ac055']->id, $semilla['tecnico']->id);
    descargoAsignar($sinRolFinanciero, $semilla['tecnico']);

    Sanctum::actingAs($semilla['tecnico'], ['*']);

    $this->postJson("/api/expedientes/{$sinRolFinanciero->id}/descargos/comunicar", [
        'descripcion' => 'Intento de comunicación de hallazgos por un operador sin competencia financiera (test).',
        'adjunto' => UploadedFile::fake()->create('oficio_hallazgos.pdf', 100, 'application/pdf'),
    ])->assertForbidden();

    $sinBandeja = descargoCrearExpediente($semilla['ejecucion']->id, $semilla['ac055']->id, $semilla['tecnico']->id);
    descargoAsignar($sinBandeja, $semilla['auditor']);

    Sanctum::actingAs($semilla['otroAuditor'], ['*']);

    $this->postJson("/api/expedientes/{$sinBandeja->id}/descargos/comunicar", [
        'descripcion' => 'Intento de comunicación sobre un expediente de la bandeja de otro auditor (test).',
        'adjunto' => UploadedFile::fake()->create('oficio_hallazgos.pdf', 100, 'application/pdf'),
    ])->assertForbidden();

    $fueraDeAc055 = descargoCrearExpediente($semilla['ejecucion']->id, $semilla['ac022']->id, $semilla['tecnico']->id);
    descargoAsignar($fueraDeAc055, $semilla['auditor']);

    Sanctum::actingAs($semilla['auditor'], ['*']);

    $this->postJson("/api/expedientes/{$fueraDeAc055->id}/descargos/comunicar", [
        'descripcion' => 'Intento de fase de descargos fuera del acuerdo AC055 que la establece (test).',
        'adjunto' => UploadedFile::fake()->create('oficio_hallazgos.pdf', 100, 'application/pdf'),
    ])->assertForbidden();
});

it('devuelve 422 sin adjunto, sin description valida, al recibir sin comunicar y al duplicar la fase', function () {
    Storage::fake('local');

    $semilla = descargoSemilla();
    $expediente = descargoCrearExpediente($semilla['ejecucion']->id, $semilla['ac055']->id, $semilla['tecnico']->id);
    descargoAsignar($expediente, $semilla['auditor']);
    descargoAbrirPlazo($expediente);

    Sanctum::actingAs($semilla['auditor'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/descargos/comunicar", [
        'descripcion' => 'Cort',
        'adjunto' => UploadedFile::fake()->create('oficio_hallazgos.pdf', 100, 'application/pdf'),
    ])->assertStatus(422)
        ->assertJsonValidationErrors('descripcion');

    $this->postJson("/api/expedientes/{$expediente->id}/descargos/comunicar", [
        'descripcion' => 'La comunicación de hallazgos sin oficio adjunto no puede tramitarse (test).',
    ])->assertStatus(422)
        ->assertJsonValidationErrors('adjunto');

    $sinComunicar = descargoCrearExpediente($semilla['ejecucion']->id, $semilla['ac055']->id, $semilla['tecnico']->id);
    descargoAsignar($sinComunicar, $semilla['auditor']);

    $this->postJson("/api/expedientes/{$sinComunicar->id}/descargos/recibir", [
        'descripcion' => 'Se intenta recibir descargos sin haberlos comunicado previamente (test).',
        'adjunto' => UploadedFile::fake()->create('escrito_descargos.pdf', 100, 'application/pdf'),
    ])->assertStatus(422)
        ->assertJsonValidationErrors('expediente');

    $this->postJson("/api/expedientes/{$expediente->id}/descargos/comunicar", [
        'descripcion' => 'Comunicación válida en la primera ronda de hallazgos (test).',
        'adjunto' => UploadedFile::fake()->create('oficio_hallazgos.pdf', 100, 'application/pdf'),
    ])->assertCreated();

    $this->postJson("/api/expedientes/{$expediente->id}/descargos/comunicar", [
        'descripcion' => 'Segunda comunicación de hallazgos para duplicar la fase (test).',
        'adjunto' => UploadedFile::fake()->create('oficio_hallazgos.pdf', 100, 'application/pdf'),
    ])->assertStatus(422)
        ->assertJsonValidationErrors('expediente');

    $this->postJson("/api/expedientes/{$expediente->id}/descargos/recibir", [
        'descripcion' => 'Recepción válida de la primera ronda de descargos (test).',
        'adjunto' => UploadedFile::fake()->create('escrito_descargos.pdf', 100, 'application/pdf'),
    ])->assertCreated();

    $this->postJson("/api/expedientes/{$expediente->id}/descargos/recibir", [
        'descripcion' => 'Segunda recepción de descargos sin sub-reloj abierto (test).',
        'adjunto' => UploadedFile::fake()->create('escrito_descargos.pdf', 100, 'application/pdf'),
    ])->assertStatus(422)
        ->assertJsonValidationErrors('expediente');
});

it('cancela la deuda de plazo: al reanudar sin días hábiles restantes el límite queda en el mismo día', function () {
    Storage::fake('local');
    descargoHabilitarFeriadosProcesales();

    $semilla = descargoSemilla();
    $expediente = descargoCrearExpediente($semilla['ejecucion']->id, $semilla['ac055']->id, $semilla['tecnico']->id);
    descargoAsignar($expediente, $semilla['auditor']);
    $plazoEjecucion = descargoAbrirPlazo($expediente, now()->endOfDay()->toDateString());

    Sanctum::actingAs($semilla['auditor'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/descargos/comunicar", [
        'descripcion' => 'Comunicación de hallazgos cuando el reloj ya estaba por vencer hoy mismo (test).',
        'adjunto' => UploadedFile::fake()->create('oficio_hallazgos.pdf', 100, 'application/pdf'),
    ])->assertCreated();

    $this->postJson("/api/expedientes/{$expediente->id}/descargos/recibir", [
        'descripcion' => 'Recepción de descargos con el reloj principal sin días hábiles sobrantes (test).',
        'adjunto' => UploadedFile::fake()->create('escrito_descargos.pdf', 100, 'application/pdf'),
    ])->assertCreated();

    $plazoEjecucion->refresh();

    expect($plazoEjecucion->estado)->toBe(DescargoFinancieroService::ESTADO_PLAZO_VIGENTE)
        ->and($plazoEjecucion->fecha_limite->format('Y-m-d'))->toBe(Carbon\Carbon::today()->format('Y-m-d'));
});
