<?php

namespace Database\Seeders;

use App\Models\CatalogoRequisito;
use App\Models\Reglamento;
use Illuminate\Database\Seeder;

class CatalogoRequisitoSeeder extends Seeder
{
    /**
     * Requisitos base vinculados a cada reglamento.
     */
    public function run(): void
    {
        $ac022 = Reglamento::where('codigo', 'AC_022_2018')->firstOrFail();
        $ac054 = Reglamento::where('codigo', 'AC_054_2018')->firstOrFail();
        $ac055 = Reglamento::where('codigo', 'AC_055_2018')->firstOrFail();

        $requisitos = [
            ['reglamento_id' => $ac022->id, 'descripcion' => 'Formulario de denuncia debidamente llenado', 'orden' => 1, 'activo' => true],
            ['reglamento_id' => $ac022->id, 'descripcion' => 'Documento de identidad del denunciante', 'orden' => 2, 'activo' => true],
            ['reglamento_id' => $ac022->id, 'descripcion' => 'Pruebas documentales de respaldo', 'orden' => 3, 'activo' => true],
            ['reglamento_id' => $ac054->id, 'descripcion' => 'Declaración jurada de ingresos', 'orden' => 1, 'activo' => true],
            ['reglamento_id' => $ac054->id, 'descripcion' => 'Comprobantes de respaldo', 'orden' => 2, 'activo' => true],
            ['reglamento_id' => $ac055->id, 'descripcion' => 'Escrito de descargos', 'orden' => 1, 'activo' => true],
            ['reglamento_id' => $ac055->id, 'descripcion' => 'Pruebas de descargo', 'orden' => 2, 'activo' => true],
        ];

        foreach ($requisitos as $req) {
            CatalogoRequisito::updateOrCreate(
                ['reglamento_id' => $req['reglamento_id'], 'descripcion' => $req['descripcion']],
                $req
            );
        }
    }
}
