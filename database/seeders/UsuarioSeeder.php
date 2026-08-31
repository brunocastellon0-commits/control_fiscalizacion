<?php

namespace Database\Seeders;

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsuarioSeeder extends Seeder
{
    /**
     * Usuario de prueba por cada rol. Contraseña: password123.
     */
    public function run(): void
    {
        $usuarios = [
            ['ci' => '1000001', 'nombres' => 'Ana', 'apellidos' => 'Encargada Test', 'cargo' => 'Encargada', 'username' => 'encargada', 'rol' => 'ENCARGADA'],
            ['ci' => '1000002', 'nombres' => 'Luis', 'apellidos' => 'Tecnico Test', 'cargo' => 'Técnico', 'username' => 'tecnico', 'rol' => 'TECNICO'],
            ['ci' => '1000003', 'nombres' => 'María', 'apellidos' => 'Auditor Juridico Test', 'cargo' => 'Auditor Jurídico', 'username' => 'aud_juridico', 'rol' => 'AUD_JURIDICO'],
            ['ci' => '1000004', 'nombres' => 'Carlos', 'apellidos' => 'Auditor Financiero Test', 'cargo' => 'Auditor Financiero', 'username' => 'aud_financiero', 'rol' => 'AUD_FINANCIERO'],
            ['ci' => '1000005', 'nombres' => 'Admin', 'apellidos' => 'Sistema Test', 'cargo' => 'Administrador', 'username' => 'admin', 'rol' => 'ADMIN'],
        ];

        foreach ($usuarios as $u) {
            $rol = Rol::where('codigo', $u['rol'])->firstOrFail();

            Usuario::updateOrCreate(
                ['username' => $u['username']],
                [
                    'ci' => $u['ci'],
                    'nombres' => $u['nombres'],
                    'apellidos' => $u['apellidos'],
                    'cargo' => $u['cargo'],
                    'password_hash' => Hash::make('password123'),
                    'rol_id' => $rol->id,
                    'activo' => true,
                ]
            );
        }
    }
}
