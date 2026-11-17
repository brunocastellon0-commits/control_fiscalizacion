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
            ['ci' => '1000001', 'nombres' => 'Ana', 'apellidos' => 'Encargada Test', 'cargo' => 'Encargada', 'username' => 'encargada', 'rol' => Rol::CODIGO_ENCARGADA],
            ['ci' => '1000002', 'nombres' => 'Luis', 'apellidos' => 'Tecnico Test', 'cargo' => 'Técnico', 'username' => 'tecnico', 'rol' => Rol::CODIGO_TECNICO],
            ['ci' => '1000003', 'nombres' => 'María', 'apellidos' => 'Auditor Juridico Test', 'cargo' => 'Auditor Jurídico', 'username' => 'aud_juridico', 'rol' => Rol::CODIGO_AUD_JURIDICO],
            ['ci' => '1000004', 'nombres' => 'Carlos', 'apellidos' => 'Auditor Financiero Test', 'cargo' => 'Auditor Financiero', 'username' => 'aud_financiero', 'rol' => Rol::CODIGO_AUD_FINANCIERO],
            ['ci' => '1000005', 'nombres' => 'Admin', 'apellidos' => 'Sistema Test', 'cargo' => 'Administrador', 'username' => 'admin', 'rol' => Rol::CODIGO_ADMIN],
        ];

        foreach ($usuarios as $u) {
            $rol = Rol::where('codigo', $u['rol'])->firstOrFail();

            // password_hash no es mass-assignable ($fillable): se persiste en
            // un ámbito unguarded propio de este seeder.
            Usuario::unguarded(function () use ($u, $rol): void {
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
            });
        }
    }
}
