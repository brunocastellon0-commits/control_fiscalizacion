<?php

use App\Models\AuditoriaUsuario;
use App\Models\Feriado;
use App\Models\Rol;
use App\Models\Usuario;
use Database\Seeders\RolSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Semilla F17: roles reales + un ADMIN activo, un ADMIN inactivo y un
 * operativo que no es ADMIN (para las condiciones de autorización).
 *
 * @return array{admin: Usuario, adminInactivo: Usuario, operativo: Usuario, rolTecnico: int}
 */
function f17mSemilla(TestCase $test): array
{
    $test->seed(RolSeeder::class);

    $rolAdmin = Rol::where('codigo', Rol::CODIGO_ADMIN)->firstOrFail();
    $rolTecnico = Rol::where('codigo', Rol::CODIGO_TECNICO)->firstOrFail();

    return [
        'admin' => Usuario::factory()->create(['rol_id' => $rolAdmin->id, 'activo' => true]),
        'adminInactivo' => Usuario::factory()->create(['rol_id' => $rolAdmin->id, 'activo' => false]),
        'operativo' => Usuario::factory()->create(['rol_id' => $rolTecnico->id, 'activo' => true]),
        'rolTecnico' => $rolTecnico->id,
    ];
}

/**
 * @return array<string, mixed>
 */
function f17mPayloadUsuario(int $rolId): array
{
    return [
        'ci' => fake()->unique()->numerify('#########'),
        'nombres' => 'Nombres F17',
        'apellidos' => 'Apellidos F17',
        'cargo' => 'Analista de fiscalizacion',
        'username' => fake()->unique()->userName(),
        'password' => 'secreto.f17',
        'rol_id' => $rolId,
    ];
}

/**
 * @return array<string, mixed>
 */
function f17mPayloadFeriado(string $fecha): array
{
    return [
        'fecha' => $fecha,
        'descripcion' => 'Feriado de prueba (F17)',
        'ambito' => 'NACIONAL',
    ];
}

/**
 * Las 7 mutaciones del panel administrador (`routes/api.php:99-108`). Los
 * objetivos se crean antes de la llamada para que el route model binding no
 * responda 404 en lugar del 401/403 que se está verificando.
 *
 * @return array<int, array{metodo: string, uri: string, payload: array<string, mixed>}>
 */
function f17mEndpoints(Usuario $objetivo, Feriado $feriado, int $rolId): array
{
    $usuario = f17mPayloadUsuario($rolId);
    $feriadoPayload = f17mPayloadFeriado('2027-01-15');

    return [
        ['metodo' => 'POST', 'uri' => '/api/admin/usuarios', 'payload' => $usuario],
        ['metodo' => 'PUT', 'uri' => "/api/admin/usuarios/{$objetivo->id}", 'payload' => $usuario],
        ['metodo' => 'POST', 'uri' => "/api/admin/usuarios/{$objetivo->id}/activar", 'payload' => []],
        ['metodo' => 'POST', 'uri' => "/api/admin/usuarios/{$objetivo->id}/inactivar", 'payload' => []],
        ['metodo' => 'POST', 'uri' => '/api/admin/feriados', 'payload' => $feriadoPayload],
        ['metodo' => 'PUT', 'uri' => "/api/admin/feriados/{$feriado->id}", 'payload' => $feriadoPayload],
        ['metodo' => 'DELETE', 'uri' => "/api/admin/feriados/{$feriado->id}", 'payload' => []],
    ];
}

function f17mLlamar(TestCase $test, array $endpoint): TestResponse
{
    return match ($endpoint['metodo']) {
        'POST' => $test->postJson($endpoint['uri'], $endpoint['payload']),
        'PUT' => $test->putJson($endpoint['uri'], $endpoint['payload']),
        'DELETE' => $test->deleteJson($endpoint['uri']),
    };
}

/**
 * @return array{objetivo: Usuario, feriado: Feriado}
 */
function f17mObjetivos(int $rolId): array
{
    return [
        'objetivo' => Usuario::factory()->create(['rol_id' => $rolId, 'activo' => true]),
        'feriado' => Feriado::create(f17mPayloadFeriado('2026-11-20')),
    ];
}

