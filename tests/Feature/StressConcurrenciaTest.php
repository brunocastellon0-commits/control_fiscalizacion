<?php

use Symfony\Component\Process\Process;

/*
 * STRESS — Concurrencia real de negocio/BD (subprocesos PHP sobre la misma BD).
 *
 * NO corre en el pipeline normal. Habilitar con:
 *   $env:RUN_STRESS_TESTS='1'; php artisan test --filter=StressConcurrenciaTest
 *
 * Diseño (mismo patrón que CadenaHashConcurrenciaTest):
 *  - Todo acceso a la BD (semilla, escritura y verificación) ocurre en
 *    subprocesos `artisan tinker` reales. Así se evita el aislamiento en
 *    transacción de RefreshDatabase (tests/Feature se aplica globalmente),
 *    que impediría a los subprocesos ver los INSERT del padre.
 *  - Cada test es autocontenido: crea sus propios roles/usuarios/catálogos
 *    (códigos únicos), sin depender del contenido previo de la BD.
 *  - `actuados` es inmutable (DELETE bloqueado por trigger): los INSERT de
 *    los subprocesos quedan committeados y persisten hasta migrate:fresh.
 *    No borra nada existente.
 *
 * Escenarios:
 *  T1 NUREJ (secuencia existente): N procesos -> correlativo +N, códigos únicos.
 *  T2 Cadena de custodia: N procesos x M inserts -> cadena íntegra.
 *  T3 Sorteo concurrente del MISMO expediente -> <=1 ACT_SORTEO_INICIAL y <=1 asignación activa.
 *  T4 Apertura masiva: N procesos x M aperturas -> NUREJ únicos y cadena íntegra.
 *  T5 sortearTodas vs aperturas concurrentes -> sin deadlocks ni NUREJ duplicados.
 */

/** Ejecuta un subproceso `artisan tinker` y devuelve su salida. */
function stressTinker(string $codigo, array $env = [], int $timeout = 120): string
{
    $process = new Process(
        [PHP_BINARY, 'artisan', 'tinker', '--execute', $codigo],
        base_path(),
        array_merge(['APP_ENV' => 'testing', 'XDEBUG_MODE' => 'off'], $env),
        $timeout,
    );
    $process->run();

    if (! $process->isSuccessful()) {
        throw new RuntimeException('Subproceso falló (exit='.$process->getExitCode().') OUT=['.$process->getOutput().'] ERR=['.$process->getErrorOutput().']');
    }

    return trim($process->getOutput());
}

/** Lanza N subprocesos a la vez y espera a todos. Devuelve los Process. */
function stressSpawn(array $codigos, array $envBase, int $timeout = 120): array
{
    $procesos = [];

    foreach ($codigos as $clave => $codigo) {
        $process = new Process(
            [PHP_BINARY, 'artisan', 'tinker', '--execute', $codigo],
            base_path(),
            $envBase + ['STR_PROC' => $clave],
            $timeout,
        );
        $process->start();
        $procesos[$clave] = $process;
    }

    foreach ($procesos as $process) {
        $process->wait();
    }

    return $procesos;
}

/**
 * Crea la base mínima autocontenida (roles, usuarios, estados, reglamento y
 * catálogos de actuado) y devuelve sus IDs en JSON.
 */
