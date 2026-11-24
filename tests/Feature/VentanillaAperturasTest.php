<?php

use App\Models\CatalogoEstado;
use App\Models\Expediente;
use App\Models\Parte;
use App\Models\Reglamento;
use App\Models\Rol;
use App\Models\Usuario;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\actingAs;

function ventanillaAperturaUsuario(string $codigoRol, bool $activo = true): Usuario
{
    return Usuario::factory()->create([
        'rol_id' => Rol::factory()->create(['codigo' => $codigoRol])->id,
        'activo' => $activo,
    ]);
}

function ventanillaAperturaExpediente(
    Usuario $creador,
    Reglamento $reglamento,
    CatalogoEstado $estado,
    int $secuencia,
    string $resumen = 'Hechos denunciados ante la autoridad municipal.',
): Expediente {
    return Expediente::create([
        'nurej_code' => '2026-VEN-'.str_pad((string) $secuencia, 5, '0', STR_PAD_LEFT),
        'via' => 'TECNICO',
        'naturaleza' => Expediente::NATURALEZA_JURISDICCIONAL,
        'reglamento_id' => $reglamento->id,
        'estado_actual_id' => $estado->id,
        'resumen_hechos' => $resumen,
        'fecha_ingreso' => now()->subMinutes($secuencia),
        'creado_por' => $creador->id,
    ]);
}

test('requiere autenticación para consultar el historial de aperturas', function () {
    $this->getJson('/api/ventanilla/aperturas')->assertUnauthorized();
});

test('la ventanilla consulta solo sus aperturas, paginadas de quince en quince', function () {
    $rolVentanilla = Rol::factory()->create(['codigo' => Rol::CODIGO_VENTANILLA]);
    $ventanilla = Usuario::factory()->create(['rol_id' => $rolVentanilla->id]);
    $otraVentanilla = Usuario::factory()->create(['rol_id' => $rolVentanilla->id]);
    $reglamento = Reglamento::factory()->create();
    $estado = CatalogoEstado::factory()->create(['codigo' => 'PENDIENTE_SORTEO']);

    foreach (range(1, 17) as $secuencia) {
        ventanillaAperturaExpediente($ventanilla, $reglamento, $estado, $secuencia);
    }
    $ajeno = ventanillaAperturaExpediente($otraVentanilla, $reglamento, $estado, 18, 'Causa de otra ventanilla.');

    Sanctum::actingAs($ventanilla, ['*']);

    $primeraPagina = $this->getJson('/api/ventanilla/aperturas')
        ->assertOk()
        ->assertJsonCount(15, 'data')
        ->assertJsonPath('meta.total', 17)
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.last_page', 2)
        ->assertJsonPath('data.0.nurej_code', '2026-VEN-00001');

    expect(collect($primeraPagina->json('data.data'))->pluck('nurej_code'))
        ->not->toContain($ajeno->nurej_code);

    $this->getJson('/api/ventanilla/aperturas?page=2')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.current_page', 2);
});

