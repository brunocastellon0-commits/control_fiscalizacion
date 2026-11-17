<?php

use App\Models\Actuado;
use App\Models\Asignacion;
use App\Models\CatalogoActuado;
use App\Models\CatalogoEstado;
use App\Models\CatalogoRequisito;
use App\Models\Expediente;
use App\Models\Reglamento;
use App\Models\Rol;
use App\Models\Usuario;
use Database\Seeders\CatalogoActuadoSeeder;
use Database\Seeders\CatalogoEstadoSeeder;
use Database\Seeders\CatalogoRequisitoSeeder;
use Database\Seeders\FeriadoSeeder;
use Database\Seeders\ParametroPlazoSeeder;
use Database\Seeders\ReglamentoSeeder;
use Database\Seeders\RolSeeder;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Semilla de contratos: catálogos reales del proyecto (sin datos demo) y
 * operadores por rol para verificar CTR-01/02/03 contra la implementación real.
 *
 * @return array{tecnico: Usuario, juridico: Usuario, encargada: Usuario, ac022: Reglamento, ac054: Reglamento}
 */
function ctrSemilla(TestCase $test): array
{
    foreach ([
        RolSeeder::class,
        CatalogoEstadoSeeder::class,
        ReglamentoSeeder::class,
        CatalogoRequisitoSeeder::class,
        ParametroPlazoSeeder::class,
        FeriadoSeeder::class,
        CatalogoActuadoSeeder::class,
    ] as $seeder) {
        $test->seed($seeder);
    }

    $rolTecnico = Rol::where('codigo', Rol::CODIGO_TECNICO)->firstOrFail();
    $rolJuridico = Rol::where('codigo', Rol::CODIGO_AUD_JURIDICO)->firstOrFail();
    $rolEncargada = Rol::where('codigo', Rol::CODIGO_ENCARGADA)->firstOrFail();

    return [
        'tecnico' => Usuario::factory()->create(['rol_id' => $rolTecnico->id, 'activo' => true]),
        'juridico' => Usuario::factory()->create(['rol_id' => $rolJuridico->id, 'activo' => true]),
        'encargada' => Usuario::factory()->create(['rol_id' => $rolEncargada->id, 'activo' => true]),
        'ac022' => Reglamento::where('codigo', 'AC_022_2018')->firstOrFail(),
        'ac054' => Reglamento::where('codigo', 'AC_054_2018')->firstOrFail(),
    ];
}

function ctrCrearExpediente(int $estadoId, int $creadorId, int $reglamentoId): Expediente
{
    return Expediente::create([
        'nurej_code' => 'EXP-'.fake()->unique()->numberBetween(10000, 99999),
        'via' => 'JURIDICO',
        'reglamento_id' => $reglamentoId,
        'estado_actual_id' => $estadoId,
        'fecha_ingreso' => now(),
        'creado_por' => $creadorId,
    ]);
}

function ctrAsignarBandeja(Expediente $expediente, Usuario $usuario, int $estadoId, int $catalogoId): void
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

/**
 * Payload con el checklist completo de los requisitos activos de AC022
 * (semilla real: 3 requisitos, órdenes 1-3).
 *
 * @return array<string, mixed>
 */
function ctrChecklistAc022(bool $cumplenTodos = true): array
{
    $requisitos = CatalogoRequisito::where('reglamento_id', Reglamento::where('codigo', 'AC_022_2018')->firstOrFail()->id)
        ->where('activo', true)
        ->orderBy('orden')
        ->get();

    return [
        'requisitos' => $requisitos->map(fn ($r) => [
            'requisito_id' => $r->id,
            'cumple' => $cumplenTodos ? true : ! $r->es_critico,
        ])->values()->all(),
    ];
}

// ─── CTR-01: GET /api/expedientes/{e}/requisitos ────────────────────────────

it('CTR-01: devuelve 401 sin token', function () {
    $this->getJson('/api/expedientes/1/requisitos')->assertUnauthorized();
});

