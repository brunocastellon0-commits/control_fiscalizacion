<?php

use App\Http\Requests\ImpugnacionRemitirRequest;
use App\Http\Requests\ResolverImpugnacionRequest;
use App\Models\CatalogoEstado;
use App\Models\Expediente;
use App\Models\Feriado;
use App\Models\Reglamento;
use App\Models\Rol;
use App\Models\Usuario;
use Database\Seeders\RolSeeder;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Semilla compartida: roles reales y usuarios activos por rol.
 *
 * @return array{admin: Usuario, tecnico: Usuario, encargada: Usuario, rolTecnico: int}
 */
function frcSemilla(TestCase $test): array
{
    $test->seed(RolSeeder::class);

    $rolAdmin = Rol::where('codigo', Rol::CODIGO_ADMIN)->firstOrFail();
    $rolTecnico = Rol::where('codigo', Rol::CODIGO_TECNICO)->firstOrFail();
    $rolEncargada = Rol::where('codigo', Rol::CODIGO_ENCARGADA)->firstOrFail();

    return [
        'admin' => Usuario::factory()->create(['rol_id' => $rolAdmin->id, 'activo' => true]),
        'tecnico' => Usuario::factory()->create(['rol_id' => $rolTecnico->id, 'activo' => true]),
        'encargada' => Usuario::factory()->create(['rol_id' => $rolEncargada->id, 'activo' => true]),
        'rolTecnico' => $rolTecnico->id,
    ];
}

/**
 * @return array{fecha: string, descripcion: string, ambito: string}
 */
function frcPayloadFeriado(string $fecha, string $descripcion = 'Feriado de prueba', string $ambito = 'NACIONAL'): array
{
    return [
        'fecha' => $fecha,
        'descripcion' => $descripcion,
        'ambito' => $ambito,
    ];
}

/**
 * @return array<string, mixed>
 */
function frcPayloadUsuario(int $rolId, string $password = 'secreto.frc'): array
{
    return [
        'ci' => fake()->unique()->numerify('#########'),
        'nombres' => 'Nombres FRC',
        'apellidos' => 'Apellidos FRC',
        'cargo' => 'Analista de fiscalización',
        'username' => fake()->unique()->userName(),
        'password' => $password,
        'rol_id' => $rolId,
    ];
}

function frcExpediente(Usuario $creadoPor): Expediente
{
    $estado = CatalogoEstado::factory()->create();
    $reglamento = Reglamento::factory()->create();

    return Expediente::create([
        'nurej_code' => 'NUREJ-FRC-'.fake()->unique()->numerify('######'),
        'via' => 'INGRESO_VERBAL',
        'reglamento_id' => $reglamento->id,
        'estado_actual_id' => $estado->id,
        'resumen_hechos' => 'Hechos denunciados sobre irregularidades administrativas verificables.',
        'fecha_ingreso' => now(),
        'creado_por' => $creadoPor->id,
    ]);
}

