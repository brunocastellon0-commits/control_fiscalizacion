<?php

use App\Models\CatalogoEstado;
use App\Models\Expediente;
use App\Models\Parte;
use App\Models\Reglamento;
use App\Models\Rol;
use App\Models\Usuario;
use Database\Seeders\RolSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolSeeder::class);
});

function carUsuario(string $codigoRol, bool $activo = true): Usuario
{
    $rol = Rol::where('codigo', $codigoRol)->firstOrFail();

    return Usuario::factory()->create(['rol_id' => $rol->id, 'activo' => $activo]);
}

function carExpediente(array $atributos = []): Expediente
{
    $reglamento = Reglamento::factory()->create(['nombre' => 'Reglamento de prueba']);
    $estado = CatalogoEstado::firstOrCreate(
        ['codigo' => 'EN_EVALUACION'],
        ['nombre' => 'En evaluación'],
    );

    return Expediente::create(array_merge([
        'nurej_code' => '2026-'.fake()->unique()->numerify('#####'),
        'via' => 'TECNICO',
        'reglamento_id' => $reglamento->id,
        'estado_actual_id' => $estado->id,
        'resumen_hechos' => 'Síntesis de los hechos para la carátula oficial.',
        'fecha_ingreso' => now(),
        'creado_por' => carUsuario(Rol::CODIGO_TECNICO)->id,
    ], $atributos));
}

it('genera la carátula oficial A4 de un expediente', function () {
    $encargada = carUsuario(Rol::CODIGO_ENCARGADA);
    $expediente = carExpediente();

    Sanctum::actingAs($encargada);

    $respuesta = $this->get("/api/reportes/caratula/{$expediente->id}/pdf")
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    expect($respuesta->headers->get('content-disposition'))->toContain('.pdf')
        ->and($respuesta->getContent())->toStartWith('%PDF');
});

it('incluye NUREJ padre, derivados, fecha, vía, partes y sello de custodia', function () {
    $encargada = carUsuario(Rol::CODIGO_ENCARGADA);
    $padre = carExpediente(['nurej_code' => '2026-90001']);

    $expediente = carExpediente([
        'nurej_code' => '2026-90002',
        'nurej_padre_id' => $padre->id,
        'via' => 'JURIDICO',
        'fecha_ingreso' => '2026-01-15 09:30:00',
    ]);

    $derivado = carExpediente([
        'nurej_code' => '2026-90003',
        'nurej_padre_id' => $expediente->id,
    ]);

    Parte::create([
        'expediente_id' => $expediente->id,
        'tipo' => 'DENUNCIANTE',
        'nombre_completo' => 'María Fernanda Quispe',
        'documento_identidad' => '12345678',
        'cargo_institucion' => 'Servidora pública',
        'es_version_actual' => true,
    ]);

    $html = view('expedientes.caratula-pdf', [
        'expediente' => $expediente->load(['padre', 'hijos', 'partesVigentes', 'reglamento', 'estadoActual', 'creador']),
        'sello' => 'ABCD1234EFGH5678',
        'logo' => null,
        'generadoPor' => 'Encargada de prueba',
        'generadoEn' => now(),
    ])->render();

    expect($html)
        ->toContain('2026-90002')
        ->toContain('2026-90001')
        ->toContain('2026-90003')
        ->toContain('15/01/2026')
        ->toContain('JURIDICO')
        ->toContain('María Fernanda Quispe')
        ->toContain('ABCD1234EFGH5678')
        ->toContain('SELLO DE CUSTODIA')
        ->toContain((string) $derivado->nurej_code);
});

it('prohíbe la carátula a roles sin acceso y a usuarios inactivos', function () {
    $expediente = carExpediente();

    $admin = carUsuario(Rol::CODIGO_ADMIN);
    Sanctum::actingAs($admin);
    $this->get("/api/reportes/caratula/{$expediente->id}/pdf")->assertForbidden();

    $ventanilla = carUsuario(Rol::CODIGO_VENTANILLA);
    Sanctum::actingAs($ventanilla);
    $this->get("/api/reportes/caratula/{$expediente->id}/pdf")->assertForbidden();

    $inactivo = carUsuario(Rol::CODIGO_ENCARGADA, activo: false);
    Sanctum::actingAs($inactivo);
    $this->get("/api/reportes/caratula/{$expediente->id}/pdf")->assertForbidden();
});

it('respeta la asignación activa del operador en la carátula', function () {
    $tecnico = carUsuario(Rol::CODIGO_TECNICO);
    $expediente = carExpediente();

    Sanctum::actingAs($tecnico);
    $this->get("/api/reportes/caratula/{$expediente->id}/pdf")->assertForbidden();
});
