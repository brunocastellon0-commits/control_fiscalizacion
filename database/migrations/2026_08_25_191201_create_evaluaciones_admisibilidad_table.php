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
        Schema::create('evaluaciones_admisibilidad', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expediente_id')->constrained('expedientes');
            $table->foreignId('requisito_id')->constrained('catalogo_requisitos');
            $table->boolean('cumple');
            $table->unsignedBigInteger('actuado_id');
            $table->timestamp('fecha')->useCurrent();

            $table->foreign('actuado_id')->references('id')->on('actuados');
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evaluaciones_admisibilidad');
    }
};
