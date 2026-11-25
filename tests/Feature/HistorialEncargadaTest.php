<?php

use App\Models\Actuado;
use App\Models\Asignacion;
use App\Models\CatalogoActuado;
use App\Models\CatalogoEstado;
use App\Models\Expediente;
use App\Models\Parte;
use App\Models\Plazo;
use App\Models\Reglamento;
use App\Models\Rol;
use App\Models\Usuario;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\actingAs;

function historialEncargadaUsuario(string $codigoRol, bool $activo = true): Usuario
{
    $rol = Rol::firstOrCreate(
        ['codigo' => $codigoRol],
        ['nombre' => $codigoRol],
    );

    return Usuario::factory()->create([
        'rol_id' => $rol->id,
        'activo' => $activo,
    ]);
}

function historialEncargadaExpediente(
    Usuario $creador,
    Reglamento $reglamento,
    CatalogoEstado $estado,
    int $secuencia,
    string $resumen = 'Hechos denunciados ante la autoridad municipal.',
): Expediente {
    return Expediente::create([
        'nurej_code' => '2026-ENC-'.str_pad((string) $secuencia, 5, '0', STR_PAD_LEFT),
        'via' => 'INGRESO_VERBAL',
        'naturaleza' => Expediente::NATURALEZA_JURISDICCIONAL,
        'reglamento_id' => $reglamento->id,
        'estado_actual_id' => $estado->id,
        'resumen_hechos' => $resumen,
        'fecha_ingreso' => now()->subMinutes($secuencia),
        'creado_por' => $creador->id,
    ]);
}

function historialEncargadaActuado(
    Expediente $expediente,
    Usuario $usuario,
    CatalogoEstado $estado,
): Actuado {
    $catalogo = CatalogoActuado::create([
        'codigo' => fake()->unique()->numerify('ACT_ENC_####'),
        'nombre' => 'Asignación de prueba',
        'fase' => 'PRUEBA',
        'rol_id' => $usuario->rol_id,
        'estado_destino_id' => $estado->id,
        'es_automatico' => false,
        'requiere_adjunto' => false,
    ]);

    return Actuado::create([
        'expediente_id' => $expediente->id,
        'catalogo_actuado_id' => $catalogo->id,
        'usuario_id' => $usuario->id,
        'estado_nuevo_id' => $estado->id,
        'contenido' => ['descripcion' => 'Asignación de prueba'],
    ])->refresh();
}

test('requiere autenticación para consultar el historial general', function () {
    $this->getJson('/api/encargada/historial')->assertUnauthorized();
    $this->getJson('/api/encargada/historial/1')->assertUnauthorized();
});

test('la encargada ve todos los expedientes, incluidos finales y remitidos, paginados de quince en quince', function () {
    $encargada = historialEncargadaUsuario(Rol::CODIGO_ENCARGADA);
    $creador = historialEncargadaUsuario(Rol::CODIGO_VENTANILLA);
    $reglamento = Reglamento::factory()->create();
    $estado = CatalogoEstado::factory()->create(['codigo' => 'PENDIENTE_SORTEO']);
    $estadoFinal = CatalogoEstado::factory()->create(['codigo' => 'CONCLUIDO', 'es_final' => true]);
    $estadoRemitido = CatalogoEstado::factory()->create(['codigo' => 'REMITIDO_UNIDAD_EXTERNA']);

    foreach (range(1, 17) as $secuencia) {
        historialEncargadaExpediente($creador, $reglamento, $estado, $secuencia);
    }
    $final = historialEncargadaExpediente($creador, $reglamento, $estadoFinal, 18);
    $remitido = historialEncargadaExpediente($creador, $reglamento, $estadoRemitido, 19);

    Sanctum::actingAs($encargada, ['*']);

    $primeraPagina = $this->getJson('/api/encargada/historial')
        ->assertOk()
        ->assertJsonCount(15, 'data')
        ->assertJsonPath('meta.total', 19)
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.last_page', 2)
        ->assertJsonPath('data.0.nurej_code', '2026-ENC-00001');

    expect(collect($primeraPagina->json('data'))->pluck('nurej_code'))
        ->not->toContain($final->nurej_code)
        ->not->toContain($remitido->nurej_code);

    $segundaPagina = $this->getJson('/api/encargada/historial?page=2')
        ->assertOk()
        ->assertJsonCount(4, 'data')
        ->assertJsonPath('meta.current_page', 2);

    expect(collect($segundaPagina->json('data'))->pluck('nurej_code'))
        ->toContain($final->nurej_code)
        ->toContain($remitido->nurej_code);
});

test('la encargada filtra el historial general por texto y por estado', function () {
    $encargada = historialEncargadaUsuario(Rol::CODIGO_ENCARGADA);
    $creador = historialEncargadaUsuario(Rol::CODIGO_VENTANILLA);
    $reglamento = Reglamento::factory()->create();
    $estadoUno = CatalogoEstado::factory()->create(['codigo' => 'EN_EVALUACION']);
    $estadoDos = CatalogoEstado::factory()->create(['codigo' => 'EN_EJECUCION']);

    historialEncargadaExpediente($creador, $reglamento, $estadoUno, 1, 'Denuncia por uso de bienes municipales.');
    historialEncargadaExpediente($creador, $reglamento, $estadoDos, 2, 'Denuncia por demoras en la atención.');

    Sanctum::actingAs($encargada, ['*']);

    $this->getJson('/api/encargada/historial?busqueda=bienes')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.nurej_code', '2026-ENC-00001');

    $this->getJson('/api/encargada/historial?estado=EN_EJECUCION')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.nurej_code', '2026-ENC-00002');
});

