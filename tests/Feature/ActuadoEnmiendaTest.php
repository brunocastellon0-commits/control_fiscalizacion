<?php

use App\Models\Actuado;
use App\Models\Asignacion;
use App\Models\CatalogoActuado;
use App\Models\CatalogoEstado;
use App\Models\Expediente;
use App\Models\Parte;
use App\Models\Plazo;
use App\Models\Reglamento;
use App\Models\Rol;
use App\Models\Usuario;
use Database\Seeders\CatalogoActuadoSeeder;
use Database\Seeders\CatalogoEstadoSeeder;
use Database\Seeders\FeriadoSeeder;
use Database\Seeders\ParametroPlazoSeeder;
use Database\Seeders\ReglamentoSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * B1.7 — Actuado de Enmienda (RF-02 / CTR-09): corrección de datos
 * caratulados con captura datos_anteriores/datos_nuevos en el payload
 * inmutable, whitelist de campos y versionado de `partes`.
 *
 * @return array{
 *     expediente: Expediente,
 *     tecnico: Usuario,
 *     tecnicoAjeno: Usuario,
 *     encargada: Usuario,
 *     admin: Usuario,
 *     estado: CatalogoEstado,
 *     actuadoBase: Actuado,
 * }
 */
function enmiendaSemilla(TestCase $test): array
{
    foreach ([
        RolSeeder::class,
        CatalogoEstadoSeeder::class,
        ReglamentoSeeder::class,
        ParametroPlazoSeeder::class,
        FeriadoSeeder::class,
        CatalogoActuadoSeeder::class,
    ] as $seeder) {
        $test->seed($seeder);
    }

    $rolEncargada = Rol::where('codigo', Rol::CODIGO_ENCARGADA)->firstOrFail();
    $rolTecnico = Rol::where('codigo', Rol::CODIGO_TECNICO)->firstOrFail();
    $rolAdmin = Rol::where('codigo', Rol::CODIGO_ADMIN)->firstOrFail();

    $estado = CatalogoEstado::where('codigo', 'EN_EVALUACION')->firstOrFail();
    $ac022 = Reglamento::where('codigo', 'AC_022_2018')->firstOrFail();

    $tecnico = Usuario::factory()->create(['rol_id' => $rolTecnico->id, 'activo' => true]);
    $tecnicoAjeno = Usuario::factory()->create(['rol_id' => $rolTecnico->id, 'activo' => true]);
    $encargada = Usuario::factory()->create(['rol_id' => $rolEncargada->id, 'activo' => true]);
    $admin = Usuario::factory()->create(['rol_id' => $rolAdmin->id, 'activo' => true]);

    $expediente = Expediente::create([
        'nurej_code' => 'EXP-ENM-'.fake()->unique()->numberBetween(100000, 999999),
        'via' => 'TECNICO',
        'reglamento_id' => $ac022->id,
        'estado_actual_id' => $estado->id,
        'resumen_hechos' => 'Hechos originales de la causa.',
        'fecha_ingreso' => now(),
        'creado_por' => $tecnico->id,
    ]);

    $actuadoBase = Actuado::create([
        'expediente_id' => $expediente->id,
        'catalogo_actuado_id' => CatalogoActuado::where('codigo', 'ACT_SORTEO_INICIAL')->firstOrFail()->id,
        'usuario_id' => $encargada->id,
        'estado_nuevo_id' => $estado->id,
        'contenido' => ['descripcion' => 'Actuado base de la semilla B1.7'],
    ])->refresh();

    Asignacion::create([
        'expediente_id' => $expediente->id,
        'usuario_id' => $tecnico->id,
        'rol_id' => $rolTecnico->id,
        'actuado_origen_id' => $actuadoBase->id,
        'fecha_asignacion' => now(),
        'activa' => true,
    ]);

    Parte::create([
        'expediente_id' => $expediente->id,
        'tipo' => 'DENUNCIADO',
        'nombre_completo' => 'Empresa Original S.A.',
        'documento_identidad' => '1234567',
        'cargo_institucion' => 'Gerente General',
        'actuado_origen_id' => $actuadoBase->id,
        'vigente_desde' => now(),
        'es_version_actual' => true,
    ]);

    Parte::create([
        'expediente_id' => $expediente->id,
        'tipo' => 'DENUNCIANTE',
        'nombre_completo' => 'Ciudadano Ejemplo',
        'documento_identidad' => '7654321',
        'actuado_origen_id' => $actuadoBase->id,
        'vigente_desde' => now(),
        'es_version_actual' => true,
    ]);

    return [
        'expediente' => $expediente,
        'tecnico' => $tecnico,
        'tecnicoAjeno' => $tecnicoAjeno,
        'encargada' => $encargada,
        'admin' => $admin,
        'estado' => $estado,
        'actuadoBase' => $actuadoBase,
    ];
}

