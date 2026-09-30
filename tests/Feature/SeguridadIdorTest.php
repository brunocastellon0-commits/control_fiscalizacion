<?php

use App\Models\Actuado;
use App\Models\Adjunto;
use App\Models\CatalogoActuado;
use App\Models\CatalogoEstado;
use App\Models\Expediente;
use App\Models\Reglamento;
use App\Models\Rol;
use App\Models\Usuario;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\actingAs;

/**
 * Semilla propia de la Fase 3: roles, usuarios, estados y catálogo.
 * Nombres únicos para no colisionar con los helpers de otros tests.
 */
function idorSemilla(): array
{
    $rolTecnico = Rol::factory()->create(['codigo' => Rol::CODIGO_TECNICO]);
    $rolEncargada = Rol::factory()->create(['codigo' => Rol::CODIGO_ENCARGADA]);
    $rolJuridico = Rol::factory()->create(['codigo' => Rol::CODIGO_AUD_JURIDICO]);
    $rolFinanciero = Rol::factory()->create(['codigo' => Rol::CODIGO_AUD_FINANCIERO]);
    $rolAdmin = Rol::factory()->create(['codigo' => Rol::CODIGO_ADMIN]);

    $tecnico = Usuario::factory()->create(['rol_id' => $rolTecnico->id]);
    $encargada = Usuario::factory()->create(['rol_id' => $rolEncargada->id]);
    $juridico = Usuario::factory()->create(['rol_id' => $rolJuridico->id]);
    $financiero = Usuario::factory()->create(['rol_id' => $rolFinanciero->id]);
    $admin = Usuario::factory()->create(['rol_id' => $rolAdmin->id]);

    $reglamento = Reglamento::factory()->create();

    return compact(
        'rolTecnico', 'rolEncargada', 'rolJuridico', 'rolFinanciero', 'rolAdmin',
        'tecnico', 'encargada', 'juridico', 'financiero', 'admin',
        'reglamento',
    );
}

function idorExpedienteConEstado(string $codigoEstado, int $creadorId, int $reglamentoId): Expediente
{
    $estado = CatalogoEstado::factory()->create(['codigo' => $codigoEstado]);

    return Expediente::create([
        'nurej_code' => 'EXP-IDOR-'.fake()->unique()->numberBetween(10000, 99999),
        'via' => 'TECNICO',
        'reglamento_id' => $reglamentoId,
        'estado_actual_id' => $estado->id,
        'fecha_ingreso' => now(),
        'creado_por' => $creadorId,
    ]);
}

it('bloquea consultar los requisitos de un expediente ajeno', function () {
    ['juridico' => $juridico, 'encargada' => $encargada, 'reglamento' => $reglamento] = idorSemilla();

    $ajeno = idorExpedienteConEstado('EN_EVALUACION', $encargada->id, $reglamento->id);

    Sanctum::actingAs($juridico, ['*']);

    $this->getJson('/api/expedientes/'.$ajeno->id.'/requisitos')
        ->assertForbidden();
});

it('bloquea descargar un adjunto de un expediente ajeno', function () {
    ['juridico' => $juridico, 'encargada' => $encargada, 'reglamento' => $reglamento] = idorSemilla();

    $ajeno = idorExpedienteConEstado('EN_EVALUACION', $encargada->id, $reglamento->id);
    $estado = $ajeno->estado_actual_id;
    $catalogo = CatalogoActuado::create([
        'codigo' => 'ACT_IDOR_'.fake()->unique()->numberBetween(1000, 9999),
        'nombre' => 'Actuado con adjunto',
        'fase' => 'ADMISIBILIDAD',
        'rol_id' => null,
        'estado_destino_id' => $estado,
        'es_automatico' => false,
        'requiere_adjunto' => true,
    ]);
    $actuado = Actuado::create([
        'expediente_id' => $ajeno->id,
        'catalogo_actuado_id' => $catalogo->id,
        'usuario_id' => $encargada->id,
        'estado_nuevo_id' => $estado,
        'contenido' => ['tipo' => 'PRUEBA'],
    ]);
    $adjunto = Adjunto::create([
        'actuado_id' => $actuado->id,
        'nombre_original' => 'prueba.pdf',
        'ruta_almacenamiento' => 'actuados/prueba.pdf',
        'hash_sha256' => hash('sha256', 'prueba'),
        'mime_type' => 'application/pdf',
        'tamanio_bytes' => 1024,
        'subido_por' => $encargada->id,
        'subido_at' => now(),
    ]);

    Sanctum::actingAs($juridico, ['*']);

    $this->getJson('/api/adjuntos/'.$adjunto->id.'/descargar')
        ->assertForbidden();
});

it('bloquea registrar evaluacion de admisibilidad sin asignacion activa', function () {
    ['juridico' => $juridico, 'encargada' => $encargada, 'reglamento' => $reglamento] = idorSemilla();

    $ajeno = idorExpedienteConEstado('EN_EVALUACION', $encargada->id, $reglamento->id);

    Sanctum::actingAs($juridico, ['*']);

    $this->postJson('/api/expedientes/'.$ajeno->id.'/evaluacion', [])
        ->assertForbidden();
});