describe('StoreFeriadoRequest / UpdateFeriadoRequest (límites reales de la tabla)', function () {
    beforeEach(function () {
        $this->semilla = frcSemilla($this);
        Sanctum::actingAs($this->semilla['admin'], ['*']);
    });

    it('rechaza una descripción de 151 caracteres con 422 y no con un 500 de MySQL', function () {
        $this->postJson('/api/admin/feriados', frcPayloadFeriado('2027-02-01', str_repeat('x', 151)))
            ->assertStatus(422)
            ->assertJsonValidationErrors('descripcion');
    });

    it('acepta una descripción de 150 caracteres', function () {
        $this->postJson('/api/admin/feriados', frcPayloadFeriado('2027-02-01', str_repeat('x', 150)))
            ->assertCreated();
    });

    it('rechaza un ámbito no admitido', function () {
        $this->postJson('/api/admin/feriados', frcPayloadFeriado('2027-02-02', 'Feriado', 'INVALIDO'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('ambito');
    });

    it('acepta LOCAL aunque el formulario no lo ofrezca, porque ya existe en los datos', function () {
        $this->postJson('/api/admin/feriados', frcPayloadFeriado('2027-02-03', 'Feriado local', 'LOCAL'))
            ->assertCreated();
    });

    it('el update también acota la descripción a 150 caracteres', function () {
        $feriado = Feriado::create(frcPayloadFeriado('2027-03-01'));

        $this->putJson("/api/admin/feriados/{$feriado->id}", frcPayloadFeriado('2027-03-01', str_repeat('x', 151)))
            ->assertStatus(422)
            ->assertJsonValidationErrors('descripcion');
    });

    it('el update rechaza un ámbito no admitido', function () {
        $feriado = Feriado::create(frcPayloadFeriado('2027-03-02'));

        $this->putJson("/api/admin/feriados/{$feriado->id}", frcPayloadFeriado('2027-03-02', 'Feriado', 'INVALIDO'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('ambito');
    });
});

describe('StoreUsuarioRequest / UpdateUsuarioRequest / IndexUsuariosAdminRequest', function () {
    beforeEach(function () {
        $this->semilla = frcSemilla($this);
        Sanctum::actingAs($this->semilla['admin'], ['*']);
    });

    it('rechaza una contraseña de más de 255 caracteres al crear', function () {
        $this->postJson('/api/admin/usuarios', frcPayloadUsuario($this->semilla['rolTecnico'], str_repeat('x', 256)))
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');
    });

    it('rechaza una contraseña de más de 255 caracteres al actualizar', function () {
        $objetivo = Usuario::factory()->create([
            'rol_id' => $this->semilla['rolTecnico'],
            'activo' => true,
        ]);

        $this->putJson(
            "/api/admin/usuarios/{$objetivo->id}",
            frcPayloadUsuario($this->semilla['rolTecnico'], str_repeat('x', 256)),
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');
    });

    it('acepta activo=1 como filtro booleano', function () {
        $this->getJson('/api/admin/usuarios?activo=1')->assertOk();
    });

    it('rechaza un valor no booleano en activo', function () {
        $this->getJson('/api/admin/usuarios?activo=abc')
            ->assertStatus(422)
            ->assertJsonValidationErrors('activo');
    });
});

describe('IndexCatalogoActuadosRequest (filtro por expediente)', function () {
    beforeEach(function () {
        $this->semilla = frcSemilla($this);
        Sanctum::actingAs($this->semilla['tecnico'], ['*']);
    });

    it('rechaza un expediente_id inexistente con 422 en lugar de servir el catálogo sin filtrar', function () {
        $this->getJson('/api/catalogo/actuados?expediente_id=999999')
            ->assertStatus(422)
            ->assertJsonValidationErrors('expediente_id');
    });

    it('acepta la ausencia del filtro', function () {
        $this->getJson('/api/catalogo/actuados')->assertOk();
    });

    it('acepta un expediente_id existente', function () {
        $expediente = frcExpediente($this->semilla['tecnico']);

        $this->getJson("/api/catalogo/actuados?expediente_id={$expediente->id}")->assertOk();
    });
});

describe('LoginRequest (longitud del username)', function () {
    it('rechaza un username de más de 60 caracteres con 422', function () {
        $this->postJson('/api/login', [
            'username' => str_repeat('a', 61),
            'password' => 'cualquiera',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('username');
    });
});

describe('ImpugnacionRemitirRequest / ResolverImpugnacionRequest (respuesta uniforme)', function () {
    beforeEach(function () {
        Route::post('/_test/impugnacion/remitir/{expediente}', fn (ImpugnacionRemitirRequest $request) => response()->json(['ok' => true]));
        Route::post('/_test/impugnacion/resolver/{expediente}', fn (ResolverImpugnacionRequest $request) => response()->json(['ok' => true]));

        $this->semilla = frcSemilla($this);
    });

    it('un expediente inexistente responde 403 y no 404 al remitir', function () {
        $this->actingAs($this->semilla['tecnico'])
            ->postJson('/_test/impugnacion/remitir/999999', [
                'descripcion' => 'Descripción suficientemente larga para pasar la validación.',
            ])
            ->assertForbidden();
    });

    it('un expediente inexistente responde 403 y no 404 al resolver', function () {
        $this->actingAs($this->semilla['encargada'])
            ->postJson('/_test/impugnacion/resolver/999999', [
                'ratifica' => true,
                'justificacion' => 'Justificación suficientemente larga para pasar la validación.',
            ])
            ->assertForbidden();
    });
});
