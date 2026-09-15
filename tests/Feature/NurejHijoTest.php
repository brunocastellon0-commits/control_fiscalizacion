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
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

function nurejHijoSemilla(): array
{
    $rolEncargada = Rol::factory()->create(['codigo' => Rol::CODIGO_ENCARGADA]);
    $rolTecnico = Rol::factory()->create(['codigo' => Rol::CODIGO_TECNICO]);
    $rolAudJuridico = Rol::factory()->create(['codigo' => Rol::CODIGO_AUD_JURIDICO]);

    $encargada = Usuario::factory()->create(['rol_id' => $rolEncargada->id, 'activo' => true]);
    $tecnico = Usuario::factory()->create(['rol_id' => $rolTecnico->id, 'activo' => true]);
    $audJuridico = Usuario::factory()->create(['rol_id' => $rolAudJuridico->id, 'activo' => true]);

    $ac022 = Reglamento::factory()->create(['codigo' => 'AC_022_2018']);

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
        'descripcion' => 'La Encargada deriva un NUREJ Hijo a partir de un expediente padre',
    ]);

    DB::table('catalogo_actuado_roles')->insert([
        ['catalogo_actuado_id' => $actCreacionHijo->id, 'rol_id' => $rolEncargada->id, 'reglamento_id' => null],
    ]);

    return compact(
        'encargada', 'tecnico', 'audJuridico',
        'ac022',
        'pendienteSorteo', 'ejecucion',
        'actCreacionHijo',
    );
}

