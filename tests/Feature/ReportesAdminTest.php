<?php

use App\Models\Actuado;
use App\Models\Asignacion;
use App\Models\CatalogoActuado;
use App\Models\CatalogoEstado;
use App\Models\Expediente;
use App\Models\Feriado;
use App\Models\Plazo;
use App\Models\Reglamento;
use App\Models\Rol;
use App\Models\Usuario;
use Laravel\Sanctum\Sanctum;

function raSemilla(): array
{
    $rolAdmin = Rol::factory()->create(['codigo' => Rol::CODIGO_ADMIN, 'nombre' => 'Administrador']);
    $rolTecnico = Rol::factory()->create(['codigo' => Rol::CODIGO_TECNICO, 'nombre' => 'Técnico']);
    $rolEncargada = Rol::factory()->create(['codigo' => Rol::CODIGO_ENCARGADA, 'nombre' => 'Encargada']);

    $admin = Usuario::factory()->create(['rol_id' => $rolAdmin->id, 'activo' => true]);
    $adminInactivo = Usuario::factory()->create(['rol_id' => $rolAdmin->id, 'activo' => false]);
    $tecnico = Usuario::factory()->create(['rol_id' => $rolTecnico->id, 'activo' => true]);

    $ac022 = Reglamento::factory()->create(['codigo' => 'AC_022_2018']);

    $pendienteSorteo = CatalogoEstado::factory()->create(['codigo' => 'PENDIENTE_SORTEO']);
    $evaluacion = CatalogoEstado::factory()->create(['codigo' => 'EN_EVALUACION']);

    $catalogoOrigen = CatalogoActuado::create([
        'codigo' => 'ACT_TEST_ORIGEN',
        'nombre' => 'Actuado de origen para asignación (test)',
        'fase' => 'INVESTIGACION',
        'rol_id' => $rolTecnico->id,
        'reglamento_id' => null,
        'estado_origen_id' => null,
        'estado_destino_id' => null,
        'es_automatico' => false,
        'requiere_adjunto' => false,
    ]);

    return compact(
        'rolAdmin', 'rolTecnico', 'rolEncargada',
        'admin', 'adminInactivo', 'tecnico',
        'ac022', 'pendienteSorteo', 'evaluacion', 'catalogoOrigen',
    );
}

function raExpediente(array $s, string $nurej, string $via, int $estadoId): Expediente
{
    return Expediente::create([
        'nurej_code' => $nurej,
        'via' => $via,
        'reglamento_id' => $s['ac022']->id,
        'estado_actual_id' => $estadoId,
        'resumen_hechos' => 'Hechos del expediente para el reporte de monitoreo (test).',
        'fecha_ingreso' => now(),
        'creado_por' => $s['tecnico']->id,
    ]);
}

function raAsignar(array $s, Expediente $expediente, Usuario $usuario): void
{
    $actuadoOrigen = Actuado::create([
        'expediente_id' => $expediente->id,
        'catalogo_actuado_id' => $s['catalogoOrigen']->id,
        'usuario_id' => $s['admin']->id,
        'estado_nuevo_id' => $expediente->estado_actual_id,
        'contenido' => ['descripcion' => 'Asignación de bandeja (test reportes)'],
    ])->refresh();

    Asignacion::create([
        'expediente_id' => $expediente->id,
        'usuario_id' => $usuario->id,
        'rol_id' => $usuario->rol_id,
        'actuado_origen_id' => $actuadoOrigen->id,
        'fecha_asignacion' => now(),
        'activa' => true,
    ]);
}

function raPoblar(): array
{
    $s = raSemilla();

    // Pendiente de sorteo, sin asignar, vía TECNICO.
    raExpediente($s, '2026-00001', 'TECNICO', $s['pendienteSorteo']->id);

    // En evaluación, asignado al Técnico, con plazo vencido (fuera de plazo).
    $conPlazo = raExpediente($s, '2026-00002', 'JURIDICO', $s['evaluacion']->id);
    raAsignar($s, $conPlazo, $s['tecnico']);
    Plazo::create([
        'expediente_id' => $conPlazo->id,
        'tipo_plazo' => 'EVALUACION',
        'dias_habiles_otorgados' => 5,
        'fecha_inicio' => now()->subDays(7),
        'fecha_limite' => now()->subDay(),
        'estado' => 'VIGENTE',
        'actuado_disparador_id' => $conPlazo->actuados()->latest('id')->value('id'),
    ]);

    // En evaluación, sin asignar, vía FINANCIERO.
    raExpediente($s, '2026-00003', 'FINANCIERO', $s['evaluacion']->id);

    Feriado::create([
        'fecha' => now()->addDays(15)->toDateString(),
        'descripcion' => 'Feriado de prueba para reportes (test)',
        'ambito' => 'NACIONAL',
    ]);

    return $s;
}

