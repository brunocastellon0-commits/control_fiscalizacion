<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hoja de ruta del trámite cuando el ingreso proviene de la distrital.
     * Columna nullable: los expedientes con ingreso verbal no la requieren.
     */
    public function up(): void
    {
        Schema::table('expedientes', function (Blueprint $table) {
            $table->string('numero_hoja_ruta', 50)->nullable()->after('via');
        });
    }

    public function down(): void
    {
        Schema::table('expedientes', function (Blueprint $table) {
            $table->dropColumn('numero_hoja_ruta');
        });
    }
};
