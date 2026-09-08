<?php

namespace Database\Factories;

use App\Models\CatalogoRequisito;
use App\Models\Reglamento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CatalogoRequisito>
 */
class CatalogoRequisitoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reglamento_id' => Reglamento::factory(),
            'descripcion' => fake()->sentence(6),
            'orden' => fake()->numberBetween(1, 20),
            'activo' => true,
            'es_critico' => false,
        ];
    }
}