function stressSeedBase(): string
{
    return <<<'PHP'
        $suf = getenv('STR_SUF');

        $rolTec = App\Models\Rol::firstWhere('codigo', 'TECNICO')
            ?? App\Models\Rol::create(['codigo' => 'TECNICO', 'nombre' => 'Tecnico', 'descripcion' => 'x']);
        $rolEnc = App\Models\Rol::firstWhere('codigo', 'ENCARGADA')
            ?? App\Models\Rol::create(['codigo' => 'ENCARGADA', 'nombre' => 'Encargada', 'descripcion' => 'x']);

        $estPend = App\Models\CatalogoEstado::firstWhere('codigo', 'PENDIENTE_SORTEO')
            ?? App\Models\CatalogoEstado::create(['codigo' => 'PENDIENTE_SORTEO', 'nombre' => 'Pendiente sorteo', 'es_final' => false]);
        $estEval = App\Models\CatalogoEstado::firstWhere('codigo', 'EN_EVALUACION')
            ?? App\Models\CatalogoEstado::create(['codigo' => 'EN_EVALUACION', 'nombre' => 'En evaluacion', 'es_final' => false]);

        $reglamento = App\Models\Reglamento::firstWhere('codigo', 'AC_022_2018')
            ?? App\Models\Reglamento::create(['codigo' => 'AC_022_2018', 'nombre' => 'Acuerdo 22', 'version' => '1.0', 'vigente_desde' => '2018-05-15', 'activo' => true]);

        $tecnico = App\Models\Usuario::firstWhere('username', 'stress_t_'.$suf)
            ?? App\Models\Usuario::create(['ci' => '111'.$suf, 'nombres' => 'Tec', 'apellidos' => 'Nico', 'username' => 'stress_t_'.$suf, 'password_hash' => 'x', 'rol_id' => $rolTec->id, 'activo' => true]);
        $encargada = App\Models\Usuario::firstWhere('username', 'stress_e_'.$suf)
            ?? App\Models\Usuario::create(['ci' => '222'.$suf, 'nombres' => 'Enc', 'apellidos' => 'Argada', 'username' => 'stress_e_'.$suf, 'password_hash' => 'x', 'rol_id' => $rolEnc->id, 'activo' => true]);

        $idsCandidatos = [];
        for ($i = 0; $i < 4; $i++) {
            $cand = App\Models\Usuario::firstWhere('username', 'stress_c_'.$suf.'_'.$i)
                ?? App\Models\Usuario::create(['ci' => '333'.$suf.$i, 'nombres' => 'Cand', 'apellidos' => 'Idato '.$i, 'username' => 'stress_c_'.$suf.'_'.$i, 'password_hash' => 'x', 'rol_id' => $rolTec->id, 'activo' => true]);
            $idsCandidatos[] = $cand->id;
        }

        $catRegistro = App\Models\CatalogoActuado::firstWhere('codigo', 'ACT_REGISTRO_DIGITALIZACION')
            ?? App\Models\CatalogoActuado::create(['codigo' => 'ACT_REGISTRO_DIGITALIZACION', 'nombre' => 'Registro digitalizacion', 'fase' => 'REGISTRO', 'rol_id' => $rolTec->id, 'estado_destino_id' => $estPend->id, 'es_automatico' => false, 'requiere_adjunto' => false]);
        $catSorteo = App\Models\CatalogoActuado::firstWhere('codigo', 'ACT_SORTEO_INICIAL')
            ?? App\Models\CatalogoActuado::create(['codigo' => 'ACT_SORTEO_INICIAL', 'nombre' => 'Sorteo inicial', 'fase' => 'ADMISIBILIDAD', 'rol_id' => $rolEnc->id, 'estado_destino_id' => $estEval->id, 'es_automatico' => false, 'requiere_adjunto' => false]);

        echo json_encode([
            'est_pendiente' => $estPend->id,
            'est_evaluacion' => $estEval->id,
            'reglamento' => $reglamento->id,
            'tecnico' => $tecnico->id,
            'encargada' => $encargada->id,
            'tec_username' => $tecnico->username,
            'enc_username' => $encargada->username,
        ]);
        PHP;
}

/** Plantilla de subproceso para aperturas masivas (usa la base sembrada). */
function stressCodigoApertura(): string
{
    return <<<'PHP'
        $tecnico = App\Models\Usuario::where('username', getenv('STR_TEC'))->firstOrFail();
        $reglamento = App\Models\Reglamento::where('codigo', 'AC_022_2018')->firstOrFail();
        $n = (int) getenv('STR_N');
        $out = [];

        for ($i = 0; $i < $n; $i++) {
            $exp = app(App\Services\ExpedienteService::class)->aperturaCausa(
                datos: [
                    'via' => 'TECNICO',
                    'reglamento_id' => $reglamento->id,
                    'resumen_hechos' => '[STRESS-MASA] corrida '.getenv('STR_PROC').' causa '.$i,
                ],
                tecnico: $tecnico,
            );
            $out[] = $exp->nurej_code;
        }

        echo implode(',', $out);
        PHP;
}

