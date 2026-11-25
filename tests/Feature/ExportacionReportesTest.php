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

function expUsuario(string $codigoRol): Usuario
{
    $rol = Rol::where('codigo', $codigoRol)->firstOrFail();

    return Usuario::factory()->create(['rol_id' => $rol->id, 'activo' => true]);
}

function expExpediente(): Expediente
{
    $reglamento = Reglamento::factory()->create();
    $estado = CatalogoEstado::factory()->create(['codigo' => 'EN_EVALUACION', 'nombre' => 'En evaluación']);

    return Expediente::create([
        'nurej_code' => '2026-'.fake()->unique()->numerify('#####'),
        'via' => 'INGRESO_VERBAL',
        'reglamento_id' => $reglamento->id,
        'estado_actual_id' => $estado->id,
        'resumen_hechos' => 'Hechos de prueba para la exportación de reportes.',
        'fecha_ingreso' => now(),
        'creado_por' => expUsuario(Rol::CODIGO_TECNICO)->id,
    ]);
}

it('descarga cada reporte en Excel con formato XLSX', function (string $codigo) {
    $usuario = expUsuario(Rol::CODIGO_ENCARGADA);
    expExpediente();

    Sanctum::actingAs($usuario);

    $respuesta = $this->get("/api/reportes/{$codigo}/exportar/excel")
        ->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    expect($respuesta->headers->get('content-disposition'))->toContain('.xlsx')
        ->and($respuesta->getFile()->getContent())->toStartWith('PK');
})->with(['R01', 'R02', 'R03', 'R04', 'R05', 'R06', 'R07', 'R08', 'R09']);

it('descarga cada reporte en PDF institucional', function (string $codigo) {
    $usuario = expUsuario(Rol::CODIGO_ENCARGADA);
    expExpediente();

    Sanctum::actingAs($usuario);

    $respuesta = $this->get("/api/reportes/{$codigo}/exportar/pdf")
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    expect($respuesta->headers->get('content-disposition'))->toContain('.pdf')
        ->and($respuesta->getContent())->toStartWith('%PDF');
})->with(['R01', 'R02', 'R03', 'R04', 'R05', 'R06', 'R07', 'R08', 'R09']);

it('aplica los filtros a las descargas', function () {
    $usuario = expUsuario(Rol::CODIGO_ENCARGADA);
    expExpediente();

    Sanctum::actingAs($usuario);

    $this->get('/api/reportes/R02/exportar/excel?estado=RECHAZADO')
        ->assertOk()
        ->assertDownload();
});

it('prohíbe las descargas al rol ADMIN', function () {
    $admin = expUsuario(Rol::CODIGO_ADMIN);

    Sanctum::actingAs($admin);

    $this->get('/api/reportes/R01/exportar/excel')->assertForbidden();
    $this->get('/api/reportes/R01/exportar/pdf')->assertForbidden();
});