it('enmienda resumen_hechos: 200, payload con datos anterior/nuevo y estado procesal sin cambio', function () {
    $s = enmiendaSemilla($this);
    Sanctum::actingAs($s['tecnico'], ['*']);

    $response = $this->postJson("/api/expedientes/{$s['expediente']->id}/enmienda", [
        'campo_modificado' => 'resumen_hechos',
        'valor_nuevo' => 'Hechos corregidos tras la enmienda.',
        'motivo' => 'Corrección de transcripción en el resumen.',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure(['actuado_enmienda_id', 'expediente_actualizado'])
        ->assertJsonPath('expediente_actualizado.resumen_hechos', 'Hechos corregidos tras la enmienda.');

    $actuado = Actuado::findOrFail($response->json('actuado_enmienda_id'));

    expect($actuado->tipoActuado->codigo)->toBe('ACT_ENMIENDA')
        ->and($actuado->contenido['campo_modificado'])->toBe('resumen_hechos')
        ->and($actuado->contenido['datos_anteriores'])->toBe('Hechos originales de la causa.')
        ->and($actuado->contenido['datos_nuevos'])->toBe('Hechos corregidos tras la enmienda.')
        ->and($actuado->contenido['motivo'])->toBe('Corrección de transcripción en el resumen.')
        ->and($actuado->hash_actuado)->toMatch('/^[0-9a-f]{64}$/')
        ->and($actuado->hash_anterior)->toBe($s['actuadoBase']->hash_actuado);

    // No-op de estado: el actuado registra el estado actual y la causa no transiciona.
    expect($actuado->estado_anterior_id)->toBe($s['estado']->id)
        ->and($actuado->estado_nuevo_id)->toBe($s['estado']->id)
        ->and($s['expediente']->fresh()->estado_actual_id)->toBe($s['estado']->id);

    // Sin efectos sobre los relojes procesales.
    expect(Plazo::where('expediente_id', $s['expediente']->id)->count())->toBe(0);
});

it('enmienda de parte: cierra la versión vigente y crea la nueva ligada al actuado', function () {
    $s = enmiendaSemilla($this);
    Sanctum::actingAs($s['tecnico'], ['*']);

    $antes = Parte::where('expediente_id', $s['expediente']->id)
        ->where('tipo', 'DENUNCIADO')
        ->where('es_version_actual', true)
        ->firstOrFail();

    $response = $this->postJson("/api/expedientes/{$s['expediente']->id}/enmienda", [
        'campo_modificado' => 'parte.DENUNCIADO.nombre_completo',
        'valor_nuevo' => 'Empresa Corregida S.R.L.',
        'motivo' => 'Razón social mal transcrita en la carátula.',
    ]);

    $response->assertStatus(200);

    $antes->refresh();
    expect($antes->es_version_actual)->toBeFalse()
        ->and($antes->vigente_hasta)->not->toBeNull()
        ->and($antes->nombre_completo)->toBe('Empresa Original S.A.');

    $nueva = Parte::where('expediente_id', $s['expediente']->id)
        ->where('tipo', 'DENUNCIADO')
        ->where('es_version_actual', true)
        ->get();

    expect($nueva)->toHaveCount(1)
        ->and($nueva->first()->nombre_completo)->toBe('Empresa Corregida S.R.L.')
        ->and($nueva->first()->documento_identidad)->toBe('1234567')
        ->and($nueva->first()->cargo_institucion)->toBe('Gerente General')
        ->and($nueva->first()->actuado_origen_id)->toBe($response->json('actuado_enmienda_id'));
});

it('la Encargada puede enmendar sin asignación activa', function () {
    $s = enmiendaSemilla($this);
    Sanctum::actingAs($s['encargada'], ['*']);

    $this->postJson("/api/expedientes/{$s['expediente']->id}/enmienda", [
        'campo_modificado' => 'parte.DENUNCIANTE.nombre_completo',
        'valor_nuevo' => 'Ciudadano Ejemplo M.',
        'motivo' => 'Ampliación del apellido paterno.',
    ])->assertStatus(200);
});

it('operador sin asignación activa del expediente recibe 403', function () {
    $s = enmiendaSemilla($this);
    Sanctum::actingAs($s['tecnicoAjeno'], ['*']);

    $this->postJson("/api/expedientes/{$s['expediente']->id}/enmienda", [
        'campo_modificado' => 'resumen_hechos',
        'valor_nuevo' => 'Intento sobre causa ajena.',
        'motivo' => 'Intento no autorizado.',
    ])->assertStatus(403);
});

it('el rol ADMIN recibe 403 en la enmienda', function () {
    $s = enmiendaSemilla($this);
    Sanctum::actingAs($s['admin'], ['*']);

    $this->postJson("/api/expedientes/{$s['expediente']->id}/enmienda", [
        'campo_modificado' => 'resumen_hechos',
        'valor_nuevo' => 'Intento administrativo.',
        'motivo' => 'Intento no autorizado.',
    ])->assertStatus(403);
});

it('rechaza campos estructurales fuera de la whitelist', function (string $campo) {
    $s = enmiendaSemilla($this);
    Sanctum::actingAs($s['tecnico'], ['*']);

    $this->postJson("/api/expedientes/{$s['expediente']->id}/enmienda", [
        'campo_modificado' => $campo,
        'valor_nuevo' => 'valor-prohibido',
        'motivo' => 'Intento de alterar dato estructural.',
    ])->assertStatus(422)->assertJsonValidationErrors('campo_modificado');
})->with([
    'via',
    'nurej_code',
    'reglamento_id',
    'fecha_ingreso',
    'estado_actual_id',
]);

it('rechaza el envío sin motivo', function () {
    $s = enmiendaSemilla($this);
    Sanctum::actingAs($s['tecnico'], ['*']);

    $this->postJson("/api/expedientes/{$s['expediente']->id}/enmienda", [
        'campo_modificado' => 'resumen_hechos',
        'valor_nuevo' => 'Valor sin motivo.',
        'motivo' => '',
    ])->assertStatus(422)->assertJsonValidationErrors('motivo');
});

it('rechaza un valor que excede la longitud de la columna de la parte', function () {
    $s = enmiendaSemilla($this);
    Sanctum::actingAs($s['tecnico'], ['*']);

    $this->postJson("/api/expedientes/{$s['expediente']->id}/enmienda", [
        'campo_modificado' => 'parte.DENUNCIADO.documento_identidad',
        'valor_nuevo' => str_repeat('9', 31),
        'motivo' => 'Documento de identidad demasiado largo.',
    ])->assertStatus(422)->assertJsonValidationErrors('valor_nuevo');
});

it('rechaza una enmienda cuyo valor nuevo es idéntico al actual', function () {
    $s = enmiendaSemilla($this);
    Sanctum::actingAs($s['tecnico'], ['*']);

    $this->postJson("/api/expedientes/{$s['expediente']->id}/enmienda", [
        'campo_modificado' => 'resumen_hechos',
        'valor_nuevo' => 'Hechos originales de la causa.',
        'motivo' => 'Sin cambio real de valor.',
    ])->assertStatus(422)->assertJsonValidationErrors('valor_nuevo');
});

it('rechaza enmendar una parte que no tiene exactamente una versión vigente', function () {
    $s = enmiendaSemilla($this);

    Parte::where('expediente_id', $s['expediente']->id)
        ->where('tipo', 'DENUNCIANTE')
        ->update(['es_version_actual' => false, 'vigente_hasta' => now()]);

    Sanctum::actingAs($s['tecnico'], ['*']);

    $this->postJson("/api/expedientes/{$s['expediente']->id}/enmienda", [
        'campo_modificado' => 'parte.DENUNCIANTE.nombre_completo',
        'valor_nuevo' => 'Nombre inexistente.',
        'motivo' => 'No hay versión vigente.',
    ])->assertStatus(422)->assertJsonValidationErrors('campo_modificado');
});

it('bloquea UPDATE y DELETE sobre el actuado de enmienda por inmutabilidad', function () {
    $s = enmiendaSemilla($this);
    Sanctum::actingAs($s['tecnico'], ['*']);

    $response = $this->postJson("/api/expedientes/{$s['expediente']->id}/enmienda", [
        'campo_modificado' => 'resumen_hechos',
        'valor_nuevo' => 'Valor inmutable posterior a la emisión.',
        'motivo' => 'Verificación de cadena de custodia.',
    ])->assertStatus(200);

    $actuado = Actuado::findOrFail($response->json('actuado_enmienda_id'));

    expect(fn () => DB::table('actuados')->where('id', $actuado->id)->update([
        'contenido' => json_encode(['descripcion' => 'manipulado']),
    ]))->toThrow(QueryException::class);

    expect(fn () => DB::table('actuados')->where('id', $actuado->id)->delete())
        ->toThrow(QueryException::class);

    expect(Actuado::find($actuado->id))->not->toBeNull()
        ->and($actuado->refresh()->contenido['datos_nuevos'])->toBe('Valor inmutable posterior a la emisión.');
});

it('exige autenticación para emitir la enmienda', function () {
    $s = enmiendaSemilla($this);

    $this->postJson("/api/expedientes/{$s['expediente']->id}/enmienda", [
        'campo_modificado' => 'resumen_hechos',
        'valor_nuevo' => 'Valor sin sesión.',
        'motivo' => 'Sin autenticación.',
    ])->assertStatus(401);
});
