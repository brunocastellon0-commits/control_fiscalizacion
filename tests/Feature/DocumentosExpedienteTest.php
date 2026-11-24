<?php

use App\Models\Actuado;
use App\Models\Asignacion;
use App\Models\CatalogoActuado;
use App\Models\CatalogoEstado;
use App\Models\Expediente;
use App\Models\Reglamento;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\FichaSorteoService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;

function documentosPdfSemilla(): array
{
    $rolTecnico = Rol::factory()->create(['codigo' => Rol::CODIGO_TECNICO]);
    $tecnico = Usuario::factory()->create(['rol_id' => $rolTecnico->id]);

    $rolVentanilla = Rol::factory()->create(['codigo' => Rol::CODIGO_VENTANILLA]);
    $ventanilla = Usuario::factory()->create(['rol_id' => $rolVentanilla->id]);
    $ventanillaAjena = Usuario::factory()->create(['rol_id' => $rolVentanilla->id]);

    $rolEncargada = Rol::factory()->create(['codigo' => Rol::CODIGO_ENCARGADA]);
    $encargada = Usuario::factory()->create(['rol_id' => $rolEncargada->id]);

    $reglamento = Reglamento::factory()->create();

    $pendiente = CatalogoEstado::factory()->create(['codigo' => 'PENDIENTE_SORTEO']);
    $evaluacion = CatalogoEstado::factory()->create(['codigo' => 'EN_EVALUACION']);

    CatalogoActuado::create([
        'codigo' => 'ACT_SORTEO_INICIAL',
        'nombre' => 'Sorteo Inicial',
        'fase' => 'ADMISIBILIDAD',
        'rol_id' => $rolEncargada->id,
        'estado_origen_id' => $pendiente->id,
        'estado_destino_id' => $evaluacion->id,
        'es_automatico' => false,
        'requiere_adjunto' => false,
    ]);

    CatalogoActuado::create([
        'codigo' => 'ACT_REGISTRO_DIGITALIZACION',
        'nombre' => 'Registro y Digitalización',
        'fase' => 'REGISTRO',
        'rol_id' => $rolVentanilla->id,
        'estado_origen_id' => null,
        'estado_destino_id' => $pendiente->id,
        'es_automatico' => false,
        'requiere_adjunto' => true,
    ]);

    return compact(
        'tecnico',
        'ventanilla',
        'ventanillaAjena',
        'encargada',
        'reglamento',
        'pendiente',
        'evaluacion',
    );
}

function documentosPdfPayload(int $reglamentoId): array
{
    return [
        'via' => 'TECNICO',
        'naturaleza' => 'JURISDICCIONAL',
        'reglamento_id' => $reglamentoId,
        'resumen_hechos' => 'Hechos denunciados para la constancia de recepción.',
        'partes' => [
            [
                'tipo' => 'DENUNCIANTE',
                'nombre_completo' => 'María Fernanda Quispe',
                'documento_identidad' => '12345678',
                'cargo_institucion' => 'Servidora pública',
            ],
            [
                'tipo' => 'DENUNCIADO',
                'nombre_completo' => 'Autoridad Municipal',
                'cargo_institucion' => 'Intendente',
            ],
        ],
    ];
}

function documentosPdfCrearExpediente(int $estadoId, int $creadorId): Expediente
{
    return Expediente::create([
        'nurej_code' => 'DOC-'.fake()->unique()->numberBetween(10000, 99999),
        'via' => 'TECNICO',
        'naturaleza' => 'JURISDICCIONAL',
        'reglamento_id' => Reglamento::factory()->create()->id,
        'estado_actual_id' => $estadoId,
        'fecha_ingreso' => now(),
        'resumen_hechos' => 'Hechos de prueba para documentos institucionales.',
        'creado_por' => $creadorId,
    ]);
}

