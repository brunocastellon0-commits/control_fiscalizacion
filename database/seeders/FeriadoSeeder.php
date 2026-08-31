<?php

namespace Database\Seeders;

use App\Models\Feriado;
use Illuminate\Database\Seeder;

class FeriadoSeeder extends Seeder
{
    /**
     * Feriados de referencia para el cálculo de días hábiles.
     */
    public function run(): void
    {
        $feriados = [
            ['fecha' => '2026-01-01', 'descripcion' => 'Año Nuevo', 'ambito' => 'NACIONAL'],
            ['fecha' => '2026-05-01', 'descripcion' => 'Día del Trabajador', 'ambito' => 'NACIONAL'],
            ['fecha' => '2026-07-16', 'descripcion' => 'Feria de la Virgen del Carmen', 'ambito' => 'LOCAL'],
            ['fecha' => '2026-08-06', 'descripcion' => 'Día de la Independencia', 'ambito' => 'NACIONAL'],
            ['fecha' => '2026-12-25', 'descripcion' => 'Navidad', 'ambito' => 'NACIONAL'],
        ];

        foreach ($feriados as $f) {
            Feriado::updateOrCreate(['fecha' => $f['fecha']], $f);
        }
    }
}
