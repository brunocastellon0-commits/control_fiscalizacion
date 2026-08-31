<?php

namespace Database\Factories;

use App\Models\Reglamento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reglamento>
 */
class ReglamentoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => 'AC_'.fake()->unique()->numerify('###_####'),
            'nombre' => fake()->words(3, true),
            'version' => '1.0',
            'vigente_desde' => '2020-01-01',
            'vigente_hasta' => null,
            'activo' => true,
        ];
    }
}
