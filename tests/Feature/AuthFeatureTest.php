<?php

use App\Models\Rol;
use App\Models\SesionAcceso;
use App\Models\Usuario;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

function paso9CrearUsuarioActivo(string $rolCodigo, string $username = 'operador'): Usuario
{
    return Usuario::factory()->create([
        'username' => $username,
        'password_hash' => Hash::make('password'),
        'activo' => true,
        'rol_id' => Rol::factory()->create(['codigo' => $rolCodigo])->id,
    ]);
}

// Simula una petición de la workstation (SPA stateful): envía Origin y Referer
// a http://localhost (dominio stateful de Sanctum) y expone una sesión, para
// que el middleware ExecuteFrontend monte la cookie de sesión en los tests.
function pasoActuarComoFrontend(TestCase $test): void
{
    $test->withHeaders([
        'Origin' => 'http://localhost',
        'Referer' => 'http://localhost',
    ])->withSession([]);
}

beforeEach(function () {
    RateLimiter::clear('login');
});

it('permite el login stateful por cookies y NO emite token Bearer', function () {
    $usuario = paso9CrearUsuarioActivo(Rol::CODIGO_ENCARGADA, 'encargada');

    pasoActuarComoFrontend($this);

    $response = postJson('/api/login', [
        'username' => 'encargada',
        'password' => 'password',
    ]);

    $response->assertOk()
        ->assertJsonPath('usuario.username', 'encargada')
        ->assertJsonPath('usuario.rol', Rol::CODIGO_ENCARGADA)
        ->assertJsonMissingPath('token');

    $this->assertAuthenticated('web');

    // En pruebas el driver de sesión es `array` (no emite cookie HttpOnly),
    // pero la sesión del guard web queda autenticada: /api/me responde 200.
    $this->getJson('/api/me')->assertOk();
});

it('rechaza con 403 el login de una peticion no-frontend (sin sesion)', function () {
    paso9CrearUsuarioActivo(Rol::CODIGO_ENCARGADA, 'encargada');

    // Sin header Origin ni sesion: la workstation stateful no aplica -> 403.
    $response = postJson('/api/login', [
        'username' => 'encargada',
        'password' => 'password',
    ]);

    $response->assertStatus(403)
        ->assertJsonPath('message', 'Autenticación no disponible para este cliente. Utilice la workstation.');

    $this->assertGuest('web');
});

it('expone al usuario autenticado en /me a traves de la sesion stateful', function () {
    $usuario = paso9CrearUsuarioActivo(Rol::CODIGO_ENCARGADA, 'encargada');

    pasoActuarComoFrontend($this);

    postJson('/api/login', ['username' => 'encargada', 'password' => 'password'])->assertOk();

    $this->assertAuthenticated('web');

    getJson('/api/me')->assertOk()
        ->assertJsonPath('id', $usuario->id)
        ->assertJsonPath('rol.codigo', Rol::CODIGO_ENCARGADA);
});

it('registra una sesion de acceso exitosa en sesiones_acceso', function () {
    paso9CrearUsuarioActivo(Rol::CODIGO_ENCARGADA, 'encargada');

    pasoActuarComoFrontend($this);

    postJson('/api/login', ['username' => 'encargada', 'password' => 'password'])->assertOk();

    $sesion = SesionAcceso::where('usuario_id', Usuario::where('username', 'encargada')->first()->id)->first();

    expect($sesion)->not->toBeNull()
        ->and($sesion->exitoso)->toBeTrue()
        ->and($sesion->logout_at)->toBeNull();
});

it('registra una sesion fallida cuando la contrasena es incorrecta', function () {
    paso9CrearUsuarioActivo(Rol::CODIGO_ENCARGADA, 'encargada');

    pasoActuarComoFrontend($this);

    postJson('/api/login', ['username' => 'encargada', 'password' => 'incorrecta'])
        ->assertStatus(401);

    $sesion = SesionAcceso::where('usuario_id', Usuario::where('username', 'encargada')->first()->id)->first();

    expect($sesion)->not->toBeNull()
        ->and($sesion->exitoso)->toBeFalse();
});

