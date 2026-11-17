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
            ['codigo' => Rol::CODIGO_ENCARGADA, 'nombre' => 'Encargada', 'descripcion' => 'Responsable de la unidad de fiscalización'],
            ['codigo' => Rol::CODIGO_TECNICO, 'nombre' => 'Técnico', 'descripcion' => 'Técnico de fiscalización'],
            ['codigo' => Rol::CODIGO_AUD_JURIDICO, 'nombre' => 'Auditor Jurídico', 'descripcion' => 'Auditoría jurídica'],
            ['codigo' => Rol::CODIGO_AUD_FINANCIERO, 'nombre' => 'Auditor Financiero', 'descripcion' => 'Auditoría financiera'],
            ['codigo' => Rol::CODIGO_ADMIN, 'nombre' => 'Administrador', 'descripcion' => 'Administración del sistema'],
        ];

        foreach ($roles as $rol) {
            Rol::updateOrCreate(['codigo' => $rol['codigo']], $rol);
        }
    }
}
