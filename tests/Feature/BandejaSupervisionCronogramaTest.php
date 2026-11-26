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

function bandejaSupervisionSemilla(): array
{
    $rolEncargada = Rol::factory()->create(['codigo' => Rol::CODIGO_ENCARGADA]);
    $rolTecnico = Rol::factory()->create(['codigo' => Rol::CODIGO_TECNICO]);

    $encargada = Usuario::factory()->create(['rol_id' => $rolEncargada->id, 'activo' => true]);
    $tecnico = Usuario::factory()->create(['rol_id' => $rolTecnico->id, 'activo' => true]);

    $reglamento = Reglamento::factory()->create(['codigo' => 'AC_022_2018']);
    $planificacion = CatalogoEstado::factory()->create(['codigo' => 'EN_PLANIFICACION']);
    $ejecucion = CatalogoEstado::factory()->create(['codigo' => 'EN_EJECUCION']);

    $cronograma = CatalogoActuado::create([
        'codigo' => 'ACT_CRONOGRAMA_TRABAJO',
        'nombre' => 'Cronograma de Trabajo',
        'fase' => 'PLANIFICACION',
        'rol_id' => $rolTecnico->id,
        'reglamento_id' => null,
        'estado_origen_id' => $planificacion->id,
        'estado_destino_id' => $ejecucion->id,
        'es_automatico' => false,
        'requiere_adjunto' => true,
    ]);

    return compact('encargada', 'tecnico', 'reglamento', 'planificacion', 'ejecucion', 'cronograma');
}

function bandejaSupervisionCrearCronograma(array $semilla, string $nurej): array
{
    $expediente = Expediente::create([
        'nurej_code' => $nurej,
        'via' => 'INGRESO_VERBAL',
        'naturaleza' => Expediente::NATURALEZA_JURISDICCIONAL,
        'reglamento_id' => $semilla['reglamento']->id,
        'estado_actual_id' => $semilla['ejecucion']->id,
        'fecha_ingreso' => now(),
        'creado_por' => $semilla['tecnico']->id,
    ]);

    $actuado = Actuado::create([
        'expediente_id' => $expediente->id,
        'catalogo_actuado_id' => $semilla['cronograma']->id,
        'usuario_id' => $semilla['tecnico']->id,
        'estado_nuevo_id' => $semilla['ejecucion']->id,
        'contenido' => ['descripcion' => 'Cronograma cargado por el Técnico (test).'],
    ]);

    Asignacion::create([
        'expediente_id' => $expediente->id,
        'usuario_id' => $semilla['tecnico']->id,
        'rol_id' => $semilla['tecnico']->rol_id,
        'actuado_origen_id' => $actuado->id,
        'fecha_asignacion' => now(),
        'activa' => true,
    ]);

    return [$expediente, $actuado];
}

test('la Encargada ve el cronograma cargado como aviso informativo en su bandeja', function () {
    $semilla = bandejaSupervisionSemilla();
    [$expediente, $actuado] = bandejaSupervisionCrearCronograma($semilla, 'EXP-SUP-0001');

    Sanctum::actingAs($semilla['encargada'], ['*']);

    $this->getJson('/api/encargada/bandeja-supervision')
        ->assertOk()
        ->assertJsonPath('data.0.id', $actuado->id)
        ->assertJsonPath('data.0.actuado_id', $actuado->id)
        ->assertJsonPath('data.0.expediente_id', $expediente->id)
        ->assertJsonPath('data.0.nurej', 'EXP-SUP-0001')
        ->assertJsonPath('data.0.descripcion', 'Cronograma cargado por el Técnico (test).')
        ->assertJsonPath('data.0.categoria', 'cronogramas')
        ->assertJsonPath('data.0.requiere_accion', false)
        ->assertJsonPath('data.0.accion_pendiente', 'Cronograma cargado (informativo)')
        ->assertJsonPath('data.0.asignado_a', trim($semilla['tecnico']->nombres.' '.$semilla['tecnico']->apellidos))
        ->assertJsonPath('meta.total', 1);
});

test('el Técnico no puede consultar la bandeja de supervisión de la Encargada', function () {
    $semilla = bandejaSupervisionSemilla();
    bandejaSupervisionCrearCronograma($semilla, 'EXP-SUP-0002');

    Sanctum::actingAs($semilla['tecnico'], ['*']);

    $this->getJson('/api/encargada/bandeja-supervision')->assertForbidden();
});

test('el filtro por planificación muestra los cronogramas y otros filtros devuelven lista vacía', function () {
    $semilla = bandejaSupervisionSemilla();
    [$expediente] = bandejaSupervisionCrearCronograma($semilla, 'EXP-SUP-0003');

    Sanctum::actingAs($semilla['encargada'], ['*']);

    $this->getJson('/api/encargada/bandeja-supervision?categoria=planificacion')
        ->assertOk()
        ->assertJsonPath('data.0.expediente_id', $expediente->id);

    $this->getJson('/api/encargada/bandeja-supervision?categoria=cronogramas')
        ->assertOk()
        ->assertJsonPath('data.0.expediente_id', $expediente->id);

    $this->getJson('/api/encargada/bandeja-supervision?categoria=ampliacion')
        ->assertOk()
        ->assertJsonPath('data', [])
        ->assertJsonPath('meta.total', 0);
});

test('la bandeja de supervisión pagina los cronogramas de quince en quince', function () {
    $semilla = bandejaSupervisionSemilla();

    for ($i = 1; $i <= 16; $i++) {
        bandejaSupervisionCrearCronograma($semilla, sprintf('EXP-SUP-%04d', $i));
    }

    Sanctum::actingAs($semilla['encargada'], ['*']);

    $respuesta = $this->getJson('/api/encargada/bandeja-supervision')->assertOk();

    expect($respuesta->json('data'))->toHaveCount(15)
        ->and($respuesta->json('meta.total'))->toBe(16)
        ->and($respuesta->json('meta.per_page'))->toBe(15);
});