it('CTR-01: devuelve 403 al operador sin asignación activa (RF-03)', function () {
    $semilla = ctrSemilla($this);
    $expediente = ctrCrearExpediente(
        CatalogoEstado::where('codigo', 'EN_EVALUACION')->firstOrFail()->id,
        $semilla['encargada']->id,
        $semilla['ac022']->id,
    );

    Sanctum::actingAs($semilla['tecnico'], ['*']);

    $this->getJson('/api/expedientes/'.$expediente->id.'/requisitos')->assertForbidden();
});

it('CTR-01: devuelve 200 con la estructura real de requisitos del reglamento', function () {
    $semilla = ctrSemilla($this);
    $evaluacion = CatalogoEstado::where('codigo', 'EN_EVALUACION')->firstOrFail();
    $catalogoAdmision = CatalogoActuado::where('codigo', 'ACT_ADMISION')->firstOrFail();

    $expediente = ctrCrearExpediente($evaluacion->id, $semilla['encargada']->id, $semilla['ac022']->id);
    ctrAsignarBandeja($expediente, $semilla['tecnico'], $evaluacion->id, $catalogoAdmision->id);

    Sanctum::actingAs($semilla['tecnico'], ['*']);

    $response = $this->getJson('/api/expedientes/'.$expediente->id.'/requisitos')
        ->assertOk()
        ->assertJsonCount(3, 'data');

    // Estructura REAL de CatalogoRequisitoResource (el contrato CTR-01 exige
    // campos que la BD no provee: ver reporte B1.1, decisión pendiente).
    $response->assertJsonStructure([
        'data' => [['id', 'descripcion', 'orden', 'es_critico', 'reglamento']],
    ]);

    expect(array_column($response->json('data'), 'orden'))->toBe([1, 2, 3]);
});

// ─── CTR-02: POST /api/expedientes/{e}/evaluacion ───────────────────────────

it('CTR-02: devuelve 401 sin token', function () {
    $this->postJson('/api/expedientes/1/evaluacion', [])->assertUnauthorized();
});

it('CTR-02: devuelve 403 si el expediente no está asignado al operador (RF-03)', function () {
    $semilla = ctrSemilla($this);
    $evaluacion = CatalogoEstado::where('codigo', 'EN_EVALUACION')->firstOrFail();
    $catalogoAdmision = CatalogoActuado::where('codigo', 'ACT_ADMISION')->firstOrFail();

    $expediente = ctrCrearExpediente($evaluacion->id, $semilla['encargada']->id, $semilla['ac022']->id);
    ctrAsignarBandeja($expediente, $semilla['tecnico'], $evaluacion->id, $catalogoAdmision->id);

    $expedienteAjeno = ctrCrearExpediente($evaluacion->id, $semilla['encargada']->id, $semilla['ac022']->id);

    Sanctum::actingAs($semilla['tecnico'], ['*']);

    $this->postJson('/api/expedientes/'.$expedienteAjeno->id.'/evaluacion', ctrChecklistAc022())
        ->assertForbidden();
});

