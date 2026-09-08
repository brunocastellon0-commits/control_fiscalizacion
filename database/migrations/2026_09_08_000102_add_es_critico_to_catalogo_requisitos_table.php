<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Distingue requisitos habilitantes críticos (falta → ACT_RECHAZO) de
     * anexos de respaldo no críticos (falta → ACT_OBSERVACION).
     */
    public function up(): void
    {
        Schema::table('catalogo_requisitos', function (Blueprint $table) {
            $table->boolean('es_critico')->default(false)->after('activo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('catalogo_requisitos', function (Blueprint $table) {
            $table->dropColumn('es_critico');
        });
    }
};
