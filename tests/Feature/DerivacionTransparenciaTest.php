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
use App\Services\MarcarPlazosVencidosService;
use App\Services\TransparenciaService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

function transparenciaSemilla(): array
{
    $rolEncargada = Rol::factory()->create(['codigo' => Rol::CODIGO_ENCARGADA]);
    $rolTecnico = Rol::factory()->create(['codigo' => Rol::CODIGO_TECNICO]);
    $rolAudJuridico = Rol::factory()->create(['codigo' => Rol::CODIGO_AUD_JURIDICO]);
    $rolAudFinanciero = Rol::factory()->create(['codigo' => Rol::CODIGO_AUD_FINANCIERO]);

    $encargada = Usuario::factory()->create(['rol_id' => $rolEncargada->id, 'activo' => true]);
    $tecnico = Usuario::factory()->create(['rol_id' => $rolTecnico->id, 'activo' => true]);
    $audJuridico = Usuario::factory()->create(['rol_id' => $rolAudJuridico->id, 'activo' => true]);
    $audFinanciero = Usuario::factory()->create(['rol_id' => $rolAudFinanciero->id, 'activo' => true]);

    $ac022 = Reglamento::factory()->create(['codigo' => 'AC_022_2018']);

    $ejecucion = CatalogoEstado::factory()->create(['codigo' => 'EN_EJECUCION']);
    $pendienteRemision = CatalogoEstado::factory()->create(['codigo' => TransparenciaService::ESTADO_PENDIENTE_REMISION]);
    $derivadoTransparencia = CatalogoEstado::factory()->create(['codigo' => TransparenciaService::ESTADO_DERIVADO_TRANSPARENCIA, 'es_final' => true]);

    $actDerivacion = CatalogoActuado::create([
        'codigo' => TransparenciaService::CODIGO_ACT_DERIVACION,
        'nombre' => 'Derivación por Incompetencia',
        'fase' => 'INVESTIGACION',
        'rol_id' => $rolTecnico->id,
        'reglamento_id' => null,
        'estado_origen_id' => null,
        'estado_destino_id' => $pendienteRemision->id,
        'es_automatico' => false,
        'requiere_adjunto' => true,
    ]);

    $actRemision = CatalogoActuado::create([
        'codigo' => TransparenciaService::CODIGO_ACT_REMISION,
        'nombre' => 'Remisión a Transparencia',
        'fase' => 'INVESTIGACION',
        'rol_id' => $rolEncargada->id,
        'reglamento_id' => null,
        'estado_origen_id' => $pendienteRemision->id,
        'estado_destino_id' => $derivadoTransparencia->id,
        'es_automatico' => false,
        'requiere_adjunto' => false,
    ]);

    foreach ([[$rolTecnico, null], [$rolAudJuridico, null], [$rolAudFinanciero, null]] as [$rol, $reglamento]) {
        DB::table('catalogo_actuado_roles')->insert([
            'catalogo_actuado_id' => $actDerivacion->id,
            'rol_id' => $rol->id,
            'reglamento_id' => $reglamento,
        ]);
    }

    DB::table('catalogo_actuado_roles')->insert([
        'catalogo_actuado_id' => $actRemision->id,
        'rol_id' => $rolEncargada->id,
        'reglamento_id' => null,
    ]);

    return compact(
        'encargada', 'tecnico', 'audJuridico', 'audFinanciero',
        'ac022',
        'ejecucion', 'pendienteRemision', 'derivadoTransparencia',
        'actDerivacion', 'actRemision',
    );
}

function transparenciaCrearExpediente(int $estadoId, int $reglamentoId, int $creadoPor): Expediente
{
    return Expediente::create([
        'nurej_code' => 'EXP-'.fake()->unique()->numberBetween(100000, 999999),
        'via' => 'TECNICO',
        'reglamento_id' => $reglamentoId,
        'estado_actual_id' => $estadoId,
        'resumen_hechos' => 'Causa en ejecución de la investigación disciplinaria.',
        'fecha_ingreso' => now(),
        'creado_por' => $creadoPor,
    ]);
}

function transparenciaAsignar(Expediente $expediente, Usuario $usuario): void
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

