<?php

use App\Models\Actuado;
use App\Models\Asignacion;
use App\Models\CatalogoActuado;
use App\Models\CatalogoEstado;
use App\Models\Expediente;
use App\Models\Parte;
use App\Models\Reglamento;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

function paso7SemillaController(): array
{
    $rolTecnico = Rol::factory()->create(['codigo' => Rol::CODIGO_TECNICO]);
    $tecnico = Usuario::factory()->create(['rol_id' => $rolTecnico->id]);

    $rolEncargada = Rol::factory()->create(['codigo' => Rol::CODIGO_ENCARGADA]);
    $encargada = Usuario::factory()->create(['rol_id' => $rolEncargada->id]);

    $rolOperador = Rol::factory()->create(['codigo' => Rol::CODIGO_AUD_JURIDICO]);
    $operador = Usuario::factory()->create(['rol_id' => $rolOperador->id]);

    $reglamento = Reglamento::factory()->create();

    $pendiente = CatalogoEstado::factory()->create(['codigo' => 'PENDIENTE_SORTEO']);
    $evaluacion = CatalogoEstado::factory()->create(['codigo' => 'EN_EVALUACION']);

    $catalogoSorteo = CatalogoActuado::create([
        'codigo' => 'ACT_SORTEO_INICIAL',
        'nombre' => 'Sorteo Inicial',
        'fase' => 'ADMISIBILIDAD',
        'rol_id' => $rolEncargada->id,
        'estado_origen_id' => $pendiente->id,
        'estado_destino_id' => $evaluacion->id,
        'es_automatico' => false,
        'requiere_adjunto' => false,
    ]);

    $catalogoRegistro = CatalogoActuado::create([
        'codigo' => 'ACT_REGISTRO_DIGITALIZACION',
        'nombre' => 'Registro y Digitalización',
        'fase' => 'REGISTRO',
        'rol_id' => $rolTecnico->id,
        'estado_origen_id' => null,
        'estado_destino_id' => $pendiente->id,
        'es_automatico' => false,
        'requiere_adjunto' => true,
    ]);

    return compact(
        'rolTecnico', 'tecnico',
        'rolEncargada', 'encargada',
        'rolOperador', 'operador',
        'reglamento',
        'pendiente', 'evaluacion',
        'catalogoSorteo', 'catalogoRegistro',
    );
}

function paso7PayloadApertura(int $reglamentoId): array
{
    return [
        'via' => 'TECNICO',
        'reglamento_id' => $reglamentoId,
        'resumen_hechos' => 'Hechos denunciados ante la autoridad municipal.',
        'partes' => [
            ['tipo' => 'DENUNCIANTE', 'nombre_completo' => 'Juan Pérez', 'documento_identidad' => '1234567'],
            ['tipo' => 'DENUNCIADO', 'nombre_completo' => 'Autoridad Municipal', 'cargo_institucion' => 'Intendente'],
        ],
    ];
}

function paso7CrearExpedienteConEstado(int $estadoId, int $creadorId): Expediente
{
    return Expediente::create([
        'nurej_code' => 'EXP-'.fake()->unique()->numberBetween(10000, 99999),
        'via' => 'TECNICO',
        'reglamento_id' => Reglamento::factory()->create()->id,
        'estado_actual_id' => $estadoId,
        'fecha_ingreso' => now(),
        'creado_por' => $creadorId,
    ]);
}

function paso7AsignarActivamente(Expediente $expediente, Usuario $usuario, int $estadoId, int $catalogoId): void
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

it('permite a un tecnico abrir una causa y responde 201 con estructura', function () {
    Storage::fake('local');
    [
        'tecnico' => $tecnico,
        'reglamento' => $reglamento,
    ] = paso7SemillaController();

    Sanctum::actingAs($tecnico, ['*']);

    $response = $this->post('/api/expedientes', array_merge(
        paso7PayloadApertura($reglamento->id),
        ['adjunto' => UploadedFile::fake()->create('denuncia.pdf', 100, 'application/pdf')],
    ));

    $response->assertStatus(201)
        ->assertJsonPath('data.nurej_code', fn ($code) => is_string($code) && $code !== '')
        ->assertJsonPath('data.estado_actual.codigo', 'PENDIENTE_SORTEO');

    $expediente = Expediente::first();
    expect($expediente)->not->toBeNull()
        ->and($expediente->estado_actual_id)->toBe(
            CatalogoEstado::where('codigo', 'PENDIENTE_SORTEO')->value('id'),
        )
        ->and(Parte::where('expediente_id', $expediente->id)->count())->toBe(2);
});

it('devuelve 422 cuando la apertura carece del adjunto obligatorio', function () {
    [
        'tecnico' => $tecnico,
        'reglamento' => $reglamento,
    ] = paso7SemillaController();

    Sanctum::actingAs($tecnico, ['*']);

    $this->post('/api/expedientes', paso7PayloadApertura($reglamento->id))
        ->assertStatus(422)
        ->assertJsonValidationErrors('adjunto');
});

