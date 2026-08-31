<?php

use App\Http\Requests\SortearExpedienteRequest;
use App\Http\Requests\StoreActuadoRequest;
use App\Http\Requests\StoreExpedienteRequest;
use App\Models\Actuado;
use App\Models\Asignacion;
use App\Models\CatalogoActuado;
use App\Models\CatalogoEstado;
use App\Models\Expediente;
use App\Models\Reglamento;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

function paso4SemillaRolesUsuarios(): array
{
    $rolEncargada = Rol::factory()->create(['codigo' => Rol::CODIGO_ENCARGADA]);
    $rolTecnico = Rol::factory()->create(['codigo' => Rol::CODIGO_TECNICO]);
    $rolAuditor = Rol::factory()->create(['codigo' => Rol::CODIGO_AUD_JURIDICO]);
    $rolAdmin = Rol::factory()->create(['codigo' => Rol::CODIGO_ADMIN]);

    $encargada = Usuario::factory()->create(['rol_id' => $rolEncargada->id, 'activo' => true]);
    $tecnico = Usuario::factory()->create(['rol_id' => $rolTecnico->id, 'activo' => true]);
    $tecnicoInactivo = Usuario::factory()->create(['rol_id' => $rolTecnico->id, 'activo' => false]);
    $auditor = Usuario::factory()->create(['rol_id' => $rolAuditor->id, 'activo' => true]);
    $admin = Usuario::factory()->create(['rol_id' => $rolAdmin->id, 'activo' => true]);

    return compact('rolEncargada', 'rolTecnico', 'rolAuditor', 'rolAdmin', 'encargada', 'tecnico', 'tecnicoInactivo', 'auditor', 'admin');
}

function paso4PayloadExpedienteValido(int $reglamentoId): array
{
    return [
        'via' => 'TECNICO',
        'reglamento_id' => $reglamentoId,
        'resumen_hechos' => 'Hechos denunciados sobre irregularidades administrativas verificables.',
        'partes' => [
            ['tipo' => 'DENUNCIANTE', 'nombre_completo' => 'Juan Pérez', 'documento_identidad' => '1234567'],
            ['tipo' => 'DENUNCIADO', 'nombre_completo' => 'Autoridad Municipal', 'cargo_institucion' => 'Dirección General'],
        ],
    ];
}

function paso4SemillaExpedienteYActuado(Usuario $creadoPor, Rol $rolActuado): array
{
    $estado = CatalogoEstado::factory()->create();
    $reglamento = Reglamento::factory()->create();

    $expediente = Expediente::create([
        'nurej_code' => 'NUREJ-'.Str::upper(Str::random(8)),
        'via' => 'TECNICO',
        'reglamento_id' => $reglamento->id,
        'estado_actual_id' => $estado->id,
        'resumen_hechos' => 'Hechos denunciados sobre irregularidades administrativas verificables.',
        'fecha_ingreso' => now(),
        'creado_por' => $creadoPor->id,
    ]);

    $actuado = CatalogoActuado::create([
        'codigo' => 'ACT_PASO4'.Str::random(4),
        'nombre' => 'Actuado de prueba',
        'fase' => 'REG',
        'rol_id' => $rolActuado->id,
        'estado_destino_id' => $estado->id,
        'es_automatico' => false,
        'requiere_adjunto' => false,
    ]);

    return [$expediente, $actuado, $estado];
}

function paso4AsignarBandeja(int $expedienteId, CatalogoActuado $actuado, Usuario $usuario, Rol $rol, int $estadoId): void
{
    $origen = Actuado::create([
        'expediente_id' => $expedienteId,
        'catalogo_actuado_id' => $actuado->id,
        'usuario_id' => $usuario->id,
        'estado_nuevo_id' => $estadoId,
        'contenido' => ['tipo' => 'asignacion', 'nota' => 'origen de asignación de bandeja'],
    ]);

    Asignacion::create([
        'expediente_id' => $expedienteId,
        'usuario_id' => $usuario->id,
        'rol_id' => $rol->id,
        'actuado_origen_id' => $origen->id,
        'fecha_asignacion' => now(),
        'activa' => true,
    ]);
}

