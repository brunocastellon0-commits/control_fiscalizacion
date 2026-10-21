<?php

use App\Models\CatalogoActuado;
use App\Models\CatalogoEstado;
use App\Models\Expediente;
use App\Models\ParametroPlazo;
use App\Models\Plazo;
use App\Models\Reglamento;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\ActuadoService;
use App\Services\PlazoCalculatorService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

function naturalezaSemillaApertura(): array
{
    $rolTecnico = Rol::factory()->create(['codigo' => Rol::CODIGO_TECNICO]);
    $tecnico = Usuario::factory()->create(['rol_id' => $rolTecnico->id]);

    $pendiente = CatalogoEstado::factory()->create(['codigo' => 'PENDIENTE_SORTEO']);

    CatalogoActuado::create([
        'codigo' => 'ACT_REGISTRO_DIGITALIZACION',
        'nombre' => 'Registro y Digitalizacion',
        'fase' => 'REGISTRO',
        'rol_id' => $rolTecnico->id,
        'estado_destino_id' => $pendiente->id,
        'es_automatico' => false,
        'requiere_adjunto' => true,
    ]);

    $reglamento = Reglamento::factory()->create();

    return compact('tecnico', 'pendiente', 'reglamento');
}

function naturalezaPayloadApertura(int $reglamentoId, ?string $naturaleza): array
{
    $payload = [
        'via' => 'TECNICO',
        'reglamento_id' => $reglamentoId,
        'resumen_hechos' => 'Hechos denunciados ante la autoridad municipal.',
        'partes' => [
            ['tipo' => 'DENUNCIANTE', 'nombre_completo' => 'Juan Perez', 'documento_identidad' => '1234567'],
            ['tipo' => 'DENUNCIADO', 'nombre_completo' => 'Autoridad Municipal', 'cargo_institucion' => 'Intendente'],
        ],
    ];

    if ($naturaleza !== null) {
        $payload['naturaleza'] = $naturaleza;
    }

    return $payload;
}

function naturalezaSemillaVistoBueno(): array
{
    $rolEncargada = Rol::factory()->create(['codigo' => Rol::CODIGO_ENCARGADA]);
    $encargada = Usuario::factory()->create(['rol_id' => $rolEncargada->id]);

    $reglamento = Reglamento::factory()->create(['codigo' => 'AC_022_2018']);

    $admitido = CatalogoEstado::factory()->create(['codigo' => 'ADMITIDO']);
    $investigacion = CatalogoEstado::factory()->create(['codigo' => 'EN_INVESTIGACION']);

    CatalogoActuado::create([
        'codigo' => 'ACT_VISTO_BUENO_PLANIFICACION',
        'nombre' => 'Visto Bueno a Planificacion',
        'fase' => 'PLANIFICACION',
        'rol_id' => $rolEncargada->id,
        'estado_origen_id' => $admitido->id,
        'estado_destino_id' => $investigacion->id,
        'es_automatico' => false,
        'requiere_adjunto' => false,
    ]);

    CatalogoActuado::create([
        'codigo' => ActuadoService::CODIGO_PASO_PLANIFICACION,
        'nombre' => 'Paso Automatico a Planificacion',
        'fase' => 'ADMISIBILIDAD',
        'rol_id' => $rolEncargada->id,
        'estado_origen_id' => $admitido->id,
        'estado_destino_id' => $investigacion->id,
        'es_automatico' => true,
        'requiere_adjunto' => false,
    ]);

    ParametroPlazo::create([
        'reglamento_id' => $reglamento->id,
        'tipo_plazo' => 'EJECUCION',
        'subtipo' => 'JURISDICCIONAL',
        'dias_habiles' => 10,
        'base_legal' => 'AC_022_2018',
        'activo' => true,
    ]);

    ParametroPlazo::create([
        'reglamento_id' => $reglamento->id,
        'tipo_plazo' => 'EJECUCION',
        'subtipo' => 'ADMINISTRATIVA',
        'dias_habiles' => 15,
        'base_legal' => 'AC_022_2018',
        'activo' => true,
    ]);

    return compact('encargada', 'reglamento', 'admitido', 'investigacion');
}

function naturalezaCrearExpediente(int $estadoId, int $creadorId, int $reglamentoId, ?string $naturaleza): Expediente
{
    return Expediente::create([
        'nurej_code' => 'EXP-'.fake()->unique()->numberBetween(10000, 99999),
        'via' => 'TECNICO',
        'naturaleza' => $naturaleza,
        'reglamento_id' => $reglamentoId,
        'estado_actual_id' => $estadoId,
        'fecha_ingreso' => now(),
        'creado_por' => $creadorId,
    ]);
}

it('rechaza la apertura cuando falta la naturaleza', function () {
    Storage::fake('local');
    $semilla = naturalezaSemillaApertura();

    Sanctum::actingAs($semilla['tecnico'], ['*']);

    $this->post('/api/expedientes', naturalezaPayloadApertura($semilla['reglamento']->id, null), [
        'Accept' => 'application/json',
    ])->assertStatus(422)
        ->assertJsonValidationErrors('naturaleza');

    expect(Expediente::count())->toBe(0);
});

