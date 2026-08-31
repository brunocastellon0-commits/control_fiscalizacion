<?php

use App\Http\Resources\PlazoResource;
use App\Models\Actuado;
use App\Models\CatalogoActuado;
use App\Models\CatalogoEstado;
use App\Models\Expediente;
use App\Models\Plazo;
use App\Models\Reglamento;
use App\Models\Rol;
use App\Models\Usuario;
use Carbon\Carbon;

const PASO6_HOY = '2026-08-28 10:00:00';

function paso6Plazo(string $estado = 'VIGENTE', ?string $fechaLimite = '2026-09-03'): Plazo
{
    $rol = Rol::factory()->create();
    $usuario = Usuario::factory()->create(['rol_id' => $rol->id]);
    $reglamento = Reglamento::factory()->create();
    $estadoCatalogo = CatalogoEstado::factory()->create();
    $catalogoActuado = CatalogoActuado::create([
        'codigo' => 'ACT_PLAZO_'.fake()->unique()->numberBetween(1000, 9999),
        'nombre' => 'Actuado de plazo',
        'fase' => 'TEST',
        'estado_destino_id' => $estadoCatalogo->id,
        'es_automatico' => false,
        'requiere_adjunto' => false,
    ]);

    $expediente = Expediente::create([
        'nurej_code' => 'PLZ-'.fake()->unique()->numberBetween(10000, 99999),
        'via' => 'TECNICO',
        'reglamento_id' => $reglamento->id,
        'estado_actual_id' => $estadoCatalogo->id,
        'fecha_ingreso' => now(),
        'creado_por' => $usuario->id,
    ]);

    $actuado = Actuado::create([
        'expediente_id' => $expediente->id,
        'catalogo_actuado_id' => $catalogoActuado->id,
        'usuario_id' => $usuario->id,
        'estado_nuevo_id' => $estadoCatalogo->id,
        'contenido' => ['descripcion' => 'disparador'],
    ]);

    return Plazo::create([
        'expediente_id' => $expediente->id,
        'tipo_plazo' => 'EVALUACION',
        'dias_habiles_otorgados' => 3,
        'fecha_inicio' => '2026-08-27',
        'fecha_limite' => $fechaLimite,
        'estado' => $estado,
        'actuado_disparador_id' => $actuado->id,
    ]);
}

it('transforma un plazo con su semaforo y fechas formateadas', function () {
    Carbon::setTestNow(PASO6_HOY);

    try {
        $plazo = paso6Plazo(estado: 'VIGENTE', fechaLimite: '2026-09-03');

        $salida = (new PlazoResource($plazo))->resolve(request());

        expect($salida)
            ->toHaveKey('id', $plazo->id)
            ->toHaveKey('tipo_plazo', 'EVALUACION')
            ->toHaveKey('estado', 'VIGENTE')
            ->toHaveKey('dias_habiles_otorgados', 3)
            ->toHaveKey('fecha_inicio', '2026-08-27')
            ->toHaveKey('fecha_limite', '2026-09-03')
            ->and($salida['sem_plazo'])
            ->toHaveKeys(['codigo_color', 'dias_restantes', 'porcentaje_consumido', 'fecha_limite', 'es_fuera_de_plazo'])
            ->and($salida['sem_plazo']['codigo_color'])->toBe('VERDE')
            ->and($salida['sem_plazo']['fecha_limite'])->toBe('2026-09-03');
    } finally {
        Carbon::setTestNow();
    }
});

it('refleja el semaforo de un plazo fuera de plazo', function () {
    Carbon::setTestNow(PASO6_HOY);

    try {
        $plazo = paso6Plazo(estado: 'VIGENTE', fechaLimite: '2026-08-27');

        $salida = (new PlazoResource($plazo))->resolve(request());

        expect($salida['sem_plazo']['codigo_color'])->toBe('FUERA_DE_PLAZO')
            ->and($salida['sem_plazo']['es_fuera_de_plazo'])->toBeTrue();
    } finally {
        Carbon::setTestNow();
    }
});

it('retorna el estado sin calculo en plazos no vigentes', function () {
    $plazo = paso6Plazo(estado: 'SUSPENDIDO', fechaLimite: '2026-08-27');

    $salida = (new PlazoResource($plazo))->resolve(request());

    expect($salida['estado'])->toBe('SUSPENDIDO')
        ->and($salida['sem_plazo']['codigo_color'])->toBe('SUSPENDIDO')
        ->and($salida['sem_plazo']['dias_restantes'])->toBeNull();
});
