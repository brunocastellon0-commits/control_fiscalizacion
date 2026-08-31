<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolSeeder::class,
            CatalogoEstadoSeeder::class,
            ReglamentoSeeder::class,
            CatalogoRequisitoSeeder::class,
            ParametroPlazoSeeder::class,
            FeriadoSeeder::class,
            UsuarioSeeder::class,
            CatalogoActuadoSeeder::class,
        ]);
    }
}
