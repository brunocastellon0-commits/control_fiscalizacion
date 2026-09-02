<?php

use App\Models\Reglamento;
use App\Models\Rol;
use App\Models\Usuario;
use Laravel\Sanctum\Sanctum;

function pasoCRol(string $codigoRol): Rol
{
    return Rol::factory()->create(['codigo' => $codigoRol]);
}

function pasoCUsuario(string $codigoRol, bool $activo = true): Usuario
{
    return Usuario::factory()->create([
        'rol_id' => pasoCRol($codigoRol)->id,
        'activo' => $activo,
    ]);
}

it('devuelve 401 sin token de sanctum', function () {
    $this->getJson('/api/reglamentos')->assertUnauthorized();
});

it('lista los reglamentos activos y vigentes ordenados por codigo', function () {
    $tecnico = pasoCUsuario(Rol::CODIGO_TECNICO);

    Reglamento::factory()->create(['codigo' => 'AA_001', 'activo' => true]);
    Reglamento::factory()->create(['codigo' => 'BB_002', 'activo' => true]);

    Sanctum::actingAs($tecnico, ['*']);

    $response = $this->getJson('/api/reglamentos')->assertOk();

    $response->assertJsonStructure(['data' => [['id', 'codigo', 'nombre', 'version']]]);

    $codigos = array_column($response->json('data'), 'codigo');
    expect($codigos)->toBe(['AA_001', 'BB_002']);
});

it('expone la estructura completa del recurso reglamento', function () {
    $tecnico = pasoCUsuario(Rol::CODIGO_TECNICO);
    $reglamento = Reglamento::factory()->create(['codigo' => 'AA_001']);

    Sanctum::actingAs($tecnico, ['*']);

    $this->getJson('/api/reglamentos')->assertOk()
        ->assertJsonPath('data.0.id', $reglamento->id)
        ->assertJsonPath('data.0.codigo', $reglamento->codigo)
        ->assertJsonPath('data.0.nombre', $reglamento->nombre)
        ->assertJsonPath('data.0.version', $reglamento->version);
});

it('no expone reglamentos inactivos', function () {
    $tecnico = pasoCUsuario(Rol::CODIGO_TECNICO);

    Reglamento::factory()->create(['codigo' => 'ACTIVO', 'activo' => true]);
    Reglamento::factory()->create(['codigo' => 'INACTIVO', 'activo' => false]);

    Sanctum::actingAs($tecnico, ['*']);

    $response = $this->getJson('/api/reglamentos')->assertOk();

    $codigos = array_column($response->json('data'), 'codigo');
    expect($codigos)->toContain('ACTIVO')
        ->not->toContain('INACTIVO');
});

it('no expone reglamentos activos pero no vigentes a la fecha', function () {
    $tecnico = pasoCUsuario(Rol::CODIGO_TECNICO);

    Reglamento::factory()->create(['codigo' => 'VIGENTE', 'activo' => true]);
    Reglamento::factory()->create([
        'codigo' => 'VENCIDO',
        'activo' => true,
        'vigente_desde' => '2000-01-01',
        'vigente_hasta' => '2000-12-31',
    ]);
    Reglamento::factory()->create([
        'codigo' => 'FUTURO',
        'activo' => true,
        'vigente_desde' => now()->addYears(5)->toDateString(),
    ]);

    Sanctum::actingAs($tecnico, ['*']);

    $response = $this->getJson('/api/reglamentos')->assertOk();

    $codigos = array_column($response->json('data'), 'codigo');
    expect($codigos)->toContain('VIGENTE')
        ->not->toContain('VENCIDO')
        ->not->toContain('FUTURO');
});

it('deniega 403 a un usuario inactivo', function () {
    $inactivo = pasoCUsuario(Rol::CODIGO_TECNICO, false);

    Sanctum::actingAs($inactivo, ['*']);

    $this->getJson('/api/reglamentos')->assertForbidden();
});

it('el admin activo puede leer el catalogo de reglamentos', function () {
    $admin = pasoCUsuario(Rol::CODIGO_ADMIN);
    Reglamento::factory()->create(['codigo' => 'AA_001']);

    Sanctum::actingAs($admin, ['*']);

    $this->getJson('/api/reglamentos')->assertOk();
});