it('responde 401 a las 7 mutaciones del panel admin sin sesion', function () {
    $s = f17mSemilla($this);
    ['objetivo' => $objetivo, 'feriado' => $feriado] = f17mObjetivos($s['rolTecnico']);

    foreach (f17mEndpoints($objetivo, $feriado, $s['rolTecnico']) as $endpoint) {
        f17mLlamar($this, $endpoint)->assertUnauthorized();
    }
});

it('responde 403 a un rol distinto de ADMIN en las 7 mutaciones del panel admin', function () {
    $s = f17mSemilla($this);
    ['objetivo' => $objetivo, 'feriado' => $feriado] = f17mObjetivos($s['rolTecnico']);

    Sanctum::actingAs($s['operativo'], ['*']);

    $usuariosAntes = Usuario::count();
    $feriadosAntes = Feriado::count();

    foreach (f17mEndpoints($objetivo, $feriado, $s['rolTecnico']) as $endpoint) {
        f17mLlamar($this, $endpoint)->assertForbidden();
    }

    expect(Usuario::count())->toBe($usuariosAntes)
        ->and(Feriado::count())->toBe($feriadosAntes);
});

it('responde 403 a un ADMIN inactivo en las 7 mutaciones del panel admin', function () {
    $s = f17mSemilla($this);
    ['objetivo' => $objetivo, 'feriado' => $feriado] = f17mObjetivos($s['rolTecnico']);

    Sanctum::actingAs($s['adminInactivo'], ['*']);

    foreach (f17mEndpoints($objetivo, $feriado, $s['rolTecnico']) as $endpoint) {
        f17mLlamar($this, $endpoint)->assertForbidden();
    }

    expect($objetivo->refresh()->activo)->toBeTrue()
        ->and(Feriado::where('fecha', '2026-11-20')->exists())->toBeTrue();
});

it('ejecuta las 7 mutaciones del panel admin con un ADMIN activo y persiste los efectos', function () {
    $s = f17mSemilla($this);
    Sanctum::actingAs($s['admin'], ['*']);
    $rolTecnico = $s['rolTecnico'];

    // 1. POST /api/admin/usuarios → 201, hash hasheado y sin filtrar la clave
    $payload = f17mPayloadUsuario($rolTecnico);

    $this->postJson('/api/admin/usuarios', $payload)
        ->assertCreated()
        ->assertJsonPath('data.username', $payload['username'])
        ->assertJsonPath('data.activo', true)
        ->assertJsonMissingPath('password_hash');

    $creado = Usuario::where('username', $payload['username'])->firstOrFail();

    expect($creado->activo)->toBeTrue()
        ->and($creado->password_hash)->not->toBe($payload['password'])
        ->and(Hash::check($payload['password'], $creado->password_hash))->toBeTrue();

    // 2a. PUT /api/admin/usuarios/{id} sin password → 200, conserva el hash
    $hashInicial = $creado->password_hash;

    $this->putJson("/api/admin/usuarios/{$creado->id}", [
        'ci' => $payload['ci'],
        'nombres' => 'Nombres Actualizados',
        'apellidos' => $payload['apellidos'],
        'cargo' => 'Coordinador',
        'username' => $payload['username'],
        'rol_id' => $rolTecnico,
    ])
        ->assertOk()
        ->assertJsonPath('data.nombres', 'Nombres Actualizados');

    $creado->refresh();

    expect($creado->nombres)->toBe('Nombres Actualizados')
        ->and($creado->cargo)->toBe('Coordinador')
        ->and($creado->activo)->toBeTrue()
        ->and($creado->password_hash)->toBe($hashInicial);

    // 2b. PUT con password nuevo → 200 y contraseña rotada (nunca en texto plano)
    $this->putJson("/api/admin/usuarios/{$creado->id}", [
        'ci' => $payload['ci'],
        'nombres' => 'Nombres Actualizados',
        'apellidos' => $payload['apellidos'],
        'cargo' => 'Coordinador',
        'username' => $payload['username'],
        'password' => 'nueva.clave.f17',
        'rol_id' => $rolTecnico,
    ])->assertOk();

    $creado->refresh();

    expect($creado->password_hash)->not->toBe($hashInicial)
        ->and(Hash::check('nueva.clave.f17', $creado->password_hash))->toBeTrue()
        ->and($creado->activo)->toBeTrue();

    // 3. POST .../inactivar → 200, activo=false + registro de auditoría
    $this->postJson("/api/admin/usuarios/{$creado->id}/inactivar")
        ->assertOk()
        ->assertJsonPath('message', 'Usuario inactivado. Sesiones cerradas y tokens revocados.');

    expect($creado->refresh()->activo)->toBeFalse()
        ->and(AuditoriaUsuario::where('usuario_objetivo_id', $creado->id)
            ->where('accion', AuditoriaUsuario::ACCION_INACTIVACION)->count())->toBe(1);

    // 4. POST .../activar → 200, activo=true + registro de auditoría
    $this->postJson("/api/admin/usuarios/{$creado->id}/activar")
        ->assertOk()
        ->assertJsonPath('message', 'Usuario reactivado correctamente.');

    expect($creado->refresh()->activo)->toBeTrue()
        ->and(AuditoriaUsuario::where('usuario_objetivo_id', $creado->id)
            ->where('accion', AuditoriaUsuario::ACCION_ACTIVACION)->count())->toBe(1);

    // 5. POST /api/admin/feriados → 201 y persiste en la BD
    $this->postJson('/api/admin/feriados', f17mPayloadFeriado('2026-12-25'))
        ->assertCreated()
        ->assertJsonPath('data.descripcion', 'Feriado de prueba (F17)')
        ->assertJsonPath('data.ambito', 'NACIONAL');

    $feriado = Feriado::whereDate('fecha', '2026-12-25')->firstOrFail();

    // 6. PUT /api/admin/feriados/{id} → 200 y refleja el cambio
    $this->putJson("/api/admin/feriados/{$feriado->id}", [
        'fecha' => '2026-12-25',
        'descripcion' => 'Feriado actualizado por F17',
        'ambito' => 'DEPARTAMENTAL',
    ])->assertOk();

    expect($feriado->fresh()->descripcion)->toBe('Feriado actualizado por F17')
        ->and($feriado->fresh()->ambito)->toBe('DEPARTAMENTAL');

    // 7. DELETE /api/admin/feriados/{id} → 200 y desaparece
    $this->deleteJson("/api/admin/feriados/{$feriado->id}")
        ->assertOk()
        ->assertJsonPath('message', 'Feriado eliminado correctamente.');

    expect(Feriado::whereDate('fecha', '2026-12-25')->exists())->toBeFalse();
});

