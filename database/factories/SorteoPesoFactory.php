<?php

namespace Database\Factories;

use App\Models\Reglamento;
use App\Models\SorteoPeso;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SorteoPeso>
 */
class SorteoPesoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'usuario_id' => Usuario::factory(),
            'reglamento_id' => Reglamento::factory(),
            'peso' => 0,
        ];
    }
}
