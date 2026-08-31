<?php

use App\Http\Resources\ExpedienteResource;
use App\Models\Actuado;
use App\Models\Asignacion;
use App\Models\CatalogoActuado;
use App\Models\CatalogoEstado;
use App\Models\Expediente;
use App\Models\Parte;
use App\Models\Plazo;
use App\Models\Reglamento;
use App\Models\Rol;
use App\Models\Usuario;
use Carbon\Carbon;

const PASO6_EXP_HOY = '2026-08-28 10:00:00';

function paso6ExpedienteCompleto(bool $conPlazoVigente = true): Expediente
{
    $rolEncargada = Rol::factory()->create(['codigo' => Rol::CODIGO_ENCARGADA]);
    $rolOperador = Rol::factory()->create(['codigo' => Rol::CODIGO_AUD_JURIDICO]);
    $usuario = Usuario::factory()->create(['rol_id' => $rolOperador->id]);
    $encargada = Usuario::factory()->create(['rol_id' => $rolEncargada->id]);
    $reglamento = Reglamento::factory()->create();
    $estado = CatalogoEstado::factory()->create();

    $expediente = Expediente::create([
        'nurej_code' => 'EXP-'.fake()->unique()->numberBetween(10000, 99999),
        'via' => 'JURIDICO',
        'reglamento_id' => $reglamento->id,
        'estado_actual_id' => $estado->id,
        'resumen_hechos' => 'Hechos denunciados sobre irregularidades administrativas verificables.',
        'fecha_ingreso' => now(),
        'creado_por' => $encargada->id,
    ]);

    $catalogoActuado = CatalogoActuado::create([
        'codigo' => 'ACT_EXP_'.fake()->unique()->numberBetween(1000, 9999),
        'nombre' => 'Actuado de expediente',
        'fase' => 'TEST',
        'rol_id' => $rolOperador->id,
        'estado_destino_id' => $estado->id,
        'es_automatico' => false,
        'requiere_adjunto' => false,
    ]);

    $origen = Actuado::create([
        'expediente_id' => $expediente->id,
        'catalogo_actuado_id' => $catalogoActuado->id,
        'usuario_id' => $usuario->id,
        'estado_nuevo_id' => $estado->id,
        'contenido' => ['tipo' => 'asignacion', 'nota' => 'origen'],
    ]);

    Asignacion::create([
        'expediente_id' => $expediente->id,
        'usuario_id' => $usuario->id,
        'rol_id' => $rolOperador->id,
        'actuado_origen_id' => $origen->id,
        'fecha_asignacion' => now(),
        'activa' => true,
    ]);

    Parte::create([
        'expediente_id' => $expediente->id,
        'tipo' => 'DENUNCIANTE',
        'nombre_completo' => 'Juan Perez',
        'es_version_actual' => true,
    ]);

    if ($conPlazoVigente) {
        Plazo::create([
            'expediente_id' => $expediente->id,
            'tipo_plazo' => 'EJECUCION',
            'dias_habiles_otorgados' => 10,
            'fecha_inicio' => '2026-08-27',
            'fecha_limite' => '2026-09-07',
            'estado' => 'VIGENTE',
            'actuado_disparador_id' => $origen->id,
        ]);
    }

    $expediente->load([
        'reglamento',
        'estadoActual',
        'creador',
        'asignacionActiva.usuario',
        'asignacionActiva.rol',
        'partesVigentes',
        'plazos',
    ]);

    return $expediente;
}

it('transforma el expediente con sus datos, asignacion y semaforo', function () {
    Carbon::setTestNow(PASO6_EXP_HOY);

    try {
        $expediente = paso6ExpedienteCompleto();

        $salida = (new ExpedienteResource($expediente))->resolve(request());

        expect($salida)
            ->toHaveKey('id', $expediente->id)
            ->toHaveKey('nurej_code', $expediente->nurej_code)
            ->toHaveKey('via', 'JURIDICO')
            ->toHaveKey('resumen_hechos', $expediente->resumen_hechos)
            ->and($salida['reglamento']['id'])->toBe($expediente->reglamento->id)
            ->and($salida['reglamento']['codigo'])->toBe($expediente->reglamento->codigo)
            ->and($salida['estado_actual']['codigo'])->toBe($expediente->estadoActual->codigo)
            ->and($salida['creador']['id'])->toBe($expediente->creador->id)
            ->and($salida['asignacion_activa']['usuario']['id'])->toBe($expediente->asignacionActiva->usuario->id)
            ->and($salida['asignacion_activa']['rol']['codigo'])->toBe(Rol::CODIGO_AUD_JURIDICO)
            ->and(collect($salida['partes_vigentes'])->pluck('tipo')->all())->toBe(['DENUNCIANTE'])
            ->and(collect($salida['plazos'])->count())->toBe(1)
            ->and($salida['sem_plazo']['codigo_color'])->toBe('VERDE')
            ->and($salida['sem_plazo']['fecha_limite'])->toBe('2026-09-07');
    } finally {
        Carbon::setTestNow();
    }
});

it('devuelve semaforo nulo cuando no hay plazos vigentes', function () {
    Carbon::setTestNow(PASO6_EXP_HOY);

    try {
        $expediente = paso6ExpedienteCompleto(conPlazoVigente: false);

        $salida = (new ExpedienteResource($expediente))->resolve(request());

        expect($salida['sem_plazo'])->toBeNull();
    } finally {
        Carbon::setTestNow();
    }
});
