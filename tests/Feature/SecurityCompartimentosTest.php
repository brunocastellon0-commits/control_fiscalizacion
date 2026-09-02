<?php

use App\Models\Actuado;
use App\Models\Asignacion;
use App\Models\CatalogoActuado;
use App\Models\CatalogoEstado;
use App\Models\Expediente;
use App\Models\Reglamento;
use App\Models\Rol;
use App\Models\Usuario;
use Laravel\Sanctum\Sanctum;

function paso9SemillaCompartimentos(): array
{
    $rolTecnico = Rol::factory()->create(['codigo' => Rol::CODIGO_TECNICO]);
    $tecnico = Usuario::factory()->create(['rol_id' => $rolTecnico->id]);

    $rolEncargada = Rol::factory()->create(['codigo' => Rol::CODIGO_ENCARGADA]);
    $encargada = Usuario::factory()->create(['rol_id' => $rolEncargada->id]);

    $rolOperador = Rol::factory()->create(['codigo' => Rol::CODIGO_AUD_JURIDICO]);
    $operador = Usuario::factory()->create(['rol_id' => $rolOperador->id]);

    $reglamento = Reglamento::factory()->create();

    $pendiente = CatalogoEstado::factory()->create(['codigo' => 'PENDIENTE_SORTEO']);
    $evaluacion = CatalogoEstado::factory()->create(['codigo' => 'EN_EVALUACION']);

    $catalogoRegistro = CatalogoActuado::create([
        'codigo' => 'ACT_REG_SEC_'.fake()->unique()->numberBetween(1000, 9999),
        'nombre' => 'Registro y Digitalizacion',
        'fase' => 'REGISTRO',
        'rol_id' => $rolTecnico->id,
        'estado_origen_id' => null,
        'estado_destino_id' => $pendiente->id,
        'es_automatico' => false,
        'requiere_adjunto' => true,
    ]);

    return compact(
        'rolTecnico', 'tecnico',
        'rolEncargada', 'encargada',
        'rolOperador', 'operador',
        'reglamento',
        'pendiente', 'evaluacion',
        'catalogoRegistro',
    );
}

function paso9CrearExpedienteConEstado(int $estadoId, int $creadorId): Expediente
{
    return Expediente::create([
        'nurej_code' => 'EXP-'.fake()->unique()->numberBetween(10000, 99999),
        'via' => 'TECNICO',
        'reglamento_id' => Reglamento::factory()->create()->id,
        'estado_actual_id' => $estadoId,
        'fecha_ingreso' => now(),
        'creado_por' => $creadorId,
    ]);
}

function paso9AsignarActivamente(Expediente $expediente, Usuario $usuario, int $estadoId, int $catalogoId): void
{
    $actuado = Actuado::create([
        'expediente_id' => $expediente->id,
        'catalogo_actuado_id' => $catalogoId,
        'usuario_id' => $usuario->id,
        'estado_nuevo_id' => $estadoId,
        'contenido' => ['tipo' => 'ASIGNACION'],
    ])->refresh();

    Asignacion::create([
        'expediente_id' => $expediente->id,
        'usuario_id' => $usuario->id,
        'rol_id' => $usuario->rol_id,
        'actuado_origen_id' => $actuado->id,
        'fecha_asignacion' => now(),
        'activa' => true,
    ]);
}

it('bloquea a un operador sin asignacion activa para ver un expediente ajeno', function () {
    [
        'operador' => $operador,
        'encargada' => $encargada,
        'pendiente' => $pendiente,
    ] = paso9SemillaCompartimentos();

    $ajeno = paso9CrearExpedienteConEstado($pendiente->id, $encargada->id);

    Sanctum::actingAs($operador, ['*']);

    $this->getJson('/api/expedientes/'.$ajeno->id)
        ->assertForbidden();
});

it('bloquea ver un expediente no asignado pese a tener asignacion en otro', function () {
    [
        'operador' => $operador,
        'encargada' => $encargada,
        'pendiente' => $pendiente,
        'catalogoRegistro' => $catalogoRegistro,
    ] = paso9SemillaCompartimentos();

    $asignado = paso9CrearExpedienteConEstado($pendiente->id, $encargada->id);
    paso9AsignarActivamente($asignado, $operador, $pendiente->id, $catalogoRegistro->id);

    $ajeno = paso9CrearExpedienteConEstado($pendiente->id, $encargada->id);

    Sanctum::actingAs($operador, ['*']);

    $this->getJson('/api/expedientes/'.$ajeno->id)
        ->assertForbidden();

    $this->getJson('/api/expedientes/'.$asignado->id)
        ->assertOk();
});