test('el detalle del historial general es reducido y no expone adjuntos ni acciones', function () {
    $encargada = historialEncargadaUsuario(Rol::CODIGO_ENCARGADA);
    $creador = historialEncargadaUsuario(Rol::CODIGO_VENTANILLA);
    $responsable = historialEncargadaUsuario(Rol::CODIGO_TECNICO);
    $reglamento = Reglamento::factory()->create();
    $estado = CatalogoEstado::factory()->create(['codigo' => 'CONCLUIDO', 'es_final' => true]);
    $expediente = historialEncargadaExpediente($creador, $reglamento, $estado, 1);
    $actuado = historialEncargadaActuado($expediente, $responsable, $estado);

    Asignacion::create([
        'expediente_id' => $expediente->id,
        'usuario_id' => $responsable->id,
        'rol_id' => $responsable->rol_id,
        'actuado_origen_id' => $actuado->id,
        'fecha_asignacion' => now(),
        'activa' => true,
    ]);

    Parte::create([
        'expediente_id' => $expediente->id,
        'tipo' => 'DENUNCIANTE',
        'nombre_completo' => 'María Pérez',
        'documento_identidad' => '1234567',
        'es_version_actual' => true,
    ]);

    Plazo::create([
        'expediente_id' => $expediente->id,
        'tipo_plazo' => 'EJECUCION',
        'dias_habiles_otorgados' => 10,
        'fecha_inicio' => now()->toDateString(),
        'fecha_limite' => now()->addDays(10)->toDateString(),
        'estado' => 'VIGENTE',
        'actuado_disparador_id' => $actuado->id,
    ]);

    Sanctum::actingAs($encargada, ['*']);

    $respuesta = $this->getJson("/api/encargada/historial/{$expediente->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $expediente->id)
        ->assertJsonPath('data.estado_actual.es_final', true)
        ->assertJsonPath('data.creador.id', $creador->id)
        ->assertJsonPath('data.responsable.usuario.id', $responsable->id)
        ->assertJsonPath('data.sem_plazo.codigo_color', 'VERDE');

    expect($respuesta->json('data'))
        ->not->toHaveKey('actuados')
        ->not->toHaveKey('adjuntos')
        ->not->toHaveKey('pasos')
        ->not->toHaveKey('partes_vigentes')
        ->not->toHaveKey('partes')
        ->not->toHaveKey('plazos')
        ->not->toHaveKey('movimientos');
});

test('niega el historial general a otros roles activos', function (string $codigoRol) {
    $creador = historialEncargadaUsuario(Rol::CODIGO_VENTANILLA);
    $reglamento = Reglamento::factory()->create();
    $estado = CatalogoEstado::factory()->create(['codigo' => 'PENDIENTE_SORTEO']);
    $expediente = historialEncargadaExpediente($creador, $reglamento, $estado, 1);

    Sanctum::actingAs(historialEncargadaUsuario($codigoRol), ['*']);

    $this->getJson('/api/encargada/historial')->assertForbidden();
    $this->getJson("/api/encargada/historial/{$expediente->id}")->assertForbidden();
})->with([
    Rol::CODIGO_TECNICO,
    Rol::CODIGO_AUD_JURIDICO,
    Rol::CODIGO_AUD_FINANCIERO,
    Rol::CODIGO_VENTANILLA,
    Rol::CODIGO_ADMIN,
]);

test('niega el historial general a una encargada inactiva', function () {
    $encargadaInactiva = historialEncargadaUsuario(Rol::CODIGO_ENCARGADA, activo: false);

    Sanctum::actingAs($encargadaInactiva, ['*']);

    $this->getJson('/api/encargada/historial')->assertForbidden();
});

test('carga las paginas web del historial general solo para la encargada', function () {
    $encargada = historialEncargadaUsuario(Rol::CODIGO_ENCARGADA);
    $creador = historialEncargadaUsuario(Rol::CODIGO_VENTANILLA);
    $reglamento = Reglamento::factory()->create();
    $estado = CatalogoEstado::factory()->create(['codigo' => 'PENDIENTE_SORTEO']);
    $expediente = historialEncargadaExpediente($creador, $reglamento, $estado, 1);

    actingAs($encargada)
        ->get('/encargada/historial')
        ->assertOk()
        ->assertSee('Historial general');

    actingAs($encargada)
        ->get("/encargada/historial/{$expediente->id}")
        ->assertOk()
        ->assertSee('Solo lectura');
});

test('niega las paginas web del historial general al tecnico', function () {
    $tecnico = historialEncargadaUsuario(Rol::CODIGO_TECNICO);
    $creador = historialEncargadaUsuario(Rol::CODIGO_VENTANILLA);
    $reglamento = Reglamento::factory()->create();
    $estado = CatalogoEstado::factory()->create(['codigo' => 'PENDIENTE_SORTEO']);
    $expediente = historialEncargadaExpediente($creador, $reglamento, $estado, 1);

    actingAs($tecnico)->get('/encargada/historial')->assertForbidden();
    actingAs($tecnico)->get("/encargada/historial/{$expediente->id}")->assertForbidden();
});