it('genera la boleta de apertura y la expone en la respuesta de Ventanilla', function () {
    Storage::fake('local');

    [
        'ventanilla' => $ventanilla,
        'reglamento' => $reglamento,
    ] = documentosPdfSemilla();

    Sanctum::actingAs($ventanilla, ['*']);

    $response = $this->post('/api/expedientes', array_merge(
        documentosPdfPayload($reglamento->id),
        ['adjunto' => UploadedFile::fake()->create('denuncia.pdf', 100, 'application/pdf')],
    ));

    $response->assertStatus(201)
        ->assertJsonPath('data.boleta_apertura.url', fn ($url) => is_string($url) && $url !== '');

    $expediente = Expediente::firstOrFail();

    expect($expediente->boleta_apertura_path)->not->toBeNull()
        ->and(Storage::disk('local')->exists($expediente->boleta_apertura_path))->toBeTrue();

    $html = view('expedientes.boleta-apertura-pdf', [
        'expediente' => $expediente->load(['reglamento', 'estadoActual', 'creador', 'partesVigentes']),
        'sello' => 'SELLO-DE-PRUEBA',
        'logo' => null,
        'generadoPor' => 'Ventanilla de prueba',
        'generadoEn' => now(),
    ])->render();

    expect($html)
        ->toContain('NURED')
        ->toContain($expediente->nurej_code)
        ->toContain('RECEPCIÓN DE CAUSA')
        ->toContain('María Fernanda Quispe')
        ->toContain('Fecha de recepción')
        ->toContain('Hora de recepción')
        ->not->toContain('Fecha y hora de recepción')
        ->not->toContain('>Vía<')
        ->not->toContain('>Naturaleza<')
        ->not->toContain('>Reglamento<')
        ->not->toContain('>Estado<')
        ->not->toContain('CONSEJO DE LA MAGISTRATURA')
        ->not->toContain('Unidad de Control y Fiscalización')
        ->not->toContain('Constancia de Ventanilla')
        ->not->toContain('Emitida:')
        ->not->toContain('SELLO DE VERIFICACIÓN')
        ->not->toContain('SELLO-DE-PRUEBA')
        ->not->toContain('Funcionario de Ventanilla')
        ->not->toContain('Documento emitido el')
        ->not->toContain('#1b5e20');
});

it('permite descargar la boleta a la ventanilla creadora y a la encargada, no a terceros', function () {
    Storage::fake('local');

    [
        'ventanilla' => $ventanilla,
        'ventanillaAjena' => $ventanillaAjena,
        'encargada' => $encargada,
        'tecnico' => $tecnico,
        'pendiente' => $pendiente,
    ] = documentosPdfSemilla();

    $expediente = documentosPdfCrearExpediente($pendiente->id, $ventanilla->id);
    $ruta = 'boletas-apertura/'.$expediente->id.'/boleta.pdf';
    Storage::disk('local')->put($ruta, '%PDF-1.4 boleta de prueba');
    $expediente->forceFill(['boleta_apertura_path' => $ruta])->save();

    Sanctum::actingAs($ventanilla, ['*']);
    $this->get('/api/expedientes/'.$expediente->id.'/boleta-apertura')
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    Sanctum::actingAs($encargada, ['*']);
    $this->get('/api/expedientes/'.$expediente->id.'/boleta-apertura')
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    Sanctum::actingAs($ventanillaAjena, ['*']);
    $this->get('/api/expedientes/'.$expediente->id.'/boleta-apertura')->assertForbidden();

    Sanctum::actingAs($tecnico, ['*']);
    $this->get('/api/expedientes/'.$expediente->id.'/boleta-apertura')->assertForbidden();
});

it('genera la ficha de sorteo individual y solo la encargada puede descargarla', function () {
    Storage::fake('local');

    [
        'encargada' => $encargada,
        'tecnico' => $tecnico,
        'ventanilla' => $ventanilla,
        'pendiente' => $pendiente,
    ] = documentosPdfSemilla();

    $expediente = documentosPdfCrearExpediente($pendiente->id, $encargada->id);

    Sanctum::actingAs($encargada, ['*']);

    $this->postJson('/api/expedientes/'.$expediente->id.'/sortear', [
        'descripcion' => 'Sorteo individual para ficha',
    ])->assertStatus(201)
        ->assertJsonPath('data.ficha_sorteo.url', fn ($url) => is_string($url) && $url !== '');

    $expediente->refresh();

    expect($expediente->ficha_sorteo_path)->not->toBeNull()
        ->and(Storage::disk('local')->exists($expediente->ficha_sorteo_path))->toBeTrue();

    $actuado = Actuado::where('expediente_id', $expediente->id)->firstOrFail();
    $expediente->load('asignacionActiva.usuario.rol');

    $htmlFicha = view('expedientes.ficha-sorteo-pdf', [
        'expediente' => $expediente,
        'ganador' => $expediente->asignacionActiva->usuario,
        'encargada' => $encargada,
        'descripcion' => 'Sorteo individual para ficha',
        'actuado' => $actuado->loadMissing('tipoActuado'),
        'sello' => 'SELLO-DE-PRUEBA',
        'logo' => null,
        'generadoEn' => now(),
    ])->render();

    expect($htmlFicha)
        ->toContain('FICHA DE ASIGNACIÓN POR SORTEO')
        ->toContain('Fecha del sorteo')
        ->toContain('Hora del sorteo')
        ->toContain($actuado->fecha_hora->format('d/m/Y'))
        ->toContain($actuado->fecha_hora->format('H:i'))
        ->not->toContain('Sin registro')
        ->not->toContain('Estado del expediente')
        ->not->toContain('SELLO DE VERIFICACIÓN')
        ->not->toContain('SELLO-DE-PRUEBA')
        ->not->toContain('CONSEJO DE LA MAGISTRATURA')
        ->not->toContain('Documento emitido el')
        ->not->toContain('#1b5e20');

    $this->get('/api/expedientes/'.$expediente->id.'/ficha-sorteo')
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    Sanctum::actingAs($tecnico, ['*']);
    $this->get('/api/expedientes/'.$expediente->id.'/ficha-sorteo')->assertForbidden();

    Sanctum::actingAs($ventanilla, ['*']);
    $this->get('/api/expedientes/'.$expediente->id.'/ficha-sorteo')->assertForbidden();
});

