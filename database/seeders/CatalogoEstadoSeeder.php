<?php

namespace Database\Seeders;

use App\Models\CatalogoEstado;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CatalogoEstadoSeeder extends Seeder
{
    /**
     * Estados huérfanos sin uso normativo (mismo criterio que la migración
     * 2026_10_07_100001_inactivar_estados_huerfanos): solo estos dos códigos
     * pueden alternar su marca `activo`.
     */
    private const ESTADOS_HUERFANOS = ['EN_INVESTIGACION', 'EN_DESCARGOS'];

    /**
     * Columnas con FK operativa real hacia catalogo_estados (no incluye
     * catalogo_estados.estado_padre_id).
     *
     * @var list<array{tabla: string, columna: string}>
     */
    private const REFERENCIAS_OPERATIVAS = [
        ['tabla' => 'expedientes', 'columna' => 'estado_actual_id'],
        ['tabla' => 'actuados', 'columna' => 'estado_anterior_id'],
        ['tabla' => 'actuados', 'columna' => 'estado_nuevo_id'],
        ['tabla' => 'catalogo_actuados', 'columna' => 'estado_origen_id'],
        ['tabla' => 'catalogo_actuados', 'columna' => 'estado_destino_id'],
    ];

    /**
     * Catálogo de estados del expediente (con subestados bajo EN_EVALUACION).
     */
    public function run(): void
    {
        // Renombrado a PENDIENTE_VISTO_BUENO_FINAL (E10-S1): el prefijo PENDIENTE_
        // indica que la pelota está en la bandeja de la Encargada. Depura el
        // código heredado EN_VISTO_BUENO_FINAL para entornos ya migrados.
        CatalogoEstado::where('codigo', 'EN_VISTO_BUENO_FINAL')->delete();

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
            ['codigo' => 'PENDIENTE_VISTO_BUENO', 'nombre' => 'Pendiente de Visto Bueno', 'padre' => null, 'es_final' => false],
            ['codigo' => 'EN_EJECUCION', 'nombre' => 'En Ejecución', 'padre' => null, 'es_final' => false],
            ['codigo' => 'PENDIENTE_APROBACION_AMPLIACION', 'nombre' => 'Pendiente de Aprobación de Ampliación', 'padre' => null, 'es_final' => false],
            ['codigo' => 'PENDIENTE_VISTO_BUENO_FINAL', 'nombre' => 'Pendiente de Visto Bueno Final', 'padre' => null, 'es_final' => false],
            ['codigo' => 'LISTO_PARA_REPARTO', 'nombre' => 'Listo para Reparto', 'padre' => null, 'es_final' => false],
            ['codigo' => 'CONCLUIDO_REMITIDO', 'nombre' => 'Concluido y Remitido', 'padre' => null, 'es_final' => true],
            ['codigo' => 'PENDIENTE_REMISION_TRANSPARENCIA', 'nombre' => 'Pendiente de Remisión a Transparencia', 'padre' => null, 'es_final' => false],
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

        $this->sincronizarEstadosHuerfanos();
    }

    /**
     * Marca activo=false los estados huérfanos sin ninguna referencia en las
     * cinco FK operativas, y activo=true los que sí tienen referencias (para
     * no ocultar un estado en uso). No toca el resto de los estados.
     */
    private function sincronizarEstadosHuerfanos(): void
    {
        foreach (self::ESTADOS_HUERFANOS as $codigo) {
            $estado = CatalogoEstado::where('codigo', $codigo)->first();

            if ($estado === null) {
                continue;
            }

            CatalogoEstado::where('id', $estado->id)->update([
                'activo' => $this->tieneReferenciasOperativas((int) $estado->id),
            ]);
        }
    }

    /**
     * Determina si el estado tiene al menos una fila que lo referencie en
     * cualquiera de las cinco FK operativas.
     */
    private function tieneReferenciasOperativas(int $estadoId): bool
    {
        foreach (self::REFERENCIAS_OPERATIVAS as $referencia) {
            $existe = DB::table($referencia['tabla'])
                ->where($referencia['columna'], $estadoId)
                ->exists();

            if ($existe) {
                return true;
            }
        }

        return false;
    }
}
