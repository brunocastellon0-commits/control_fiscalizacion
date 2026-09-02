<?php

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Support\Facades\Hash;

use function Pest\Laravel\actingAs;

function paso8Rol(string $codigoRol): Rol
{
    return Rol::firstOrCreate(
        ['codigo' => $codigoRol],
        ['nombre' => $codigoRol],
    );
}

function paso8UsuarioConRol(string $codigoRol, ?string $username = null): Usuario
{
    return Usuario::factory()->create([
        'username' => $username ?? 'user_'.strtolower($codigoRol).'_'.fake()->unique()->numberBetween(1, 9999),
        'password_hash' => Hash::make('password'),
        'activo' => true,
        'rol_id' => paso8Rol($codigoRol)->id,
    ]);
}

it('la encargada activa lista los usuarios operativos asignables', function () {
    $encargada = paso8UsuarioConRol(Rol::CODIGO_ENCARGADA);

    $tecnico = paso8UsuarioConRol(Rol::CODIGO_TECNICO);
    $juridico = paso8UsuarioConRol(Rol::CODIGO_AUD_JURIDICO);
    $financiero = paso8UsuarioConRol(Rol::CODIGO_AUD_FINANCIERO);

    paso8UsuarioConRol(Rol::CODIGO_ADMIN);
    $inactivo = paso8UsuarioConRol(Rol::CODIGO_TECNICO, 'tecnico_inactivo');
    $inactivo->update(['activo' => false]);

    $response = actingAs($encargada)->getJson('/api/usuarios?rol=operativo');

    $response->assertOk()
        ->assertJsonStructure(['data', 'links', 'meta'])
        ->assertJsonCount(3, 'data');

    $responseIds = collect($response->json('data'))->pluck('id');
    expect($responseIds)->toContain($tecnico->id)
        ->toContain($juridico->id)
        ->toContain($financiero->id)
        ->not->toContain($inactivo->id);
});

it('la encargada ve la estructura completa del recurso operativo', function () {
    $encargada = paso8UsuarioConRol(Rol::CODIGO_ENCARGADA);
    $tecnico = paso8UsuarioConRol(Rol::CODIGO_TECNICO);

    $response = actingAs($encargada)->getJson('/api/usuarios?rol=operativo');

    $response->assertOk()->assertJsonPath('data.0.id', $tecnico->id)
        ->assertJsonPath('data.0.nombres', $tecnico->nombres)
        ->assertJsonPath('data.0.apellidos', $tecnico->apellidos)
        ->assertJsonPath('data.0.ci', $tecnico->ci)
        ->assertJsonPath('data.0.rol.codigo', Rol::CODIGO_TECNICO)
        ->assertJsonPath('data.0.rol.nombre', Rol::CODIGO_TECNICO);
});

it('un operador no puede consultar el catalogo de usuarios (403)', function () {
    $tecnico = paso8UsuarioConRol(Rol::CODIGO_TECNICO);

    $this->withHeader('Accept', 'application/json');
    actingAs($tecnico)->getJson('/api/usuarios')->assertForbidden();
});

it('una encargada inactiva no puede consultar el catalogo (403)', function () {
    $encargada = paso8UsuarioConRol(Rol::CODIGO_ENCARGADA, 'enc_inactiva');
    $encargada->update(['activo' => false]);

    $this->withHeader('Accept', 'application/json');
    actingAs($encargada)->getJson('/api/usuarios')->assertForbidden();
});