it('bloquea registrar actudados sin asignacion activa en un expediente ajeno', function () {
    [
        'operador' => $operador,
        'tecnico' => $tecnico,
        'rolTecnico' => $rolTecnico,
        'evaluacion' => $evaluacion,
    ] = paso9SemillaCompartimentos();

    $catalogo = CatalogoActuado::create([
        'codigo' => 'ACT_SEC_'.fake()->unique()->numberBetween(1000, 9999),
        'nombre' => 'Actuado de prueba',
        'fase' => 'ADMISIBILIDAD',
        'rol_id' => $rolTecnico->id,
        'estado_destino_id' => $evaluacion->id,
        'es_automatico' => false,
        'requiere_adjunto' => false,
    ]);

    $ajeno = paso9CrearExpedienteConEstado($evaluacion->id, $tecnico->id);

    Sanctum::actingAs($operador, ['*']);

    $this->postJson('/api/expedientes/'.$ajeno->id.'/actuados', [
        'catalogo_actuado_id' => $catalogo->id,
        'descripcion' => 'Intento de actuado sobre expediente ajeno.',
    ])->assertForbidden();
});

it('permite el detalle de un expediente asignado activamente', function () {
    [
        'operador' => $operador,
        'encargada' => $encargada,
        'pendiente' => $pendiente,
        'catalogoRegistro' => $catalogoRegistro,
    ] = paso9SemillaCompartimentos();

    $expediente = paso9CrearExpedienteConEstado($pendiente->id, $encargada->id);
    paso9AsignarActivamente($expediente, $operador, $pendiente->id, $catalogoRegistro->id);

    Sanctum::actingAs($operador, ['*']);

    $this->getJson('/api/expedientes/'.$expediente->id)
        ->assertOk()
        ->assertJsonPath('data.asignacion_activa.usuario.id', $operador->id);
});

it('bloquea a ADMIN sin asignacion para ver un expediente ajeno (sin bypass indebido)', function () {
    [
        'encargada' => $encargada,
        'pendiente' => $pendiente,
    ] = paso9SemillaCompartimentos();

    $rolAdmin = Rol::factory()->create(['codigo' => Rol::CODIGO_ADMIN]);
    $admin = Usuario::factory()->create(['rol_id' => $rolAdmin->id]);

    $ajeno = paso9CrearExpedienteConEstado($pendiente->id, $encargada->id);

    Sanctum::actingAs($admin, ['*']);

    $this->getJson('/api/expedientes/'.$ajeno->id)
        ->assertForbidden();
});

it('bloquea a ADMIN sin asignacion para registrar actudados en un expediente ajeno', function () {
    [
        'operador' => $operador,
        'tecnico' => $tecnico,
        'rolTecnico' => $rolTecnico,
        'evaluacion' => $evaluacion,
    ] = paso9SemillaCompartimentos();

    $rolAdmin = Rol::factory()->create(['codigo' => Rol::CODIGO_ADMIN]);
    $admin = Usuario::factory()->create(['rol_id' => $rolAdmin->id]);

    $catalogo = CatalogoActuado::create([
        'codigo' => 'ACT_SEC_ADM'.fake()->unique()->numberBetween(1000, 9999),
        'nombre' => 'Actuado admin',
        'fase' => 'ADMISIBILIDAD',
        'rol_id' => $rolTecnico->id,
        'estado_destino_id' => $evaluacion->id,
        'es_automatico' => false,
        'requiere_adjunto' => false,
    ]);

    $ajeno = paso9CrearExpedienteConEstado($evaluacion->id, $tecnico->id);

    Sanctum::actingAs($admin, ['*']);

    $this->postJson('/api/expedientes/'.$ajeno->id.'/actuados', [
        'catalogo_actuado_id' => $catalogo->id,
        'descripcion' => 'Intento de actuado administrativo sobre expediente ajeno.',
    ])->assertForbidden();
});

it('permite a la encargada ver cualquier expediente por jerarquia', function () {
    [
        'encargada' => $encargada,
        'tecnico' => $tecnico,
        'pendiente' => $pendiente,
    ] = paso9SemillaCompartimentos();

    $expediente = paso9CrearExpedienteConEstado($pendiente->id, $tecnico->id);

    Sanctum::actingAs($encargada, ['*']);

    $this->getJson('/api/expedientes/'.$expediente->id)
        ->assertOk()
        ->assertJsonPath('data.nurej_code', $expediente->nurej_code);
});

it('bloquea a un usuario inactivo pese a tener una asignacion activa', function () {
    [
        'encargada' => $encargada,
        'rolOperador' => $rolOperador,
        'pendiente' => $pendiente,
        'catalogoRegistro' => $catalogoRegistro,
    ] = paso9SemillaCompartimentos();

    $usuarioInactivo = Usuario::factory()->create([
        'rol_id' => $rolOperador->id,
        'activo' => false,
    ]);

    $expediente = paso9CrearExpedienteConEstado($pendiente->id, $encargada->id);
    paso9AsignarActivamente($expediente, $usuarioInactivo, $pendiente->id, $catalogoRegistro->id);

    Sanctum::actingAs($usuarioInactivo, ['*']);

    $this->getJson('/api/expedientes/'.$expediente->id)
        ->assertForbidden();
});

it('rechaza la peticion sin token de autenticacion al consultar un detalle', function () {
    [
        'encargada' => $encargada,
        'pendiente' => $pendiente,
    ] = paso9SemillaCompartimentos();

    $expediente = paso9CrearExpedienteConEstado($pendiente->id, $encargada->id);

    $this->getJson('/api/expedientes/'.$expediente->id)
        ->assertUnauthorized();
});
