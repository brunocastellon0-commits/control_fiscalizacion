<?php

use App\Models\Actuado;
use App\Models\CatalogoActuado;
use App\Models\CatalogoEstado;
use App\Models\Expediente;
use App\Models\Parte;
use App\Models\Reglamento;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

function nurejEspSemilla(): array
{
    $rolEncargada = Rol::factory()->create(['codigo' => Rol::CODIGO_ENCARGADA]);
    $rolTecnico = Rol::factory()->create(['codigo' => Rol::CODIGO_TECNICO]);
    $rolAudJuridico = Rol::factory()->create(['codigo' => Rol::CODIGO_AUD_JURIDICO]);

    $encargada = Usuario::factory()->create(['rol_id' => $rolEncargada->id, 'activo' => true]);
    $tecnico = Usuario::factory()->create(['rol_id' => $rolTecnico->id, 'activo' => true]);
    $audJuridico = Usuario::factory()->create(['rol_id' => $rolAudJuridico->id, 'activo' => true]);

    $ac022 = Reglamento::factory()->create(['codigo' => 'AC_022_2018']);
    $ac054 = Reglamento::factory()->create(['codigo' => 'AC_054_2018']);
    $ac055 = Reglamento::factory()->create(['codigo' => 'AC_055_2018']);

    $pendienteSorteo = CatalogoEstado::factory()->create(['codigo' => 'PENDIENTE_SORTEO']);
    $ejecucion = CatalogoEstado::factory()->create(['codigo' => 'EN_EJECUCION']);

    $actCreacionHijo = CatalogoActuado::create([
        'codigo' => 'ACT_CREACION_NUREJ_HIJO',
        'nombre' => 'Creación de NUREJ Hijo',
        'fase' => 'INVESTIGACION',
        'rol_id' => $rolEncargada->id,
        'reglamento_id' => null,
        'estado_origen_id' => null,
        'estado_destino_id' => null,
        'es_automatico' => false,
        'requiere_adjunto' => false,
    ]);

    DB::table('catalogo_actuado_roles')->insert([
        ['catalogo_actuado_id' => $actCreacionHijo->id, 'rol_id' => $rolEncargada->id, 'reglamento_id' => null],
    ]);

    $actInformeConRec = CatalogoActuado::create([
        'codigo' => 'ACT_INFORME_TECNICO_CON_RESPONSABILIDAD_RECOMENDACION',
        'nombre' => 'Informe Final Técnico con Responsabilidad y Recomendación de Auditoría',
        'fase' => 'INVESTIGACION',
        'rol_id' => $rolTecnico->id,
        'reglamento_id' => null,
        'estado_origen_id' => null,
        'estado_destino_id' => null,
        'es_automatico' => false,
        'requiere_adjunto' => false,
    ]);

    $actInformeSinRec = CatalogoActuado::create([
        'codigo' => 'ACT_INFORME_TECNICO_SIN_RESPONSABILIDAD_RECOMENDACION',
        'nombre' => 'Informe Final Técnico sin Responsabilidad y Recomendación de Auditoría',
        'fase' => 'INVESTIGACION',
        'rol_id' => $rolTecnico->id,
        'reglamento_id' => null,
        'estado_origen_id' => null,
        'estado_destino_id' => null,
        'es_automatico' => false,
        'requiere_adjunto' => false,
    ]);

    $actInformeJuridico = CatalogoActuado::create([
        'codigo' => 'ACT_INFORME_AUDITORIA_JURIDICA_CON_RESPONSABILIDAD',
        'nombre' => 'Informe de Auditoría Jurídica con Responsabilidad',
        'fase' => 'INVESTIGACION',
        'rol_id' => $rolAudJuridico->id,
        'reglamento_id' => null,
        'estado_origen_id' => null,
        'estado_destino_id' => null,
        'es_automatico' => false,
        'requiere_adjunto' => false,
    ]);

    return compact(
        'encargada', 'tecnico', 'audJuridico',
        'ac022', 'ac054', 'ac055',
        'pendienteSorteo', 'ejecucion',
        'actCreacionHijo', 'actInformeConRec', 'actInformeSinRec', 'actInformeJuridico',
    );
}

