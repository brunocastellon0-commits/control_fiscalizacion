<?php

use App\Models\Actuado;
use App\Models\Asignacion;
use App\Models\CatalogoActuado;
use App\Models\CatalogoEstado;
use App\Models\Expediente;
use App\Models\Reglamento;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\ActuadoService;
use Database\Seeders\CatalogoActuadoSeeder;
use Database\Seeders\CatalogoEstadoSeeder;
use Database\Seeders\FeriadoSeeder;
use Database\Seeders\ParametroPlazoSeeder;
use Database\Seeders\ReglamentoSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Semilla con los catálogos reales del proyecto (D-6a/D-6b aplicados) y un
 * operador por rol. Cubre AUD-0033: validación de estado origen con bloqueo
 * pesimista (D-6g) y el paso automático ADMITIDO → EN_PLANIFICACION (D-6b).
 *
 * @return array{encargada: Usuario, tecnico: Usuario, ac022: Reglamento, estados: array<string, CatalogoEstado>}
 */
function mefSemilla(TestCase $test): array
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

    $estados = [];
    foreach (['PENDIENTE_SORTEO', 'EN_EVALUACION', 'EN_IMPUGNACION', 'ADMITIDO', 'EN_PLANIFICACION', 'ARCHIVO_DEFINITIVO'] as $codigo) {
        $estados[$codigo] = CatalogoEstado::where('codigo', $codigo)->firstOrFail();
    }

    return [
        'encargada' => Usuario::factory()->create(['rol_id' => $rolEncargada->id, 'activo' => true]),
        'tecnico' => Usuario::factory()->create(['rol_id' => $rolTecnico->id, 'activo' => true]),
        'ac022' => Reglamento::where('codigo', 'AC_022_2018')->firstOrFail(),
        'estados' => $estados,
    ];
}

function mefCrearExpediente(int $estadoId, int $reglamentoId, int $creadorId): Expediente
{
    return Expediente::create([
        'nurej_code' => 'EXP-MEF-'.fake()->unique()->numberBetween(10000, 99999),
        'via' => 'TECNICO',
        'reglamento_id' => $reglamentoId,
        'estado_actual_id' => $estadoId,
        'fecha_ingreso' => now(),
        'creado_por' => $creadorId,
    ]);
}

it('rechaza con 422 cada actuado del catálogo emitido desde un estado que no es su estado origen', function () {
    $semilla = mefSemilla($this);

    $catalogos = CatalogoActuado::whereNotNull('estado_origen_id')->orderBy('id')->get();
    expect($catalogos->count())->toBeGreaterThan(20);

    // ARCHIVO_DEFINITIVO nunca figura como estado origen en el catálogo real,
    // por lo que es un "estado incorrecto" válido para todas las filas.
    $expediente = mefCrearExpediente(
        $semilla['estados']['ARCHIVO_DEFINITIVO']->id,
        $semilla['ac022']->id,
        $semilla['encargada']->id,
    );

    $servicio = app(ActuadoService::class);
    $probados = 0;

    foreach ($catalogos as $catalogo) {
        expect($catalogo->estado_origen_id)->not->toBe($expediente->estado_actual_id);

        $adjunto = $catalogo->requiere_adjunto
            ? UploadedFile::fake()->create('soporte.pdf', 100, 'application/pdf')
            : null;

        try {
            $servicio->registerActuado(
                expediente: $expediente,
                catalogoActuado: $catalogo,
                emisor: $semilla['encargada'],
                descripcion: 'Emisión inválida fuera del estado origen (D-6g).',
                adjunto: $adjunto,
            );
            $this->fail("El actuado {$catalogo->codigo} fue aceptado fuera de su estado origen.");
        } catch (ValidationException $e) {
            expect(array_key_exists('estado_origen_id', $e->errors()))
                ->toBeTrue("Fallo inesperado en {$catalogo->codigo}: ".json_encode($e->errors()));
        }

        $probados++;
    }

    expect($probados)->toBe($catalogos->count())
        ->and($expediente->actuados()->count())->toBe(0);

    $expediente->refresh();
    expect($expediente->estado_actual_id)->toBe($semilla['estados']['ARCHIVO_DEFINITIVO']->id);
});

