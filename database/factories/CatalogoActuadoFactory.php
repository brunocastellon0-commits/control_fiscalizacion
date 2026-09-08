<?php

namespace Database\Factories;

use App\Models\CatalogoActuado;
use App\Models\Rol;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CatalogoActuado>
 */
class CatalogoActuadoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => 'ACT_'.fake()->unique()->numerify('####'),
            'nombre' => fake()->words(2, true),
            'fase' => 'ADMISIBILIDAD',
            'rol_id' => Rol::factory(),
            'reglamento_id' => null,
            'estado_origen_id' => null,
            'estado_destino_id' => null,
            'es_automatico' => false,
            'requiere_adjunto' => false,
            'descripcion' => null,
        ];
    }
}
