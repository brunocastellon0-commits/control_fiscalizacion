<?php

use App\Models\Actuado;
use App\Models\Asignacion;
use App\Models\CatalogoActuado;
use App\Models\CatalogoEstado;
use App\Models\Expediente;
use App\Models\NurejSequence;
use App\Models\Parte;
use App\Models\Reglamento;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\ActuadoService;
use App\Services\BoletaAperturaService;
use App\Services\ExpedienteService;
use App\Services\FichaSorteoService;
use App\Services\NurejGeneratorService;
use App\Services\SorteoAlgorithmService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function setupDatosAperturaCausa(): array
{
    $rolVentanilla = Rol::factory()->create(['codigo' => Rol::CODIGO_VENTANILLA]);
    $ventanilla = Usuario::factory()->create(['rol_id' => $rolVentanilla->id]);
    $estado = CatalogoEstado::factory()->create(['codigo' => 'PENDIENTE_SORTEO']);
    $catalogoActuado = CatalogoActuado::create([
        'codigo' => 'ACT_REGISTRO_DIGITALIZACION',
        'nombre' => 'Registro y Digitalización',
        'fase' => 'REGISTRO',
        'rol_id' => $rolVentanilla->id,
        'estado_destino_id' => $estado->id,
        'es_automatico' => false,
        'requiere_adjunto' => true,
        'descripcion' => 'Registro y digitalización del expediente',
    ]);
    $reglamento = Reglamento::factory()->create();

    return [$rolVentanilla, $ventanilla, $estado, $catalogoActuado, $reglamento];
}

it('abre una causa registrando expediente, actuado y partes sin asignar bandeja', function () {
    Carbon::setTestNow('2026-08-28 10:00:00');
    Storage::fake('local');

    try {
        [, $ventanilla, $estado, $catalogoActuado, $reglamento] = setupDatosAperturaCausa();

        $adjunto = UploadedFile::fake()->create('denuncia.pdf', 100, 'application/pdf');

        $expediente = app(ExpedienteService::class)->aperturaCausa([
            'via' => 'INGRESO_VERBAL',
            'naturaleza' => 'JURISDICCIONAL',
            'reglamento_id' => $reglamento->id,
            'resumen_hechos' => 'Hechos denunciados.',
            'partes' => [
                ['tipo' => 'DENUNCIANTE', 'nombre_completo' => 'Juan Pérez', 'documento_identidad' => '1234567'],
                ['tipo' => 'DENUNCIADO', 'nombre_completo' => 'Autoridad Municipal'],
            ],
        ], $ventanilla, adjunto: $adjunto);

        expect($expediente->nurej_code)->toMatch('/^2026-\d{5}$/')
            ->and($expediente->estado_actual_id)->toBe($estado->id)
            ->and($expediente->via)->toBe('INGRESO_VERBAL')
            ->and($expediente->creado_por)->toBe($ventanilla->id);

        $actuado = $expediente->actuados()->first();
        expect($actuado)->not->toBeNull()
            ->and($actuado->catalogo_actuado_id)->toBe($catalogoActuado->id)
            ->and($actuado->usuario_id)->toBe($ventanilla->id)
            ->and($actuado->estado_anterior_id)->toBe($estado->id)
            ->and($actuado->estado_nuevo_id)->toBe($estado->id)
            ->and($actuado->contenido['tipo'])->toBe('APERTURA')
            ->and(array_key_exists('usuario_destino_id', $actuado->contenido))->toBeFalse()
            ->and($actuado->hash_anterior)->toBeNull()
            ->and($actuado->hash_actuado)->toMatch('/^[0-9a-f]{64}$/');

        expect($expediente->asignacionActiva)->toBeNull()
            ->and(Asignacion::count())->toBe(0);

        expect(Parte::where('expediente_id', $expediente->id)->count())->toBe(2)
            ->and(Parte::where('expediente_id', $expediente->id)->where('actuado_origen_id', $actuado->id)->count())->toBe(2);

        $adjuntoRegistrado = $actuado->adjuntos()->first();
        expect($adjuntoRegistrado)->not->toBeNull()
            ->and($adjuntoRegistrado->nombre_original)->toBe('denuncia.pdf')
            ->and($adjuntoRegistrado->subido_por)->toBe($ventanilla->id)
            ->and($adjuntoRegistrado->hash_sha256)->toMatch('/^[0-9a-f]{64}$/');
    } finally {
        Carbon::setTestNow();
    }
});

it('revierte toda la apertura si falla el registro del actuado', function () {
    [, $ventanilla, , , $reglamento] = setupDatosAperturaCausa();

    $actuadoService = Mockery::mock(ActuadoService::class);
    $actuadoService->shouldReceive('registerActuado')
        ->once()
        ->andThrow(new RuntimeException('Falla simulada de registro'));

    $service = new ExpedienteService(
        new NurejGeneratorService,
        $actuadoService,
        new SorteoAlgorithmService,
        app(BoletaAperturaService::class),
        app(FichaSorteoService::class),
    );

    expect(fn () => $service->aperturaCausa([
        'via' => 'INGRESO_VERBAL',
        'naturaleza' => 'JURISDICCIONAL',
        'reglamento_id' => $reglamento->id,
        'partes' => [
            ['tipo' => 'DENUNCIANTE', 'nombre_completo' => 'Juan Pérez'],
        ],
    ], $ventanilla))->toThrow(RuntimeException::class);

    expect(NurejSequence::count())->toBe(0)
        ->and(Expediente::count())->toBe(0)
        ->and(Parte::count())->toBe(0)
        ->and(Asignacion::count())->toBe(0)
        ->and(Actuado::count())->toBe(0);
});

it('revierte la apertura completa si falla la generación de la boleta', function () {
    Storage::fake('local');

    [, $ventanilla, , , $reglamento] = setupDatosAperturaCausa();

    $boletaService = Mockery::mock(BoletaAperturaService::class);
    $boletaService->shouldReceive('generar')
        ->once()
        ->andThrow(new RuntimeException('Falla simulada de boleta'));

    $service = new ExpedienteService(
        new NurejGeneratorService,
        app(ActuadoService::class),
        new SorteoAlgorithmService,
        $boletaService,
        app(FichaSorteoService::class),
    );

    expect(fn () => $service->aperturaCausa([
        'via' => 'INGRESO_VERBAL',
        'naturaleza' => 'JURISDICCIONAL',
        'reglamento_id' => $reglamento->id,
        'partes' => [
            ['tipo' => 'DENUNCIANTE', 'nombre_completo' => 'Juan Pérez'],
        ],
    ], $ventanilla, adjunto: UploadedFile::fake()->create('denuncia.pdf', 100, 'application/pdf')))
        ->toThrow(RuntimeException::class);

    expect(NurejSequence::count())->toBe(0)
        ->and(Expediente::count())->toBe(0)
        ->and(Parte::count())->toBe(0)
        ->and(Actuado::count())->toBe(0);
});
