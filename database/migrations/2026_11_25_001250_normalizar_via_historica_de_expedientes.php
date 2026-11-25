<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * El campo `via` deja de representar la especialidad y pasa a registrar
     * el origen del ingreso. Los expedientes históricos no tienen ese dato,
     * por lo que se normalizan a INGRESO_VERBAL sin inventar una distrital.
     *
     * Migración de datos irreversible: al sobrescribir la especialidad
     * original no es posible recuperarla en down(). La reversión se hace con
     * una migración correctiva hacia adelante si el contexto lo requiere.
     */
    public function up(): void
    {
        DB::table('expedientes')
            ->whereIn('via', ['TECNICO', 'JURIDICO', 'FINANCIERO'])
            ->update(['via' => 'INGRESO_VERBAL']);
    }

    public function down(): void
    {
        //
    }
};
