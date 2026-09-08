<?php

use App\Models\AuditoriaUsuario;
use App\Models\Rol;
use App\Models\SesionAcceso;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;

function seguridad9CrearRol(string $codigo): Rol
{
    return Rol::factory()->create(['codigo' => $codigo]);
}

function seguridad9CrearUsuario(Rol $rol, string $username): Usuario
{
    return Usuario::factory()->create([
        'username' => $username,
        'password_hash' => Hash::make('password'),
        'activo' => true,
        'rol_id' => $rol->id,
    ]);
}

function seguridad9SembrarSesiones(Usuario $usuario, int $cantidad = 2): void
{
    $filas = [];

    foreach (range(1, $cantidad) as $i) {
        $filas[] = [
            'id' => uniqid('ses_', true),
            'user_id' => $usuario->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'workstation',
            'payload' => base64_encode(serialize([])),
            'last_activity' => now()->timestamp,
        ];
    }

    DB::table('sessions')->insert($filas);
}

beforeEach(function () {
    $this->rolAdmin = seguridad9CrearRol(Rol::CODIGO_ADMIN);
    $this->admin = seguridad9CrearUsuario($this->rolAdmin, 'admin1');
    $this->rolEncargada = seguridad9CrearRol(Rol::CODIGO_ENCARGADA);
    $this->encargada = seguridad9CrearUsuario($this->rolEncargada, 'encargada1');
});

it('purgas todas las sesiones activas del usuario al desloguear, sin tocar las de otros', function () {
    $objetivo = seguridad9CrearUsuario($this->rolEncargada, 'operador1');
    $otro = seguridad9CrearUsuario($this->rolEncargada, 'operador2');

    seguridad9SembrarSesiones($objetivo, 2);
    seguridad9SembrarSesiones($otro, 1);

    $token = $objetivo->createToken('auth_token')->plainTextToken;

    SesionAcceso::create([
        'usuario_id' => $objetivo->id,
        'ip_origen' => '127.0.0.1',
        'login_at' => now(),
        'exitoso' => true,
    ]);

    $this->withToken($token)->postJson('/api/logout')->assertOk();

    expect(DB::table('sessions')->where('user_id', $objetivo->id)->count())->toBe(0)
        ->and(DB::table('sessions')->where('user_id', $otro->id)->count())->toBe(1);

    $sesion = SesionAcceso::where('usuario_id', $objetivo->id)->first();
    expect($sesion->logout_at)->not->toBeNull();
});

it('solo un ADMIN puede inactivar a un usuario (403 en otros roles)', function () {
    $objetivo = seguridad9CrearUsuario($this->rolEncargada, 'operador1');

    Sanctum::actingAs($this->encargada, ['*']);
    $this->postJson('/api/usuarios/'.$objetivo->id.'/inactivar')->assertForbidden();

    Sanctum::actingAs($this->admin, ['*']);
    $this->postJson('/api/usuarios/'.$objetivo->id.'/inactivar')
        ->assertOk()
        ->assertJsonPath('message', 'Usuario inactivado. Sesiones cerradas y tokens revocados.');

    expect($objetivo->refresh()->activo)->toBeFalse();
});

it('expulsa en tiempo real: desactiva, purga sesiones, revoca tokens y audita', function () {
    $objetivo = seguridad9CrearUsuario($this->rolEncargada, 'operador1');

    $token = $objetivo->createToken('auth_token')->plainTextToken;
    seguridad9SembrarSesiones($objetivo, 2);

    Sanctum::actingAs($this->admin, ['*']);

    $this->postJson('/api/usuarios/'.$objetivo->id.'/inactivar')
        ->assertOk();

    expect($objetivo->refresh()->activo)->toBeFalse()
        ->and(DB::table('sessions')->where('user_id', $objetivo->id)->count())->toBe(0)
        ->and(PersonalAccessToken::where('tokenable_id', $objetivo->id)->count())->toBe(0);

    $auditoria = AuditoriaUsuario::where('usuario_objetivo_id', $objetivo->id)->first();
    expect($auditoria)->not->toBeNull()
        ->and($auditoria->admin_id)->toBe($this->admin->id)
        ->and($auditoria->accion)->toBe(AuditoriaUsuario::ACCION_INACTIVACION);

    $this->app['auth']->forgetGuards();
    $this->withToken($token)->getJson('/api/me')->assertUnauthorized();
});

it('rechaza que un ADMIN se inactive a sí mismo (422)', function () {
    Sanctum::actingAs($this->admin, ['*']);

    $this->postJson('/api/usuarios/'.$this->admin->id.'/inactivar')
        ->assertUnprocessable();
});

it('rechaza inactivar a un usuario ya inactivo (422)', function () {
    $objetivo = Usuario::factory()->create([
        'username' => 'ya_inactivo',
        'password_hash' => Hash::make('password'),
        'activo' => false,
        'rol_id' => $this->rolEncargada->id,
    ]);

    Sanctum::actingAs($this->admin, ['*']);

    $this->postJson('/api/usuarios/'.$objetivo->id.'/inactivar')
        ->assertUnprocessable();
});
