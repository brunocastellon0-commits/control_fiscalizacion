<?php

namespace Database\Seeders;

use App\Models\CatalogoEstado;
use Illuminate\Database\Seeder;

class CatalogoEstadoSeeder extends Seeder
{
    /**
     * Catálogo de estados del expediente (con subestados bajo EN_EVALUACION).
     */
    public function run(): void
    {
        $estados = [
            ['codigo' => 'PENDIENTE_SORTEO', 'nombre' => 'Pendiente de Sorteo', 'padre' => null, 'es_final' => false],
            ['codigo' => 'EN_EVALUACION', 'nombre' => 'En Evaluación', 'padre' => null, 'es_final' => false],
            ['codigo' => 'OBSERVADO', 'nombre' => 'Observado', 'padre' => 'EN_EVALUACION', 'es_final' => false],
            ['codigo' => 'RECHAZADO', 'nombre' => 'Rechazado', 'padre' => 'EN_EVALUACION', 'es_final' => false],
            ['codigo' => 'ADMITIDO', 'nombre' => 'Admitido', 'padre' => null, 'es_final' => false],
            ['codigo' => 'EN_INVESTIGACION', 'nombre' => 'En Investigación', 'padre' => null, 'es_final' => false],
            ['codigo' => 'EN_DESCARGOS', 'nombre' => 'En Descargos', 'padre' => null, 'es_final' => false],
            ['codigo' => 'CONCLUIDO', 'nombre' => 'Concluido', 'padre' => null, 'es_final' => true],
            ['codigo' => 'ARCHIVO_DEFINITIVO', 'nombre' => 'Archivo Definitivo', 'padre' => null, 'es_final' => true],
            ['codigo' => 'EN_SUBSANACION', 'nombre' => 'En Subsanación', 'padre' => null, 'es_final' => false],
            ['codigo' => 'EN_PLANIFICACION', 'nombre' => 'En Planificación', 'padre' => null, 'es_final' => false],
            ['codigo' => 'EN_EJECUCION', 'nombre' => 'En Ejecución', 'padre' => null, 'es_final' => false],
            ['codigo' => 'EN_VISTO_BUENO_FINAL', 'nombre' => 'En Visto Bueno Final', 'padre' => null, 'es_final' => false],
            ['codigo' => 'CONCLUIDO_REMITIDO', 'nombre' => 'Concluido y Remitido', 'padre' => null, 'es_final' => true],
            ['codigo' => 'DERIVADO_TRANSPARENCIA', 'nombre' => 'Derivado a Transparencia', 'padre' => null, 'es_final' => true],
            ['codigo' => 'ARCHIVO_POR_ABANDONO', 'nombre' => 'Archivo por Abandono', 'padre' => null, 'es_final' => true],
            ['codigo' => 'EN_IMPUGNACION', 'nombre' => 'En Impugnación', 'padre' => null, 'es_final' => false],
        ];

        foreach ($estados as $e) {
            if ($e['padre'] === null) {
                CatalogoEstado::updateOrCreate(
                    ['codigo' => $e['codigo']],
                    ['nombre' => $e['nombre'], 'estado_padre_id' => null, 'es_final' => $e['es_final']]
                );
            }
        }

        foreach ($estados as $e) {
            if ($e['padre'] !== null) {
                $padre = CatalogoEstado::where('codigo', $e['padre'])->firstOrFail();

                CatalogoEstado::updateOrCreate(
                    ['codigo' => $e['codigo']],
                    ['nombre' => $e['nombre'], 'estado_padre_id' => $padre->id, 'es_final' => $e['es_final']]
                );
            }
        }
    }
}
