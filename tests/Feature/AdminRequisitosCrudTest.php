<?php

use App\Models\CatalogoRequisito;
use App\Models\Reglamento;
use App\Models\Rol;
use App\Models\Usuario;
use Database\Seeders\RolSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolSeeder::class);
});

function arcUsuario(string $codigoRol, bool $activo = true): Usuario
{
    $rol = Rol::where('codigo', $codigoRol)->firstOrFail();

    return Usuario::factory()->create(['rol_id' => $rol->id, 'activo' => $activo]);
}

it('lista el catálogo de requisitos con su reglamento para un administrador', function () {
    $admin = arcUsuario(Rol::CODIGO_ADMIN);
    $reglamento = Reglamento::factory()->create(['codigo' => 'AC_022_2018']);
    $requisito = CatalogoRequisito::factory()->create([
        'reglamento_id' => $reglamento->id,
        'descripcion' => 'Documento de identidad vigente',
        'es_critico' => true,
    ]);

    Sanctum::actingAs($admin);

    $this->getJson('/api/admin/requisitos')
        ->assertOk()
        ->assertJsonPath('data.0.id', $requisito->id)
        ->assertJsonPath('data.0.descripcion', 'Documento de identidad vigente')
        ->assertJsonPath('data.0.es_critico', true)
        ->assertJsonPath('data.0.reglamento.codigo', 'AC_022_2018')
        ->assertJsonPath('reglamentos.0.codigo', 'AC_022_2018');
});

it('filtra requisitos por reglamento, texto y estado', function () {
    $admin = arcUsuario(Rol::CODIGO_ADMIN);
    $reglamentoA = Reglamento::factory()->create(['codigo' => 'AC_A']);
    $reglamentoB = Reglamento::factory()->create(['codigo' => 'AC_B']);

    CatalogoRequisito::factory()->create([
        'reglamento_id' => $reglamentoA->id,
        'descripcion' => 'Requisito de prueba alfa',
        'activo' => true,
    ]);

    CatalogoRequisito::factory()->create([
        'reglamento_id' => $reglamentoB->id,
        'descripcion' => 'Requisito de prueba beta',
        'activo' => false,
    ]);

    Sanctum::actingAs($admin);

    $this->getJson('/api/admin/requisitos?reglamento_id='.$reglamentoA->id.'&buscar=alfa&activo=1')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.descripcion', 'Requisito de prueba alfa');
});

it('registra un requisito de admisibilidad', function () {
    $admin = arcUsuario(Rol::CODIGO_ADMIN);
    $reglamento = Reglamento::factory()->create();

    Sanctum::actingAs($admin);

    $this->postJson('/api/admin/requisitos', [
        'reglamento_id' => $reglamento->id,
        'descripcion' => 'Copia legalizada del documento',
        'orden' => 4,
        'es_critico' => true,
    ])
        ->assertCreated()
        ->assertJsonPath('data.descripcion', 'Copia legalizada del documento')
        ->assertJsonPath('data.orden', 4)
        ->assertJsonPath('data.es_critico', true)
        ->assertJsonPath('data.activo', true);

    $this->assertDatabaseHas('catalogo_requisitos', [
        'reglamento_id' => $reglamento->id,
        'descripcion' => 'Copia legalizada del documento',
        'es_critico' => true,
        'activo' => true,
    ]);
});

it('edita la descripción, el reglamento y el carácter crítico de un requisito', function () {
    $admin = arcUsuario(Rol::CODIGO_ADMIN);
    $reglamento = Reglamento::factory()->create();
    $requisito = CatalogoRequisito::factory()->create([
        'reglamento_id' => $reglamento->id,
        'descripcion' => 'Texto original',
        'es_critico' => false,
    ]);

    Sanctum::actingAs($admin);

    $this->putJson("/api/admin/requisitos/{$requisito->id}", [
        'descripcion' => 'Texto actualizado',
        'es_critico' => true,
    ])
        ->assertOk()
        ->assertJsonPath('data.descripcion', 'Texto actualizado')
        ->assertJsonPath('data.es_critico', true);

    $this->assertDatabaseHas('catalogo_requisitos', [
        'id' => $requisito->id,
        'descripcion' => 'Texto actualizado',
        'es_critico' => true,
    ]);
});

it('desactiva lógicamente un requisito sin borrar su historial', function () {
    $admin = arcUsuario(Rol::CODIGO_ADMIN);
    $requisito = CatalogoRequisito::factory()->create(['activo' => true]);

    Sanctum::actingAs($admin);

    $this->deleteJson("/api/admin/requisitos/{$requisito->id}")
        ->assertOk()
        ->assertJsonPath('data.activo', false);

    $this->assertDatabaseHas('catalogo_requisitos', [
        'id' => $requisito->id,
        'activo' => false,
    ]);
});

it('valida los datos del requisito', function () {
    $admin = arcUsuario(Rol::CODIGO_ADMIN);

    Sanctum::actingAs($admin);

    $this->postJson('/api/admin/requisitos', [
        'descripcion' => '',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['reglamento_id', 'descripcion']);
});

it('prohíbe gestionar requisitos a roles distintos de ADMIN', function () {
    $tecnico = arcUsuario(Rol::CODIGO_TECNICO);
    $requisito = CatalogoRequisito::factory()->create();

    Sanctum::actingAs($tecnico);

    $this->getJson('/api/admin/requisitos')->assertForbidden();
    $this->postJson('/api/admin/requisitos', [])->assertForbidden();
    $this->deleteJson("/api/admin/requisitos/{$requisito->id}")->assertForbidden();
});

it('prohíbe gestionar requisitos a un administrador inactivo', function () {
    $inactivo = arcUsuario(Rol::CODIGO_ADMIN, false);

    Sanctum::actingAs($inactivo);

    $this->getJson('/api/admin/requisitos')->assertForbidden();
});