it('T1: N subprocesos generan NUREJ padre únicos sobre una secuencia existente (correlativo +N)', function () {
    $nProcesos = 8;
    $suf = 't1'.rand(10000, 99999);

    stressTinker(<<<'PHP'
        Illuminate\Support\Facades\DB::table('nurej_sequences')->insertOrIgnore([
            'anio' => 2026,
            'correlativo' => 100,
        ]);
        Illuminate\Support\Facades\DB::table('nurej_sequences')
            ->where('anio', 2026)->update(['correlativo' => 100]);
        echo 'OK';
        PHP);

    $antes = (int) json_decode(stressTinker(<<<'PHP'
        echo Illuminate\Support\Facades\DB::table('nurej_sequences')->where('anio', 2026)->value('correlativo');
        PHP), true);

    $codigo = <<<'PHP'
        $code = app(App\Services\NurejGeneratorService::class)->generarPadre();
        echo $code;
        PHP;

    $procesos = stressSpawn(array_fill(0, $nProcesos, $codigo), ['STR_SUF' => $suf]);

    $codigos = [];
    foreach ($procesos as $clave => $process) {
        $out = trim($process->getOutput());
        $err = trim($process->getErrorOutput());
        expect($process->isSuccessful(), "T1 [$clave] falló: OUT=[$out] ERR=[$err]")->toBeTrue();
        if (stripos($out.' '.$err, 'deadlock') !== false || stripos($err, '1213') !== false) {
            $codigos[] = 'DEADLOCK['.$clave.']';
        } else {
            $codigos[] = $out;
        }
    }

    $despues = (int) json_decode(stressTinker(<<<'PHP'
        echo Illuminate\Support\Facades\DB::table('nurej_sequences')->where('anio', 2026)->value('correlativo');
        PHP), true);

    expect($despues)->toBe($antes + $nProcesos, 'La secuencia debe avanzar exactamente +N');
    $validos = array_filter($codigos, fn ($c) => preg_match('/^\d{4}-\d{5}$/', $c));
    expect(count($validos))->toBe($nProcesos, 'Todos los procesos deben generar su NUREJ')
        ->and(collect($validos)->unique()->count())->toBe($nProcesos);
})
    ->skip(! (bool) getenv('RUN_STRESS_TESTS'), 'Stress habilitado con RUN_STRESS_TESTS=1');

