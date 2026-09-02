<?php

use App\Models\Actuado;
use App\Models\Adjunto;
use App\Models\CatalogoActuado;
use App\Models\CatalogoEstado;
use App\Models\Expediente;
use App\Models\Reglamento;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\ActuadoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function paso7SemillaActuadoService(bool $requiereAdjunto, bool $asignaDestino = false): array
{
    $rolEmisor = Rol::factory()->create();
    $emisor = Usuario::factory()->create(['rol_id' => $rolEmisor->id]);

    $rolDestino = $asignaDestino ? Rol::factory()->create() : null;
    $usuarioDestino = $asignaDestino
        ? Usuario::factory()->create(['rol_id' => $rolDestino->id])
        : null;

    $reglamento = Reglamento::factory()->create();
    $estadoOrigen = CatalogoEstado::factory()->create(['codigo' => 'EN_EVALUACION']);
    $estadoDestino = CatalogoEstado::factory()->create(['codigo' => 'SUBSANACION']);

    $catalogoActuado = CatalogoActuado::create([
        'codigo' => 'ACT_ADU_'.fake()->unique()->numberBetween(1000, 9999),
        'nombre' => 'Actuado de prueba',
        'fase' => 'TEST',
        'rol_id' => $rolEmisor->id,
        'estado_origen_id' => $estadoOrigen->id,
        'estado_destino_id' => $estadoDestino->id,
        'es_automatico' => false,
        'requiere_adjunto' => $requiereAdjunto,
    ]);

    $expediente = Expediente::create([
        'nurej_code' => 'SRV-'.fake()->unique()->numberBetween(10000, 99999),
        'via' => 'TECNICO',
        'reglamento_id' => $reglamento->id,
        'estado_actual_id' => $estadoOrigen->id,
        'fecha_ingreso' => now(),
        'creado_por' => $emisor->id,
    ]);

    return compact('emisor', 'usuarioDestino', 'estadoOrigen', 'estadoDestino', 'catalogoActuado', 'expediente');
}

function paso7ServicioActuado(): ActuadoService
{
    return app(ActuadoService::class);
}

it('registra un actuado con adjunto, transiciona estado y deja el hash de custodia', function () {
    Storage::fake('local');

    [
        'emisor' => $emisor,
        'usuarioDestino' => $usuarioDestino,
        'estadoDestino' => $estadoDestino,
        'catalogoActuado' => $catalogoActuado,
        'expediente' => $expediente,
    ] = paso7SemillaActuadoService(false, asignaDestino: true);

    $adjunto = UploadedFile::fake()->create('denuncia.pdf', 100, 'application/pdf');

    $estadoAnteriorEsperado = $expediente->estado_actual_id;

    $actuado = paso7ServicioActuado()->registerActuado(
        expediente: $expediente,
        catalogoActuado: $catalogoActuado,
        emisor: $emisor,
        descripcion: 'Actuado con adjunto',
        usuarioDestinoId: $usuarioDestino->id,
        ipOrigen: '127.0.0.1',
        adjunto: $adjunto,
    );

    expect($actuado)->toBeInstanceOf(Actuado::class)
        ->and($actuado->expediente_id)->toBe($expediente->id)
        ->and($actuado->catalogo_actuado_id)->toBe($catalogoActuado->id)
        ->and($actuado->estado_anterior_id)->toBe($estadoAnteriorEsperado)
        ->and($actuado->estado_nuevo_id)->toBe($catalogoActuado->estado_destino_id)
        ->and($actuado->contenido['usuario_destino_id'])->toBe($usuarioDestino->id)
        ->and($actuado->ip_origen)->toBe('127.0.0.1')
        ->and($actuado->hash_anterior)->toBeNull()
        ->and($actuado->hash_actuado)->toMatch('/^[0-9a-f]{64}$/');

    $expediente->refresh();
    expect($expediente->estado_actual_id)->toBe($estadoDestino->id);

    expect($expediente->asignacionActiva)->not->toBeNull()
        ->and($expediente->asignacionActiva->usuario_id)->toBe($usuarioDestino->id);

    $adjuntoRegistrado = Adjunto::where('actuado_id', $actuado->id)->first();
    expect($adjuntoRegistrado)->not->toBeNull()
        ->and($adjuntoRegistrado->nombre_original)->toBe('denuncia.pdf')
        ->and($adjuntoRegistrado->subido_por)->toBe($emisor->id)
        ->and(Storage::disk('local')->exists($adjuntoRegistrado->ruta_almacenamiento))->toBeTrue();
});

it('no exige adjunto cuando el catálogo no lo requiere y no llega archivo', function () {
    [
        'emisor' => $emisor,
        'catalogoActuado' => $catalogoActuado,
        'expediente' => $expediente,
    ] = paso7SemillaActuadoService(false);

    $actuado = paso7ServicioActuado()->registerActuado(
        expediente: $expediente,
        catalogoActuado: $catalogoActuado,
        emisor: $emisor,
        descripcion: 'Actuado sin adjunto, no requerido',
    );

    expect($actuado)->toBeInstanceOf(Actuado::class)
        ->and(Adjunto::where('actuado_id', $actuado->id)->count())->toBe(0);
});

it('lanza ValidationException (422) si el catálogo exige adjunto y no llega archivo', function () {
    [
        'emisor' => $emisor,
        'catalogoActuado' => $catalogoActuado,
        'expediente' => $expediente,
    ] = paso7SemillaActuadoService(true);

    $previos = Actuado::count();

    try {
        paso7ServicioActuado()->registerActuado(
            expediente: $expediente,
            catalogoActuado: $catalogoActuado,
            emisor: $emisor,
            descripcion: 'Actuado que exige adjunto',
        );
        $this->fail('Debió lanzar ValidationException.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('adjunto');
    }

    // No debe haberse persistido ningún actuado inmutable.
    expect(Actuado::count())->toBe($previos);
});
