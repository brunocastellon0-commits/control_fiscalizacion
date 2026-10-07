<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Estados huérfanos sin uso normativo (MAQUINA_ESTADOS.md §1). Solo estos
     * dos códigos pueden inactivarse; CONCLUIDO y el resto de salidas firmes
     * del Reparto Institucional jamás se tocan.
     */
    private const ESTADOS_HUERFANOS = ['EN_INVESTIGACION', 'EN_DESCARGOS'];

    /**
     * Columnas con FK operativa real hacia catalogo_estados. No incluye
     * catalogo_estados.estado_padre_id (no cuenta como uso operativo).
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
     * Agrega la columna activo (aditiva) e inactiva los estados huérfanos que
     * no posean ninguna referencia operativa. Idempotente: puede re-ejecutarse.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('catalogo_estados', 'activo')) {
            Schema::table('catalogo_estados', function (Blueprint $table) {
                $table->boolean('activo')->default(true);
            });
        }

        foreach (self::ESTADOS_HUERFANOS as $codigo) {
            $estado = DB::table('catalogo_estados')->where('codigo', $codigo)->first();

            if ($estado === null || $this->tieneReferenciasOperativas((int) $estado->id)) {
                continue;
            }

            DB::table('catalogo_estados')->where('id', $estado->id)->update(['activo' => false]);
        }
    }

    /**
     * Elimina la columna activo (nueva en esta migración, sin datos previos).
     */
    public function down(): void
    {
        if (Schema::hasColumn('catalogo_estados', 'activo')) {
            Schema::table('catalogo_estados', function (Blueprint $table) {
                $table->dropColumn('activo');
            });
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
};