it('T2: cadena de custodia íntegra con N subprocesos x M inserts concurrentes', function () {
    $nProcesos = 8;
    $insertsPorProceso = 2;
    $suf = 't2'.rand(10000, 99999);

    $semilla = stressTinker('
        $rol = App\Models\Rol::factory()->create(["codigo" => "TEC_STR_'.$suf.'"]);
        $usuario = App\Models\Usuario::factory()->create(["rol_id" => $rol->id]);
        $reglamento = App\Models\Reglamento::factory()->create();
        $estado = App\Models\CatalogoEstado::factory()->create();
        $catalogo = App\Models\CatalogoActuado::create([
            "codigo" => "ACT_STR_'.$suf.'",
            "nombre" => "Actuado stress cadena",
            "fase" => "REGISTRO",
            "rol_id" => $rol->id,
            "estado_destino_id" => $estado->id,
            "es_automatico" => false,
            "requiere_adjunto" => false,
        ]);
        $expediente = App\Models\Expediente::create([
            "nurej_code" => "2026-STR-'.$suf.'",
            "via" => "TECNICO",
            "reglamento_id" => $reglamento->id,
            "estado_actual_id" => $estado->id,
            "fecha_ingreso" => now(),
            "creado_por" => $usuario->id,
        ]);
        echo $expediente->id."|".$catalogo->id."|".$usuario->id."|".$estado->id;
    ');

    $semilla = explode('|', $semilla);
    expect($semilla)->toHaveCount(4);

    [$expId, $catId, $usrId, $estId] = array_map('intval', $semilla);

    $codigoInsert = <<<'PHP'
        $exp = (int) getenv('STR_EXP');
        $cat = (int) getenv('STR_CAT');
        $usr = (int) getenv('STR_USR');
        $est = (int) getenv('STR_EST');

        Illuminate\Support\Facades\DB::transaction(function () use ($exp, $cat, $usr, $est) {
            Illuminate\Support\Facades\DB::table('expedientes')->where('id', $exp)->lockForUpdate()->first();
            for ($i = 0; $i < (int) getenv('STR_M'); $i++) {
                Illuminate\Support\Facades\DB::table('actuados')->insert([
                    'expediente_id' => $exp,
                    'catalogo_actuado_id' => $cat,
                    'usuario_id' => $usr,
                    'estado_nuevo_id' => $est,
                    'contenido' => json_encode(['descripcion' => getenv('STR_PROC').'-'.$i]),
                ]);
            }
        });

        echo 'OK';
        PHP;

    $envBase = [
        'STR_SUF' => $suf,
        'STR_EXP' => (string) $expId,
        'STR_CAT' => (string) $catId,
        'STR_USR' => (string) $usrId,
        'STR_EST' => (string) $estId,
        'STR_M' => (string) $insertsPorProceso,
    ];

    $procesos = stressSpawn(array_fill(0, $nProcesos, $codigoInsert), $envBase);

    foreach ($procesos as $clave => $process) {
        expect($process->isSuccessful(), "T2 [$clave] falló: OUT=[".$process->getOutput().'] ERR=['.$process->getErrorOutput().']')->toBeTrue();
    }

    $totalEsperado = $nProcesos * $insertsPorProceso;

    $resultado = json_decode(stressTinker(<<<'PHP'
        $rows = Illuminate\Support\Facades\DB::table('actuados')
            ->where('expediente_id', (int) getenv('STR_EXP'))
            ->orderBy('id')
            ->get(['id', 'hash_anterior', 'hash_actuado']);

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
            'total' => $rows->count(),
            'unicos' => $rows->pluck('hash_actuado')->unique()->count(),
            'rota' => $rota,
            'primero_hash_anterior' => $rows->first()->hash_anterior ?? null,
        ]);
        PHP, $envBase), true);

    expect($resultado)
        ->toMatchArray(['total' => $totalEsperado, 'unicos' => $totalEsperado, 'rota' => false])
        ->and($resultado['primero_hash_anterior'])->toBeNull();
})
    ->skip(! (bool) getenv('RUN_STRESS_TESTS'), 'Stress habilitado con RUN_STRESS_TESTS=1');