function nurejEspCrearPadre(array $semilla, string $via = 'TECNICO'): Expediente
{
    $reglamento = match ($via) {
        'JURIDICO' => $semilla['ac054'],
        'FINANCIERO' => $semilla['ac055'],
        default => $semilla['ac022'],
    };

    $padre = Expediente::create([
        'nurej_code' => '2026-00001',
        'via' => $via,
        'reglamento_id' => $reglamento->id,
        'estado_actual_id' => $semilla['ejecucion']->id,
        'resumen_hechos' => 'Hechos de la causa padre con detalle suficiente para su derivación.',
        'fecha_ingreso' => now(),
        'creado_por' => $semilla['tecnico']->id,
    ]);

    Parte::create([
        'expediente_id' => $padre->id,
        'tipo' => 'DENUNCIANTE',
        'nombre_completo' => 'Juan Perez Mamani',
        'documento_identidad' => '1234567 La Paz',
        'cargo_institucion' => null,
        'actuado_origen_id' => null,
        'vigente_desde' => now(),
        'vigente_hasta' => null,
        'es_version_actual' => true,
    ]);

    return $padre;
}

function nurejEspRegistrarInforme(Expediente $expediente, CatalogoActuado $catalogo, Usuario $usuario): Actuado
{
    return Actuado::create([
        'expediente_id' => $expediente->id,
        'catalogo_actuado_id' => $catalogo->id,
        'usuario_id' => $usuario->id,
        'estado_nuevo_id' => $expediente->estado_actual_id,
        'contenido' => ['descripcion' => 'Informe registrado para habilitar la derivación (test).'],
    ])->refresh();
}

it('M1: deriva a JURIDICO con reglamento AC_054_2018 y traza via_destino/reglamento_destino_id en el actuado del padre (C8)', function () {
    $semilla = nurejEspSemilla();
    $padre = nurejEspCrearPadre($semilla);
    nurejEspRegistrarInforme($padre, $semilla['actInformeConRec'], $semilla['tecnico']);

    Sanctum::actingAs($semilla['encargada'], ['*']);

    $this->postJson("/api/expedientes/{$padre->id}/nurej-hijo", [
        'motivo' => 'Informe final técnico que recomienda la intervención de la auditoría jurídica.',
        'via_destino' => 'JURIDICO',
    ])->assertCreated()
        ->assertJsonPath('data.nurej_code', '2026-00001-1')
        ->assertJsonPath('data.via', 'JURIDICO')
        ->assertJsonPath('data.reglamento.id', $semilla['ac054']->id)
        ->assertJsonPath('data.estado_actual.codigo', 'PENDIENTE_SORTEO');

    $hijo = Expediente::where('nurej_code', '2026-00001-1')->firstOrFail();

    expect($hijo->nurej_padre_id)->toBe($padre->id)
        ->and($hijo->via)->toBe('JURIDICO')
        ->and($hijo->reglamento_id)->toBe($semilla['ac054']->id)
        ->and($hijo->actuados()->count())->toBe(0)
        ->and($hijo->plazos()->count())->toBe(0)
        ->and($hijo->asignaciones()->count())->toBe(0)
        ->and($hijo->partesVigentes()->count())->toBe(1);

    $padre->refresh();
    expect($padre->estado_actual_id)->toBe($semilla['ejecucion']->id)
        ->and($padre->via)->toBe('TECNICO')
        ->and($padre->reglamento_id)->toBe($semilla['ac022']->id);

    $actuadoDerivacion = $padre->actuados()
        ->whereHas('tipoActuado', fn ($query) => $query->where('codigo', 'ACT_CREACION_NUREJ_HIJO'))
        ->latest('id')
        ->firstOrFail();

    expect($actuadoDerivacion->contenido['expediente_hijo_id'])->toBe($hijo->id)
        ->and($actuadoDerivacion->contenido['via_destino'])->toBe('JURIDICO')
        ->and($actuadoDerivacion->contenido['reglamento_destino_id'])->toBe($semilla['ac054']->id);
});

it('M2: deriva a FINANCIERO con reglamento AC_055_2018 (mapeo server-side)', function () {
    $semilla = nurejEspSemilla();
    $padre = nurejEspCrearPadre($semilla);
    nurejEspRegistrarInforme($padre, $semilla['actInformeConRec'], $semilla['tecnico']);

    Sanctum::actingAs($semilla['encargada'], ['*']);

    $this->postJson("/api/expedientes/{$padre->id}/nurej-hijo", [
        'motivo' => 'Informe final técnico que recomienda la intervención de la auditoría financiera.',
        'via_destino' => 'FINANCIERO',
    ])->assertCreated()
        ->assertJsonPath('data.via', 'FINANCIERO')
        ->assertJsonPath('data.reglamento.id', $semilla['ac055']->id);

    $hijo = Expediente::where('nurej_padre_id', $padre->id)->firstOrFail();

    expect($hijo->via)->toBe('FINANCIERO')
        ->and($hijo->reglamento_id)->toBe($semilla['ac055']->id);
});