it('permite emitir el actuado cuando el estado actual coincide con su estado origen', function () {
    $semilla = mefSemilla($this);

    $catalogo = CatalogoActuado::where('codigo', 'ACT_SORTEO_INICIAL')->firstOrFail();
    $expediente = mefCrearExpediente(
        $semilla['estados']['PENDIENTE_SORTEO']->id,
        $semilla['ac022']->id,
        $semilla['encargada']->id,
    );

    $actuado = app(ActuadoService::class)->registerActuado(
        expediente: $expediente,
        catalogoActuado: $catalogo,
        emisor: $semilla['encargada'],
        descripcion: 'Sorteo inicial emitido desde el estado origen correcto.',
    );

    expect($actuado->estado_anterior_id)->toBe($semilla['estados']['PENDIENTE_SORTEO']->id)
        ->and($actuado->estado_nuevo_id)->toBe($semilla['estados']['EN_EVALUACION']->id);

    $expediente->refresh();
    expect($expediente->estado_actual_id)->toBe($semilla['estados']['EN_EVALUACION']->id);
});

it('transiciona automáticamente de ADMITIDO a EN_PLANIFICACION con actuado formal encadenado (D-6b)', function () {
    $semilla = mefSemilla($this);

    $expediente = mefCrearExpediente(
        $semilla['estados']['EN_IMPUGNACION']->id,
        $semilla['ac022']->id,
        $semilla['encargada']->id,
    );

    $tecnico = $semilla['tecnico'];
    $revoca = CatalogoActuado::where('codigo', 'ACT_RESOLUCION_REVOCA_RECHAZO')->firstOrFail();

    $actuadoOrigen = Actuado::create([
        'expediente_id' => $expediente->id,
        'catalogo_actuado_id' => $revoca->id,
        'usuario_id' => $semilla['encargada']->id,
        'estado_nuevo_id' => $semilla['estados']['EN_IMPUGNACION']->id,
        'contenido' => ['descripcion' => 'Asignación inicial de la semilla (B1.2)'],
    ])->refresh();

    $asignacionPrevia = Asignacion::create([
        'expediente_id' => $expediente->id,
        'usuario_id' => $tecnico->id,
        'rol_id' => $tecnico->rol_id,
        'actuado_origen_id' => $actuadoOrigen->id,
        'fecha_asignacion' => now(),
        'activa' => true,
    ]);

    $actuadoRevoca = app(ActuadoService::class)->registerActuado(
        expediente: $expediente,
        catalogoActuado: $revoca,
        emisor: $semilla['encargada'],
        descripcion: 'Revocación del rechazo: el expediente retorna a ADMITIDO.',
    );

    expect($actuadoRevoca->estado_nuevo_id)->toBe($semilla['estados']['ADMITIDO']->id);

    $expediente->refresh();
    expect($expediente->estado_actual_id)->toBe($semilla['estados']['EN_PLANIFICACION']->id);

    $actuados = $expediente->actuados()->orderBy('id')->get();
    // 1 de la asignación de semilla + revocación + paso automático (D-6b).
    expect($actuados)->toHaveCount(3);

    $paso = $actuados->last();
    $paso->refresh();

    expect($paso->tipoActuado->codigo)->toBe(ActuadoService::CODIGO_PASO_PLANIFICACION)
        ->and($paso->estado_anterior_id)->toBe($semilla['estados']['ADMITIDO']->id)
        ->and($paso->estado_nuevo_id)->toBe($semilla['estados']['EN_PLANIFICACION']->id)
        ->and($paso->usuario_id)->toBe($semilla['encargada']->id)
        ->and($paso->contenido['tipo'] ?? null)->toBe('AUTOMATICO')
        ->and($paso->hash_anterior)->toBe($actuadoRevoca->hash_actuado);

    // El plazo PLANIFICACION lo abre la revocación (MAPA_TIPO_PLAZO); el paso
    // automático no tiene entrada en el mapa y no debe abrir un segundo reloj.
    expect($expediente->plazos()->where('tipo_plazo', 'PLANIFICACION')->count())->toBe(1);

    // El paso automático no reasigna la bandeja: la revocación ya devolvió
    // el expediente al operador original.
    $asignacionActiva = $expediente->asignacionActiva()->first();
    expect($asignacionActiva)->not->toBeNull()
        ->and($asignacionActiva->id)->toBe($asignacionPrevia->id)
        ->and($asignacionActiva->usuario_id)->toBe($tecnico->id);
});

