<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Vincula cada fila del checklist de admisibilidad con el operador que
     * realizó la evaluación (auditoría de quién marcó cada requisito).
     */
    public function up(): void
    {
        Schema::table('evaluaciones_admisibilidad', function (Blueprint $table) {
            $table->unsignedBigInteger('operador_id')->nullable()->after('requisito_id');

            $table->foreign('operador_id')->references('id')->on('usuarios');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('evaluaciones_admisibilidad', function (Blueprint $table) {
            $table->dropForeign(['operador_id']);
            $table->dropColumn('operador_id');
        });
    }
};