test('la ventanilla ve el detalle de lectura de una causa que aperturó', function () {
    $ventanilla = ventanillaAperturaUsuario(Rol::CODIGO_VENTANILLA);
    $reglamento = Reglamento::factory()->create();
    $estado = CatalogoEstado::factory()->create(['codigo' => 'PENDIENTE_SORTEO']);
    $expediente = ventanillaAperturaExpediente($ventanilla, $reglamento, $estado, 1);

    Parte::create([
        'expediente_id' => $expediente->id,
        'tipo' => 'DENUNCIANTE',
        'nombre_completo' => 'María Pérez',
        'documento_identidad' => '1234567',
        'es_version_actual' => true,
    ]);

    Sanctum::actingAs($ventanilla, ['*']);

    $respuesta = $this->getJson("/api/ventanilla/aperturas/{$expediente->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $expediente->id)
        ->assertJsonPath('data.nurej_code', $expediente->nurej_code)
        ->assertJsonPath('data.naturaleza', Expediente::NATURALEZA_JURISDICCIONAL)
        ->assertJsonPath('data.reglamento.id', $reglamento->id)
        ->assertJsonPath('data.estado_actual.codigo', 'PENDIENTE_SORTEO')
        ->assertJsonPath('data.partes.0.nombre_completo', 'María Pérez');

    expect($respuesta->json('data'))->not->toHaveKey('movimientos')
        ->and($respuesta->json('data.responsable'))->toBeNull();
});

test('la ventanilla no ve el detalle de una causa aperturada por otro usuario', function () {
    $rolVentanilla = Rol::factory()->create(['codigo' => Rol::CODIGO_VENTANILLA]);
    $ventanilla = Usuario::factory()->create(['rol_id' => $rolVentanilla->id]);
    $otraVentanilla = Usuario::factory()->create(['rol_id' => $rolVentanilla->id]);
    $reglamento = Reglamento::factory()->create();
    $estado = CatalogoEstado::factory()->create(['codigo' => 'PENDIENTE_SORTEO']);
    $ajeno = ventanillaAperturaExpediente($otraVentanilla, $reglamento, $estado, 1);

    Sanctum::actingAs($ventanilla, ['*']);

    $this->getJson("/api/ventanilla/aperturas/{$ajeno->id}")->assertForbidden();
});

test('niega el historial y el detalle de ventanilla a otros roles activos', function (string $codigoRol) {
    $reglamento = Reglamento::factory()->create();
    $estado = CatalogoEstado::factory()->create(['codigo' => 'PENDIENTE_SORTEO']);
    $creador = ventanillaAperturaUsuario(Rol::CODIGO_VENTANILLA);
    $expediente = ventanillaAperturaExpediente($creador, $reglamento, $estado, 1);

    Sanctum::actingAs(ventanillaAperturaUsuario($codigoRol), ['*']);

    $this->getJson('/api/ventanilla/aperturas')->assertForbidden();
    $this->getJson("/api/ventanilla/aperturas/{$expediente->id}")->assertForbidden();
})->with([
    Rol::CODIGO_TECNICO,
    Rol::CODIGO_ENCARGADA,
    Rol::CODIGO_AUD_JURIDICO,
    Rol::CODIGO_AUD_FINANCIERO,
    Rol::CODIGO_ADMIN,
]);

test('niega el historial de aperturas a una ventanilla inactiva', function () {
    $ventanillaInactiva = ventanillaAperturaUsuario(Rol::CODIGO_VENTANILLA, activo: false);

    Sanctum::actingAs($ventanillaInactiva, ['*']);

    $this->getJson('/api/ventanilla/aperturas')->assertForbidden();
});

test('niega la apertura de causas al tecnico activo', function () {
    Sanctum::actingAs(ventanillaAperturaUsuario(Rol::CODIGO_TECNICO), ['*']);

    $this->postJson('/api/expedientes', [])->assertForbidden();
});

test('carga las paginas web de aperturas solo para la ventanilla', function () {
    $ventanilla = ventanillaAperturaUsuario(Rol::CODIGO_VENTANILLA);
    $reglamento = Reglamento::factory()->create();
    $estado = CatalogoEstado::factory()->create(['codigo' => 'PENDIENTE_SORTEO']);
    $expediente = ventanillaAperturaExpediente($ventanilla, $reglamento, $estado, 1);

    actingAs($ventanilla)
        ->get('/ventanilla/aperturas')
        ->assertOk()
        ->assertSee('Mis aperturas');

    actingAs($ventanilla)
        ->get("/ventanilla/aperturas/{$expediente->id}")
        ->assertOk()
        ->assertSee('Solo lectura');
});

test('niega las paginas web de aperturas al tecnico', function () {
    $tecnico = ventanillaAperturaUsuario(Rol::CODIGO_TECNICO);
    $reglamento = Reglamento::factory()->create();
    $estado = CatalogoEstado::factory()->create(['codigo' => 'PENDIENTE_SORTEO']);
    $expediente = ventanillaAperturaExpediente(
        ventanillaAperturaUsuario(Rol::CODIGO_VENTANILLA),
        $reglamento,
        $estado,
        1,
    );

    $this->withHeader('Accept', 'application/json');

    actingAs($tecnico)->get('/ventanilla/aperturas')->assertForbidden();
    actingAs($tecnico)->get("/ventanilla/aperturas/{$expediente->id}")->assertForbidden();
});
