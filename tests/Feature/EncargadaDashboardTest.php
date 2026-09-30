<?php

use App\Models\Actuado;
use App\Models\Asignacion;
use App\Models\CatalogoActuado;
use App\Models\CatalogoEstado;
use App\Models\Expediente;
use App\Models\Plazo;
use App\Models\Reglamento;
use App\Models\Rol;
use App\Models\Usuario;
use Laravel\Sanctum\Sanctum;

function encargadaDashboardRol(string $codigo): Rol
{
    $nombre = match ($codigo) {
        Rol::CODIGO_ENCARGADA => 'Encargada',
        Rol::CODIGO_TECNICO => 'Técnico',
        Rol::CODIGO_AUD_JURIDICO => 'Auditoría jurídica',
        Rol::CODIGO_AUD_FINANCIERO => 'Auditoría financiera',
        Rol::CODIGO_ADMIN => 'Administración',
        default => 'Rol de prueba',
    };

    return Rol::factory()->create([
        'codigo' => $codigo,
        'nombre' => $nombre,
    ]);
}

function encargadaDashboardUsuario(string $codigo, bool $activo = true): Usuario
{
    return Usuario::factory()->create([
        'rol_id' => encargadaDashboardRol($codigo)->id,
        'activo' => $activo,
    ]);
}

it('redirige al login sin sesión', function () {
    $this->get('/encargada/dashboard')->assertRedirect(route('login'));
});

it('muestra el dashboard a una encargada activa', function () {
    $encargada = encargadaDashboardUsuario(Rol::CODIGO_ENCARGADA);

    $this->actingAs($encargada)
        ->get(route('encargada.dashboard'))
        ->assertOk()
        ->assertSee('Dashboard de Encargada');
});

it('protege el dashboard web de la encargada', function (string $rol) {
    $usuario = encargadaDashboardUsuario($rol);

    $this->actingAs($usuario)
        ->get(route('encargada.dashboard'))
        ->assertForbidden();
})->with([
    Rol::CODIGO_TECNICO,
    Rol::CODIGO_AUD_JURIDICO,
    Rol::CODIGO_AUD_FINANCIERO,
    Rol::CODIGO_ADMIN,
]);

it('devuelve datos operativos sin exponer información administrativa', function () {
    $encargada = encargadaDashboardUsuario(Rol::CODIGO_ENCARGADA);
    $reglamento = Reglamento::factory()->create();
    $pendiente = CatalogoEstado::factory()->create(['codigo' => 'PENDIENTE_SORTEO']);

    Expediente::create([
        'nurej_code' => 'ENC-'.fake()->unique()->numerify('#####'),
        'via' => 'JURIDICO',
        'reglamento_id' => $reglamento->id,
        'estado_actual_id' => $pendiente->id,
        'fecha_ingreso' => now(),
        'creado_por' => $encargada->id,
    ]);

    Sanctum::actingAs($encargada, ['*']);

    $this->getJson('/api/encargada/dashboard')
        ->assertOk()
        ->assertJsonPath('expedientes.total', 1)
        ->assertJsonPath('expedientes.pendientes_sorteo', 1)
        ->assertJsonPath('asignaciones.activas', 0)
        ->assertJsonMissingPath('usuarios')
        ->assertJsonMissingPath('seguridad')
        ->assertJsonMissingPath('auditoria');
});

it('calcula la carga de los operadores activos', function () {
    $encargada = encargadaDashboardUsuario(Rol::CODIGO_ENCARGADA);
    $operador = encargadaDashboardUsuario(Rol::CODIGO_AUD_JURIDICO);
    $reglamento = Reglamento::factory()->create();
    $evaluacion = CatalogoEstado::factory()->create(['codigo' => 'EN_EVALUACION']);
    $catalogo = CatalogoActuado::create([
        'codigo' => 'ACT_DASHBOARD_'.fake()->unique()->numerify('####'),
        'nombre' => 'Actuado de prueba',
        'fase' => 'ADMISIBILIDAD',
        'rol_id' => $operador->rol_id,
        'estado_origen_id' => $evaluacion->id,
        'estado_destino_id' => $evaluacion->id,
    ]);
    $expediente = Expediente::create([
        'nurej_code' => 'ENC-'.fake()->unique()->numerify('#####'),
        'via' => 'JURIDICO',
        'reglamento_id' => $reglamento->id,
        'estado_actual_id' => $evaluacion->id,
        'fecha_ingreso' => now(),
        'creado_por' => $encargada->id,
    ]);
    $actuado = Actuado::create([
        'expediente_id' => $expediente->id,
        'catalogo_actuado_id' => $catalogo->id,
        'usuario_id' => $encargada->id,
        'estado_nuevo_id' => $evaluacion->id,
        'contenido' => ['descripcion' => 'Asignación de prueba'],
    ]);
    Asignacion::create([
        'expediente_id' => $expediente->id,
        'usuario_id' => $operador->id,
        'rol_id' => $operador->rol_id,
        'actuado_origen_id' => $actuado->id,
        'fecha_asignacion' => now(),
        'activa' => true,
    ]);
    Plazo::create([
        'expediente_id' => $expediente->id,
        'tipo_plazo' => 'EVALUACION',
        'dias_habiles_otorgados' => 5,
        'fecha_inicio' => now()->subDays(2),
        'fecha_limite' => now()->addDays(5),
        'estado' => 'VIGENTE',
        'actuado_disparador_id' => $actuado->id,
    ]);

    Sanctum::actingAs($encargada, ['*']);

    $this->getJson('/api/encargada/dashboard')
        ->assertOk()
        ->assertJsonPath('asignaciones.activas', 1)
        ->assertJsonPath('asignaciones.carga_operadores.0.total', 1)
        ->assertJsonPath('asignaciones.carga_operadores.0.rol', 'Auditoría jurídica');
});

it('rechaza a una encargada inactiva y a otros roles', function (string $rol, bool $activo) {
    $usuario = encargadaDashboardUsuario($rol, $activo);

    Sanctum::actingAs($usuario, ['*']);

    $this->getJson('/api/encargada/dashboard')
        ->assertForbidden();
})->with([
    [Rol::CODIGO_ENCARGADA, false],
    [Rol::CODIGO_TECNICO, true],
    [Rol::CODIGO_AUD_JURIDICO, true],
    [Rol::CODIGO_AUD_FINANCIERO, true],
    [Rol::CODIGO_ADMIN, true],
]);
