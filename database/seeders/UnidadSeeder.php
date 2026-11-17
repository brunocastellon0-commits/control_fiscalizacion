<?php

namespace Database\Seeders;

use App\Models\Unidad;
use Illuminate\Database\Seeder;

class UnidadSeeder extends Seeder
{
    /**
     * Catálogo maestro de unidades externas (RF-05 / enmienda AM-02): sin
     * usuarios ni sesiones ligadas a la unidad; reutilizable por la
     * remisión inter-unidad y por el Reparto Institucional final.
     * Idempotente: alta/actualización por código, no altera `activa`.
     */
    public function run(): void
    {
        $unidades = [
            ['codigo' => 'TRANSPARENCIA', 'nombre' => 'Unidad de Transparencia (Ley N° 974)'],
            ['codigo' => 'MINISTERIO_PUBLICO', 'nombre' => 'Ministerio Público'],
            ['codigo' => 'REGIMEN_DISCIPLINARIO', 'nombre' => 'Régimen Disciplinario'],
        ];

        foreach ($unidades as $unidad) {
            Unidad::updateOrCreate(['codigo' => $unidad['codigo']], $unidad);
        }
    }
}
