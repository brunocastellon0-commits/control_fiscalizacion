<?php

namespace Database\Seeders;

use App\Models\CatalogoRequisito;
use App\Models\Reglamento;
use Illuminate\Database\Seeder;

class CatalogoRequisitoSeeder extends Seeder
{
    /**
     * Requisitos de admisibilidad vinculados a cada reglamento.
     *
     * Los habilitantes de ingreso (formulario, documento de identidad,
     * declaración jurada) son CRÍTICOS: si faltan → ACT_RECHAZO. Los anexos
     * de respaldo son NO críticos: si faltan → ACT_OBSERVACION.
     *
     * Los descargos del Acuerdo 55 pertenecen a la Fase de Ejecución, no al
     * ingreso; se conservan en el catálogo pero desactivados para admisibilidad.
     */
    public function run(): void
    {
        $ac022 = Reglamento::where('codigo', 'AC_022_2018')->firstOrFail();
        $ac054 = Reglamento::where('codigo', 'AC_054_2018')->firstOrFail();
        $ac055 = Reglamento::where('codigo', 'AC_055_2018')->firstOrFail();

        $requisitos = [
            ['reglamento_id' => $ac022->id, 'descripcion' => 'Formulario de denuncia debidamente llenado', 'orden' => 1, 'activo' => true, 'es_critico' => true],
            ['reglamento_id' => $ac022->id, 'descripcion' => 'Documento de identidad del denunciante', 'orden' => 2, 'activo' => true, 'es_critico' => true],
            ['reglamento_id' => $ac022->id, 'descripcion' => 'Pruebas documentales de respaldo', 'orden' => 3, 'activo' => true, 'es_critico' => false],
            ['reglamento_id' => $ac054->id, 'descripcion' => 'Declaración jurada de ingresos', 'orden' => 1, 'activo' => true, 'es_critico' => true],
            ['reglamento_id' => $ac054->id, 'descripcion' => 'Comprobantes de respaldo', 'orden' => 2, 'activo' => true, 'es_critico' => false],
            ['reglamento_id' => $ac055->id, 'descripcion' => 'Formulario de Solicitud de Auditoría o Denuncia Financiera', 'orden' => 1, 'activo' => true, 'es_critico' => true],
            ['reglamento_id' => $ac055->id, 'descripcion' => 'Documento de Identidad del Solicitante o Denunciante', 'orden' => 2, 'activo' => true, 'es_critico' => true],
            ['reglamento_id' => $ac055->id, 'descripcion' => 'Escrito de descargos', 'orden' => 3, 'activo' => false, 'es_critico' => false],
            ['reglamento_id' => $ac055->id, 'descripcion' => 'Pruebas de descargo', 'orden' => 4, 'activo' => false, 'es_critico' => false],
        ];

        foreach ($requisitos as $req) {
            CatalogoRequisito::updateOrCreate(
                ['reglamento_id' => $req['reglamento_id'], 'descripcion' => $req['descripcion']],
                $req
            );
        }
    }
}
