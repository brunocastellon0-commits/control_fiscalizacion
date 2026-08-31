<?php

namespace Database\Factories;

use App\Models\CatalogoEstado;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CatalogoEstado>
 */
class CatalogoEstadoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => 'EST_'.fake()->unique()->numerify('####'),
            'nombre' => fake()->words(2, true),
            'estado_padre_id' => null,
            'es_final' => false,
        ];
    }
}
