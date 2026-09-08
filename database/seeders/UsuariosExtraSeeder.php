<?php

namespace Database\Seeders;

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsuariosExtraSeeder extends Seeder
{
    /**
     * Segundo lote de usuarios de prueba: 2 adicionales por cada rol, para
     * ejercitar el sorteo probabilístico con varios candidatos activos por vía.
     * Idempotente: re-ejecutar no duplica (updateOrCreate por username).
     * Contraseña compartida con UsuarioSeeder: password123.
     */
    public function run(): void
    {
        $usuarios = [
            ['ci' => '2000001', 'nombres' => 'Marta', 'apellidos' => 'Fernandez Rios', 'cargo' => 'Encargada - Unidad', 'username' => 'encargada02', 'rol' => 'ENCARGADA'],
            ['ci' => '2000002', 'nombres' => 'Carmen', 'apellidos' => 'Gutierrez Paz', 'cargo' => 'Encargada - Unidad', 'username' => 'encargada03', 'rol' => 'ENCARGADA'],
            ['ci' => '2000003', 'nombres' => 'José', 'apellidos' => 'Mamani Choque', 'cargo' => 'Técnico de Fiscalización', 'username' => 'tecnico02', 'rol' => 'TECNICO'],
            ['ci' => '2000004', 'nombres' => 'Rodrigo', 'apellidos' => 'Vaca Diez', 'cargo' => 'Técnico de Fiscalización', 'username' => 'tecnico03', 'rol' => 'TECNICO'],
            ['ci' => '2000005', 'nombres' => 'Valentina', 'apellidos' => 'Rocha Medina', 'cargo' => 'Auditor Jurídico', 'username' => 'aud_juridico02', 'rol' => 'AUD_JURIDICO'],
            ['ci' => '2000006', 'nombres' => 'Fernando', 'apellidos' => 'Salinas Vera', 'cargo' => 'Auditor Jurídico', 'username' => 'aud_juridico03', 'rol' => 'AUD_JURIDICO'],
            ['ci' => '2000007', 'nombres' => 'Paola', 'apellidos' => 'Urioste Ramos', 'cargo' => 'Auditor Financiero', 'username' => 'aud_financiero02', 'rol' => 'AUD_FINANCIERO'],
            ['ci' => '2000008', 'nombres' => 'Ricardo', 'apellidos' => 'Paredes Loza', 'cargo' => 'Auditor Financiero', 'username' => 'aud_financiero03', 'rol' => 'AUD_FINANCIERO'],
            ['ci' => '2000009', 'nombres' => 'Sofía', 'apellidos' => 'Delgadillo Ortiz', 'cargo' => 'Administradora', 'username' => 'admin02', 'rol' => 'ADMIN'],
            ['ci' => '2000010', 'nombres' => 'Diego', 'apellidos' => 'Quispe Andrade', 'cargo' => 'Administrador', 'username' => 'admin03', 'rol' => 'ADMIN'],
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