function transparenciaAbrirPlazo(Expediente $expediente, string $tipo, ?string $fechaLimite = null): Plazo
{
    return Plazo::create([
        'expediente_id' => $expediente->id,
        'tipo_plazo' => $tipo,
        'dias_habiles_otorgados' => 10,
        'fecha_inicio' => now()->subDay(),
        'fecha_limite' => $fechaLimite ?? now()->addDay(),
        'estado' => 'VIGENTE',
        'actuado_disparador_id' => $expediente->actuados()->latest('id')->value('id'),
    ]);
}

it('un Técnico deriva por incompetencia: PENDIENTE_REMISION_TRANSPARENCIA, plazos congelados y bandeja a la Encargada', function () {
    Storage::fake('local');

    $semilla = transparenciaSemilla();
    $expediente = transparenciaCrearExpediente($semilla['ejecucion']->id, $semilla['ac022']->id, $semilla['tecnico']->id);
    transparenciaAsignar($expediente, $semilla['tecnico']);

    $plazo = transparenciaAbrirPlazo($expediente, 'EJECUCION');

    Sanctum::actingAs($semilla['tecnico'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/derivacion-transparencia", [
        'justificacion' => 'La vía disciplinaria no es competente para esta denuncia de posible hecho penal (test).',
        'adjunto' => UploadedFile::fake()->create('informe.pdf', 100, 'application/pdf'),
    ])->assertCreated()
        ->assertJsonPath('data.tipo_actuado.codigo', TransparenciaService::CODIGO_ACT_DERIVACION)
        ->assertJsonPath('data.estado_anterior.codigo', 'EN_EJECUCION')
        ->assertJsonPath('data.estado_nuevo.codigo', TransparenciaService::ESTADO_PENDIENTE_REMISION);

    $expediente->refresh();

    expect($expediente->estado_actual_id)->toBe($semilla['pendienteRemision']->id);

    $plazo->refresh();
    expect($plazo->estado)->toBe(TransparenciaService::ESTADO_PLAZO_SUSPENDIDO)
        ->and($plazo->fecha_pausa)->not->toBeNull();

    $asignacionActiva = $expediente->asignacionActiva()->first();
    expect($asignacionActiva)->not->toBeNull()
        ->and($asignacionActiva->usuario_id)->toBe($semilla['encargada']->id);

    $actuado = $expediente->actuados()
        ->whereHas('tipoActuado', fn ($query) => $query->where('codigo', TransparenciaService::CODIGO_ACT_DERIVACION))
        ->latest('id')
        ->first();

    expect($actuado)->not->toBeNull()
        ->and($actuado->usuario_id)->toBe($semilla['tecnico']->id)
        ->and($actuado->contenido['tipo'])->toBe('DERIVACION_TRANSPARENCIA')
        ->and($actuado->contenido['motivo'])->toBe('INCOMPETENCIA')
        ->and($actuado->contenido['usuario_destino_id'])->toBe($semilla['encargada']->id)
        ->and($actuado->hash_anterior)->not->toBeNull()
        ->and($actuado->hash_actuado)->toMatch('/^[0-9a-f]{64}$/')
        ->and($actuado->adjuntos()->count())->toBe(1);
});

it('la derivación por incompetencia es transversal: Auditor Jurídico', function () {
    Storage::fake('local');

    $semilla = transparenciaSemilla();
    $expediente = transparenciaCrearExpediente($semilla['ejecucion']->id, $semilla['ac022']->id, $semilla['tecnico']->id);
    transparenciaAsignar($expediente, $semilla['audJuridico']);

    Sanctum::actingAs($semilla['audJuridico'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/derivacion-transparencia", [
        'justificacion' => 'Hallazgos que exceden la competencia disciplinaria y corresponden a la vía penal (test).',
        'adjunto' => UploadedFile::fake()->create('informe.pdf', 100, 'application/pdf'),
    ])->assertCreated();
});