it('rechaza la apertura con una naturaleza fuera del enum', function () {
    Storage::fake('local');
    $semilla = naturalezaSemillaApertura();

    Sanctum::actingAs($semilla['tecnico'], ['*']);

    $this->post('/api/expedientes', naturalezaPayloadApertura($semilla['reglamento']->id, 'MIXTA'), [
        'Accept' => 'application/json',
    ])->assertStatus(422)
        ->assertJsonValidationErrors('naturaleza');

    expect(Expediente::count())->toBe(0);
});

it('persiste la naturaleza JURISDICCIONAL al abrir una causa', function () {
    Storage::fake('local');
    $semilla = naturalezaSemillaApertura();

    Sanctum::actingAs($semilla['tecnico'], ['*']);

    $this->post('/api/expedientes', array_merge(
        naturalezaPayloadApertura($semilla['reglamento']->id, 'JURISDICCIONAL'),
        ['adjunto' => UploadedFile::fake()->create('denuncia.pdf', 100, 'application/pdf')],
    ))->assertStatus(201);

    expect(Expediente::first()->naturaleza)->toBe(Expediente::NATURALEZA_JURISDICCIONAL);
});

it('persiste la naturaleza ADMINISTRATIVA al abrir una causa', function () {
    Storage::fake('local');
    $semilla = naturalezaSemillaApertura();

    Sanctum::actingAs($semilla['tecnico'], ['*']);

    $this->post('/api/expedientes', array_merge(
        naturalezaPayloadApertura($semilla['reglamento']->id, 'ADMINISTRATIVA'),
        ['adjunto' => UploadedFile::fake()->create('denuncia.pdf', 100, 'application/pdf')],
    ))->assertStatus(201);

    expect(Expediente::first()->naturaleza)->toBe(Expediente::NATURALEZA_ADMINISTRATIVA);
});

it('abre el reloj de ejecucion con 15 dias para una causa administrativa (RN-05)', function () {
    $semilla = naturalezaSemillaVistoBueno();

    $expediente = naturalezaCrearExpediente(
        $semilla['admitido']->id,
        $semilla['encargada']->id,
        $semilla['reglamento']->id,
        Expediente::NATURALEZA_ADMINISTRATIVA,
    );

    Sanctum::actingAs($semilla['encargada'], ['*']);

    $catalogoVb = CatalogoActuado::where('codigo', 'ACT_VISTO_BUENO_PLANIFICACION')->firstOrFail();

    $this->postJson('/api/expedientes/'.$expediente->id.'/actuados', [
        'catalogo_actuado_id' => $catalogoVb->id,
        'descripcion' => 'Visto Bueno al Cronograma de Investigacion presentado.',
    ])->assertStatus(201);

    $plazo = Plazo::where('expediente_id', $expediente->id)
        ->where('tipo_plazo', 'EJECUCION')
        ->first();

    expect($plazo)->not->toBeNull()
        ->and($plazo->dias_habiles_otorgados)->toBe(15)
        ->and($plazo->estado)->toBe('VIGENTE');

    $esperado = app(PlazoCalculatorService::class)->calculateDueDate(now(), 15);
    expect($plazo->fecha_limite->toDateString())->toBe($esperado->toDateString());
});

it('abre el reloj de ejecucion con 10 dias para una causa jurisdiccional (regresion RN-05)', function () {
    $semilla = naturalezaSemillaVistoBueno();

    $expediente = naturalezaCrearExpediente(
        $semilla['admitido']->id,
        $semilla['encargada']->id,
        $semilla['reglamento']->id,
        Expediente::NATURALEZA_JURISDICCIONAL,
    );

    Sanctum::actingAs($semilla['encargada'], ['*']);

    $catalogoVb = CatalogoActuado::where('codigo', 'ACT_VISTO_BUENO_PLANIFICACION')->firstOrFail();

    $this->postJson('/api/expedientes/'.$expediente->id.'/actuados', [
        'catalogo_actuado_id' => $catalogoVb->id,
        'descripcion' => 'Visto Bueno al Cronograma de Investigacion presentado.',
    ])->assertStatus(201);

    $plazo = Plazo::where('expediente_id', $expediente->id)
        ->where('tipo_plazo', 'EJECUCION')
        ->first();

    expect($plazo)->not->toBeNull()
        ->and($plazo->dias_habiles_otorgados)->toBe(10)
        ->and($plazo->estado)->toBe('VIGENTE');
});

it('bloquea con 422 el visto bueno de un expediente sin naturaleza que intenta abrir ejecucion', function () {
    $semilla = naturalezaSemillaVistoBueno();

    $expediente = naturalezaCrearExpediente(
        $semilla['admitido']->id,
        $semilla['encargada']->id,
        $semilla['reglamento']->id,
        null,
    );

    Sanctum::actingAs($semilla['encargada'], ['*']);

    $catalogoVb = CatalogoActuado::where('codigo', 'ACT_VISTO_BUENO_PLANIFICACION')->firstOrFail();

    $this->postJson('/api/expedientes/'.$expediente->id.'/actuados', [
        'catalogo_actuado_id' => $catalogoVb->id,
        'descripcion' => 'Visto Bueno sobre un expediente sin naturaleza.',
    ])->assertStatus(422)
        ->assertJsonValidationErrors('naturaleza');

    expect(Plazo::where('expediente_id', $expediente->id)->where('tipo_plazo', 'EJECUCION')->exists())->toBeFalse();
});