it('no registra una sesion de acceso para un usuario inexistente', function () {
    pasoActuarComoFrontend($this);

    postJson('/api/login', ['username' => 'fantasma', 'password' => 'cualquiera'])
        ->assertStatus(401);

    expect(SesionAcceso::count())->toBe(0);
});

it('rechaza el login de un usuario inactivo con 403', function () {
    $usuario = Usuario::factory()->create([
        'username' => 'desactivado',
        'password_hash' => Hash::make('password'),
        'activo' => false,
        'rol_id' => Rol::factory()->create()->id,
    ]);

    pasoActuarComoFrontend($this);

    $response = postJson('/api/login', ['username' => 'desactivado', 'password' => 'password'])
        ->assertStatus(403);

    expect($response->json('message'))->toContain('Usuario inactivo');

    $sesion = SesionAcceso::where('usuario_id', $usuario->id)->first();
    expect($sesion)->not->toBeNull()
        ->and($sesion->exitoso)->toBeFalse();
});

it('expone al usuario autenticado a traves de /me', function () {
    $usuario = paso9CrearUsuarioActivo(Rol::CODIGO_ENCARGADA, 'encargada');
    Sanctum::actingAs($usuario);

    $this->getJson('/api/me')
        ->assertOk()
        ->assertJsonPath('id', $usuario->id)
        ->assertJsonPath('rol.codigo', Rol::CODIGO_ENCARGADA);
});

it('bloquea /me sin autenticacion', function () {
    $this->getJson('/api/me')->assertUnauthorized();
});

it('cierra la sesion stateful y registra el logout', function () {
    $usuario = paso9CrearUsuarioActivo(Rol::CODIGO_ENCARGADA, 'encargada');

    pasoActuarComoFrontend($this);

    postJson('/api/login', ['username' => 'encargada', 'password' => 'password'])->assertOk();
    $this->assertAuthenticated('web');

    postJson('/api/logout')->assertOk();

    $this->assertGuest('web');

    $sesion = SesionAcceso::where('usuario_id', $usuario->id)->first();
    expect($sesion->logout_at)->not->toBeNull();
});

it('revoca el token y registra el logout en la sesion abierta', function () {
    $usuario = paso9CrearUsuarioActivo(Rol::CODIGO_ENCARGADA, 'encargada');

    $token = $usuario->createToken('auth_token')->plainTextToken;

    SesionAcceso::create([
        'usuario_id' => $usuario->id,
        'ip_origen' => '127.0.0.1',
        'login_at' => now(),
        'exitoso' => true,
    ]);

    $this->withToken($token)->postJson('/api/logout')->assertOk();

    expect(PersonalAccessToken::where('tokenable_id', $usuario->id)->count())->toBe(0);

    $sesion = SesionAcceso::where('usuario_id', $usuario->id)->first();
    expect($sesion->logout_at)->not->toBeNull();
});

it('deja de aceptar el token tras el logout', function () {
    $usuario = paso9CrearUsuarioActivo(Rol::CODIGO_ENCARGADA, 'encargada');
    $token = $usuario->createToken('auth_token')->plainTextToken;

    $this->withToken($token)->postJson('/api/logout')->assertOk();

    $this->app['auth']->forgetGuards();

    $this->withToken($token)->getJson('/api/me')->assertUnauthorized();
});

it('aplica rate limiting al endpoint de login (5 por minuto)', function () {
    paso9CrearUsuarioActivo(Rol::CODIGO_ENCARGADA, 'encargada');

    pasoActuarComoFrontend($this);

    foreach (range(1, 5) as $intento) {
        postJson('/api/login', ['username' => 'encargada', 'password' => 'password'])
            ->assertStatus(200);
    }

    postJson('/api/login', ['username' => 'encargada', 'password' => 'password'])
        ->assertStatus(429);
});
