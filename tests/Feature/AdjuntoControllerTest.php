<?php

use App\Models\Actuado;
use App\Models\Asignacion;
use App\Models\CatalogoActuado;
use App\Models\CatalogoEstado;
use App\Models\Expediente;
use App\Models\Reglamento;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\AdjuntoService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

function fase3SemillaAdjunto(): array
{
    $rolOperador = Rol::factory()->create(['codigo' => Rol::CODIGO_AUD_JURIDICO]);
    $operador = Usuario::factory()->create(['rol_id' => $rolOperador->id]);

    $rolEncargada = Rol::factory()->create(['codigo' => Rol::CODIGO_ENCARGADA]);
    $encargada = Usuario::factory()->create(['rol_id' => $rolEncargada->id]);

    $rolAjeno = Rol::factory()->create(['codigo' => Rol::CODIGO_AUD_FINANCIERO]);
    $ajeno = Usuario::factory()->create(['rol_id' => $rolAjeno->id]);

    $reglamento = Reglamento::factory()->create();
    $estado = CatalogoEstado::factory()->create();

    $catalogo = CatalogoActuado::create([
        'codigo' => 'ACT_SEMILLA_ADJ',
        'nombre' => 'Actuado con adjunto',
        'fase' => 'TEST',
        'rol_id' => $rolOperador->id,
        'estado_destino_id' => $estado->id,
        'es_automatico' => false,
        'requiere_adjunto' => false,
    ]);

    $expediente = Expediente::create([
        'nurej_code' => 'ADJCNT-'.fake()->unique()->numberBetween(10000, 99999),
        'via' => 'TECNICO',
        'reglamento_id' => $reglamento->id,
        'estado_actual_id' => $estado->id,
        'fecha_ingreso' => now(),
        'creado_por' => $operador->id,
    ]);

    $actuado = Actuado::create([
        'expediente_id' => $expediente->id,
        'catalogo_actuado_id' => $catalogo->id,
        'usuario_id' => $operador->id,
        'estado_nuevo_id' => $estado->id,
        'contenido' => ['descripcion' => 'con adjunto'],
    ])->refresh();

    return compact('operador', 'encargada', 'ajeno', 'expediente', 'actuado');
}

it('descarga el adjunto a un operador con asignacion activa (RF-03)', function () {
    Storage::fake('local');
    [
        'operador' => $operador,
        'actuado' => $actuado,
    ] = fase3SemillaAdjunto();

    $adjunto = app(AdjuntoService::class)->guardarParaActuado(
        $actuado,
        UploadedFile::fake()->create('informe.pdf', 100, 'application/pdf'),
        $operador,
    );

    $expediente = $actuado->expediente;
    Asignacion::create([
        'expediente_id' => $expediente->id,
        'usuario_id' => $operador->id,
        'rol_id' => $operador->rol_id,
        'actuado_origen_id' => $actuado->id,
        'fecha_asignacion' => now(),
        'activa' => true,
    ]);

    Sanctum::actingAs($operador, ['*']);

    $response = $this->get('/api/adjuntos/'.$adjunto->id.'/descargar')->assertOk();

    $disposition = $response->headers->get('content-disposition') ?? '';
    expect($disposition)->toContain('informe.pdf');
});

it('permite a la encargada descargar cualquier adjunto', function () {
    Storage::fake('local');
    [
        'encargada' => $encargada,
        'actuado' => $actuado,
    ] = fase3SemillaAdjunto();

    $adjunto = app(AdjuntoService::class)->guardarParaActuado(
        $actuado,
        UploadedFile::fake()->create('respaldo.pdf', 100, 'application/pdf'),
        $encargada,
    );

    Sanctum::actingAs($encargada, ['*']);

    $this->get('/api/adjuntos/'.$adjunto->id.'/descargar')->assertOk();
});

it('deniega 403 a un operador sin asignacion activa (RF-03)', function () {
    Storage::fake('local');
    [
        'ajeno' => $ajeno,
        'operador' => $operador,
        'actuado' => $actuado,
    ] = fase3SemillaAdjunto();

    $adjunto = app(AdjuntoService::class)->guardarParaActuado(
        $actuado,
        UploadedFile::fake()->create('privado.pdf', 100, 'application/pdf'),
        $operador,
    );

    Sanctum::actingAs($ajeno, ['*']);

    $this->get('/api/adjuntos/'.$adjunto->id.'/descargar')->assertForbidden();
});
