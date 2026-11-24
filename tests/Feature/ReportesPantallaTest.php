<?php

use App\Models\CatalogoEstado;
use App\Models\Expediente;
use App\Models\Reglamento;
use App\Models\Rol;
use App\Models\Usuario;
use Database\Seeders\RolSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolSeeder::class);
});

function rptUsuario(string $codigoRol, bool $activo = true): Usuario
{
    $rol = Rol::where('codigo', $codigoRol)->firstOrFail();

    return Usuario::factory()->create(['rol_id' => $rol->id, 'activo' => $activo]);
}

function rptExpediente(array $atributos = []): Expediente
{
    $reglamento = Reglamento::factory()->create();
    $estado = CatalogoEstado::factory()->create(['codigo' => 'EN_EVALUACION', 'nombre' => 'En evaluación']);

    return Expediente::create(array_merge([
        'nurej_code' => '2026-'.fake()->unique()->numerify('#####'),
        'via' => 'TECNICO',
        'reglamento_id' => $reglamento->id,
        'estado_actual_id' => $estado->id,
        'resumen_hechos' => 'Hechos de prueba para la pantalla de reportes.',
        'fecha_ingreso' => now(),
        'creado_por' => rptUsuario(Rol::CODIGO_TECNICO)->id,
    ], $atributos));
}

it('muestra los 9 reportes a los roles autorizados', function (string $codigoRol) {
    $usuario = rptUsuario($codigoRol);

    $this->actingAs($usuario)
        ->get('/reportes')
        ->assertOk()
        ->assertSee('Reportes institucionales')
        ->assertSee('R01')
        ->assertSee('R09')
        ->assertSee('Descargar el reporte actual en Excel', false);
})->with([
    Rol::CODIGO_ENCARGADA,
    Rol::CODIGO_TECNICO,
    Rol::CODIGO_AUD_JURIDICO,
    Rol::CODIGO_AUD_FINANCIERO,
]);

it('oculta y bloquea los reportes al rol ADMIN', function () {
    $admin = rptUsuario(Rol::CODIGO_ADMIN);

    $this->actingAs($admin)->get('/reportes')->assertForbidden();

    Sanctum::actingAs($admin);
    $this->getJson('/api/reportes/R01/datos')->assertForbidden();
});

it('oculta y bloquea los reportes al rol de Ventanilla', function () {
    $ventanilla = rptUsuario(Rol::CODIGO_VENTANILLA);

    $this->actingAs($ventanilla)->get('/reportes')->assertForbidden();

    Sanctum::actingAs($ventanilla);
    $this->getJson('/api/reportes/R01/datos')->assertForbidden();
    $this->getJson('/api/reportes/R01/exportar/excel')->assertForbidden();
    $this->getJson('/api/reportes/R01/exportar/pdf')->assertForbidden();
});

it('bloquea los reportes a usuarios inactivos', function () {
    $usuario = rptUsuario(Rol::CODIGO_TECNICO, activo: false);

    $this->actingAs($usuario)->get('/reportes')->assertForbidden();

    Sanctum::actingAs($usuario);
    $this->getJson('/api/reportes/R01/datos')->assertForbidden();
});

it('redirige al login sin sesión y devuelve 401 en la API', function () {
    $this->get('/reportes')->assertRedirect('/login');
    $this->getJson('/api/reportes/R01/datos')->assertUnauthorized();
});

it('rechaza filtros de fecha inválidos con 422', function () {
    $usuario = rptUsuario(Rol::CODIGO_ENCARGADA);

    Sanctum::actingAs($usuario);

    $this->getJson('/api/reportes/R02/datos?fecha_desde=2026-11-20&fecha_hasta=2026-11-01')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['fecha_hasta']);
});

it('devuelve las filas del reporte con la misma forma que la pantalla', function () {
    $usuario = rptUsuario(Rol::CODIGO_ENCARGADA);
    $expediente = rptExpediente(['nurej_code' => '2026-00123']);

    Sanctum::actingAs($usuario);

    $respuesta = $this->getJson('/api/reportes/R02/datos?estado=EN_EVALUACION')
        ->assertOk()
        ->assertJsonPath('codigo', 'R02')
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.nurej', '2026-00123');

    expect($respuesta->json('columnas'))->toHaveCount(6)
        ->and($respuesta->json('filtros_aplicados.estado'))->toBe('EN_EVALUACION')
        ->and($respuesta->json('data.0.nurej'))->toBe($expediente->nurej_code);
});

it('acepta los códigos con prefijo RF y rechaza reportes inexistentes', function () {
    $usuario = rptUsuario(Rol::CODIGO_TECNICO);
    rptExpediente();

    Sanctum::actingAs($usuario);

    $this->getJson('/api/reportes/RF-R02/datos')
        ->assertOk()
        ->assertJsonPath('codigo', 'R02');

    $this->getJson('/api/reportes/R99/datos')->assertNotFound();
});

it('incluye el identificador del expediente en el reporte de carátula', function () {
    $usuario = rptUsuario(Rol::CODIGO_ENCARGADA);
    $expediente = rptExpediente();

    Sanctum::actingAs($usuario);

    $this->getJson('/api/reportes/R08/datos')
        ->assertOk()
        ->assertJsonPath('codigo', 'R08')
        ->assertJsonPath('data.0.expediente_id', $expediente->id);
});
