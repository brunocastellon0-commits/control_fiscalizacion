<?php

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Support\Facades\Hash;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

function paso1WebCrearUsuarioActivo(string $rolCodigo, string $username): Usuario
{
    return Usuario::factory()->create([
        'username' => $username,
        'password_hash' => Hash::make('password'),
        'activo' => true,
        'rol_id' => Rol::factory()->create(['codigo' => $rolCodigo])->id,
    ]);
}

it('redirige a la vista de login cuando no hay sesión web', function () {
    get('/expedientes')->assertRedirect(route('login'));

    get('/bandeja/sorteo')->assertRedirect(route('login'));

    get('/expedientes/nuevo')->assertRedirect(route('login'));
});

it('carga la bandeja de entrada con sesión web autenticada', function () {
    $usuario = paso1WebCrearUsuarioActivo(Rol::CODIGO_TECNICO, 'operador_tec');

    actingAs($usuario)
        ->get('/expedientes')
        ->assertOk()
        ->assertSee('Bandeja de entrada');
});

it('carga la bandeja de sorteo solo con sesión web autenticada', function () {
    $usuario = paso1WebCrearUsuarioActivo(Rol::CODIGO_ENCARGADA, 'encargada_web');

    actingAs($usuario)
        ->get('/bandeja/sorteo')
        ->assertOk()
        ->assertSee('Sorteo de expedientes');
});

it('deniega 403 a un operador en la bandeja de sorteo de la encargada', function () {
    $usuario = paso1WebCrearUsuarioActivo(Rol::CODIGO_TECNICO, 'operador_sorteo');

    $this->withHeader('Accept', 'application/json');
    actingAs($usuario)->get('/bandeja/sorteo')->assertForbidden();
});

it('deniega 403 a la encargada en la bandeja operativa de /expedientes', function () {
    $usuario = paso1WebCrearUsuarioActivo(Rol::CODIGO_ENCARGADA, 'enc_bajo_expedientes');

    $this->withHeader('Accept', 'application/json');
    actingAs($usuario)->get('/expedientes')->assertForbidden();
});

it('carga la apertura de causa solo con sesión web autenticada y rol tecnico', function () {
    $usuario = paso1WebCrearUsuarioActivo(Rol::CODIGO_TECNICO, 'tecnico_apertura');

    actingAs($usuario)
        ->get('/expedientes/nuevo')
        ->assertOk()
        ->assertSee('Apertura de causa');
});

it('deniega 403 a un rol no tecnico en la apertura de causa', function () {
    $usuario = paso1WebCrearUsuarioActivo(Rol::CODIGO_AUD_JURIDICO, 'auditor_apertura');

    $this->withHeader('Accept', 'application/json');
    actingAs($usuario)->get('/expedientes/nuevo')->assertForbidden();
});
