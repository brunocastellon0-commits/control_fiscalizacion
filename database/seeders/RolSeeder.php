<?php

namespace Database\Seeders;

use App\Models\Rol;
use Illuminate\Database\Seeder;

class RolSeeder extends Seeder
{
    /**
     * Catálogo base de roles del sistema.
     */
    public function run(): void
    {
        $roles = [
            ['codigo' => 'ENCARGADA', 'nombre' => 'Encargada', 'descripcion' => 'Responsable de la unidad de fiscalización'],
            ['codigo' => 'TECNICO', 'nombre' => 'Técnico', 'descripcion' => 'Técnico de fiscalización'],
            ['codigo' => 'AUD_JURIDICO', 'nombre' => 'Auditor Jurídico', 'descripcion' => 'Auditoría jurídica'],
            ['codigo' => 'AUD_FINANCIERO', 'nombre' => 'Auditor Financiero', 'descripcion' => 'Auditoría financiera'],
            ['codigo' => 'ADMIN', 'nombre' => 'Administrador', 'descripcion' => 'Administración del sistema'],
        ];

        foreach ($roles as $rol) {
            Rol::updateOrCreate(['codigo' => $rol['codigo']], $rol);
        }
    }
}