it('T3: sorteo concurrente del mismo expediente produce a lo sumo 1 ACT_SORTEO_INICIAL y 1 asignación activa', function () {
    $nProcesos = 8;
    $suf = 't3'.rand(10000, 99999);

    $base = json_decode(stressTinker(stressSeedBase(), ['STR_SUF' => $suf]), true);

    $expId = (int) json_decode(stressTinker(<<<'PHP'
        $base = json_decode(getenv('STR_BASE'), true);

        $expediente = App\Models\Expediente::create([
            'nurej_code' => '2026-CONC-'.getenv('STR_SUF'),
            'via' => 'TECNICO',
            'reglamento_id' => $base['reglamento'],
            'estado_actual_id' => $base['est_pendiente'],
            'resumen_hechos' => '[STRESS-T3] sorteo concurrente',
            'fecha_ingreso' => now(),
            'creado_por' => $base['tecnico'],
        ]);

        echo $expediente->id;
        PHP, ['STR_BASE' => json_encode($base), 'STR_SUF' => $suf]), true);

    $codigoSorteo = <<<'PHP'
        usleep(random_int(0, 150) * 1000);

        $base = json_decode(getenv('STR_BASE'), true);
        $exp = App\Models\Expediente::find((int) getenv('STR_EXP'));
        $encargada = App\Models\Usuario::find($base['encargada']);

        try {
            $ganador = app(App\Services\ExpedienteService::class)->ejecutarSorteo(
                expediente: $exp,
                encargada: $encargada,
                descripcion: 'Sorteo concurrente '.getenv('STR_PROC'),
            );
            echo 'OK|'.$ganador->id;
        } catch (\Throwable $e) {
            echo 'ERR|'.$e->getMessage();
        }
        PHP;

    $envBase = ['STR_SUF' => $suf, 'STR_BASE' => json_encode($base), 'STR_EXP' => (string) $expId];

    $procesos = stressSpawn(array_fill(0, $nProcesos, $codigoSorteo), $envBase);

    $exitos = [];
    $errores = [];
    foreach ($procesos as $clave => $process) {
        $out = trim($process->getOutput());
        if (str_starts_with($out, 'OK')) {
            $exitos[$clave] = $out;
        } else {
            $errores[$clave] = $out;
        }
    }

    $verificacion = json_decode(stressTinker(<<<'PHP'
        $expId = (int) getenv('STR_EXP');

        $actuadosSorteo = Illuminate\Support\Facades\DB::table('actuados')
            ->join('catalogo_actuados', 'actuados.catalogo_actuado_id', '=', 'catalogo_actuados.id')
            ->where('actuados.expediente_id', $expId)
            ->where('catalogo_actuados.codigo', 'ACT_SORTEO_INICIAL')
            ->count();

        $asignacionesActivas = Illuminate\Support\Facades\DB::table('asignaciones')
            ->where('expediente_id', $expId)
            ->where('activa', true)
            ->count();

        echo json_encode([
            'actuados_sorteo' => $actuadosSorteo,
            'asignaciones_activas' => $asignacionesActivas,
        ]);
        PHP, $envBase), true);

    expect($exitos)->toHaveCount(1, 'Sorteos ganadores de la carrera (deben ser 1, no '.count($exitos).'): '.json_encode($exitos).' / errores: '.json_encode($errores))
        ->and($verificacion['actuados_sorteo'])->toBe(1, 'ACT_SORTEO_INICIAL registrados: '.$verificacion['actuados_sorteo'])
        ->and($verificacion['asignaciones_activas'])->toBe(1, 'Asignaciones activas: '.$verificacion['asignaciones_activas']);
})
    ->skip(! (bool) getenv('RUN_STRESS_TESTS'), 'Stress habilitado con RUN_STRESS_TESTS=1');