it('CTR-02: 201 con resumen.resultado y evaluaciones ligadas al actuado (comportamiento real)', function () {
    $semilla = ctrSemilla($this);
    $evaluacion = CatalogoEstado::where('codigo', 'EN_EVALUACION')->firstOrFail();
    $catalogoAdmision = CatalogoActuado::where('codigo', 'ACT_ADMISION')->firstOrFail();

    $expediente = ctrCrearExpediente($evaluacion->id, $semilla['encargada']->id, $semilla['ac022']->id);
    ctrAsignarBandeja($expediente, $semilla['tecnico'], $evaluacion->id, $catalogoAdmision->id);

    Sanctum::actingAs($semilla['tecnico'], ['*']);

    $response = $this->postJson('/api/expedientes/'.$expediente->id.'/evaluacion', ctrChecklistAc022())
        ->assertStatus(201)
        ->assertJsonStructure(['resumen' => ['resultado', 'requisitos_cumplidos', 'requisitos_faltantes', 'faltantes_criticos'], 'evaluaciones'])
        ->assertJsonPath('resumen.resultado', 'ACT_ADMISION')
        ->assertJsonPath('resumen.requisitos_faltantes', 0)
        ->assertJsonCount(3, 'evaluaciones');

    // El contrato CTR-02 exige {resultado, actuado_id} a nivel raíz con 200;
    // la implementación real responde 201 con {resumen, evaluaciones} y el
    // actuado ligado por evaluaciones[].actuado_id (reporte B1.1).
    foreach ($response->json('evaluaciones') as $evaluacionFila) {
        expect($evaluacionFila['actuado_id'])->not->toBeNull();
    }

    expect($expediente->refresh()->estado_actual_id)
        ->toBe(CatalogoEstado::where('codigo', 'EN_PLANIFICACION')->firstOrFail()->id);
});

// ─── CTR-03: GET /api/catalogo/actuados?expediente_id={e} ───────────────────

it('CTR-03: devuelve 401 sin token', function () {
    $this->getJson('/api/catalogo/actuados')->assertUnauthorized();
});

it('CTR-03: 200 con la estructura del contrato y sin automáticos', function () {
    $semilla = ctrSemilla($this);

    Sanctum::actingAs($semilla['tecnico'], ['*']);

    $response = $this->getJson('/api/catalogo/actuados')->assertOk();

    $response->assertJsonStructure([
        'data' => [['id', 'codigo', 'nombre', 'requiere_adjunto', 'estado_destino']],
    ]);

    $codigos = array_column($response->json('data'), 'codigo');

    expect($codigos)->toContain('ACT_INFORME_TECNICO_CON_RESPONSABILIDAD')
        ->not->toContain('ACT_ARCHIVO_POR_ABANDONO');
});

it('CTR-03: filtra el catálogo por el reglamento del expediente consultado', function () {
    $semilla = ctrSemilla($this);
    $evaluacion = CatalogoEstado::where('codigo', 'EN_EVALUACION')->firstOrFail();

    $expedienteAc022 = ctrCrearExpediente($evaluacion->id, $semilla['encargada']->id, $semilla['ac022']->id);
    $expedienteAc054 = ctrCrearExpediente($evaluacion->id, $semilla['encargada']->id, $semilla['ac054']->id);

    Sanctum::actingAs($semilla['juridico'], ['*']);

    // Expediente AC022: los actuados con pivote (AUD_JURIDICO, AC054) quedan
    // fuera; los de pivote con reglamento null siguen visibles.
    $codigosAc022 = array_column(
        $this->getJson('/api/catalogo/actuados?expediente_id='.$expedienteAc022->id)
            ->assertOk()->json('data'),
        'codigo',
    );

    expect($codigosAc022)->toContain('ACT_INFORME_FINAL')
        ->not->toContain('ACT_MPA')
        ->not->toContain('ACT_INFORME_AUDITORIA_JURIDICA_CON_RESPONSABILIDAD');

    // Expediente AC054: sí aparecen los MPA e informes jurídicos.
    $codigosAc054 = array_column(
        $this->getJson('/api/catalogo/actuados?expediente_id='.$expedienteAc054->id)
            ->assertOk()->json('data'),
        'codigo',
    );

    expect($codigosAc054)->toContain('ACT_MPA')
        ->toContain('ACT_INFORME_AUDITORIA_JURIDICA_CON_RESPONSABILIDAD')
        ->toContain('ACT_INFORME_AUDITORIA_JURIDICA_SIN_RESPONSABILIDAD');
});

// ─── Seeder B1.1: informes faltantes (AUD-0008, D-6d/D-6e) ─────────────────

