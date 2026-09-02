<?php

use App\Models\CatalogoEstado;
use App\Models\Rol;
use App\Models\Usuario;
use Laravel\Sanctum\Sanctum;

function pasoDRol(string $codigoRol): Rol
{
    return Rol::factory()->create(['codigo' => $codigoRol]);
}

function pasoDUsuario(string $codigoRol, bool $activo = true): Usuario
{
    return Usuario::factory()->create([
        'rol_id' => pasoDRol($codigoRol)->id,
        'activo' => $activo,
    ]);
}

it('devuelve 401 sin token de sanctum', function () {
    $this->getJson('/api/estados')->assertUnauthorized();
});

it('lista los estados ordenados por codigo con la estructura del recurso', function () {
    $operador = pasoDUsuario(Rol::CODIGO_AUD_JURIDICO);

    CatalogoEstado::factory()->create(['codigo' => 'ZZ']);
    CatalogoEstado::factory()->create(['codigo' => 'AA']);

    Sanctum::actingAs($operador, ['*']);

    $response = $this->getJson('/api/estados')->assertOk();

    $response->assertJsonStructure(['data' => [['id', 'codigo', 'nombre', 'es_final', 'estado_padre']]]);

    $codigos = array_column($response->json('data'), 'codigo');
    expect($codigos)->toBe(['AA', 'ZZ']);
});

it('filtra por estados finales cuando se envia es_final', function () {
    $operador = pasoDUsuario(Rol::CODIGO_AUD_JURIDICO);

    CatalogoEstado::factory()->create(['codigo' => 'FINAL', 'es_final' => true]);
    CatalogoEstado::factory()->create(['codigo' => 'NO_FINAL', 'es_final' => false]);

    Sanctum::actingAs($operador, ['*']);

    $response = $this->getJson('/api/estados?es_final=1')->assertOk();

    $codigos = array_column($response->json('data'), 'codigo');
    expect($codigos)->toContain('FINAL')
        ->not->toContain('NO_FINAL');
});

it('filtra por estado padre cuando se envia estado_padre_id', function () {
    $operador = pasoDUsuario(Rol::CODIGO_AUD_JURIDICO);

    $padre = CatalogoEstado::factory()->create(['codigo' => 'PADRE']);
    CatalogoEstado::factory()->create(['codigo' => 'HIJO', 'estado_padre_id' => $padre->id]);
    CatalogoEstado::factory()->create(['codigo' => 'HUERFANO']);

    Sanctum::actingAs($operador, ['*']);

    $response = $this->getJson('/api/estados?estado_padre_id='.$padre->id)->assertOk();

    $codigos = array_column($response->json('data'), 'codigo');
    expect($codigos)->toContain('HIJO')
        ->not->toContain('PADRE')
        ->not->toContain('HUERFANO');
});

it('deniega 403 a un usuario inactivo', function () {
    $inactivo = pasoDUsuario(Rol::CODIGO_AUD_JURIDICO, false);

    Sanctum::actingAs($inactivo, ['*']);

    $this->getJson('/api/estados')->assertForbidden();
});

it('el admin activo puede leer el catalogo de estados', function () {
    $admin = pasoDUsuario(Rol::CODIGO_ADMIN);
    CatalogoEstado::factory()->create(['codigo' => 'AA']);

    Sanctum::actingAs($admin, ['*']);

    $this->getJson('/api/estados')->assertOk();
});