it('T4: apertura masiva concurrente (N procesos x M aperturas) con NUREJ únicos y cadena íntegra', function () {
    $nProcesos = 8;
    $aperturasPorProceso = 3;
    $suf = 't4'.rand(10000, 99999);

    $base = json_decode(stressTinker(stressSeedBase(), ['STR_SUF' => $suf]), true);

    $procesos = stressSpawn(
        array_fill(0, $nProcesos, stressCodigoApertura()),
        ['STR_SUF' => $suf, 'STR_TEC' => $base['tec_username'], 'STR_N' => (string) $aperturasPorProceso],
        180,
    );

    $muestras = [];
    foreach ($procesos as $clave => $process) {
        $out = trim($process->getOutput());
        $err = trim($process->getErrorOutput());
        expect($process->isSuccessful(), "T4 [$clave] falló: OUT=[$out] ERR=[$err]")->toBeTrue();
        if (stripos($out.' '.$err, 'deadlock') !== false || stripos($err, '1213') !== false) {
            $muestras[] = 'DEADLOCK['.$clave.']';
        } else {
            $muestras = array_merge($muestras, array_values(array_filter(array_map('trim', explode(',', $out)))));
        }
    }

    $totalEsperado = $nProcesos * $aperturasPorProceso;

    $codigosValidos = array_filter($muestras, fn ($c) => preg_match('/^\d{4}-\d{5}$/', $c));
    expect(count($codigosValidos))->toBe($totalEsperado, 'Debieron crearse '.$totalEsperado.' NUREJ (muestras: '.json_encode($muestras).')')
        ->and(collect($codigosValidos)->unique()->count())->toBe($totalEsperado, 'NUREJ duplicados bajo apertura concurrente');

    $integridad = json_decode(stressTinker(<<<'PHP'
        $codigos = array_values(array_filter(array_map('trim', explode(',', getenv('STR_CODES')))));
        $pendiente = App\Models\CatalogoEstado::where('codigo', 'PENDIENTE_SORTEO')->first()->id;

        $resumen = [];
        foreach ($codigos as $code) {
            $exp = Illuminate\Support\Facades\DB::table('expedientes')->where('nurej_code', $code)->first();
            if (! $exp) {
                $resumen[] = ['code' => $code, 'existe' => false];
                continue;
            }

            $actuados = Illuminate\Support\Facades\DB::table('actuados')
                ->where('expediente_id', $exp->id)
                ->orderBy('id')
                ->get(['hash_anterior', 'hash_actuado']);

            $rota = false;
            $anterior = null;
            foreach ($actuados as $a) {
                if ($anterior !== null && $a->hash_anterior !== $anterior) {
                    $rota = true;
                }
                $anterior = $a->hash_actuado;
            }

            $resumen[] = [
                'code' => $code,
                'existe' => true,
                'estado_ok' => $exp->estado_actual_id === $pendiente,
                'actuados' => $actuados->count(),
                'rota' => $rota,
                'sin_actuados' => $actuados->isEmpty(),
                'primer_hash' => $actuados->first()?->hash_anterior,
            ];
        }

        echo json_encode($resumen);
        PHP, ['STR_CODES' => implode(',', $codigosValidos)]), true);

    expect($integridad)->toHaveCount($totalEsperado);

    foreach ($integridad as $exp) {
        expect($exp['existe'])->toBeTrue('Expediente '.$exp['code'].' no llegó a la BD')
            ->and($exp['estado_ok'])->toBeTrue('Expediente '.$exp['code'].' no quedó PENDIENTE_SORTEO')
            ->and($exp['actuados'])->toBe(1, 'Cada apertura debe registrar exactamente 1 actuado ('.$exp['code'].')')
            ->and($exp['rota'])->toBeFalse('Cadena de custodia bifurcada en '.$exp['code'])
            ->and($exp['sin_actuados'])->toBeFalse('Expediente '.$exp['code'].' no registró actuado')
            ->and($exp['primer_hash'])->toBeNull();
    }
})
    ->skip(! (bool) getenv('RUN_STRESS_TESTS'), 'Stress habilitado con RUN_STRESS_TESTS=1');

