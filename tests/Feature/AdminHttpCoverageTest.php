<?php

use App\Models\Rol;
use App\Models\Usuario;
use Database\Seeders\RolSeeder;

use function Pest\Laravel\actingAs;

/**
 * Rutas web del panel del Administrador protegidas por `auth` + EnsureAdmin.
 *
 * @return array<int, string>
 */
function ahcRutasPanel(): array
{
    return [
        '/administrador/dashboard',
        '/administrador/usuarios',
        '/administrador/feriados',
        '/administrador/monitoreo',
    ];
}

function ahcUsuario(string $codigoRol, bool $activo = true): Usuario
{
    $rol = Rol::where('codigo', $codigoRol)->firstOrFail();

    return Usuario::factory()->create(['rol_id' => $rol->id, 'activo' => $activo]);
}

beforeEach(function () {
    $this->seed(RolSeeder::class);
});

it('redirige al login el panel del administrador sin sesión', function () {
    foreach (ahcRutasPanel() as $ruta) {
        $this->get($ruta)->assertRedirect(route('login'));
    }
});

it('responde 403 a un rol distinto de ADMIN en las cuatro rutas del panel', function () {
    $tecnico = ahcUsuario(Rol::CODIGO_TECNICO);

    foreach (ahcRutasPanel() as $ruta) {
        actingAs($tecnico)->get($ruta)->assertForbidden();
    }
});

it('responde 403 a un ADMIN inactivo en las cuatro rutas del panel', function () {
    $inactivo = ahcUsuario(Rol::CODIGO_ADMIN, false);

    foreach (ahcRutasPanel() as $ruta) {
        actingAs($inactivo)->get($ruta)->assertForbidden();
    }
});

it('carga las cuatro vistas del panel con un ADMIN activo', function () {
    $admin = ahcUsuario(Rol::CODIGO_ADMIN);

    foreach (ahcRutasPanel() as $ruta) {
        actingAs($admin)->get($ruta)->assertOk();
    }
});
