<?php

use App\Models\Actuado;
use App\Models\Asignacion;
use App\Models\CatalogoActuado;
use App\Models\CatalogoEstado;
use App\Models\Expediente;
use App\Models\ParametroPlazo;
use App\Models\Reglamento;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\ActuadoService;
use App\Services\ImpugnacionService;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;

function impugnacionSemilla(): array
{
    $rolEncargada = Rol::factory()->create(['codigo' => Rol::CODIGO_ENCARGADA]);
    $rolTecnico = Rol::factory()->create(['codigo' => Rol::CODIGO_TECNICO]);
    $rolAudJuridico = Rol::factory()->create(['codigo' => Rol::CODIGO_AUD_JURIDICO]);

    $encargada = Usuario::factory()->create(['rol_id' => $rolEncargada->id, 'activo' => true]);
    $tecnico = Usuario::factory()->create(['rol_id' => $rolTecnico->id, 'activo' => true]);
    $otroOperador = Usuario::factory()->create(['rol_id' => $rolAudJuridico->id, 'activo' => true]);

    $reglamento = Reglamento::factory()->create();

    $evaluacion = CatalogoEstado::factory()->create(['codigo' => 'EN_EVALUACION']);
    $rechazado = CatalogoEstado::factory()->create(['codigo' => 'RECHAZADO']);
    $enImpugnacion = CatalogoEstado::factory()->create(['codigo' => 'EN_IMPUGNACION']);
    $admitido = CatalogoEstado::factory()->create(['codigo' => 'ADMITIDO']);
    $archivoDefinitivo = CatalogoEstado::factory()->create(['codigo' => 'ARCHIVO_DEFINITIVO', 'es_final' => true]);
    $planificacion = CatalogoEstado::factory()->create(['codigo' => 'EN_PLANIFICACION']);

    $actRechazo = CatalogoActuado::create([
        'codigo' => ImpugnacionService::CODIGO_ACT_RECHAZO,
        'nombre' => 'Rechazo',
        'fase' => 'ADMISIBILIDAD',
        'rol_id' => $rolTecnico->id,
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
        'rol_id' => $rolTecnico->id,
        'reglamento_id' => null,
        'estado_origen_id' => $rechazado->id,
        'estado_destino_id' => $enImpugnacion->id,
        'es_automatico' => false,
        'requiere_adjunto' => false,
    ]);

    $actRatifica = CatalogoActuado::create([
        'codigo' => ImpugnacionService::CODIGO_ACT_RATIFICA,
        'nombre' => 'Ratificación del Rechazo',
        'fase' => 'ADMISIBILIDAD',
        'rol_id' => $rolEncargada->id,
        'reglamento_id' => null,
        'estado_origen_id' => $enImpugnacion->id,
        'estado_destino_id' => $archivoDefinitivo->id,
        'es_automatico' => false,
        'requiere_adjunto' => false,
    ]);

    $actRevoca = CatalogoActuado::create([
        'codigo' => ImpugnacionService::CODIGO_ACT_REVOCA,
        'nombre' => 'Revocación del Rechazo',
        'fase' => 'ADMISIBILIDAD',
        'rol_id' => $rolEncargada->id,
        'reglamento_id' => null,
        'estado_origen_id' => $enImpugnacion->id,
        'estado_destino_id' => $admitido->id,
        'es_automatico' => false,
        'requiere_adjunto' => false,
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

    ParametroPlazo::create([
        'reglamento_id' => $reglamento->id,
        'tipo_plazo' => 'PLANIFICACION',
        'subtipo' => null,
        'dias_habiles' => 2,
        'base_legal' => 'RN-08',
        'activo' => true,
    ]);

    return compact(
        'encargada', 'tecnico', 'otroOperador',
        'reglamento',
        'evaluacion', 'rechazado', 'enImpugnacion', 'admitido', 'archivoDefinitivo', 'planificacion',
        'actRechazo', 'actRemitir', 'actRatifica', 'actRevoca',
    );
}

function impugnacionCrearExpediente(int $estadoId, int $reglamentoId, int $creadoPor): Expediente
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

function impugnacionAsignar(Expediente $expediente, Usuario $operador): void
{
    $actuadoOrigen = Actuado::create([
        'expediente_id' => $expediente->id,
        'catalogo_actuado_id' => CatalogoActuado::first()->id,
        'usuario_id' => $operador->id,
        'estado_nuevo_id' => $expediente->estado_actual_id,
        'contenido' => ['descripcion' => 'Asignación inicial de la semilla'],
    ])->refresh();

    Asignacion::create([
        'expediente_id' => $expediente->id,
        'usuario_id' => $operador->id,
        'rol_id' => $operador->rol_id,
        'actuado_origen_id' => $actuadoOrigen->id,
        'fecha_asignacion' => now(),
        'activa' => true,
    ]);
}

function impugnacionEmitirRechazo(array $semilla, Expediente $expediente, Usuario $operador): void
{
    app(ActuadoService::class)->registerActuado(
        expediente: $expediente,
        catalogoActuado: $semilla['actRechazo'],
        emisor: $operador,
        descripcion: 'Rechazo por requisito crítico ausente (test).',
        metadatos: ['tipo' => 'ACTUADO'],
    );
}

it('devuelve 403 si un operador sin asignación intenta remitir la impugnación', function () {
    $semilla = impugnacionSemilla();
    $expediente = impugnacionCrearExpediente($semilla['rechazado']->id, $semilla['reglamento']->id, $semilla['tecnico']->id);

    Sanctum::actingAs($semilla['otroOperador'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/impugnacion/remitir", [
        'descripcion' => 'Intento de remisión por un operador no asignado (test).',
    ])->assertForbidden();
});

it('devuelve 403 si un operador intenta resolver la impugnación', function () {
    $semilla = impugnacionSemilla();
    $expediente = impugnacionCrearExpediente($semilla['rechazado']->id, $semilla['reglamento']->id, $semilla['tecnico']->id);

    Sanctum::actingAs($semilla['tecnico'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/impugnacion/resolver", [
        'ratifica' => true,
        'justificacion' => 'Intento de resolución por un operador no autorizado (test).',
    ])->assertForbidden();
});

it('devuelve 403 si la Encargada intenta remitir la impugnación', function () {
    $semilla = impugnacionSemilla();
    $expediente = impugnacionCrearExpediente($semilla['rechazado']->id, $semilla['reglamento']->id, $semilla['tecnico']->id);

    Sanctum::actingAs($semilla['encargada'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/impugnacion/remitir", [
        'descripcion' => 'Intento de remisión por la Encargada (no operativa) (test).',
    ])->assertForbidden();
});

it('remite a la Encargada abriendo el plazo de 3 días hábiles con salto de fin de semana', function () {
    Carbon::setTestNow('2026-09-10 10:00:00');

    $semilla = impugnacionSemilla();
    $expediente = impugnacionCrearExpediente($semilla['evaluacion']->id, $semilla['reglamento']->id, $semilla['tecnico']->id);

    impugnacionAsignar($expediente, $semilla['tecnico']);
    impugnacionEmitirRechazo($semilla, $expediente, $semilla['tecnico']);

    Sanctum::actingAs($semilla['tecnico'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/impugnacion/remitir", [
        'descripcion' => 'Remisión a la Encargada para resolución de la impugnación (test).',
    ])->assertCreated();

    $expediente->refresh();

    expect($expediente->estado_actual_id)->toBe($semilla['enImpugnacion']->id);

    $asignacionActiva = $expediente->asignacionActiva()->first();
    expect($asignacionActiva)->not->toBeNull()
        ->and($asignacionActiva->usuario_id)->toBe($semilla['encargada']->id);

    $plazoResolver = $expediente->plazos()
        ->where('tipo_plazo', 'IMPUGNACION_RESOLVER')
        ->first();

    expect($plazoResolver)->not->toBeNull()
        ->and($plazoResolver->estado)->toBe('VIGENTE')
        ->and($plazoResolver->fecha_limite->toDateString())->toBe('2026-09-15');

    $plazoRemitir = $expediente->plazos()
        ->where('tipo_plazo', 'IMPUGNACION_REMITIR')
        ->first();

    expect($plazoRemitir)->not->toBeNull()
        ->and($plazoRemitir->estado)->toBe('CERRADO');

    $impugnacion = $expediente->impugnaciones()->first();
    expect($impugnacion)->not->toBeNull()
        ->and($impugnacion->resultado)->toBe(ImpugnacionService::RESULTADO_PENDIENTE)
        ->and($impugnacion->fecha_limite_resolucion->toDateString())->toBe('2026-09-15');

    Carbon::setTestNow();
});

it('ratifica el rechazo dejando el expediente en ARCHIVO_DEFINITIVO con la bandeja cerrada', function () {
    Carbon::setTestNow('2026-09-10 10:00:00');

    $semilla = impugnacionSemilla();
    $expediente = impugnacionCrearExpediente($semilla['evaluacion']->id, $semilla['reglamento']->id, $semilla['tecnico']->id);

    impugnacionAsignar($expediente, $semilla['tecnico']);
    impugnacionEmitirRechazo($semilla, $expediente, $semilla['tecnico']);

    Sanctum::actingAs($semilla['tecnico'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/impugnacion/remitir", [
        'descripcion' => 'Remisión a la Encargada para resolución de la impugnación (test).',
    ])->assertCreated();

    Sanctum::actingAs($semilla['encargada'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/impugnacion/resolver", [
        'ratifica' => true,
        'justificacion' => 'Se ratifica el rechazo de la denuncia original por los fundamentos citados (test).',
    ])->assertOk();

    $expediente->refresh();

    expect($expediente->estado_actual_id)->toBe($semilla['archivoDefinitivo']->id);

    $impugnacion = $expediente->impugnaciones()->first();
    expect($impugnacion)->not->toBeNull()
        ->and($impugnacion->resultado)->toBe(ImpugnacionService::RESULTADO_RATIFICADO)
        ->and($impugnacion->actuado_resolucion_id)->not->toBeNull();

    $actuado = $impugnacion->actuadoResolucion()->first();
    expect($actuado)->not->toBeNull()
        ->and($actuado->catalogo_actuado_id)->toBe($semilla['actRatifica']->id)
        ->and($actuado->usuario_id)->toBe($semilla['encargada']->id);

    expect($expediente->asignaciones()->where('activa', true)->count())->toBe(0)
        ->and($expediente->plazos()->where('estado', 'VIGENTE')->count())->toBe(0);

    Carbon::setTestNow();
});

it('revoca el rechazo devolviendo el expediente a ADMITIDO con el operador original y plazo de planificación', function () {
    Carbon::setTestNow('2026-09-10 10:00:00');

    $semilla = impugnacionSemilla();
    $expediente = impugnacionCrearExpediente($semilla['evaluacion']->id, $semilla['reglamento']->id, $semilla['tecnico']->id);

    impugnacionAsignar($expediente, $semilla['tecnico']);
    impugnacionEmitirRechazo($semilla, $expediente, $semilla['tecnico']);

    Sanctum::actingAs($semilla['tecnico'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/impugnacion/remitir", [
        'descripcion' => 'Remisión a la Encargada para resolución de la impugnación (test).',
    ])->assertCreated();

    Sanctum::actingAs($semilla['encargada'], ['*']);

    $this->postJson("/api/expedientes/{$expediente->id}/impugnacion/resolver", [
        'ratifica' => false,
        'justificacion' => 'Se revoca el rechazo por haberse subsanado los fundamentos que lo motivaron (test).',
    ])->assertOk();

    $expediente->refresh();

    expect($expediente->estado_actual_id)->toBe($semilla['admitido']->id);

    $asignacionActiva = $expediente->asignacionActiva()->first();
    expect($asignacionActiva)->not->toBeNull()
        ->and($asignacionActiva->usuario_id)->toBe($semilla['tecnico']->id);

    $plazoPlanificacion = $expediente->plazos()
        ->where('tipo_plazo', 'PLANIFICACION')
        ->first();

    expect($plazoPlanificacion)->not->toBeNull()
        ->and($plazoPlanificacion->estado)->toBe('VIGENTE')
        ->and($plazoPlanificacion->fecha_limite->toDateString())->toBe('2026-09-14');

    $impugnacion = $expediente->impugnaciones()->first();
    expect($impugnacion)->not->toBeNull()
        ->and($impugnacion->resultado)->toBe(ImpugnacionService::RESULTADO_REVOCADO)
        ->and($impugnacion->actuado_resolucion_id)->not->toBeNull();

    Carbon::setTestNow();
});

it('lanza error de validación al resolver un expediente que no está en EN_IMPUGNACION', function () {
    $semilla = impugnacionSemilla();
    $expediente = impugnacionCrearExpediente($semilla['rechazado']->id, $semilla['reglamento']->id, $semilla['tecnico']->id);

    try {
        app(ImpugnacionService::class)->resolverImpugnacion(
            expediente: $expediente,
            ratifica: true,
            justificacion: 'Resolución fuera de estado para validar el rechazo del servicio (test).',
            encargada: $semilla['encargada'],
        );

        expect(true)->toBeFalse('Debería haber lanzado ValidationException');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('expediente');
    }
});
