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
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\actingAs;

function historialOperativoUsuario(string $codigoRol, bool $activo = true): Usuario
{
    $rol = Rol::firstOrCreate(
        ['codigo' => $codigoRol],
        ['nombre' => $codigoRol],
    );

    return Usuario::factory()->create([
        'rol_id' => $rol->id,
        'activo' => $activo,
    ]);
}

function historialOperativoCatalogo(Usuario $usuario, CatalogoEstado $estado): CatalogoActuado
{
    return CatalogoActuado::create([
        'codigo' => fake()->unique()->numerify('ACT_HIST_####'),
        'nombre' => 'Sorteo inicial',
        'fase' => 'RECEPCION',
        'rol_id' => $usuario->rol_id,
        'estado_destino_id' => $estado->id,
        'es_automatico' => false,
        'requiere_adjunto' => false,
    ]);
}

function historialOperativoExpediente(
    Usuario $creador,
    Reglamento $reglamento,
    CatalogoEstado $estado,
    int $secuencia,
    string $resumen = 'Hechos denunciados ante la autoridad municipal.',
): Expediente {
    return Expediente::create([
        'nurej_code' => '2026-HIS-'.str_pad((string) $secuencia, 5, '0', STR_PAD_LEFT),
        'via' => 'TECNICO',
        'naturaleza' => Expediente::NATURALEZA_JURISDICCIONAL,
        'reglamento_id' => $reglamento->id,
        'estado_actual_id' => $estado->id,
        'resumen_hechos' => $resumen,
        'fecha_ingreso' => now()->subMinutes($secuencia),
        'creado_por' => $creador->id,
    ]);
}

function historialOperativoAsignar(
    Expediente $expediente,
    Usuario $usuario,
    CatalogoActuado $catalogo,
    bool $activa = true,
): Actuado {
    $actuado = Actuado::create([
        'expediente_id' => $expediente->id,
        'catalogo_actuado_id' => $catalogo->id,
        'usuario_id' => $usuario->id,
        'estado_nuevo_id' => $expediente->estado_actual_id,
        'contenido' => ['descripcion' => 'Asignación inicial'],
    ])->refresh();

    Asignacion::create([
        'expediente_id' => $expediente->id,
        'usuario_id' => $usuario->id,
        'rol_id' => $usuario->rol_id,
        'actuado_origen_id' => $actuado->id,
        'fecha_asignacion' => now()->subMinutes(5),
        'activa' => $activa,
    ]);

    return $actuado;
}

test('requiere autenticación para consultar el historial operativo', function () {
    $this->getJson('/api/operativo/historial')->assertUnauthorized();
    $this->getJson('/api/operativo/historial/1')->assertUnauthorized();
});

test('los tres roles operativos consultan su historial y detalle', function (string $codigoRol) {
    $usuario = historialOperativoUsuario($codigoRol);
    $reglamento = Reglamento::factory()->create();
    $estado = CatalogoEstado::factory()->create(['codigo' => 'EN_EVALUACION']);
    $expediente = historialOperativoExpediente($usuario, $reglamento, $estado, 1);
    $catalogo = historialOperativoCatalogo($usuario, $estado);
    historialOperativoAsignar($expediente, $usuario, $catalogo);

    Sanctum::actingAs($usuario, ['*']);

    $this->getJson('/api/operativo/historial')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $expediente->id)
        ->assertJsonPath('data.0.asignacion.activa', true)
        ->assertJsonPath('data.0.asignacion.rol.codigo', $codigoRol);

    $this->getJson("/api/operativo/historial/{$expediente->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $expediente->id);
})->with([
    Rol::CODIGO_TECNICO,
    Rol::CODIGO_AUD_JURIDICO,
    Rol::CODIGO_AUD_FINANCIERO,
]);