it('prohíbe el dashboard y el monitoreo a roles que no son ADMIN', function (string $rolCodigo) {
    $s = raSemilla();

    Sanctum::actingAs(
        Usuario::factory()->create(['rol_id' => Rol::where('codigo', $rolCodigo)->first()->id, 'activo' => true]),
        ['*'],
    );

    $this->getJson('/api/admin/dashboard')->assertForbidden();
    $this->getJson('/api/admin/monitoreo')->assertForbidden();
})->with([
    Rol::CODIGO_TECNICO,
    Rol::CODIGO_ENCARGADA,
]);

it('el dashboard del ADMIN refleja totales verificables: usuarios, expedientes, semáforo, feriados y fuera de plazo', function () {
    $s = raPoblar();

    Sanctum::actingAs($s['admin'], ['*']);

    $respuesta = $this->getJson('/api/admin/dashboard')->assertOk();

    expect($respuesta->json('usuarios.total'))->toBe(Usuario::count())
        ->and($respuesta->json('usuarios.activos'))->toBe(Usuario::where('activo', true)->count())
        ->and($respuesta->json('usuarios.inactivos'))->toBe(Usuario::where('activo', false)->count())
        ->and($respuesta->json('expedientes.total'))->toBe(Expediente::count())
        ->and($respuesta->json('expedientes.sin_asignar'))->toBe(
            Expediente::whereDoesntHave('asignacionActiva')->count(),
        )
        ->and($respuesta->json('asignaciones.activas'))->toBe(
            Asignacion::where('activa', true)->count(),
        );

    $porEstado = collect($respuesta->json('expedientes.por_estado'));
    expect($porEstado->firstWhere('codigo', 'PENDIENTE_SORTEO')['total'])->toBe(1)
        ->and($porEstado->firstWhere('codigo', 'EN_EVALUACION')['total'])->toBe(2);

    $porVia = collect($respuesta->json('expedientes.por_via'));
    expect($porVia->firstWhere('via', 'TECNICO')['total'])->toBe(1)
        ->and($porVia->firstWhere('via', 'JURIDICO')['total'])->toBe(1)
        ->and($porVia->firstWhere('via', 'FINANCIERO')['total'])->toBe(1);

    expect($respuesta->json('semaforo.total_fuera_de_plazo'))->toBe(1)
        ->and($respuesta->json('expedientes_fuera_de_plazo.0.nurej_code'))->toBe('2026-00002')
        ->and($respuesta->json('feriados_proximos.0.fecha'))->toBe(now()->addDays(15)->toDateString())
        ->and($respuesta->json('seguridad.intentos_fallidos_24h'))->toBe(0);
});

it('el monitoreo del ADMIN devuelve resumen coherente y filtra por estado, responsable y búsqueda', function () {
    $s = raPoblar();

    Sanctum::actingAs($s['admin'], ['*']);

    $respuesta = $this->getJson('/api/admin/monitoreo')->assertOk();

    expect($respuesta->json('resumen'))->toBe([
        'total' => 3,
        'sin_asignar' => 2,
        'en_tramite' => 0,
        'por_vencer' => 0,
        'fuera_plazo' => 1,
    ]);

    $porEstado = $this->getJson('/api/admin/monitoreo?estado=FUERA_DE_PLAZO')->assertOk();
    expect($porEstado->json('data'))->toHaveCount(1)
        ->and($porEstado->json('data.0.nurej'))->toBe('2026-00002')
        ->and($porEstado->json('resumen.total'))->toBe(1);

    $porResponsable = $this->getJson('/api/admin/monitoreo?responsable=TECNICO')->assertOk();
    expect($porResponsable->json('data'))->toHaveCount(1)
        ->and($porResponsable->json('data.0.responsable.id'))->toBe($s['tecnico']->id);

    $porBuscar = $this->getJson('/api/admin/monitoreo?buscar=2026-00001')->assertOk();
    expect($porBuscar->json('data'))->toHaveCount(1)
        ->and($porBuscar->json('data.0.nurej'))->toBe('2026-00001');
});

it('prohíbe el dashboard y el monitoreo a un ADMIN inactivo (coherencia con EnsureAdmin y UsuarioPolicy)', function () {
    $s = raSemilla();

    Sanctum::actingAs($s['adminInactivo'], ['*']);

    $this->getJson('/api/admin/dashboard')->assertForbidden();
    $this->getJson('/api/admin/monitoreo')->assertForbidden();
});
