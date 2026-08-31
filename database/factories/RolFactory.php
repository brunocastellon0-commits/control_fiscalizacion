<?php

namespace Database\Factories;

use App\Models\Rol;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Rol>
 */
class RolFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => 'ROL'.fake()->unique()->numberBetween(1000, 9999),
            'nombre' => fake()->words(2, true),
            'descripcion' => fake()->sentence(),
        ];
    }
}