describe('StoreExpedienteRequest (apertura de causa, rol TECNICO)', function () {
    beforeEach(function () {
        Route::post('/_test/expedientes', fn (StoreExpedienteRequest $request) => response()->json(['ok' => true]));
        $this->semilla = paso4SemillaRolesUsuarios();
        $this->reglamento = Reglamento::factory()->create();
    });

    test('un tecnico puede abrir una causa con payload valido', function () {
        $this->actingAs($this->semilla['tecnico'])
            ->postJson('/_test/expedientes', paso4PayloadExpedienteValido($this->reglamento->id))
            ->assertOk()
            ->assertJson(['ok' => true]);
    });

    test('un usuario sin rol TECNICO recibe 403', function () {
        $this->actingAs($this->semilla['encargada'])
            ->postJson('/_test/expedientes', paso4PayloadExpedienteValido($this->reglamento->id))
            ->assertForbidden();
    });

    test('un payload invalido recibe 422', function () {
        $this->actingAs($this->semilla['tecnico'])
            ->postJson('/_test/expedientes', [
                'via' => 'DESCONOCIDA',
                'reglamento_id' => 999999,
                'resumen_hechos' => 'corto',
                'partes' => [
                    ['tipo' => 'VIOLACION', 'nombre_completo' => ''],
                ],
                'adjunto' => 'no-es-un-archivo.pdf',
            ])
            ->assertUnprocessable();
    });
});

describe('SortearExpedienteRequest (sorteo, rol ENCARGADA)', function () {
    beforeEach(function () {
        Route::post('/_test/sortear', fn (SortearExpedienteRequest $request) => response()->json(['ok' => true]));
        $this->semilla = paso4SemillaRolesUsuarios();
    });

    test('la encargada puede sortear hacia un operador activo', function () {
        $this->actingAs($this->semilla['encargada'])
            ->postJson('/_test/sortear', ['usuario_destino_id' => $this->semilla['auditor']->id, 'descripcion' => 'Pase a auditoría'])
            ->assertOk()
            ->assertJson(['ok' => true]);
    });

    test('un usuario sin rol ENCARGADA recibe 403', function () {
        $this->actingAs($this->semilla['tecnico'])
            ->postJson('/_test/sortear', ['usuario_destino_id' => $this->semilla['auditor']->id])
            ->assertForbidden();
    });

    test('el destino debe estar activo y pertenecer a un rol operativo', function () {
        $this->actingAs($this->semilla['encargada'])
            ->postJson('/_test/sortear', ['usuario_destino_id' => $this->semilla['tecnicoInactivo']->id])
            ->assertUnprocessable();

        $this->actingAs($this->semilla['encargada'])
            ->postJson('/_test/sortear', ['usuario_destino_id' => $this->semilla['admin']->id])
            ->assertUnprocessable();
    });
});

describe('StoreActuadoRequest (emisión de actuados, ExpedientePolicy)', function () {
    beforeEach(function () {
        Route::post('/_test/expedientes/{expediente}/actuados', fn (StoreActuadoRequest $request) => response()->json(['ok' => true]));
        $this->semilla = paso4SemillaRolesUsuarios();
    });

    test('un auditor sin asignacion activa recibe 403 (RF-03)', function () {
        [$expediente, $actuado, $estado] = paso4SemillaExpedienteYActuado($this->semilla['tecnico'], $this->semilla['rolAuditor']);

        $this->actingAs($this->semilla['auditor'])
            ->postJson("/_test/expedientes/{$expediente->id}/actuados", [
                'catalogo_actuado_id' => $actuado->id,
                'descripcion' => 'Descripción del actuado válida.',
            ])
            ->assertForbidden();
    });

    test('un tecnico con asignacion activa y rol del catalogo emite el actuado', function () {
        [$expediente, $actuado, $estado] = paso4SemillaExpedienteYActuado($this->semilla['tecnico'], $this->semilla['rolTecnico']);

        paso4AsignarBandeja($expediente->id, $actuado, $this->semilla['tecnico'], $this->semilla['rolTecnico'], $estado->id);

        $this->actingAs($this->semilla['tecnico'])
            ->postJson("/_test/expedientes/{$expediente->id}/actuados", [
                'catalogo_actuado_id' => $actuado->id,
                'descripcion' => 'Descripción del actuado válida.',
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);
    });

    test('la encargada emite un actuado jerarquico sin tener bandeja (RN-07)', function () {
        [$expediente, $actuado, $estado] = paso4SemillaExpedienteYActuado($this->semilla['encargada'], $this->semilla['rolEncargada']);

        $this->actingAs($this->semilla['encargada'])
            ->postJson("/_test/expedientes/{$expediente->id}/actuados", [
                'catalogo_actuado_id' => $actuado->id,
                'descripcion' => 'Descripción del actuado válida.',
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);
    });

    test('un payload invalido recibe 422', function () {
        [$expediente, $actuado, $estado] = paso4SemillaExpedienteYActuado($this->semilla['tecnico'], $this->semilla['rolTecnico']);

        paso4AsignarBandeja($expediente->id, $actuado, $this->semilla['tecnico'], $this->semilla['rolTecnico'], $estado->id);

        $this->actingAs($this->semilla['tecnico'])
            ->postJson("/_test/expedientes/{$expediente->id}/actuados", [
                'catalogo_actuado_id' => $actuado->id,
                'descripcion' => 'corta',
                'usuario_destino_id' => 999999,
            ])
            ->assertUnprocessable();
    });
});