it('la derivación por incompetencia es transversal: Auditor Financiero', function () {
    Storage::fake('local');

    $semilla = transparenciaSemilla();
    $expediente = transparenciaCrearExpediente($semilla['ejecucion']->id, $semilla['ac022']->id, $semilla['tecnico']->id);
    transparenciaAsignar($expediente, $semilla['audFinanciero']);

    Sanctum::actingAs($semilla['audFinanciero'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/derivacion-transparencia", [
        'justificacion' => 'Hallazgos que exceden la competencia disciplinaria y corresponden a la vía penal (test).',
        'adjunto' => UploadedFile::fake()->create('informe.pdf', 100, 'application/pdf'),
    ])->assertCreated();
});

it('devuelve 403 si la Encargada intenta autogenerar la derivación', function () {
    $semilla = transparenciaSemilla();
    $expediente = transparenciaCrearExpediente($semilla['ejecucion']->id, $semilla['ac022']->id, $semilla['tecnico']->id);
    transparenciaAsignar($expediente, $semilla['tecnico']);

    Sanctum::actingAs($semilla['encargada'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/derivacion-transparencia", [
        'justificacion' => 'La Encargada no puede derivar por incompetencia su propio expediente (test).',
        'adjunto' => UploadedFile::fake()->create('informe.pdf', 100, 'application/pdf'),
    ])->assertForbidden();
});

it('devuelve 403 si un operador sin la bandeja activa intenta derivar', function () {
    $semilla = transparenciaSemilla();
    $expediente = transparenciaCrearExpediente($semilla['ejecucion']->id, $semilla['ac022']->id, $semilla['tecnico']->id);
    transparenciaAsignar($expediente, $semilla['audJuridico']);

    Sanctum::actingAs($semilla['tecnico'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/derivacion-transparencia", [
        'justificacion' => 'Intento de derivación sobre un expediente asignado a otro operador (test).',
        'adjunto' => UploadedFile::fake()->create('informe.pdf', 100, 'application/pdf'),
    ])->assertForbidden();
});

it('devuelve 422 con justificación corta o sin adjunto probatorio', function () {
    $semilla = transparenciaSemilla();
    $expediente = transparenciaCrearExpediente($semilla['ejecucion']->id, $semilla['ac022']->id, $semilla['tecnico']->id);
    transparenciaAsignar($expediente, $semilla['tecnico']);

    Sanctum::actingAs($semilla['tecnico'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/derivacion-transparencia", [
        'justificacion' => 'Corta.',
        'adjunto' => UploadedFile::fake()->create('informe.pdf', 100, 'application/pdf'),
    ])->assertStatus(422)
        ->assertJsonValidationErrors('justificacion');

    $this->postJson("/api/expedientes/{$expediente->id}/derivacion-transparencia", [
        'justificacion' => 'La derivación sin respaldo documental no puede tramitarse por la vía penal (test).',
    ])->assertStatus(422)
        ->assertJsonValidationErrors('adjunto');
});

it('cierra el ciclo completo: derivación del operador y remisión de la Encargada a DERIVADO_TRANSPARENCIA', function () {
    Storage::fake('local');

    $semilla = transparenciaSemilla();
    $expediente = transparenciaCrearExpediente($semilla['ejecucion']->id, $semilla['ac022']->id, $semilla['tecnico']->id);
    transparenciaAsignar($expediente, $semilla['tecnico']);
    transparenciaAbrirPlazo($expediente, 'EJECUCION');

    Sanctum::actingAs($semilla['tecnico'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/derivacion-transparencia", [
        'justificacion' => 'La vía disciplinaria no es competente para esta denuncia de posible hecho penal (test).',
        'adjunto' => UploadedFile::fake()->create('informe.pdf', 100, 'application/pdf'),
    ])->assertCreated();

    Sanctum::actingAs($semilla['encargada'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/transparencia/remitir", [
        'nota_remision' => 'Se remite la causa a Transparencia por incompetencia de la vía penal (test).',
    ])->assertCreated()
        ->assertJsonPath('data.tipo_actuado.codigo', TransparenciaService::CODIGO_ACT_REMISION)
        ->assertJsonPath('data.estado_anterior.codigo', TransparenciaService::ESTADO_PENDIENTE_REMISION)
        ->assertJsonPath('data.estado_nuevo.codigo', TransparenciaService::ESTADO_DERIVADO_TRANSPARENCIA);

    $expediente->refresh();

    expect($expediente->estado_actual_id)->toBe($semilla['derivadoTransparencia']->id)
        ->and($expediente->asignacionActiva()->first())->toBeNull()
        ->and($expediente->asignaciones()->where('activa', true)->count())->toBe(0);

    $actuadoRemision = $expediente->actuados()
        ->whereHas('tipoActuado', fn ($query) => $query->where('codigo', TransparenciaService::CODIGO_ACT_REMISION))
        ->latest('id')
        ->first();

    expect($actuadoRemision)->not->toBeNull()
        ->and($actuadoRemision->usuario_id)->toBe($semilla['encargada']->id)
        ->and($actuadoRemision->contenido['tipo'])->toBe('REMISION_TRANSPARENCIA')
        ->and($actuadoRemision->contenido['via'])->toBe('PENAL');
});

