<?php

use App\Models\Actuado;
use App\Models\Asignacion;
use App\Models\CatalogoActuado;
use App\Models\CatalogoEstado;
use App\Models\Expediente;
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

function ffSemilla(): array
{
    $rolAudFinanciero = Rol::factory()->create(['codigo' => Rol::CODIGO_AUD_FINANCIERO]);
    $rolTecnico = Rol::factory()->create(['codigo' => Rol::CODIGO_TECNICO]);

    $auditor = Usuario::factory()->create(['rol_id' => $rolAudFinanciero->id, 'activo' => true]);
    $tecnico = Usuario::factory()->create(['rol_id' => $rolTecnico->id, 'activo' => true]);

    $ac055 = Reglamento::factory()->create(['codigo' => 'AC_055_2018']);

    $ejecucion = CatalogoEstado::factory()->create(['codigo' => 'EN_EJECUCION']);
    $admision = CatalogoEstado::factory()->create(['codigo' => 'ADMISION']);
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

    $actInformeSin = CatalogoActuado::create([
        'codigo' => DescargoFinancieroService::CODIGO_INFORME_FINANCIERO_SIN_RESPONSABILIDAD,
        'nombre' => 'Informe Final sin Responsabilidad',
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
        'catalogo_actuado_id' => $actInformeSin->id,
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
        'auditor', 'tecnico', 'ac055',
        'ejecucion', 'admision', 'pendienteVbFinal',
        'actComunicar', 'actRecibir', 'actInformeSin', 'paramDescargos',
    );
}

function ffCrearExpediente(int $estadoId, int $reglamentoId, int $creadoPor): Expediente
{
    return Expediente::create([
        'nurej_code' => 'EXP-'.fake()->unique()->numberBetween(100000, 999999),
        'via' => 'TECNICO',
        'reglamento_id' => $reglamentoId,
        'estado_actual_id' => $estadoId,
        'resumen_hechos' => 'Auditoría financiera en ejecución con descargos en trámite.',
        'fecha_ingreso' => now(),
        'creado_por' => $creadoPor,
    ]);
}

function ffAsignar(Expediente $expediente, Usuario $usuario): void
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

function ffAbrirPlazo(Expediente $expediente): Plazo
{
    return Plazo::create([
        'expediente_id' => $expediente->id,
        'tipo_plazo' => 'EJECUCION',
        'dias_habiles_otorgados' => 10,
        'fecha_inicio' => now()->subDay(),
        'fecha_limite' => now()->addDays(10),
        'estado' => 'VIGENTE',
        'actuado_disparador_id' => $expediente->actuados()->latest('id')->value('id'),
    ]);
}

it('estado invalido: devuelve 403 al comunicar y al recibir con el expediente fuera de EN_EJECUCION', function () {
    Storage::fake('local');

    $semilla = ffSemilla();
    $expediente = ffCrearExpediente($semilla['admision']->id, $semilla['ac055']->id, $semilla['tecnico']->id);
    ffAsignar($expediente, $semilla['auditor']);

    Sanctum::actingAs($semilla['auditor'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/descargos/comunicar", [
        'descripcion' => 'Comunicación intentada fuera de la fase de ejecución (test).',
        'adjunto' => UploadedFile::fake()->create('oficio_hallazgos.pdf', 100, 'application/pdf'),
    ])->assertForbidden();

    $this->postJson("/api/expedientes/{$expediente->id}/descargos/recibir", [
        'descripcion' => 'Recepción intentada fuera de la fase de ejecución (test).',
        'adjunto' => UploadedFile::fake()->create('escrito_descargos.pdf', 100, 'application/pdf'),
    ])->assertForbidden();
});

it('el servicio rechaza con 422 la comunicacion cuando no hay reloj de EJECUCION vigente que pausar', function () {
    Storage::fake('local');

    $semilla = ffSemilla();
    $expediente = ffCrearExpediente($semilla['ejecucion']->id, $semilla['ac055']->id, $semilla['tecnico']->id);
    ffAsignar($expediente, $semilla['auditor']);

    Sanctum::actingAs($semilla['auditor'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/descargos/comunicar", [
        'descripcion' => 'Comunicación sin reloj principal que pausar (test).',
        'adjunto' => UploadedFile::fake()->create('oficio_hallazgos.pdf', 100, 'application/pdf'),
    ])->assertStatus(422)
        ->assertJsonPath('errors.expediente.0', 'No hay un reloj de EJECUCION vigente que pausar.');

    expect(Actuado::where('expediente_id', $expediente->id)->count())->toBe(1);
});

it('la recepcion exige el reloj principal suspendido: 422 sin efectos secundarios si vuelve a VIGENTE', function () {
    Storage::fake('local');

    $semilla = ffSemilla();
    $expediente = ffCrearExpediente($semilla['ejecucion']->id, $semilla['ac055']->id, $semilla['tecnico']->id);
    ffAsignar($expediente, $semilla['auditor']);
    ffAbrirPlazo($expediente);

    Sanctum::actingAs($semilla['auditor'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/descargos/comunicar", [
        'descripcion' => 'Comunicación válida que pausa el reloj principal (test).',
        'adjunto' => UploadedFile::fake()->create('oficio_hallazgos.pdf', 100, 'application/pdf'),
    ])->assertCreated();

    Plazo::where('expediente_id', $expediente->id)
        ->where('tipo_plazo', 'EJECUCION')
        ->update(['estado' => 'VIGENTE']);

    $actuadosAntes = Actuado::where('expediente_id', $expediente->id)->count();

    $this->postJson("/api/expedientes/{$expediente->id}/descargos/recibir", [
        'descripcion' => 'Recepción con el reloj principal fuera de pausa (test).',
        'adjunto' => UploadedFile::fake()->create('escrito_descargos.pdf', 100, 'application/pdf'),
    ])->assertStatus(422)
        ->assertJsonPath('errors.expediente.0', 'No hay un reloj de EJECUCION suspendido que reanudar.');

    $expediente->refresh();

    expect(Actuado::where('expediente_id', $expediente->id)->count())->toBe($actuadosAntes)
        ->and(Plazo::where('expediente_id', $expediente->id)->where('tipo_plazo', 'DESCARGOS')->value('estado'))
        ->toBe(DescargoFinancieroService::ESTADO_PLAZO_VIGENTE)
        ->and($expediente->estado_actual_id)->toBe($semilla['ejecucion']->id);
});

it('el Bloqueo de Salida aplica al informe SIN responsabilidad antes y despues de la fase de descargos (RN-09)', function () {
    Storage::fake('local');

    $semilla = ffSemilla();
    $expediente = ffCrearExpediente($semilla['ejecucion']->id, $semilla['ac055']->id, $semilla['tecnico']->id);
    ffAsignar($expediente, $semilla['auditor']);
    ffAbrirPlazo($expediente);

    Sanctum::actingAs($semilla['auditor'], ['*']);

    $payload = [
        'catalogo_actuado_id' => $semilla['actInformeSin']->id,
        'descripcion' => 'Informe final de auditoría financiera sin responsabilidad (test).',
        'adjunto' => UploadedFile::fake()->create('informe_sin_responsabilidad.pdf', 100, 'application/pdf'),
    ];

    $this->postJson("/api/expedientes/{$expediente->id}/actuados", $payload)
        ->assertStatus(422)
        ->assertJsonPath('errors.catalogo_actuado_id.0', 'La emisión del Informe Final de auditoría financiera exige la fase de descargos previa (RN-09).');

    $this->postJson("/api/expedientes/{$expediente->id}/descargos/comunicar", [
        'descripcion' => 'Comunicación previa de hallazgos del auditor financiero (test).',
        'adjunto' => UploadedFile::fake()->create('oficio_hallazgos.pdf', 100, 'application/pdf'),
    ])->assertCreated();

    $this->postJson("/api/expedientes/{$expediente->id}/descargos/recibir", [
        'descripcion' => 'Descargos recibidos antes de emitir el informe sin responsabilidad (test).',
        'adjunto' => UploadedFile::fake()->create('escrito_descargos.pdf', 100, 'application/pdf'),
    ])->assertCreated();

    $this->postJson("/api/expedientes/{$expediente->id}/actuados", $payload)
        ->assertCreated()
        ->assertJsonPath('data.tipo_actuado.codigo', DescargoFinancieroService::CODIGO_INFORME_FINANCIERO_SIN_RESPONSABILIDAD)
        ->assertJsonPath('data.estado_nuevo.codigo', 'PENDIENTE_VISTO_BUENO_FINAL');

    $expediente->refresh();
    expect($expediente->estado_actual_id)->toBe($semilla['pendienteVbFinal']->id);
});
