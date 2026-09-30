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

function fnSemilla(): array
{
    $rolEncargada = Rol::factory()->create(['codigo' => Rol::CODIGO_ENCARGADA]);
    $encargada = Usuario::factory()->create(['rol_id' => $rolEncargada->id, 'activo' => true]);

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
    ]);

    DB::table('catalogo_actuado_roles')->insert([
        ['catalogo_actuado_id' => $actCreacionHijo->id, 'rol_id' => $rolEncargada->id, 'reglamento_id' => null],
    ]);

    return compact('encargada', 'ac022', 'pendienteSorteo', 'ejecucion', 'actCreacionHijo');
}

function fnCrearPadre(array $semilla, bool $conHistorial = false): Expediente
{
    $padre = Expediente::create([
        'nurej_code' => '2026-00001',
        'via' => 'TECNICO',
        'reglamento_id' => $semilla['ac022']->id,
        'estado_actual_id' => $semilla['ejecucion']->id,
        'resumen_hechos' => 'Hechos de la causa padre con detalle suficiente para su derivación.',
        'fecha_ingreso' => now(),
        'creado_por' => $semilla['encargada']->id,
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
            'usuario_id' => $semilla['encargada']->id,
            'estado_nuevo_id' => $padre->estado_actual_id,
            'contenido' => ['descripcion' => 'Actuado previo del padre en su línea de tiempo'],
        ])->refresh();

        Asignacion::create([
            'expediente_id' => $padre->id,
            'usuario_id' => $semilla['encargada']->id,
            'rol_id' => $semilla['encargada']->rol_id,
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

it('las líneas de tiempo son independientes: los actuados del hijo jamás aparecen en el padre ni viceversa (RN-10)', function () {
    $semilla = fnSemilla();
    $padre = fnCrearPadre($semilla, conHistorial: true);

    Sanctum::actingAs($semilla['encargada'], ['*']);

    $this->postJson("/api/expedientes/{$padre->id}/nurej-hijo", [
        'motivo' => 'Derivación para causa independiente con alcance de auditoría especial.',
    ])->assertCreated();

    $hijo = Expediente::where('nurej_padre_id', $padre->id)->firstOrFail();

    $actuadoHijo = Actuado::create([
        'expediente_id' => $hijo->id,
        'catalogo_actuado_id' => $semilla['actCreacionHijo']->id,
        'usuario_id' => $semilla['encargada']->id,
        'estado_nuevo_id' => $hijo->estado_actual_id,
        'contenido' => ['descripcion' => 'Actuado propio del NUREJ Hijo'],
    ])->refresh();

    $detallePadre = $this->getJson("/api/expedientes/{$padre->id}")
        ->assertOk()
        ->json('data.actuados');

    $detalleHijo = $this->getJson("/api/expedientes/{$hijo->id}")
        ->assertOk()
        ->json('data.actuados');

    $idsPadre = collect($detallePadre)->pluck('id');
    $idsHijo = collect($detalleHijo)->pluck('id');

    expect($idsPadre)->toHaveCount(2)
        ->and($idsHijo)->toHaveCount(1)
        ->and($idsHijo->all())->toBe([$actuadoHijo->id])
        ->and($idsPadre->contains($actuadoHijo->id))->toBeFalse();

    $contenidosPadre = collect($detallePadre)
        ->map(fn ($actuado) => $actuado['descripcion'] ?? null)
        ->all();

    expect($contenidosPadre)->not->toContain('Actuado propio del NUREJ Hijo')
        ->and($contenidosPadre)->toContain('Actuado previo del padre en su línea de tiempo');
});

it('el hijo no hereda plazos ni asignaciones y su segunda derivación usa correlativo -2 (RF-01)', function () {
    $semilla = fnSemilla();
    $padre = fnCrearPadre($semilla, conHistorial: true);

    Sanctum::actingAs($semilla['encargada'], ['*']);

    $this->postJson("/api/expedientes/{$padre->id}/nurej-hijo", [
        'motivo' => 'Primera derivación con motivo suficientemente largo para el validador.',
    ])->assertCreated()
        ->assertJsonPath('data.nurej_code', '2026-00001-1');

    $this->postJson("/api/expedientes/{$padre->id}/nurej-hijo", [
        'motivo' => 'Segunda derivación con motivo suficientemente largo para el validador.',
    ])->assertCreated()
        ->assertJsonPath('data.nurej_code', '2026-00001-2');

    $hijos = Expediente::where('nurej_padre_id', $padre->id)->get();

    expect($hijos)->toHaveCount(2)
        ->and($hijos->every(fn (Expediente $hijo) => $hijo->plazos()->count() === 0 && $hijo->asignaciones()->count() === 0))->toBeTrue()
        ->and($padre->plazos()->count())->toBe(1)
        ->and($padre->asignaciones()->count())->toBe(1);
});

it('las partes copiadas al hijo son copias independientes: modificar las del hijo no toca las del padre', function () {
    $semilla = fnSemilla();
    $padre = fnCrearPadre($semilla);

    Sanctum::actingAs($semilla['encargada'], ['*']);

    $this->postJson("/api/expedientes/{$padre->id}/nurej-hijo", [
        'motivo' => 'Derivación para prueba de independencia de partes entre causas.',
    ])->assertCreated();

    $hijo = Expediente::where('nurej_padre_id', $padre->id)->firstOrFail();

    expect($hijo->partesVigentes()->count())->toBe(2)
        ->and($padre->partesVigentes()->count())->toBe(2);

    $parteHijo = $hijo->partesVigentes()->where('tipo', 'DENUNCIANTE')->firstOrFail();
    $parteHijo->update(['nombre_completo' => 'Nombre Modificado Solo En El Hijo']);

    $partePadre = $padre->partesVigentes()->where('tipo', 'DENUNCIANTE')->firstOrFail();

    expect($partePadre->id)->not->toBe($parteHijo->id)
        ->and($partePadre->nombre_completo)->toBe('Juan Perez Mamani')
        ->and($parteHijo->nombre_completo)->toBe('Nombre Modificado Solo En El Hijo');
});

it('devuelve 422 al intentar derivar un NUREJ desde un expediente ya derivado (RN-10)', function () {
    $semilla = fnSemilla();
    $padre = fnCrearPadre($semilla);

    Sanctum::actingAs($semilla['encargada'], ['*']);

    $this->postJson("/api/expedientes/{$padre->id}/nurej-hijo", [
        'motivo' => 'Primera derivación con motivo suficientemente largo para el validador.',
    ])->assertCreated();

    $hijo = Expediente::where('nurej_padre_id', $padre->id)->firstOrFail();

    $this->postJson("/api/expedientes/{$hijo->id}/nurej-hijo", [
        'motivo' => 'Intento de sub-derivación rechazado por la regla RN-10 del sistema.',
    ])->assertStatus(422)
        ->assertJsonPath('message', 'No se pueden generar NUREJ hijos de un expediente ya derivado (RN-10).');

    expect(Expediente::where('nurej_padre_id', $hijo->id)->count())->toBe(0)
        ->and(Expediente::where('nurej_padre_id', $padre->id)->count())->toBe(1);
});
