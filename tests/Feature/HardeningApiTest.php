<?php

use App\Models\Rol;
use App\Models\Usuario;
use Database\Seeders\RolSeeder;
use Laravel\Sanctum\Sanctum;

/**
 * Hardening F17: rate limiting de la API autenticada e irradiación de datos.
 */
function f17hUsuario(string $codigoRol, bool $activo = true): Usuario
{
    $rol = Rol::where('codigo', $codigoRol)->firstOrFail();

    return Usuario::factory()->create(['rol_id' => $rol->id, 'activo' => $activo]);
}

it('aplica throttle:api (60 req/min) y responde 429 al excederlo', function () {
    $this->seed(RolSeeder::class);

    $usuario = f17hUsuario(Rol::CODIGO_ADMIN);

    Sanctum::actingAs($usuario, ['*']);

    foreach (range(1, 60) as $intento) {
        $this->getJson('/api/me')->assertOk();
    }

    $this->getJson('/api/me')->assertStatus(429);
});

it('no expone password_hash en la respuesta de /api/me', function () {
    $this->seed(RolSeeder::class);

    $usuario = f17hUsuario(Rol::CODIGO_TECNICO);

    Sanctum::actingAs($usuario, ['*']);

    $respuesta = $this->getJson('/api/me')
        ->assertOk()
        ->assertJsonPath('id', $usuario->id)
        ->assertJsonPath('username', $usuario->username)
        ->assertJsonPath('rol.codigo', Rol::CODIGO_TECNICO)
        ->assertJsonMissingPath('password_hash');

    expect($respuesta->getContent())->not->toContain('password_hash');
});