it('devuelve 403 a un usuario sin rol tecnico al intentar abrir causa', function () {
    [
        'encargada' => $encargada,
        'reglamento' => $reglamento,
    ] = paso7SemillaController();

    Sanctum::actingAs($encargada, ['*']);

    $this->postJson('/api/expedientes', paso7PayloadApertura($reglamento->id))
        ->assertForbidden();
});

it('devuelve detalle a un operador con asignacion activa (RF-03)', function () {
    [
        'operador' => $operador,
        'pendiente' => $pendiente,
        'catalogoRegistro' => $catalogoRegistro,
    ] = paso7SemillaController();

    $expediente = paso7CrearExpedienteConEstado($pendiente->id, $operador->id);
    paso7AsignarActivamente($expediente, $operador, $pendiente->id, $catalogoRegistro->id);

    Sanctum::actingAs($operador, ['*']);

    $this->getJson('/api/expedientes/'.$expediente->id)
        ->assertOk()
        ->assertJsonPath('data.nurej_code', $expediente->nurej_code)
        ->assertJsonPath('data.asignacion_activa.usuario.id', $operador->id);
});

it('devuelve 403 a un operador sin asignacion activa (RF-03)', function () {
    [
        'operador' => $operador,
        'pendiente' => $pendiente,
        'encargada' => $encargada,
    ] = paso7SemillaController();

    $expediente = paso7CrearExpedienteConEstado($pendiente->id, $encargada->id);

    Sanctum::actingAs($operador, ['*']);

    $this->getJson('/api/expedientes/'.$expediente->id)
        ->assertForbidden();
});

it('permite a la encargada ver el detalle de cualquier expediente', function () {
    [
        'encargada' => $encargada,
        'tecnico' => $tecnico,
        'pendiente' => $pendiente,
    ] = paso7SemillaController();

    $expediente = paso7CrearExpedienteConEstado($pendiente->id, $tecnico->id);

    Sanctum::actingAs($encargada, ['*']);

    $this->getJson('/api/expedientes/'.$expediente->id)
        ->assertOk()
        ->assertJsonPath('data.nurej_code', $expediente->nurej_code);
});

it('expone la bandeja de sorteo solo a la encargada', function () {
    [
        'encargada' => $encargada,
        'operador' => $operador,
        'pendiente' => $pendiente,
    ] = paso7SemillaController();

    $expediente = paso7CrearExpedienteConEstado($pendiente->id, $encargada->id);

    Sanctum::actingAs($operador, ['*']);
    $this->getJson('/api/bandeja/sorteo')->assertForbidden();

    Sanctum::actingAs($encargada, ['*']);
    $this->getJson('/api/bandeja/sorteo')
        ->assertOk()
        ->assertJsonPath('data.0.nurej_code', $expediente->nurej_code);
});

it('devuelve la bandeja del operador solo con expedientes asignados', function () {
    [
        'operador' => $operador,
        'encargada' => $encargada,
        'pendiente' => $pendiente,
        'catalogoRegistro' => $catalogoRegistro,
    ] = paso7SemillaController();

    $asignado = paso7CrearExpedienteConEstado($pendiente->id, $encargada->id);
    paso7AsignarActivamente($asignado, $operador, $pendiente->id, $catalogoRegistro->id);

    $ajeno = paso7CrearExpedienteConEstado($pendiente->id, $encargada->id);

    Sanctum::actingAs($operador, ['*']);

    $response = $this->getJson('/api/bandeja')->assertOk();

    $json = json_decode($response->getContent(), true);
    $nurejs = array_column($json['data'], 'nurej_code');
    expect($nurejs)->toContain($asignado->nurej_code)
        ->and($nurejs)->not->toContain($ajeno->nurej_code);
});

it('sortea un expediente pendiente y lo asigna al operador destino', function () {
    [
        'encargada' => $encargada,
        'operador' => $operador,
        'pendiente' => $pendiente,
    ] = paso7SemillaController();

    $expediente = paso7CrearExpedienteConEstado($pendiente->id, $encargada->id);

    Sanctum::actingAs($encargada, ['*']);

    $this->postJson('/api/expedientes/'.$expediente->id.'/sortear', [
        'usuario_destino_id' => $operador->id,
        'descripcion' => 'Sorteo inicial',
    ])->assertStatus(201);

    $expediente->refresh();
    expect($expediente->estado_actual_id)->toBe(
        CatalogoEstado::where('codigo', 'EN_EVALUACION')->value('id'),
    )
        ->and($expediente->asignacionActiva)->not->toBeNull()
        ->and($expediente->asignacionActiva->usuario_id)->toBe($operador->id);
});

it('rechaza sortear un expediente que no esta pendiente de sorteo', function () {
    [
        'encargada' => $encargada,
        'operador' => $operador,
        'evaluacion' => $evaluacion,
    ] = paso7SemillaController();

    $expediente = paso7CrearExpedienteConEstado($evaluacion->id, $encargada->id);

    Sanctum::actingAs($encargada, ['*']);

    $this->postJson('/api/expedientes/'.$expediente->id.'/sortear', [
        'usuario_destino_id' => $operador->id,
    ])->assertStatus(422);
});