it('el seeder crea los 6 informes faltantes con destino PENDIENTE_VISTO_BUENO_FINAL', function () {
    ctrSemilla($this);

    $esperados = [
        'ACT_INFORME_TECNICO_CON_RESPONSABILIDAD' => Rol::CODIGO_TECNICO,
        'ACT_INFORME_TECNICO_SIN_RESPONSABILIDAD' => Rol::CODIGO_TECNICO,
        'ACT_INFORME_TECNICO_CON_RESPONSABILIDAD_RECOMENDACION' => Rol::CODIGO_TECNICO,
        'ACT_INFORME_TECNICO_SIN_RESPONSABILIDAD_RECOMENDACION' => Rol::CODIGO_TECNICO,
        'ACT_INFORME_AUDITORIA_JURIDICA_CON_RESPONSABILIDAD' => Rol::CODIGO_AUD_JURIDICO,
        'ACT_INFORME_AUDITORIA_JURIDICA_SIN_RESPONSABILIDAD' => Rol::CODIGO_AUD_JURIDICO,
    ];

    $pendienteVbFinal = CatalogoEstado::where('codigo', 'PENDIENTE_VISTO_BUENO_FINAL')->firstOrFail();

    foreach ($esperados as $codigo => $rolCodigo) {
        $actuado = CatalogoActuado::where('codigo', $codigo)->firstOrFail();

        expect($actuado->estado_destino_id)->toBe($pendienteVbFinal->id)
            ->and($actuado->es_automatico)->toBeFalse()
            ->and($actuado->requiere_adjunto)->toBeTrue()
            ->and($actuado->fase)->toBe('INVESTIGACION')
            ->and($actuado->rol_id)->toBe(Rol::where('codigo', $rolCodigo)->firstOrFail()->id)
            ->and($actuado->roles()->count())->toBe(1);
    }

    // Pivotes específicos por reglamento (decisión B1.1): Técnico→AC022,
    // Jurídico→AC054.
    $ac022 = Reglamento::where('codigo', 'AC_022_2018')->firstOrFail();
    $ac054 = Reglamento::where('codigo', 'AC_054_2018')->firstOrFail();

    $tecnicoConAc022 = CatalogoActuado::where('codigo', 'ACT_INFORME_TECNICO_CON_RESPONSABILIDAD')
        ->firstOrFail()->roles()->first();
    expect($tecnicoConAc022->pivot->reglamento_id)->toBe($ac022->id);

    $juridicoConAc054 = CatalogoActuado::where('codigo', 'ACT_INFORME_AUDITORIA_JURIDICA_CON_RESPONSABILIDAD')
        ->firstOrFail()->roles()->first();
    expect($juridicoConAc054->pivot->reglamento_id)->toBe($ac054->id);
});

it('el seeder declara ACT_INFORME_FINAL con destino PENDIENTE_VISTO_BUENO_FINAL (D-6a aplicado en B1.2)', function () {
    ctrSemilla($this);

    $actuado = CatalogoActuado::where('codigo', 'ACT_INFORME_FINAL')->firstOrFail();
    $pendienteVbFinal = CatalogoEstado::where('codigo', 'PENDIENTE_VISTO_BUENO_FINAL')->firstOrFail();

    expect($actuado->estado_destino_id)->toBe($pendienteVbFinal->id);
});

it('el seeder declara ACT_PASO_PLANIFICACION como evento automático ADMITIDO → EN_PLANIFICACION (D-6b)', function () {
    ctrSemilla($this);

    $actuado = CatalogoActuado::where('codigo', 'ACT_PASO_PLANIFICACION')->firstOrFail();
    $admitido = CatalogoEstado::where('codigo', 'ADMITIDO')->firstOrFail();
    $planificacion = CatalogoEstado::where('codigo', 'EN_PLANIFICACION')->firstOrFail();

    expect($actuado->estado_origen_id)->toBe($admitido->id)
        ->and($actuado->estado_destino_id)->toBe($planificacion->id)
        ->and($actuado->es_automatico)->toBeTrue()
        ->and($actuado->requiere_adjunto)->toBeFalse();
});
