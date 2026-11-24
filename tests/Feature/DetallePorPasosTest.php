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

use function Pest\Laravel\actingAs;

/**
 * @param  array<int, string>  $codigosActuados
 * @return array{expediente: Expediente, usuario: Usuario, estado: CatalogoEstado}
 */
function pasosSemilla(string $codigoEstado, array $codigosActuados = [], string $codigoReglamento = 'AC_022_2018'): array
{
    $rol = Rol::factory()->create(['codigo' => Rol::CODIGO_AUD_JURIDICO]);
    $usuario = Usuario::factory()->create(['rol_id' => $rol->id, 'activo' => true]);
    $reglamento = Reglamento::factory()->create(['codigo' => $codigoReglamento]);
    $estado = CatalogoEstado::factory()->create(['codigo' => $codigoEstado]);

    $catalogoOrigen = CatalogoActuado::create([
        'codigo' => 'ACT_ORIGEN_'.fake()->unique()->numberBetween(1000, 9999),
        'nombre' => 'Actuado de origen',
        'fase' => 'PRUEBA',
        'rol_id' => $rol->id,
        'estado_destino_id' => $estado->id,
        'es_automatico' => false,
        'requiere_adjunto' => false,
    ]);

    $expediente = Expediente::create([
        'nurej_code' => 'PASOS-'.fake()->unique()->numberBetween(10000, 99999),
        'via' => 'JURIDICO',
        'reglamento_id' => $reglamento->id,
        'estado_actual_id' => $estado->id,
        'fecha_ingreso' => now(),
        'creado_por' => $usuario->id,
    ]);

    $actuadoOrigen = Actuado::create([
        'expediente_id' => $expediente->id,
        'catalogo_actuado_id' => $catalogoOrigen->id,
        'usuario_id' => $usuario->id,
        'estado_nuevo_id' => $estado->id,
        'contenido' => ['descripcion' => 'Asignación de prueba'],
    ])->refresh();

    Asignacion::create([
        'expediente_id' => $expediente->id,
        'usuario_id' => $usuario->id,
        'rol_id' => $rol->id,
        'actuado_origen_id' => $actuadoOrigen->id,
        'fecha_asignacion' => now(),
        'activa' => true,
    ]);

    foreach ($codigosActuados as $codigo) {
        $catalogo = CatalogoActuado::create([
            'codigo' => $codigo,
            'nombre' => $codigo,
            'fase' => 'PRUEBA',
            'rol_id' => $rol->id,
            'estado_destino_id' => $estado->id,
            'es_automatico' => false,
            'requiere_adjunto' => false,
        ]);

        Actuado::create([
            'expediente_id' => $expediente->id,
            'catalogo_actuado_id' => $catalogo->id,
            'usuario_id' => $usuario->id,
            'estado_nuevo_id' => $estado->id,
            'contenido' => ['descripcion' => $codigo],
        ]);
    }

    return compact('expediente', 'usuario', 'estado');
}

it('bloquea los pasos posteriores mientras el expediente está en evaluación', function () {
    ['expediente' => $expediente, 'usuario' => $usuario] = pasosSemilla('EN_EVALUACION');

    Sanctum::actingAs($usuario, ['*']);

    $pasos = $this->getJson('/api/expedientes/'.$expediente->id)
        ->assertOk()
        ->json('data.pasos');

    expect(array_column($pasos, 'codigo'))->toBe(['recepcion', 'admisibilidad', 'planificacion', 'ejecucion', 'cierre'])
        ->and($pasos[0]['descripcion'])->toBe('Solo consulta')
        ->and(array_column($pasos, 'estado'))->toBe(['completado', 'activo', 'bloqueado', 'bloqueado', 'bloqueado'])
        ->and($pasos[2]['mensaje_bloqueo'])->toContain('admisibilidad')
        ->and($this->getJson('/api/expedientes/'.$expediente->id)->json('data.estado_actual.es_final'))->toBeFalse()
        ->and($pasos[3]['mensaje_bloqueo'])->toContain('cronograma')
        ->and($pasos[4]['mensaje_bloqueo'])->toContain('informe final');
});

it('habilita la ejecución cuando la planificación ya fue completada', function () {
    ['expediente' => $expediente, 'usuario' => $usuario] = pasosSemilla('EN_EJECUCION', [
        'ACT_ADMISION',
        'ACT_CRONOGRAMA_TRABAJO',
    ]);

    Sanctum::actingAs($usuario, ['*']);

    $pasos = $this->getJson('/api/expedientes/'.$expediente->id)
        ->assertOk()
        ->json('data.pasos');

    expect(array_column($pasos, 'estado'))->toBe(['completado', 'completado', 'completado', 'activo', 'bloqueado']);
});

