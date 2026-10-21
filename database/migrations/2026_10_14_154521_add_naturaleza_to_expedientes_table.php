<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * B3.2 (AUD-0024): la naturaleza (JURISDICCIONAL/ADMINISTRATIVA) es una
     * propiedad del expediente (RN-05), no del reglamento. Columna nullable
     * de forma transicional: la validación obligatoria vive en
     * StoreExpedienteRequest para toda apertura nueva; los expedientes
     * históricos y los NUREJ hijos permanecen NULL hasta la decisión
     * institucional sobre su clasificación (no se asigna ningún valor por
     * defecto ni backfill). Migración reversible.
     */
    public function up(): void
    {
        Schema::table('expedientes', function (Blueprint $table) {
            $table->enum('naturaleza', ['JURISDICCIONAL', 'ADMINISTRATIVA'])
                ->nullable()
                ->after('via');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expedientes', function (Blueprint $table) {
            $table->dropColumn('naturaleza');
        });
    }
};
