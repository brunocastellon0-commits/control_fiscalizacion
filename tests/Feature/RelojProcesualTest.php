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
use App\Services\PlazoCalculatorService;
use Laravel\Sanctum\Sanctum;

function relojProcesualSemilla(): array
{
    $rolTecnico = Rol::factory()->create(['codigo' => Rol::CODIGO_TECNICO]);
    $tecnico = Usuario::factory()->create(['rol_id' => $rolTecnico->id]);

    $rolEncargada = Rol::factory()->create(['codigo' => Rol::CODIGO_ENCARGADA]);
    $encargada = Usuario::factory()->create(['rol_id' => $rolEncargada->id]);

    $rolAuditor = Rol::factory()->create(['codigo' => Rol::CODIGO_AUD_JURIDICO]);
    $auditor = Usuario::factory()->create(['rol_id' => $rolAuditor->id]);

    $reglamento = Reglamento::factory()->create(['codigo' => 'AC_022_2018']);

    $evaluacion = CatalogoEstado::factory()->create(['codigo' => 'EN_EVALUACION']);
    $admitido = CatalogoEstado::factory()->create(['codigo' => 'ADMITIDO']);
    $investigacion = CatalogoEstado::factory()->create(['codigo' => 'EN_INVESTIGACION']);

    $catalogoAdmision = CatalogoActuado::create([
        'codigo' => 'ACT_ADMISION',
        'nombre' => 'Admision',
        'fase' => 'ADMISIBILIDAD',
        'rol_id' => $rolAuditor->id,
        'estado_origen_id' => $evaluacion->id,
        'estado_destino_id' => $admitido->id,
        'es_automatico' => false,
        'requiere_adjunto' => false,
    ]);

    $catalogoVistoBueno = CatalogoActuado::create([
        'codigo' => 'ACT_VISTO_BUENO_PLANIFICACION',
        'nombre' => 'Visto Bueno a Planificacion',
        'fase' => 'PLANIFICACION',
        'rol_id' => $rolEncargada->id,
        'estado_origen_id' => $admitido->id,
        'estado_destino_id' => $investigacion->id,
        'es_automatico' => false,
        'requiere_adjunto' => false,
    ]);

    ParametroPlazo::create([
        'reglamento_id' => $reglamento->id,
        'tipo_plazo' => 'PLANIFICACION',
        'subtipo' => null,
        'dias_habiles' => 2,
        'base_legal' => 'AC_022_2018',
        'activo' => true,
    ]);

    ParametroPlazo::create([
        'reglamento_id' => $reglamento->id,
        'tipo_plazo' => 'EJECUCION',
        'subtipo' => 'JURISDICCIONAL',
        'dias_habiles' => 10,
        'base_legal' => 'AC_022_2018',
        'activo' => true,
    ]);

    return compact(
        'tecnico', 'encargada', 'auditor',
        'reglamento',
        'evaluacion', 'admitido', 'investigacion',
        'catalogoAdmision', 'catalogoVistoBueno',
    );
}

function relojProcesualCrearExpediente(int $estadoId, int $creadorId, int $reglamentoId): Expediente
{
    return Expediente::create([
        'nurej_code' => 'EXP-'.fake()->unique()->numberBetween(10000, 99999),
        'via' => 'TECNICO',
        'reglamento_id' => $reglamentoId,
        'estado_actual_id' => $estadoId,
        'fecha_ingreso' => now(),
        'creado_por' => $creadorId,
    ]);
}

function relojProcesualAsignarActivamente(Expediente $expediente, Usuario $usuario, int $estadoId, int $catalogoId): void
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

it('la admision abre el plazo de PLANIFICACION, no el de EJECUCION', function () {
    [
        'auditor' => $auditor,
        'reglamento' => $reglamento,
        'evaluacion' => $evaluacion,
        'admitido' => $admitido,
        'catalogoAdmision' => $catalogoAdmision,
    ] = relojProcesualSemilla();

    $expediente = relojProcesualCrearExpediente($evaluacion->id, $auditor->id, $reglamento->id);
    relojProcesualAsignarActivamente($expediente, $auditor, $evaluacion->id, $catalogoAdmision->id);

    Sanctum::actingAs($auditor, ['*']);

    $this->postJson('/api/expedientes/'.$expediente->id.'/actuados', [
        'catalogo_actuado_id' => $catalogoAdmision->id,
        'descripcion' => 'Admision del expediente tras revisar requisitos.',
    ])->assertStatus(201)
        ->assertJsonPath('data.estado_nuevo.codigo', 'ADMITIDO');

    $plazoPlanificacion = Plazo::where('expediente_id', $expediente->id)
        ->where('tipo_plazo', 'PLANIFICACION')
        ->first();

    expect($plazoPlanificacion)->not->toBeNull()
        ->and($plazoPlanificacion->dias_habiles_otorgados)->toBe(2)
        ->and($plazoPlanificacion->estado)->toBe('VIGENTE');

    expect(Plazo::where('expediente_id', $expediente->id)
        ->where('tipo_plazo', 'EJECUCION')->exists())->toBeFalse();
});

it('el Visto Bueno a Planificacion arranca el reloj EJECUCION y pasa a EN_INVESTIGACION', function () {
    [
        'encargada' => $encargada,
        'reglamento' => $reglamento,
        'admitido' => $admitido,
        'catalogoVistoBueno' => $catalogoVistoBueno,
    ] = relojProcesualSemilla();

    $expediente = relojProcesualCrearExpediente($admitido->id, $encargada->id, $reglamento->id);

    Sanctum::actingAs($encargada, ['*']);

    $this->postJson('/api/expedientes/'.$expediente->id.'/actuados', [
        'catalogo_actuado_id' => $catalogoVistoBueno->id,
        'descripcion' => 'Visto Bueno al Cronograma de Investigacion presentado.',
    ])->assertStatus(201)
        ->assertJsonPath('data.estado_nuevo.codigo', 'EN_INVESTIGACION')
        ->assertJsonPath('data.tipo_actuado.codigo', 'ACT_VISTO_BUENO_PLANIFICACION');

    $plazoEjecucion = Plazo::where('expediente_id', $expediente->id)
        ->where('tipo_plazo', 'EJECUCION')
        ->first();

    expect($plazoEjecucion)->not->toBeNull()
        ->and($plazoEjecucion->dias_habiles_otorgados)->toBe(10)
        ->and($plazoEjecucion->estado)->toBe('VIGENTE')
        ->and($plazoEjecucion->actuado_disparador_id)->not->toBeNull();

    $esperado = app(PlazoCalculatorService::class)->calculateDueDate(now(), 10);
    expect($plazoEjecucion->fecha_limite->toDateString())->toBe($esperado->toDateString());
});

it('bloquea a un rol distinto de Encargada emitir el Visto Bueno', function () {
    [
        'tecnico' => $tecnico,
        'reglamento' => $reglamento,
        'admitido' => $admitido,
        'catalogoVistoBueno' => $catalogoVistoBueno,
    ] = relojProcesualSemilla();

    $expediente = relojProcesualCrearExpediente($admitido->id, $tecnico->id, $reglamento->id);
    relojProcesualAsignarActivamente($expediente, $tecnico, $admitido->id, $catalogoVistoBueno->id);

    Sanctum::actingAs($tecnico, ['*']);

    $this->postJson('/api/expedientes/'.$expediente->id.'/actuados', [
        'catalogo_actuado_id' => $catalogoVistoBueno->id,
        'descripcion' => 'Intento de visto bueno por un tecnico.',
    ])->assertForbidden();
});
