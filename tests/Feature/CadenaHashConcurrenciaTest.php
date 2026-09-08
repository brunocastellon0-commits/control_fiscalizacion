<?php

use Symfony\Component\Process\Process;

/*
 * Test B — Concurrencia real (JOB SEPARADO, NO BLOQUEANTE).
 *
 * Habilitar explícitamente con RUN_CONCURRENCY_TEST=1:
 *   $env:RUN_CONCURRENCY_TEST='1'; php artisan test --filter=CadenaHashConcurrenciaTest
 *
 * Por qué no corre en el pipeline normal:
 *  - Lanza procesos PHP reales (artisan tinker) que insertan sobre la misma
 *    BD; es determinista en la verificación pero pesado y potencialmente flaky
 *    en CI si no hay recursos.
 *  - Los INSERT de los subprocesos quedan COMMITTEADOS. Como `actuados` es
 *    inmutable (DELETE bloqueado por trigger), esas filas y su expediente de
 *    prueba persisten en la BD hasta el próximo migrate:fresh. No borra nada
 *    existente, pero deja data de prueba.
 *
 * Verifica que, bajo N subprocesos concurrentes insertando actuados sobre el
 * mismo expediente, el trigger trg_actuados_hash_before_insert (que hace
 * SELECT id ... FROM expedientes WHERE id = NEW.expediente_id FOR UPDATE)
 * serializa los inserts y la cadena de hashes queda íntegra.
 */

function concurrenciaTinker(Process $process): string
{
    $process->run();

    if (! $process->isSuccessful()) {
        throw new RuntimeException('Fallo el subproceso: '.$process->getErrorOutput());
    }

    return trim($process->getOutput());
}

function concurrenciaSeed(): array
{
    $codigo = <<<'PHP'
        $rol = App\Models\Rol::factory()->create();
        $usuario = App\Models\Usuario::factory()->create(['rol_id' => $rol->id]);
        $reglamento = App\Models\Reglamento::factory()->create();
        $estado = App\Models\CatalogoEstado::factory()->create();
        $catalogo = App\Models\CatalogoActuado::create([
            'codigo' => 'ACT_CONC_'.rand(10000, 99999),
            'nombre' => 'Actuado concurrencia real',
            'fase' => 'REGISTRO',
            'rol_id' => $rol->id,
            'estado_destino_id' => $estado->id,
            'es_automatico' => false,
            'requiere_adjunto' => false,
        ]);
        $expediente = App\Models\Expediente::create([
            'nurej_code' => '2026-'.rand(1000000, 9999999),
            'via' => 'TECNICO',
            'reglamento_id' => $reglamento->id,
            'estado_actual_id' => $estado->id,
            'fecha_ingreso' => now(),
            'creado_por' => $usuario->id,
        ]);
        echo $expediente->id.'|'.$catalogo->id.'|'.$usuario->id.'|'.$estado->id;
        PHP;

    $output = concurrenciaTinker(new Process(
        [PHP_BINARY, 'artisan', 'tinker', '--execute', $codigo],
        base_path(),
        ['APP_ENV' => 'testing', 'XDEBUG_MODE' => 'off'],
        60,
    ));

    $match = [];
    preg_match('/(\d+)\|(\d+)\|(\d+)\|(\d+)$/m', $output, $match);

    if (count($match) !== 5) {
        throw new RuntimeException('No se pudieron obtener IDs de la semilla: '.$output);
    }

    return ['expediente_id' => (int) $match[1], 'catalogo_id' => (int) $match[2],
        'usuario_id' => (int) $match[3], 'estado_id' => (int) $match[4]];
}

it('no bifurca la cadena de hashes bajo N subprocesos concurrentes', function () {
    $semilla = concurrenciaSeed();

    $codigoInsert = <<<'PHP'
        $exp = (int) getenv('CONC_EXP');
        $cat = (int) getenv('CONC_CAT');
        $usr = (int) getenv('CONC_USR');
        $est = (int) getenv('CONC_EST');
        $marcador = getenv('CONC_MARKER');

        Illuminate\Support\Facades\DB::transaction(function () use ($exp, $cat, $usr, $est, $marcador) {
            Illuminate\Support\Facades\DB::table('expedientes')
                ->where('id', $exp)
                ->lockForUpdate()
                ->first();

            Illuminate\Support\Facades\DB::table('actuados')->insert([
                'expediente_id' => $exp,
                'catalogo_actuado_id' => $cat,
                'usuario_id' => $usr,
                'estado_nuevo_id' => $est,
                'contenido' => json_encode(['descripcion' => $marcador]),
            ]);
        });

        echo 'OK';
        PHP;

    $envBase = [
        'APP_ENV' => 'testing',
        'XDEBUG_MODE' => 'off',
        'CONC_EXP' => (string) $semilla['expediente_id'],
        'CONC_CAT' => (string) $semilla['catalogo_id'],
        'CONC_USR' => (string) $semilla['usuario_id'],
        'CONC_EST' => (string) $semilla['estado_id'],
    ];
    $procesos = [];

    foreach (range(1, 4) as $i) {
        $process = new Process(
            [PHP_BINARY, 'artisan', 'tinker', '--execute', $codigoInsert],
            base_path(),
            $envBase + ['CONC_MARKER' => "Nodo-concurrente-{$i}"],
            60,
        );
        $process->start();
        $procesos[] = $process;
    }

    foreach ($procesos as $process) {
        $process->wait();
        expect($process->isSuccessful())
            ->toBeTrue('Subproceso falló (exit='.$process->getExitCode().') OUT=['.$process->getOutput().'] ERR=['.$process->getErrorOutput().']');
    }

    $codigoVerificar = <<<'PHP'
        $exp = (int) getenv('CONC_EXP');

        $rows = Illuminate\Support\Facades\DB::table('actuados')
            ->where('expediente_id', $exp)
            ->orderBy('id')
            ->get(['id', 'hash_anterior', 'hash_actuado']);

        $total = $rows->count();
        $unicos = $rows->pluck('hash_actuado')->unique()->count();
        $anterior = null;
        $rota = false;

        foreach ($rows as $fila) {
            if ($anterior !== null && $fila->hash_anterior !== $anterior) {
                $rota = true;
                break;
            }
            $anterior = $fila->hash_actuado;
        }

        echo json_encode([
            'total' => $total,
            'unicos' => $unicos,
            'rota' => $rota,
            'primero_hash_anterior' => $rows->first()->hash_anterior ?? null,
        ]);
        PHP;

    $resultado = json_decode(concurrenciaTinker(new Process(
        [PHP_BINARY, 'artisan', 'tinker', '--execute', $codigoVerificar],
        base_path(),
        $envBase,
        60,
    )), true);

    expect($resultado)
        ->toMatchArray(['total' => 4, 'unicos' => 4, 'rota' => false])
        ->and($resultado['primero_hash_anterior'])->toBeNull();
})
    ->skip(! (bool) getenv('RUN_CONCURRENCY_TEST'), 'Test B habilitado explícitamente con RUN_CONCURRENCY_TEST=1');
