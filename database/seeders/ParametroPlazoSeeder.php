<?php

namespace Database\Seeders;

use App\Models\ParametroPlazo;
use App\Models\Reglamento;
use Illuminate\Database\Seeder;

class ParametroPlazoSeeder extends Seeder
{
    /**
     * Parámetros de plazo por reglamento.
     */
    public function run(): void
    {
        $ac022 = Reglamento::where('codigo', 'AC_022_2018')->firstOrFail();
        $ac054 = Reglamento::where('codigo', 'AC_054_2018')->firstOrFail();
        $ac055 = Reglamento::where('codigo', 'AC_055_2018')->firstOrFail();

        $plazos = [
            ['reglamento_id' => $ac022->id, 'tipo_plazo' => 'EVALUACION', 'subtipo' => null, 'dias_habiles' => 2, 'base_legal' => 'AC_022_2018', 'activo' => true],
            ['reglamento_id' => $ac022->id, 'tipo_plazo' => 'SUBSANACION', 'subtipo' => null, 'dias_habiles' => 3, 'base_legal' => 'AC_022_2018', 'activo' => true],
            ['reglamento_id' => $ac022->id, 'tipo_plazo' => 'EJECUCION_JURISDICCIONAL', 'subtipo' => null, 'dias_habiles' => 10, 'base_legal' => 'AC_022_2018', 'activo' => true],
            ['reglamento_id' => $ac022->id, 'tipo_plazo' => 'EJECUCION_ADMINISTRATIVA', 'subtipo' => null, 'dias_habiles' => 15, 'base_legal' => 'AC_022_2018', 'activo' => true],
            ['reglamento_id' => $ac054->id, 'tipo_plazo' => 'EVALUACION', 'subtipo' => null, 'dias_habiles' => 5, 'base_legal' => 'AC_054_2018', 'activo' => true],
            ['reglamento_id' => $ac055->id, 'tipo_plazo' => 'EVALUACION', 'subtipo' => null, 'dias_habiles' => 5, 'base_legal' => 'AC_055_2018', 'activo' => true],
            ['reglamento_id' => $ac055->id, 'tipo_plazo' => 'DESCARGOS', 'subtipo' => null, 'dias_habiles' => 5, 'base_legal' => 'AC_055_2018', 'activo' => true],
        ];

        foreach ($plazos as $p) {
            ParametroPlazo::updateOrCreate(
                ['reglamento_id' => $p['reglamento_id'], 'tipo_plazo' => $p['tipo_plazo'], 'subtipo' => $p['subtipo']],
                $p
            );
        }
    }
}
