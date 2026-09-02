<?php

use App\Models\Actuado;
use App\Models\Adjunto;
use App\Models\CatalogoActuado;
use App\Models\CatalogoEstado;
use App\Models\Expediente;
use App\Models\Reglamento;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\AdjuntoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function paso7SemillaActuadoReal(): array
{
    $rol = Rol::factory()->create();
    $usuario = Usuario::factory()->create(['rol_id' => $rol->id]);
    $reglamento = Reglamento::factory()->create();
    $estado = CatalogoEstado::factory()->create();
    $catalogoActuado = CatalogoActuado::create([
        'codigo' => 'ACT_ADJ_'.fake()->unique()->numberBetween(1000, 9999),
        'nombre' => 'Actuado con adjunto',
        'fase' => 'TEST',
        'rol_id' => $rol->id,
        'estado_destino_id' => $estado->id,
        'es_automatico' => false,
        'requiere_adjunto' => false,
    ]);

    $expediente = Expediente::create([
        'nurej_code' => 'ADJ-'.fake()->unique()->numberBetween(10000, 99999),
        'via' => 'TECNICO',
        'reglamento_id' => $reglamento->id,
        'estado_actual_id' => $estado->id,
        'fecha_ingreso' => now(),
        'creado_por' => $usuario->id,
    ]);

    $actuado = Actuado::create([
        'expediente_id' => $expediente->id,
        'catalogo_actuado_id' => $catalogoActuado->id,
        'usuario_id' => $usuario->id,
        'estado_nuevo_id' => $estado->id,
        'contenido' => ['descripcion' => 'con adjunto'],
    ])->refresh();

    return compact('usuario', 'actuado', 'expediente');
}

it('guarda un adjunto calculando el hash del contenido original y particionando por expediente', function () {
    Storage::fake('local');

    [$usuario, $actuado, $expediente] = array_values(paso7SemillaActuadoReal());

    $archivo = UploadedFile::fake()->create('informe.pdf', 200, 'application/pdf');

    $adjunto = app(AdjuntoService::class)->guardarParaActuado($actuado, $archivo, $usuario);

    expect($adjunto)->toBeInstanceOf(Adjunto::class)
        ->and($adjunto->actuado_id)->toBe($actuado->id)
        ->and($adjunto->nombre_original)->toBe('informe.pdf')
        ->and($adjunto->subido_por)->toBe($usuario->id)
        ->and($adjunto->hash_sha256)->toMatch('/^[0-9a-f]{64}$/')
        ->and($adjunto->mime_type)->toBe('application/pdf')
        ->and($adjunto->tamanio_bytes)->toBeGreaterThan(0)
        ->and($adjunto->subido_at)->not->toBeNull();

    expect($adjunto->ruta_almacenamiento)->toStartWith('adjuntos/'.$expediente->id.'/')
        ->and(Storage::disk('local')->exists($adjunto->ruta_almacenamiento))->toBeTrue();

    $hashReal = hash_file('sha256', $archivo->getRealPath());
    expect($adjunto->hash_sha256)->toBe($hashReal);
});

it('usa el hash del contenido en el nombre del archivo guardado', function () {
    Storage::fake('local');

    [$usuario, $actuado] = array_values(paso7SemillaActuadoReal());

    $archivo = UploadedFile::fake()->create('expediente.pdf', 100, 'application/pdf');

    $adjunto = app(AdjuntoService::class)->guardarParaActuado($actuado, $archivo, $usuario);

    $nombreArchivo = basename($adjunto->ruta_almacenamiento);
    expect($nombreArchivo)->toStartWith($adjunto->hash_sha256)
        ->and($nombreArchivo)->toEndWith('.pdf')
        ->and($adjunto->subido_at)->not->toBeNull();
});

it('borra el archivo físico si falla la persistencia del adjunto en BD', function () {
    Storage::fake('local');

    [$usuario, $actuado] = array_values(paso7SemillaActuadoReal());

    Adjunto::creating(function () {
        throw new RuntimeException('Fallo de BD al persistir el adjunto');
    });

    $archivo = UploadedFile::fake()->create('fallido.pdf', 100, 'application/pdf');

    expect(fn () => app(AdjuntoService::class)->guardarParaActuado($actuado, $archivo, $usuario))
        ->toThrow(RuntimeException::class, 'Fallo de BD al persistir el adjunto');

    expect(Storage::disk('local')->allFiles('adjuntos/'.$actuado->expediente_id))->toBe([]);
});
