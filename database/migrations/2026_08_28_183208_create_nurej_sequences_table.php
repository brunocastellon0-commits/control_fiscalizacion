<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Secuencia atómica de correlativos NUREJ por año. Una única fila por
     * ejercicio sirve de bloqueo pesimista (SELECT ... FOR UPDATE) para
     * serializar la generación de códigos ante concurrencia.
     */
    public function up(): void
    {
        Schema::create('nurej_sequences', function (Blueprint $table) {
            $table->unsignedSmallInteger('anio')->primary();
            $table->unsignedBigInteger('correlativo')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nurej_sequences');
    }
};