it('T5: sortearTodas vs aperturas concurrentes sin deadlocks ni NUREJ duplicados', function () {
    $suf = 't5'.rand(10000, 99999);
    $base = json_decode(stressTinker(stressSeedBase(), ['STR_SUF' => $suf]), true);

    stressTinker(<<<'PHP'
        $base = json_decode(getenv('STR_BASE'), true);

        $catalogo = App\Models\CatalogoActuado::where('codigo', 'ACT_REGISTRO_DIGITALIZACION')->firstOrFail();

        for ($i = 0; $i < 5; $i++) {
            $exp = App\Models\Expediente::create([
                'nurej_code' => '2026-T5-'.rand(100000, 999999),
                'via' => 'TECNICO',
                'reglamento_id' => $base['reglamento'],
                'estado_actual_id' => $base['est_pendiente'],
                'resumen_hechos' => '[STRESS-T5] base '.$i,
                'fecha_ingreso' => now(),
                'creado_por' => $base['tecnico'],
            ]);
            App\Models\Actuado::create([
                'expediente_id' => $exp->id,
                'catalogo_actuado_id' => $catalogo->id,
                'usuario_id' => $base['tecnico'],
                'estado_anterior_id' => null,
                'estado_nuevo_id' => $base['est_pendiente'],
                'contenido' => ['descripcion' => 'Apertura base '.$i],
            ]);
        }
        echo 'OK';
        PHP, ['STR_BASE' => json_encode($base)]);

    $codigoApertura = stressCodigoApertura();
    $codigoSorteo = <<<'PHP'
        try {
            $base = json_decode(getenv('STR_BASE'), true);
            $resultados = app(App\Services\ExpedienteService::class)->sortearTodas(
                encargada: App\Models\Usuario::find($base['encargada']),
            );
            echo 'OK|'.count($resultados);
        } catch (\Throwable $e) {
            echo 'ERR|'.$e->getMessage();
        }
        PHP;

    $envAperturas = ['STR_SUF' => $suf, 'STR_TEC' => $base['tec_username'], 'STR_N' => '2'];
    $procesos = stressSpawn(array_fill(0, 5, $codigoApertura), $envAperturas, 180);

    $sorteoProcess = new Process(
        [PHP_BINARY, 'artisan', 'tinker', '--execute', $codigoSorteo],
        base_path(),
        ['APP_ENV' => 'testing', 'XDEBUG_MODE' => 'off', 'STR_BASE' => json_encode($base), 'STR_PROC' => 'SORTEO'],
        180,
    );
    $sorteoProcess->start();
    $sorteoProcess->wait();

    $errores = [];
    $codigos = [];

    foreach ($procesos as $clave => $process) {
        $out = trim($process->getOutput());
        $err = trim($process->getErrorOutput());

        if (stripos($out.' '.$err, 'deadlock') !== false || stripos($err, '1213') !== false) {
            $errores[] = "DEADLOCK [$clave] OUT=[$out] ERR=[$err]";
        }

        $codigos = array_merge($codigos, array_values(array_filter(array_map('trim', explode(',', $out)))));
    }

    $outSorteo = trim($sorteoProcess->getOutput());

    if (stripos($outSorteo, 'deadlock') !== false || stripos($sorteoProcess->getErrorOutput(), '1213') !== false) {
        $errores[] = 'DEADLOCK sorteo OUT=['.$outSorteo.'] ERR=['.trim($sorteoProcess->getErrorOutput()).']';
    }

    $exitosSorteo = str_starts_with($outSorteo, 'OK|') ? (int) substr($outSorteo, 3) : -1;

    expect($errores)->toBeEmpty('Errores/deadlocks detectados: '.json_encode($errores))
        ->and(str_starts_with($outSorteo, 'OK|'))->toBeTrue('sortearTodas debió completarse: '.$outSorteo);
    $this->assertGreaterThanOrEqual(5, $exitosSorteo, 'sortearTodas debió sortear al menos 5 expedientes');

    $codigosValidos = array_filter($codigos, fn ($c) => preg_match('/^\d{4}-\d{5}$/', $c));

    $verificacion = json_decode(stressTinker(<<<'PHP'
        $codigos = array_values(array_filter(array_map('trim', explode(',', getenv('STR_CODES')))));

        $duplicados = 0;
        $existentes = 0;
        foreach ($codigos as $code) {
            $count = Illuminate\Support\Facades\DB::table('expedientes')->where('nurej_code', $code)->count();
            $existentes += $count;
            if ($count !== 1) {
                $duplicados += $count === 0 ? -1 : ($count - 1);
            }
        }

        echo json_encode([
            'codigos' => count($codigos),
            'existentes' => $existentes,
            'duplicados' => $duplicados,
        ]);
        PHP, ['STR_CODES' => implode(',', $codigosValidos)]), true);

    expect($verificacion['existentes'])->toBe($verificacion['codigos'], 'NUREJ duplicados/faltantes en la BD (verif: '.json_encode($verificacion).')')
        ->and($verificacion['duplicados'])->toBe(0);
})
    ->skip(! (bool) getenv('RUN_STRESS_TESTS'), 'Stress habilitado con RUN_STRESS_TESTS=1');
