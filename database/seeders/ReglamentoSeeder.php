<?php

namespace Database\Seeders;

use App\Models\Reglamento;
use Illuminate\Database\Seeder;

class ReglamentoSeeder extends Seeder
{
    /**
     * Normas / reglamentos base del sistema.
     */
    public function run(): void
    {
        $reglamentos = [
            [
                'codigo' => 'AC_022_2018',
                'nombre' => 'Reglamento de Control y Fiscalización (AC_022_2018)',
                'version' => '1.0',
                'vigente_desde' => '2018-05-15',
                'vigente_hasta' => null,
                'activo' => true,
            ],
            [
                'codigo' => 'AC_054_2018',
                'nombre' => 'Reglamento de Fiscalización (AC_054_2018)',
                'version' => '1.0',
                'vigente_desde' => '2018-09-01',
                'vigente_hasta' => null,
                'activo' => true,
            ],
            [
                'codigo' => 'AC_055_2018',
                'nombre' => 'Reglamento de Sanciones y Descargos (AC_055_2018)',
                'version' => '1.0',
                'vigente_desde' => '2018-09-01',
                'vigente_hasta' => null,
                'activo' => true,
            ],
        ];

        foreach ($reglamentos as $r) {
            Reglamento::updateOrCreate(['codigo' => $r['codigo'], 'version' => $r['version']], $r);
        }
    }
}
