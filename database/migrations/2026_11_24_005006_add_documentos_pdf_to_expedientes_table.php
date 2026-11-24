<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('expedientes', function (Blueprint $table) {
            $table->string('boleta_apertura_path', 512)->nullable()->after('creado_por');
            $table->string('ficha_sorteo_path', 512)->nullable()->after('boleta_apertura_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expedientes', function (Blueprint $table) {
            $table->dropColumn(['boleta_apertura_path', 'ficha_sorteo_path']);
        });
    }
};