function nurejHijoCrearPadre(array $semilla, bool $conHistorial = false): Expediente
{
    $padre = Expediente::create([
        'nurej_code' => '2026-00001',
        'via' => 'TECNICO',
        'reglamento_id' => $semilla['ac022']->id,
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

    Parte::create([
        'expediente_id' => $padre->id,
        'tipo' => 'DENUNCIADO',
        'nombre_completo' => 'Maria Quispe Condori',
        'documento_identidad' => '7654321 Cochabamba',
        'cargo_institucion' => 'Funcionaria Municipal',
        'actuado_origen_id' => null,
        'vigente_desde' => now(),
        'vigente_hasta' => null,
        'es_version_actual' => true,
    ]);

    if ($conHistorial) {
        $actuadoOrigen = Actuado::create([
            'expediente_id' => $padre->id,
            'catalogo_actuado_id' => $semilla['actCreacionHijo']->id,
            'usuario_id' => $semilla['tecnico']->id,
            'estado_nuevo_id' => $padre->estado_actual_id,
            'contenido' => ['descripcion' => 'Actuado previo de la semilla'],
        ])->refresh();

        Asignacion::create([
            'expediente_id' => $padre->id,
            'usuario_id' => $semilla['tecnico']->id,
            'rol_id' => $semilla['tecnico']->rol_id,
            'actuado_origen_id' => $actuadoOrigen->id,
            'fecha_asignacion' => now(),
            'activa' => true,
        ]);

        Plazo::create([
            'expediente_id' => $padre->id,
            'tipo_plazo' => 'EJECUCION',
            'parametro_plazo_id' => null,
            'dias_habiles_otorgados' => 10,
            'fecha_inicio' => now(),
            'fecha_limite' => now()->addDays(10),
            'estado' => 'VIGENTE',
            'actuado_disparador_id' => $actuadoOrigen->id,
        ]);
    }

    return $padre;
}

it('la Encargada deriva un NUREJ Hijo: emite ACT_CREACION_NUREJ_HIJO, padre conserva estado', function () {
    $semilla = nurejHijoSemilla();
    $padre = nurejHijoCrearPadre($semilla);

    Sanctum::actingAs($semilla['encargada'], ['*']);

    $this->postJson("/api/expedientes/{$padre->id}/nurej-hijo", [
        'motivo' => 'El informe final del operador recomienda auditoría especializada sobre los hallazgos.',
    ])->assertCreated()
        ->assertJsonPath('data.nurej_code', '2026-00001-1')
        ->assertJsonPath('data.nurej_padre_id', $padre->id)
        ->assertJsonPath('data.via', 'TECNICO')
        ->assertJsonPath('data.estado_actual.codigo', 'PENDIENTE_SORTEO')
        ->assertJsonPath('data.reglamento.id', $semilla['ac022']->id)
        ->assertJsonPath('data.resumen_hechos', $padre->resumen_hechos);

    $hijo = Expediente::where('nurej_code', '2026-00001-1')->first();
    expect($hijo)->not->toBeNull()
        ->and($hijo->nurej_padre_id)->toBe($padre->id)
        ->and($hijo->estado_actual_id)->toBe($semilla['pendienteSorteo']->id)
        ->and($hijo->via)->toBe('TECNICO')
        ->and($hijo->reglamento_id)->toBe($semilla['ac022']->id)
        ->and($hijo->creado_por)->toBe($semilla['encargada']->id);

    $partesHijo = $hijo->partesVigentes()->get();
    expect($partesHijo)->toHaveCount(2)
        ->and($partesHijo->first()->nombre_completo)->toBe('Juan Perez Mamani')
        ->and($partesHijo->last()->nombre_completo)->toBe('Maria Quispe Condori');

    $padre->refresh();
    expect($padre->estado_actual_id)->toBe($semilla['ejecucion']->id);

    $actuadoDerivacion = $padre->actuados()
        ->whereHas('tipoActuado', fn ($query) => $query->where('codigo', 'ACT_CREACION_NUREJ_HIJO'))
        ->latest('id')
        ->first();

    expect($actuadoDerivacion)->not->toBeNull()
        ->and($actuadoDerivacion->usuario_id)->toBe($semilla['encargada']->id)
        ->and($actuadoDerivacion->estado_anterior_id)->toBe($semilla['ejecucion']->id)
        ->and($actuadoDerivacion->estado_nuevo_id)->toBe($semilla['ejecucion']->id)
        ->and($actuadoDerivacion->contenido['descripcion'])->toBe('El informe final del operador recomienda auditoría especializada sobre los hallazgos.')
        ->and($actuadoDerivacion->contenido['expediente_hijo_id'])->toBe($hijo->id)
        ->and($actuadoDerivacion->contenido['nurej_hijo_code'])->toBe('2026-00001-1');
});

it('el NUREJ Hijo nace con línea de tiempo en cero: actuados, plazos y asignaciones vacíos', function () {
    $semilla = nurejHijoSemilla();
    $padre = nurejHijoCrearPadre($semilla, conHistorial: true);

    expect($padre->actuados()->count())->toBe(1)
        ->and($padre->plazos()->count())->toBe(1)
        ->and($padre->asignaciones()->count())->toBe(1);

    Sanctum::actingAs($semilla['encargada'], ['*']);

    $this->postJson("/api/expedientes/{$padre->id}/nurej-hijo", [
        'motivo' => 'Hallazgos de auditoría que requieren causa independiente para su profundización.',
    ])->assertCreated();

    $hijo = Expediente::where('nurej_code', '2026-00001-1')->first();
    expect($hijo)->not->toBeNull()
        ->and($hijo->actuados()->count())->toBe(0)
        ->and($hijo->plazos()->count())->toBe(0)
        ->and($hijo->asignaciones()->count())->toBe(0)
        ->and($hijo->partesVigentes()->count())->toBe(2);
});

it('el padre opera en paralelo: conserva su historial, plazos y bandeja tras derivar el hijo', function () {
    $semilla = nurejHijoSemilla();
    $padre = nurejHijoCrearPadre($semilla, conHistorial: true);

    Sanctum::actingAs($semilla['encargada'], ['*']);

    $this->postJson("/api/expedientes/{$padre->id}/nurej-hijo", [
        'motivo' => 'Derivación por recomendación de informe final con alcance de auditoría especial.',
    ])->assertCreated();

    $padre->refresh();

    expect($padre->estado_actual_id)->toBe($semilla['ejecucion']->id)
        ->and($padre->actuados()->count())->toBe(2)
        ->and($padre->plazos()->count())->toBe(1)
        ->and($padre->asignaciones()->count())->toBe(1)
        ->and($padre->asignacionActiva()->first()->usuario_id)->toBe($semilla['tecnico']->id)
        ->and($padre->partesVigentes()->count())->toBe(2);
});

it('devuelve 403 si un Técnico intenta derivar un NUREJ Hijo', function () {
    $semilla = nurejHijoSemilla();
    $padre = nurejHijoCrearPadre($semilla);

    Sanctum::actingAs($semilla['tecnico'], ['*']);

    $this->postJson("/api/expedientes/{$padre->id}/nurej-hijo", [
        'motivo' => 'Intento de derivación por un operador sin atribución (test).',
    ])->assertForbidden();
});

it('devuelve 403 si un Auditor Jurídico intenta derivar un NUREJ Hijo', function () {
    $semilla = nurejHijoSemilla();
    $padre = nurejHijoCrearPadre($semilla);

    Sanctum::actingAs($semilla['audJuridico'], ['*']);

    $this->postJson("/api/expedientes/{$padre->id}/nurej-hijo", [
        'motivo' => 'Intento de derivación por un auditor sin atribución (test).',
    ])->assertForbidden();
});

it('devuelve 403 si la Encargada está inactiva', function () {
    $semilla = nurejHijoSemilla();
    $padre = nurejHijoCrearPadre($semilla);

    $semilla['encargada']->update(['activo' => false]);

    Sanctum::actingAs($semilla['encargada'], ['*']);

    $this->postJson("/api/expedientes/{$padre->id}/nurej-hijo", [
        'motivo' => 'Intento de derivación por una Encargada desactivada (test).',
    ])->assertForbidden();
});

it('devuelve 422 si el motivo de la derivación es demasiado corto', function () {
    $semilla = nurejHijoSemilla();
    $padre = nurejHijoCrearPadre($semilla);

    Sanctum::actingAs($semilla['encargada'], ['*']);

    $this->postJson("/api/expedientes/{$padre->id}/nurej-hijo", [
        'motivo' => 'Corto.',
    ])->assertStatus(422)
        ->assertJsonValidationErrors('motivo');
});
