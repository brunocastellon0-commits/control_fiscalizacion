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
        Schema::table('adjuntos', function (Blueprint $table) {
            $table->foreignId('requisito_id')->nullable()->after('actuado_id')->constrained('catalogo_requisitos');
            $table->string('finalidad', 20)->nullable()->after('nombre_original');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('adjuntos', function (Blueprint $table) {
            $table->dropForeign(['requisito_id']);
            $table->dropColumn(['requisito_id', 'finalidad']);
        });
    }
};
