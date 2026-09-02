<?php

use App\Models\CatalogoActuado;
use App\Models\CatalogoEstado;
use App\Models\Rol;
use App\Models\Usuario;
use Laravel\Sanctum\Sanctum;

function fase3SemillaCatalogo(): array
{
    $rolOperador = Rol::factory()->create(['codigo' => Rol::CODIGO_AUD_JURIDICO]);
    $operador = Usuario::factory()->create(['rol_id' => $rolOperador->id]);

    $rolEncargada = Rol::factory()->create(['codigo' => Rol::CODIGO_ENCARGADA]);
    $encargada = Usuario::factory()->create(['rol_id' => $rolEncargada->id]);

    $evaluacion = CatalogoEstado::factory()->create(['codigo' => 'EN_EVALUACION']);

    $catalogoOperador = CatalogoActuado::create([
        'codigo' => 'ACT_OBSERVACION',
        'nombre' => 'Observacion',
        'fase' => 'ADMISIBILIDAD',
        'rol_id' => $rolOperador->id,
        'estado_origen_id' => $evaluacion->id,
        'es_automatico' => false,
        'requiere_adjunto' => false,
    ]);

    $catalogoAutomatico = CatalogoActuado::create([
        'codigo' => 'ACT_AUTOMATICO_TEST',
        'nombre' => 'Automatico',
        'fase' => 'TEST',
        'rol_id' => $rolOperador->id,
        'es_automatico' => true,
        'requiere_adjunto' => false,
    ]);

    return compact('rolOperador', 'operador', 'rolEncargada', 'encargada', 'evaluacion', 'catalogoOperador', 'catalogoAutomatico');
}

it('expone solo los actuados no automaticos del rol del operador', function () {
    [
        'operador' => $operador,
        'catalogoOperador' => $catalogoOperador,
        'catalogoAutomatico' => $catalogoAutomatico,
    ] = fase3SemillaCatalogo();

    Sanctum::actingAs($operador, ['*']);

    $response = $this->getJson('/api/catalogo/actuados')->assertOk();

    $codigos = array_column($response->json('data'), 'codigo');
    expect($codigos)->toContain($catalogoOperador->codigo)
        ->and($codigos)->not->toContain($catalogoAutomatico->codigo);
});

it('filtra el catalogo por estado origen cuando se envia el parametro', function () {
    [
        'operador' => $operador,
        'evaluacion' => $evaluacion,
        'catalogoOperador' => $catalogoOperador,
    ] = fase3SemillaCatalogo();

    $otroEstado = CatalogoEstado::factory()->create(['codigo' => 'ADMITIDO']);

    CatalogoActuado::create([
        'codigo' => 'ACT_INFORME_FINAL',
        'nombre' => 'Informe Final',
        'fase' => 'EJECUCION',
        'rol_id' => $operador->rol_id,
        'estado_origen_id' => $otroEstado->id,
        'es_automatico' => false,
        'requiere_adjunto' => false,
    ]);

    Sanctum::actingAs($operador, ['*']);

    $response = $this->getJson('/api/catalogo/actuados?estado_origen_id='.$evaluacion->id)->assertOk();

    $codigos = array_column($response->json('data'), 'codigo');
    expect($codigos)->toContain($catalogoOperador->codigo)
        ->and($codigos)->not->toContain('ACT_INFORME_FINAL');
});

it('devuelve 401 sin token de sanctum', function () {
    $this->getJson('/api/catalogo/actuados')->assertUnauthorized();
});
