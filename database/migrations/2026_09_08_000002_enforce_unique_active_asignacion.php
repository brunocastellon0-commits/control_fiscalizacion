<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Refuerza RF-03 a nivel de motor de BD: un expediente nunca puede tener
     * dos bandejas activas simultáneamente. La columna generada `activa_key`
     * vale `expediente_id` solo cuando `activa = 1` (NULL en el resto), y el
     * índice único `uq_asignacion_activa` admite múltiples NULLs, simulando
     * un índice único parcial. Además se agrega el índice compuesto
     * `(expediente_id, activa)` para que el `SELECT ... FOR UPDATE` de la
     * reasignación haga seeks exactos en vez de escanear la tabla.
     */
    public function up(): void
    {
        Schema::table('asignaciones', function ($table) {
            $table->unsignedBigInteger('activa_key')
                ->storedAs('IF(activa = 1, expediente_id, NULL)')
                ->after('activa');
        });

        DB::statement('ALTER TABLE asignaciones ADD UNIQUE KEY uq_asignacion_activa (activa_key)');
        DB::statement('ALTER TABLE asignaciones ADD INDEX idx_asignaciones_exp_activa (expediente_id, activa)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE asignaciones DROP INDEX uq_asignacion_activa');
        DB::statement('ALTER TABLE asignaciones DROP INDEX idx_asignaciones_exp_activa');
        Schema::table('asignaciones', function ($table) {
            $table->dropColumn('activa_key');
        });
    }
};
