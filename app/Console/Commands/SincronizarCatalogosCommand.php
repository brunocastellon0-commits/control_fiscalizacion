<?php

namespace App\Console\Commands;

use App\Models\CatalogoActuado;
use App\Models\CatalogoEstado;
use Database\Seeders\CatalogoActuadoSeeder;
use Database\Seeders\CatalogoEstadoSeeder;
use Database\Seeders\CatalogoRequisitoSeeder;
use Database\Seeders\FeriadoSeeder;
use Database\Seeders\ParametroPlazoSeeder;
use Database\Seeders\ReglamentoSeeder;
use Database\Seeders\RolSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

#[Signature('catalogos:sync {--dry-run : Solo reporta el estado actual; no escribe en la BD.}')]
#[Description('Sincroniza unicamente los catalogos operativos, sin seeders de demo ni borrado de datos.')]
class SincronizarCatalogosCommand extends Command
{
    /** @var list<class-string> */
    private const SEEDERS = [
        RolSeeder::class,
        CatalogoEstadoSeeder::class,
        ReglamentoSeeder::class,
        CatalogoRequisitoSeeder::class,
        ParametroPlazoSeeder::class,
        FeriadoSeeder::class,
        CatalogoActuadoSeeder::class,
    ];

    /** @var list<string> */
    private const ESTADOS_CRITICOS = [
        'ADMITIDO',
        'EN_PLANIFICACION',
        'EN_SUBSANACION',
        'EN_EJECUCION',
    ];

    /** @var list<string> */
    private const ACTUADOS_CRITICOS = [
        'ACT_ADMISION',
        'ACT_OBSERVACION',
        'ACT_RECHAZO',
        'ACT_PASO_PLANIFICACION',
    ];

    public function handle(): int
    {
        if (! $this->sinCodigosHeredados()) {
            return self::FAILURE;
        }

        $antes = $this->contar();
        $this->line("Antes: estados={$antes['estados']}, actuados={$antes['actuados']}.");

        $faltantes = $this->faltantesCriticos();

        if ($faltantes === []) {
            $this->info('Los catalogos criticos ya estan presentes.');
        } else {
            $this->warn('Faltan catalogos criticos: '.implode(', ', $faltantes).'.');
        }

        if ($this->option('dry-run')) {
            $this->warn('Dry-run: no se escribio en la BD.');

            return self::SUCCESS;
        }

        try {
            DB::transaction(function (): void {
                foreach (self::SEEDERS as $seeder) {
                    $this->call($seeder);
                    $this->line('  Sincronizado: '.class_basename($seeder));
                }

                $faltantesTrasSincronizar = $this->faltantesCriticos();

                if ($faltantesTrasSincronizar !== []) {
                    throw new RuntimeException(
                        'Faltan catalogos criticos tras la sincronizacion: '.implode(', ', $faltantesTrasSincronizar).'.'
                    );
                }
            });
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $despues = $this->contar();
        $this->info("Despues: estados={$despues['estados']}, actuados={$despues['actuados']}.");
        $this->info('Sincronizacion de catalogos completada.');

        return self::SUCCESS;
    }

    /**
     * Los seeders de catalogo eliminan estos codigos heredados. Abortamos antes
     * para no borrar datos sin confirmacion explicita.
     */
    private function sinCodigosHeredados(): bool
    {
        $estadoHeredado = CatalogoEstado::where('codigo', 'EN_VISTO_BUENO_FINAL')->exists();
        $actuadosHeredados = CatalogoActuado::whereIn('codigo', [
            'ACT_DERIVACION_INCOMPETENCIA',
            'ACT_REMISION_TRANSPARENCIA',
        ])->exists();

        if (! $estadoHeredado && ! $actuadosHeredados) {
            return true;
        }

        $this->error('La BD contiene codigos heredados que los seeders borrarian. Revision manual requerida.');

        return false;
    }

    /** @return array{estados: int, actuados: int} */
    private function contar(): array
    {
        return [
            'estados' => CatalogoEstado::count(),
            'actuados' => CatalogoActuado::count(),
        ];
    }

    /** @return list<string> */
    private function faltantesCriticos(): array
    {
        $estados = CatalogoEstado::whereIn('codigo', self::ESTADOS_CRITICOS)
            ->pluck('codigo')
            ->all();
        $actuados = CatalogoActuado::whereIn('codigo', self::ACTUADOS_CRITICOS)
            ->pluck('codigo')
            ->all();

        return array_values(array_diff(
            array_merge(self::ESTADOS_CRITICOS, self::ACTUADOS_CRITICOS),
            array_merge($estados, $actuados),
        ));
    }
}