function bandejaSupervisionCrearCatalogoInformativo(array $semilla, string $codigo): CatalogoActuado
{
    return CatalogoActuado::create([
        'codigo' => $codigo,
        'nombre' => $codigo,
        'fase' => 'ADMISIBILIDAD',
        'rol_id' => $semilla['tecnico']->rol_id,
        'reglamento_id' => null,
        'estado_origen_id' => $semilla['planificacion']->id,
        'estado_destino_id' => $semilla['ejecucion']->id,
        'es_automatico' => false,
        'requiere_adjunto' => false,
    ]);
}

function bandejaSupervisionCrearActuadoInformativo(array $semilla, CatalogoActuado $catalogo, string $nurej, string $descripcion): array
{
    $expediente = Expediente::create([
        'nurej_code' => $nurej,
        'via' => 'INGRESO_VERBAL',
        'naturaleza' => Expediente::NATURALEZA_JURISDICCIONAL,
        'reglamento_id' => $semilla['reglamento']->id,
        'estado_actual_id' => $semilla['ejecucion']->id,
        'fecha_ingreso' => now(),
        'creado_por' => $semilla['tecnico']->id,
    ]);

    $actuado = Actuado::create([
        'expediente_id' => $expediente->id,
        'catalogo_actuado_id' => $catalogo->id,
        'usuario_id' => $semilla['tecnico']->id,
        'estado_nuevo_id' => $semilla['ejecucion']->id,
        'contenido' => ['descripcion' => $descripcion],
    ]);

    Asignacion::create([
        'expediente_id' => $expediente->id,
        'usuario_id' => $semilla['tecnico']->id,
        'rol_id' => $semilla['tecnico']->rol_id,
        'actuado_origen_id' => $actuado->id,
        'fecha_asignacion' => now(),
        'activa' => true,
    ]);

    return [$expediente, $actuado];
}

test('la Encargada marca revisado sin alterar el estado del expediente', function () {
    $semilla = bandejaSupervisionSemilla();
    [$expediente, $actuado] = bandejaSupervisionCrearCronograma($semilla, 'EXP-SUP-0101');

    Sanctum::actingAs($semilla['encargada'], ['*']);

    $this->postJson('/api/encargada/bandeja-supervision/marcar-revisado', [
        'actuado_id' => $actuado->id,
    ])->assertOk()
        ->assertJsonPath('actuado_id', $actuado->id)
        ->assertJsonPath('revisado', true)
        ->assertJsonPath('revisado_por', trim($semilla['encargada']->nombres.' '.$semilla['encargada']->apellidos));

    $actuado->refresh();
    expect($actuado->revisado_por)->toBe($semilla['encargada']->id)
        ->and($actuado->revisado_en)->not->toBeNull();

    $this->getJson('/api/encargada/bandeja-supervision')
        ->assertJsonPath('data.0.revisado', true)
        ->assertJsonPath('data.0.revisado_por', trim($semilla['encargada']->nombres.' '.$semilla['encargada']->apellidos));

    expect($expediente->fresh()->estado_actual_id)->toBe($semilla['ejecucion']->id);
});

test('el Técnico no puede marcar revisado un actuado de supervisión', function () {
    $semilla = bandejaSupervisionSemilla();
    [, $actuado] = bandejaSupervisionCrearCronograma($semilla, 'EXP-SUP-0102');

    Sanctum::actingAs($semilla['tecnico'], ['*']);

    $this->postJson('/api/encargada/bandeja-supervision/marcar-revisado', [
        'actuado_id' => $actuado->id,
    ])->assertForbidden();
});

test('la bandeja agrupa avisos de observación, rechazo, subsanación e impugnación', function () {
    $semilla = bandejaSupervisionSemilla();

    $casos = [
        ['ACT_OBSERVACION', 'observacion', 'Aviso de observación (test).'],
        ['ACT_RECHAZO', 'rechazo', 'Aviso de rechazo (test).'],
        ['ACT_SUBSANACION_ACEPTADA', 'subsanacion', 'Subsanación aceptada (test).'],
        ['ACT_REMITIR_IMPUGNACION', 'impugnacion', 'Impugnación remitida (test).'],
        ['ACT_RESOLUCION_RATIFICA_RECHAZO', 'impugnacion', 'Rechazo ratificado (test).'],
    ];

    foreach ($casos as $i => [$codigo, $categoria, $descripcion]) {
        $catalogo = bandejaSupervisionCrearCatalogoInformativo($semilla, $codigo);
        bandejaSupervisionCrearActuadoInformativo(
            $semilla,
            $catalogo,
            sprintf('EXP-SUP-01%02d', $i + 3),
            $descripcion,
        );
    }

    Sanctum::actingAs($semilla['encargada'], ['*']);

    foreach ($casos as [$codigo, $categoria, $descripcion]) {
        $respuesta = $this->getJson('/api/encargada/bandeja-supervision?categoria='.$categoria)
            ->assertOk();

        $elementos = $respuesta->json('data');
        $descripciones = collect($elementos)->pluck('descripcion')->all();

        expect($descripciones)->toContain($descripcion)
            ->and(collect($elementos)->every(fn ($item) => $item['categoria'] === $categoria && $item['requiere_accion'] === false))->toBeTrue();
    }

    $this->getJson('/api/encargada/bandeja-supervision')
        ->assertOk()
        ->assertJsonPath('meta.total', 5);
});
