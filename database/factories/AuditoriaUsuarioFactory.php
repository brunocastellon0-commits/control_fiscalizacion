<?php

namespace Database\Factories;

use App\Models\AuditoriaUsuario;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditoriaUsuario>
 */
class AuditoriaUsuarioFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'admin_id' => Usuario::factory(),
            'usuario_objetivo_id' => Usuario::factory(),
            'accion' => AuditoriaUsuario::ACCION_INACTIVACION,
            'ip_origen' => fake()->ipv4(),
        ];
    }
}