it('habilita el cierre cuando el informe final ya fue registrado', function () {
    ['expediente' => $expediente, 'usuario' => $usuario] = pasosSemilla('PENDIENTE_VISTO_BUENO_FINAL', [
        'ACT_ADMISION',
        'ACT_VISTO_BUENO_PLANIFICACION',
        'ACT_INFORME_FINAL',
    ]);

    Sanctum::actingAs($usuario, ['*']);

    $pasos = $this->getJson('/api/expedientes/'.$expediente->id)
        ->assertOk()
        ->json('data.pasos');

    expect(array_column($pasos, 'estado'))->toBe(['completado', 'completado', 'completado', 'completado', 'activo']);
});

it('muestra los cinco pasos, el aviso de bloqueo y los formularios de cada etapa en la vista', function () {
    ['expediente' => $expediente, 'usuario' => $usuario] = pasosSemilla('EN_PLANIFICACION', ['ACT_ADMISION']);

    actingAs($usuario)
        ->get('/expedientes/'.$expediente->id)
        ->assertOk()
        ->assertSee('Pasos del expediente')
        ->assertSee('Secciones del expediente')
        ->assertSee('Carátula del expediente')
        ->assertSee('Progreso del trámite')
        ->assertSee('aria-current')
        ->assertSee('puedeAvanzarPaso()')
        ->assertSee('Complete y guarde el trabajo de esta etapa para habilitar el avance.')
        ->assertSee('Ver cadena de custodia')
        ->assertSee('group-hover:opacity-100')
        ->assertSee('Información general')
        ->assertSee('Partes Procesales')
        ->assertSee('Hechos del caso')
        ->assertSee('Estado del expediente')
        ->assertSee('Historial de movimientos')
        ->assertSee('Evaluación de admisibilidad')
        ->assertSee('Revise los requisitos según el reglamento de la causa.')
        ->assertDontSee('Objetivo del paso')
        ->assertSee('Planificación')
        ->assertSee('Ejecución de la investigación')
        ->assertSee('Cierre del expediente')
        ->assertSee('seleccionarPaso')
        ->assertSee('paso.mensaje_bloqueo')
        ->assertSee('/planificacion')
        ->assertSee('/ampliacion')
        ->assertSee('descargos/comunicar')
        ->assertSee('/cierre/visto-bueno')
        ->assertDontSee('Ver todos los plazos')
        ->assertDontSee('Etapa actual:')
        ->assertDontSee('irAEtapaActual');
});

it('muestra los pasos completados como solo consulta y bloquea sus acciones', function () {
    ['expediente' => $expediente, 'usuario' => $usuario] = pasosSemilla('EN_EJECUCION', [
        'ACT_ADMISION',
        'ACT_CRONOGRAMA_TRABAJO',
    ]);

    actingAs($usuario)
        ->get('/expedientes/'.$expediente->id)
        ->assertOk()
        ->assertSee('pasoEditable()', false)
        ->assertSee("pasoCompletado('admisibilidad')", false)
        ->assertSee('Evaluación registrada. Los requisitos se muestran tal como se guardaron y no pueden modificarse.')
        ->assertSee('Completado, solo consulta.')
        ->assertSee('sincronizarPasoActivo({ forzar: true })', false)
        ->assertDontSee(":disabled=\"pasoActivo === 'recepcion' || estadoFinal()\"", false);
});

it('deniega 403 al operador sin asignación que intenta usar un formulario de paso bloqueado', function () {
    ['expediente' => $expediente] = pasosSemilla('EN_PLANIFICACION', ['ACT_ADMISION']);

    $otroRol = Rol::factory()->create(['codigo' => Rol::CODIGO_AUD_FINANCIERO]);
    $sinAsignacion = Usuario::factory()->create(['rol_id' => $otroRol->id, 'activo' => true]);

    Sanctum::actingAs($sinAsignacion, ['*']);

    $this->postJson('/api/expedientes/'.$expediente->id.'/planificacion', [
        'descripcion' => 'Cronograma de prueba con descripción suficiente.',
    ])->assertForbidden();
});

it('muestra la ruta de navegación con el NUREJ como último nivel no clicable', function () {
    ['expediente' => $expediente, 'usuario' => $usuario] = pasosSemilla('EN_PLANIFICACION', ['ACT_ADMISION']);

    actingAs($usuario)
        ->get('/expedientes/'.$expediente->id)
        ->assertOk()
        ->assertSee('Inicio')
        ->assertSee('Expedientes')
        ->assertSee($expediente->nurej_code)
        ->assertSee('aria-current="page"', false);
});

it('muestra el botón para avanzar al siguiente paso al pie del detalle', function () {
    ['expediente' => $expediente, 'usuario' => $usuario] = pasosSemilla('EN_PLANIFICACION', ['ACT_ADMISION']);

    actingAs($usuario)
        ->get('/expedientes/'.$expediente->id)
        ->assertOk()
        ->assertSee('Siguiente paso')
        ->assertSee('irASiguientePaso')
        ->assertSee('cumple: !!r._cumplido', false)
        ->assertDontSee('Observación (si no se cumple)');
});