test('niega el historial operativo a otros roles y a un operativo inactivo', function (string $codigoRol, bool $activo) {
    $usuario = historialOperativoUsuario($codigoRol, $activo);

    Sanctum::actingAs($usuario, ['*']);

    $this->getJson('/api/operativo/historial')->assertForbidden();
})->with([
    [Rol::CODIGO_VENTANILLA, true],
    [Rol::CODIGO_ENCARGADA, true],
    [Rol::CODIGO_ADMIN, true],
    [Rol::CODIGO_TECNICO, false],
]);

test('el historial incluye asignaciones inactivas, pagina de quince en quince y filtra', function () {
    $tecnico = historialOperativoUsuario(Rol::CODIGO_TECNICO);
    $otroTecnico = historialOperativoUsuario(Rol::CODIGO_TECNICO);
    $reglamento = Reglamento::factory()->create();
    $estado = CatalogoEstado::factory()->create(['codigo' => 'EN_EVALUACION']);
    $estadoAdmitido = CatalogoEstado::factory()->create(['codigo' => 'ADMITIDO']);
    $catalogo = historialOperativoCatalogo($tecnico, $estado);

    foreach (range(1, 17) as $secuencia) {
        $estadoExpediente = $secuencia === 9 ? $estadoAdmitido : $estado;
        $expediente = historialOperativoExpediente($tecnico, $reglamento, $estadoExpediente, $secuencia);
        historialOperativoAsignar($expediente, $tecnico, $catalogo, activa: $secuencia % 2 === 0);
    }

    $ajeno = historialOperativoExpediente($otroTecnico, $reglamento, $estado, 18);
    historialOperativoAsignar($ajeno, $otroTecnico, historialOperativoCatalogo($otroTecnico, $estado));

    Sanctum::actingAs($tecnico, ['*']);

    $primeraPagina = $this->getJson('/api/operativo/historial')
        ->assertOk()
        ->assertJsonCount(15, 'data')
        ->assertJsonPath('meta.total', 17)
        ->assertJsonPath('meta.last_page', 2)
        ->assertJsonPath('data.0.nurej_code', '2026-HIS-00001');

    expect(collect($primeraPagina->json('data.data'))->pluck('nurej_code'))
        ->not->toContain($ajeno->nurej_code);

    $this->getJson('/api/operativo/historial?page=2')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.current_page', 2);

    $this->getJson('/api/operativo/historial?busqueda=2026-HIS-00009')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.nurej_code', '2026-HIS-00009');

    $filtrados = $this->getJson('/api/operativo/historial?estado=EN_EVALUACION')
        ->assertOk()
        ->assertJsonPath('meta.total', 16)
        ->assertJsonCount(15, 'data');

    expect(collect($filtrados->json('data.data'))->pluck('nurej_code'))
        ->not->toContain('2026-HIS-00009');
});