it('muestra la ficha de sorteo inline para imprimir cuando se solicita', function () {
    Storage::fake('local');

    [
        'encargada' => $encargada,
        'pendiente' => $pendiente,
    ] = documentosPdfSemilla();

    $expediente = documentosPdfCrearExpediente($pendiente->id, $encargada->id);
    $ruta = 'fichas-sorteo/'.$expediente->id.'/ficha.pdf';
    Storage::disk('local')->put($ruta, '%PDF-1.4 ficha de prueba');
    $expediente->forceFill(['ficha_sorteo_path' => $ruta])->save();

    Sanctum::actingAs($encargada, ['*']);

    $this->get('/api/expedientes/'.$expediente->id.'/ficha-sorteo?impresion=1')
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertHeaderContains('content-disposition', 'inline');

    $this->get('/api/expedientes/'.$expediente->id.'/ficha-sorteo')
        ->assertOk()
        ->assertHeaderContains('content-disposition', 'attachment');
});

it('genera una ficha por cada causa en el sorteo masivo', function () {
    Storage::fake('local');

    [
        'encargada' => $encargada,
        'pendiente' => $pendiente,
    ] = documentosPdfSemilla();

    $primero = documentosPdfCrearExpediente($pendiente->id, $encargada->id);
    $segundo = documentosPdfCrearExpediente($pendiente->id, $encargada->id);

    Sanctum::actingAs($encargada, ['*']);

    $this->postJson('/api/bandeja/sorteo/todos')->assertOk();

    foreach ([$primero, $segundo] as $expediente) {
        $expediente->refresh();

        expect($expediente->ficha_sorteo_path)->not->toBeNull()
            ->and(Storage::disk('local')->exists($expediente->ficha_sorteo_path))->toBeTrue();
    }
});

it('filtra la bandeja de sorteo por el expediente indicado en la URL', function () {
    [
        'encargada' => $encargada,
        'pendiente' => $pendiente,
    ] = documentosPdfSemilla();

    $buscado = documentosPdfCrearExpediente($pendiente->id, $encargada->id);
    documentosPdfCrearExpediente($pendiente->id, $encargada->id);

    Sanctum::actingAs($encargada, ['*']);

    $this->getJson('/api/bandeja/sorteo?expediente='.$buscado->id)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $buscado->id);
});

it('expone puede_sortear solo mientras la causa está pendiente de sorteo', function () {
    [
        'encargada' => $encargada,
        'pendiente' => $pendiente,
        'evaluacion' => $evaluacion,
    ] = documentosPdfSemilla();

    $pendienteExpediente = documentosPdfCrearExpediente($pendiente->id, $encargada->id);
    $asignado = documentosPdfCrearExpediente($evaluacion->id, $encargada->id);

    Sanctum::actingAs($encargada, ['*']);

    $this->getJson('/api/encargada/historial/'.$pendienteExpediente->id)
        ->assertOk()
        ->assertJsonPath('data.puede_sortear', true);

    $this->getJson('/api/encargada/historial/'.$asignado->id)
        ->assertOk()
        ->assertJsonPath('data.puede_sortear', false);
});

it('revierte el sorteo completo si falla la generación de la ficha', function () {
    Storage::fake('local');

    [
        'encargada' => $encargada,
        'pendiente' => $pendiente,
    ] = documentosPdfSemilla();

    $expediente = documentosPdfCrearExpediente($pendiente->id, $encargada->id);

    $this->mock(FichaSorteoService::class, function (MockInterface $mock) {
        $mock->shouldReceive('generar')
            ->once()
            ->andThrow(new RuntimeException('Falla simulada de ficha'));
    });

    Sanctum::actingAs($encargada, ['*']);

    expect(fn () => $this->withoutExceptionHandling()
        ->postJson('/api/expedientes/'.$expediente->id.'/sortear', [
            'descripcion' => 'Sorteo con falla de ficha',
        ]))->toThrow(RuntimeException::class);

    $expediente->refresh();

    expect($expediente->estado_actual_id)->toBe($pendiente->id)
        ->and($expediente->ficha_sorteo_path)->toBeNull()
        ->and(Actuado::where('expediente_id', $expediente->id)->count())->toBe(0)
        ->and(Asignacion::where('expediente_id', $expediente->id)->count())->toBe(0);
});