it('devuelve 422 por HTTP al emitir un actuado fuera de su estado origen', function () {
    $semilla = mefSemilla($this);

    $catalogo = CatalogoActuado::where('codigo', 'ACT_SORTEO_INICIAL')->firstOrFail();
    $expediente = mefCrearExpediente(
        $semilla['estados']['EN_EVALUACION']->id,
        $semilla['ac022']->id,
        $semilla['encargada']->id,
    );

    Sanctum::actingAs($semilla['encargada'], ['*']);

    $this->postJson('/api/expedientes/'.$expediente->id.'/actuados', [
        'catalogo_actuado_id' => $catalogo->id,
        'descripcion' => 'Sorteo inicial emitido fuera de PENDIENTE_SORTEO.',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['estado_origen_id']);

    $expediente->refresh();
    expect($expediente->estado_actual_id)->toBe($semilla['estados']['EN_EVALUACION']->id)
        ->and($expediente->actuados()->count())->toBe(0);
});

/**
 * B1.2 — Concurrencia real (JOB SEPARADO, NO BLOQUEANTE). Habilitar con:
 *   $env:RUN_CONCURRENCY_TEST='1'; php artisan test --filter=MaquinaEstadosTransicionesTest
 *
 * Proceso A toma el lock del expediente, cambia su estado y sostiene la
 * transacción; proceso B emite un actuado cuyo estado origen ya no coincide.
 * Con lockForUpdate antes de validar (D-6g), B espera a que A libere y debe
 * rechazar con 422 sin insertar ningún actuado.
 */
function mefTinker(Process $process): string
{
    $process->run();

    if (! $process->isSuccessful()) {
        throw new RuntimeException('Fallo el subproceso: '.$process->getErrorOutput());
    }

    return trim($process->getOutput());
}

function mefSemillaConcurrente(): array
{
    $codigo = <<<'PHP'
        $rol = App\Models\Rol::factory()->create();
        $usuario = App\Models\Usuario::factory()->create(['rol_id' => $rol->id]);
        $reglamento = App\Models\Reglamento::factory()->create();
        $estadoOrigen = App\Models\CatalogoEstado::factory()->create(['codigo' => 'EST_ORIGEN_'.rand(10000, 99999)]);
        $estadoDestino = App\Models\CatalogoEstado::factory()->create(['codigo' => 'EST_DESTINO_'.rand(10000, 99999)]);
        $catalogo = App\Models\CatalogoActuado::create([
            'codigo' => 'ACT_MEF_'.rand(10000, 99999),
            'nombre' => 'Actuado concurrencia D-6g',
            'fase' => 'ADMISIBILIDAD',
            'rol_id' => $rol->id,
            'estado_origen_id' => $estadoOrigen->id,
            'estado_destino_id' => $estadoDestino->id,
            'es_automatico' => false,
            'requiere_adjunto' => false,
        ]);
        $expediente = App\Models\Expediente::create([
            'nurej_code' => '2026-'.rand(1000000, 9999999),
            'via' => 'TECNICO',
            'reglamento_id' => $reglamento->id,
            'estado_actual_id' => $estadoOrigen->id,
            'fecha_ingreso' => now(),
            'creado_por' => $usuario->id,
        ]);
        echo $expediente->id.'|'.$catalogo->id.'|'.$usuario->id.'|'.$estadoOrigen->id.'|'.$estadoDestino->id;
        PHP;

    $output = mefTinker(new Process(
        [PHP_BINARY, 'artisan', 'tinker', '--execute', $codigo],
        base_path(),
        ['APP_ENV' => 'testing', 'XDEBUG_MODE' => 'off'],
        60,
    ));

    $match = [];
    preg_match('/(\d+)\|(\d+)\|(\d+)\|(\d+)\|(\d+)$/m', $output, $match);

    if (count($match) !== 6) {
        throw new RuntimeException('No se pudieron obtener IDs de la semilla concurrente: '.$output);
    }

    return [
        'expediente_id' => (int) $match[1],
        'catalogo_id' => (int) $match[2],
        'usuario_id' => (int) $match[3],
        'estado_origen_id' => (int) $match[4],
        'estado_destino_id' => (int) $match[5],
    ];
}

it('bloquea la validación de estado origen frente a transiciones concurrentes (D-6g)', function () {
    $semilla = mefSemillaConcurrente();

    $codigoBloqueo = <<<'PHP'
        $exp = (int) getenv('MEF_EXP');
        $destino = (int) getenv('MEF_DEST');

        Illuminate\Support\Facades\DB::transaction(function () use ($exp, $destino) {
            Illuminate\Support\Facades\DB::table('expedientes')
                ->where('id', $exp)
                ->lockForUpdate()
                ->first();

            Illuminate\Support\Facades\DB::table('expedientes')
                ->where('id', $exp)
                ->update(['estado_actual_id' => $destino]);

            echo 'MEF_LOCKED';
            flush();
            sleep(5);
        });

        echo '|MEF_DONE';
        PHP;

    $procesoA = new Process(
        [PHP_BINARY, 'artisan', 'tinker', '--execute', $codigoBloqueo],
        base_path(),
        ['APP_ENV' => 'testing', 'XDEBUG_MODE' => 'off',
            'MEF_EXP' => (string) $semilla['expediente_id'],
            'MEF_DEST' => (string) $semilla['estado_destino_id']],
        60,
    );
    $procesoA->start();

    $espera = microtime(true) + 30;
    while (microtime(true) < $espera && ! str_contains($procesoA->getOutput(), 'MEF_LOCKED')) {
        usleep(200000);
    }

    if (! str_contains($procesoA->getOutput(), 'MEF_LOCKED')) {
        $procesoA->stop(1);
        throw new RuntimeException('El proceso A nunca tomó el lock: '.$procesoA->getErrorOutput());
    }

    $codigoEmision = <<<'PHP'
        $exp = App\Models\Expediente::find((int) getenv('MEF_EXP'));
        $cat = App\Models\CatalogoActuado::find((int) getenv('MEF_CAT'));
        $usr = App\Models\Usuario::find((int) getenv('MEF_USR'));

        try {
            app(App\Services\ActuadoService::class)->registerActuado(
                expediente: $exp,
                catalogoActuado: $cat,
                emisor: $usr,
                descripcion: 'Emisión concurrente contra estado desactualizado (D-6g).',
            );
            echo 'MEF_INSERTO';
        } catch (Illuminate\Validation\ValidationException $e) {
            echo 'MEF_RECHAZADO';
        }
        PHP;

    $procesoB = new Process(
        [PHP_BINARY, 'artisan', 'tinker', '--execute', $codigoEmision],
        base_path(),
        ['APP_ENV' => 'testing', 'XDEBUG_MODE' => 'off',
            'MEF_EXP' => (string) $semilla['expediente_id'],
            'MEF_CAT' => (string) $semilla['catalogo_id'],
            'MEF_USR' => (string) $semilla['usuario_id']],
        60,
    );
    $procesoB->run();

    $procesoA->wait();

    expect($procesoA->isSuccessful())->toBeTrue('Proceso A falló: '.$procesoA->getErrorOutput())
        ->and($procesoB->isSuccessful())->toBeTrue('Proceso B falló: '.$procesoB->getErrorOutput())
        ->and($procesoB->getOutput())->toContain('MEF_RECHAZADO')
        ->and($procesoB->getOutput())->not->toContain('MEF_INSERTO');

    $codigoVerificar = <<<'PHP'
        $exp = (int) getenv('MEF_EXP');
        echo App\Models\Actuado::where('expediente_id', $exp)->count();
        PHP;

    $totalActuados = (int) mefTinker(new Process(
        [PHP_BINARY, 'artisan', 'tinker', '--execute', $codigoVerificar],
        base_path(),
        ['APP_ENV' => 'testing', 'XDEBUG_MODE' => 'off', 'MEF_EXP' => (string) $semilla['expediente_id']],
        60,
    ));

    expect($totalActuados)->toBe(0);
})
    ->skip(! (bool) getenv('RUN_CONCURRENCY_TEST'), 'Concurrencia D-6g habilitada explícitamente con RUN_CONCURRENCY_TEST=1');