test('el detalle de consulta muestra información completa del expediente finalizado', function () {
    $auditor = historialOperativoUsuario(Rol::CODIGO_AUD_FINANCIERO);
    $reglamento = Reglamento::factory()->create();
    $estado = CatalogoEstado::factory()->create(['codigo' => 'CONCLUIDO', 'es_final' => true]);
    $expediente = historialOperativoExpediente($auditor, $reglamento, $estado, 1);
    $catalogo = historialOperativoCatalogo($auditor, $estado);
    $actuado = historialOperativoAsignar($expediente, $auditor, $catalogo, activa: false);

    Parte::create([
        'expediente_id' => $expediente->id,
        'tipo' => 'DENUNCIANTE',
        'nombre_completo' => 'María Pérez',
        'documento_identidad' => '1234567',
        'es_version_actual' => true,
    ]);

    Plazo::create([
        'expediente_id' => $expediente->id,
        'tipo_plazo' => 'EJECUCION',
        'dias_habiles_otorgados' => 10,
        'fecha_inicio' => now()->toDateString(),
        'fecha_limite' => now()->addDays(10)->toDateString(),
        'estado' => 'VIGENTE',
        'actuado_disparador_id' => $actuado->id,
    ]);

    Sanctum::actingAs($auditor, ['*']);

    $respuesta = $this->getJson("/api/operativo/historial/{$expediente->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $expediente->id)
        ->assertJsonPath('data.es_final', true)
        ->assertJsonPath('data.estado_actual.es_final', true)
        ->assertJsonPath('data.partes_vigentes.0.nombre_completo', 'María Pérez')
        ->assertJsonPath('data.plazos.0.tipo_plazo', 'EJECUCION')
        ->assertJsonPath('data.actuados.0.id', $actuado->id)
        ->assertJsonCount(5, 'data.pasos');

    $detalle = $respuesta->json('data.actuados.0');

    expect($detalle)->not->toHaveKey('url')
        ->not->toHaveKey('ruta_descarga')
        ->not->toHaveKey('descargable');
});

test('el detalle en proceso conserva los cinco pasos sin ampliar permisos de escritura', function () {
    $tecnico = historialOperativoUsuario(Rol::CODIGO_TECNICO);
    $reglamento = Reglamento::factory()->create();
    $estado = CatalogoEstado::factory()->create(['codigo' => 'EN_EVALUACION']);
    $expediente = historialOperativoExpediente($tecnico, $reglamento, $estado, 1);
    $catalogo = historialOperativoCatalogo($tecnico, $estado);
    historialOperativoAsignar($expediente, $tecnico, $catalogo, activa: false);

    Sanctum::actingAs($tecnico, ['*']);

    $this->getJson("/api/operativo/historial/{$expediente->id}")
        ->assertOk()
        ->assertJsonPath('data.es_final', false)
        ->assertJsonPath('data.pasos.0.estado', 'completado')
        ->assertJsonPath('data.pasos.1.codigo', 'admisibilidad')
        ->assertJsonPath('data.pasos.4.codigo', 'cierre');

    expect(Gate::forUser($tecnico)->denies('view', $expediente))->toBeTrue()
        ->and(Gate::forUser($tecnico)->denies('crearActuado', [$expediente, $catalogo]))->toBeTrue();
});

test('el detalle del historial niega causas ajenas y nunca asignadas', function () {
    $tecnico = historialOperativoUsuario(Rol::CODIGO_TECNICO);
    $otroTecnico = historialOperativoUsuario(Rol::CODIGO_TECNICO);
    $reglamento = Reglamento::factory()->create();
    $estado = CatalogoEstado::factory()->create(['codigo' => 'EN_EVALUACION']);
    $ajeno = historialOperativoExpediente($otroTecnico, $reglamento, $estado, 1);
    historialOperativoAsignar($ajeno, $otroTecnico, historialOperativoCatalogo($otroTecnico, $estado));

    $nuncaAsignado = historialOperativoExpediente($otroTecnico, $reglamento, $estado, 2);

    Sanctum::actingAs($tecnico, ['*']);

    $this->getJson("/api/operativo/historial/{$ajeno->id}")->assertForbidden();
    $this->getJson("/api/operativo/historial/{$nuncaAsignado->id}")->assertForbidden();
});

test('carga las paginas web del historial solo para los roles operativos', function (string $codigoRol) {
    $usuario = historialOperativoUsuario($codigoRol);
    $reglamento = Reglamento::factory()->create();
    $estado = CatalogoEstado::factory()->create(['codigo' => 'EN_EVALUACION']);
    $expediente = historialOperativoExpediente($usuario, $reglamento, $estado, 1);
    historialOperativoAsignar($expediente, $usuario, historialOperativoCatalogo($usuario, $estado));

    actingAs($usuario)
        ->get('/operativo/historial')
        ->assertOk()
        ->assertSee('Historial de expedientes');

    actingAs($usuario)
        ->get("/operativo/historial/{$expediente->id}")
        ->assertOk()
        ->assertSee('Solo consulta');
})->with([
    Rol::CODIGO_TECNICO,
    Rol::CODIGO_AUD_JURIDICO,
    Rol::CODIGO_AUD_FINANCIERO,
]);

test('niega las paginas web del historial a la ventanilla', function () {
    $ventanilla = historialOperativoUsuario(Rol::CODIGO_VENTANILLA);

    actingAs($ventanilla)->get('/operativo/historial')->assertForbidden();
});