it('M3: el informe técnico SIN responsabilidad con recomendación también habilita la derivación', function () {
    $semilla = nurejEspSemilla();
    $padre = nurejEspCrearPadre($semilla);
    nurejEspRegistrarInforme($padre, $semilla['actInformeSinRec'], $semilla['tecnico']);

    Sanctum::actingAs($semilla['encargada'], ['*']);

    $this->postJson("/api/expedientes/{$padre->id}/nurej-hijo", [
        'motivo' => 'Informe sin responsabilidad que detecta irregularidades de fondo y recomienda auditoría.',
        'via_destino' => 'JURIDICO',
    ])->assertCreated()
        ->assertJsonPath('data.via', 'JURIDICO')
        ->assertJsonPath('data.reglamento.id', $semilla['ac054']->id);
});

it('M5: responde 422 cuando la vía destino es TECNICO (combinación prohibida por la matriz)', function () {
    $semilla = nurejEspSemilla();
    $padre = nurejEspCrearPadre($semilla);
    nurejEspRegistrarInforme($padre, $semilla['actInformeConRec'], $semilla['tecnico']);

    Sanctum::actingAs($semilla['encargada'], ['*']);

    $this->postJson("/api/expedientes/{$padre->id}/nurej-hijo", [
        'motivo' => 'Intento de derivación a la misma especialidad que el padre.',
        'via_destino' => 'TECNICO',
    ])->assertStatus(422)
        ->assertJsonValidationErrors('via_destino');

    expect(Expediente::where('nurej_padre_id', $padre->id)->count())->toBe(0);
});

it('M8 (D-P1): responde 422 si el padre no tiene un informe técnico habilitante previo', function () {
    $semilla = nurejEspSemilla();
    $padre = nurejEspCrearPadre($semilla);

    Sanctum::actingAs($semilla['encargada'], ['*']);

    $this->postJson("/api/expedientes/{$padre->id}/nurej-hijo", [
        'motivo' => 'Derivación sin informe previo que la habilite según la matriz.',
        'via_destino' => 'JURIDICO',
    ])->assertStatus(422)
        ->assertJsonValidationErrors('via_destino');

    expect(Expediente::where('nurej_padre_id', $padre->id)->count())->toBe(0)
        ->and($padre->actuados()->count())->toBe(0);
});

it('M6: un padre en vía JURIDICO no habilita ninguna derivación aunque tenga su informe de especialidad', function () {
    $semilla = nurejEspSemilla();
    $padre = nurejEspCrearPadre($semilla, via: 'JURIDICO');
    nurejEspRegistrarInforme($padre, $semilla['actInformeJuridico'], $semilla['audJuridico']);

    Sanctum::actingAs($semilla['encargada'], ['*']);

    $this->postJson("/api/expedientes/{$padre->id}/nurej-hijo", [
        'motivo' => 'Derivación desde una causa jurídica sin fila habilitante en la matriz.',
        'via_destino' => 'FINANCIERO',
    ])->assertStatus(422)
        ->assertJsonValidationErrors('via_destino');

    expect(Expediente::where('nurej_padre_id', $padre->id)->count())->toBe(0);
});

it('valida la sintaxis de via_destino: 422 si falta y 422 con un valor fuera de TECNICO/JURIDICO/FINANCIERO', function () {
    $semilla = nurejEspSemilla();
    $padre = nurejEspCrearPadre($semilla);
    nurejEspRegistrarInforme($padre, $semilla['actInformeConRec'], $semilla['tecnico']);

    Sanctum::actingAs($semilla['encargada'], ['*']);

    $this->postJson("/api/expedientes/{$padre->id}/nurej-hijo", [
        'motivo' => 'Falta la especialidad de destino requerida por el formulario.',
    ])->assertStatus(422)
        ->assertJsonValidationErrors('via_destino');

    $this->postJson("/api/expedientes/{$padre->id}/nurej-hijo", [
        'motivo' => 'Especialidad de destino con un código que no es del catálogo de vías.',
        'via_destino' => 'AUD_JURIDICO',
    ])->assertStatus(422)
        ->assertJsonValidationErrors('via_destino');

    expect(Expediente::where('nurej_padre_id', $padre->id)->count())->toBe(0);
});