it('valida las payloads de las mutaciones admin con 422', function () {
    $s = f17mSemilla($this);
    Sanctum::actingAs($s['admin'], ['*']);
    $rolTecnico = $s['rolTecnico'];

    // ci duplicada
    $payload = f17mPayloadUsuario($rolTecnico);
    $payload['ci'] = $s['operativo']->ci;

    $this->postJson('/api/admin/usuarios', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors('ci');

    // password corta
    $payload = f17mPayloadUsuario($rolTecnico);
    $payload['password'] = 'corta';

    $this->postJson('/api/admin/usuarios', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors('password');

    // rol inexistente
    $payload = f17mPayloadUsuario($rolTecnico);
    $payload['rol_id'] = 999999;

    $this->postJson('/api/admin/usuarios', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors('rol_id');

    // PUT con password corta
    $this->putJson("/api/admin/usuarios/{$s['operativo']->id}", [
        'ci' => $s['operativo']->ci,
        'nombres' => 'Nombres',
        'apellidos' => 'Apellidos',
        'username' => $s['operativo']->username,
        'password' => 'corta',
        'rol_id' => $rolTecnico,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('password');

    // POST de feriado con fecha duplicada
    Feriado::create(f17mPayloadFeriado('2026-12-25'));

    $this->postJson('/api/admin/feriados', f17mPayloadFeriado('2026-12-25'))
        ->assertStatus(422)
        ->assertJsonValidationErrors('fecha');

    // PUT de feriado hacia una fecha ya ocupada por otro registro
    $otro = Feriado::create(f17mPayloadFeriado('2027-03-10'));

    $this->putJson("/api/admin/feriados/{$otro->id}", f17mPayloadFeriado('2026-12-25'))
        ->assertStatus(422)
        ->assertJsonValidationErrors('fecha');
});