it('bloquea cargar planificacion sin asignacion activa', function () {
    ['juridico' => $juridico, 'encargada' => $encargada, 'reglamento' => $reglamento] = idorSemilla();

    $ajeno = idorExpedienteConEstado('EN_PLANIFICACION', $encargada->id, $reglamento->id);

    Sanctum::actingAs($juridico, ['*']);

    $this->postJson('/api/expedientes/'.$ajeno->id.'/planificacion', [])
        ->assertForbidden();
});

it('bloquea solicitar ampliacion de plazo sin asignacion activa', function () {
    ['tecnico' => $tecnico, 'encargada' => $encargada, 'reglamento' => $reglamento] = idorSemilla();

    $ajeno = idorExpedienteConEstado('EN_EJECUCION', $encargada->id, $reglamento->id);

    Sanctum::actingAs($tecnico, ['*']);

    $this->postJson('/api/expedientes/'.$ajeno->id.'/ampliacion', [])
        ->assertForbidden();
});

it('bloquea comunicar hallazgos sin asignacion activa al rol financiero', function () {
    ['financiero' => $financiero, 'encargada' => $encargada, 'reglamento' => $reglamento] = idorSemilla();

    $ajeno = idorExpedienteConEstado('EN_EJECUCION', $encargada->id, $reglamento->id);

    Sanctum::actingAs($financiero, ['*']);

    $this->postJson('/api/expedientes/'.$ajeno->id.'/descargos/comunicar', [])
        ->assertForbidden();
});

it('bloquea resolver impugnaciones a un rol que no es la encargada', function () {
    ['tecnico' => $tecnico, 'encargada' => $encargada, 'reglamento' => $reglamento] = idorSemilla();

    $expediente = idorExpedienteConEstado('EN_IMPUGNACION', $encargada->id, $reglamento->id);

    Sanctum::actingAs($tecnico, ['*']);

    $this->postJson('/api/expedientes/'.$expediente->id.'/impugnacion/resolver', [])
        ->assertForbidden();
});

it('bloquea el sorteo de expedientes a un rol que no es la encargada', function () {
    ['tecnico' => $tecnico, 'encargada' => $encargada, 'reglamento' => $reglamento] = idorSemilla();

    $pendiente = idorExpedienteConEstado('PENDIENTE_SORTEO', $encargada->id, $reglamento->id);

    Sanctum::actingAs($tecnico, ['*']);

    $this->postJson('/api/expedientes/'.$pendiente->id.'/sortear', [])
        ->assertForbidden();
});

it('bloquea la bandeja de sorteo de la api a un rol que no es la encargada', function () {
    ['tecnico' => $tecnico] = idorSemilla();

    Sanctum::actingAs($tecnico, ['*']);

    $this->getJson('/api/bandeja/sorteo')
        ->assertForbidden();
});

it('bloquea el dashboard de la encargada de la api a un rol que no es la encargada', function () {
    ['tecnico' => $tecnico] = idorSemilla();

    Sanctum::actingAs($tecnico, ['*']);

    $this->getJson('/api/encargada/dashboard')
        ->assertForbidden();
});

it('bloquea el catalogo de operativos a un tecnico', function () {
    ['tecnico' => $tecnico] = idorSemilla();

    Sanctum::actingAs($tecnico, ['*']);

    $this->getJson('/api/usuarios')
        ->assertForbidden();
});

it('bloquea inactivar usuarios a un rol que no es el administrador', function () {
    ['encargada' => $encargada, 'juridico' => $juridico] = idorSemilla();

    Sanctum::actingAs($encargada, ['*']);

    $this->postJson('/api/usuarios/'.$juridico->id.'/inactivar', [])
        ->assertForbidden();
});

it('bloquea el panel administrativo de la api a un operativo', function () {
    ['tecnico' => $tecnico] = idorSemilla();

    Sanctum::actingAs($tecnico, ['*']);

    $this->getJson('/api/admin/dashboard')->assertForbidden();
    $this->getJson('/api/admin/monitoreo')->assertForbidden();
    $this->getJson('/api/admin/usuarios')->assertForbidden();
    $this->getJson('/api/admin/feriados')->assertForbidden();
});

it('bloquea la apertura de expedientes a un rol que no es el tecnico', function () {
    ['encargada' => $encargada, 'reglamento' => $reglamento] = idorSemilla();

    Sanctum::actingAs($encargada, ['*']);

    $this->postJson('/api/expedientes', [
        'via' => 'TECNICO',
        'reglamento_id' => $reglamento->id,
        'fecha_ingreso' => now()->toDateString(),
    ])->assertForbidden();
});

it('bloquea el panel administrativo web a un operativo', function () {
    ['juridico' => $juridico] = idorSemilla();

    actingAs($juridico)
        ->get('/administrador/dashboard')
        ->assertForbidden();
});

it('exige autenticacion en los endpoints administrativos de la api', function () {
    $this->getJson('/api/admin/usuarios')->assertUnauthorized();
    $this->getJson('/api/admin/dashboard')->assertUnauthorized();
});