it('el motor de cálculo ignora los plazos congelados y sigue marcando los vigentes', function () {
    $semilla = transparenciaSemilla();
    $expediente = transparenciaCrearExpediente($semilla['ejecucion']->id, $semilla['ac022']->id, $semilla['tecnico']->id);
    transparenciaAsignar($expediente, $semilla['tecnico']);

    $plazoVigente = transparenciaAbrirPlazo($expediente, 'EJECUCION');

    Sanctum::actingAs($semilla['tecnico'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/derivacion-transparencia", [
        'justificacion' => 'La vía disciplinaria no es competente para esta denuncia de posible hecho penal (test).',
        'adjunto' => UploadedFile::fake()->create('informe.pdf', 100, 'application/pdf'),
    ])->assertCreated();

    $control = transparenciaCrearExpediente($semilla['ejecucion']->id, $semilla['ac022']->id, $semilla['tecnico']->id);
    transparenciaAsignar($control, $semilla['tecnico']);
    $plazoControl = transparenciaAbrirPlazo($control, 'EJECUCION', now()->subDay()->toDateString());

    $plazoVigente->refresh();
    expect($plazoVigente->estado)->toBe(TransparenciaService::ESTADO_PLAZO_SUSPENDIDO);

    $marcados = app(MarcarPlazosVencidosService::class)->marcarVencidos();

    $plazoVigente->refresh();
    $plazoControl->refresh();

    expect($plazoVigente->fuera_de_plazo)->toBeFalse()
        ->and($plazoVigente->estado)->toBe(TransparenciaService::ESTADO_PLAZO_SUSPENDIDO)
        ->and($plazoControl->fuera_de_plazo)->toBeTrue()
        ->and($marcados)->toBe(1);
});

it('devuelve 403 si un operador intenta la remisión y 403 si la Encargada remite fuera de estado', function () {
    $semilla = transparenciaSemilla();

    $expediente = transparenciaCrearExpediente($semilla['pendienteRemision']->id, $semilla['ac022']->id, $semilla['tecnico']->id);
    transparenciaAsignar($expediente, $semilla['tecnico']);

    Sanctum::actingAs($semilla['tecnico'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/transparencia/remitir", [
        'nota_remision' => 'El operador no tiene atribución para la salida a Transparencia (test).',
    ])->assertForbidden();

    $fueraDeEstado = transparenciaCrearExpediente($semilla['ejecucion']->id, $semilla['ac022']->id, $semilla['encargada']->id);
    transparenciaAsignar($fueraDeEstado, $semilla['encargada']);

    Sanctum::actingAs($semilla['encargada'], ['*']);

    $this->postJson("/api/expedientes/{$fueraDeEstado->id}/transparencia/remitir", [
        'nota_remision' => 'Remisión sobre un expediente que aún no fue derivado por incompetencia (test).',
    ])->assertForbidden();
});

it('devuelve 422 si la nota de remisión es demasiado corta', function () {
    $semilla = transparenciaSemilla();
    $expediente = transparenciaCrearExpediente($semilla['pendienteRemision']->id, $semilla['ac022']->id, $semilla['tecnico']->id);
    transparenciaAsignar($expediente, $semilla['tecnico']);

    Sanctum::actingAs($semilla['encargada'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/transparencia/remitir", [
        'nota_remision' => 'Corta.',
    ])->assertStatus(422)
        ->assertJsonValidationErrors('nota_remision');
});
