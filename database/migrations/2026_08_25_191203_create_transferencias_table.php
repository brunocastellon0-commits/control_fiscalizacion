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
        Schema::create('transferencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expediente_id')->constrained('expedientes');
            $table->string('unidad_destino', 120);
            $table->unsignedBigInteger('actuado_remision_id');
            $table->unsignedBigInteger('actuado_recepcion_id')->nullable();
            $table->string('estado', 20)->default('PENDIENTE');

            $table->foreign('actuado_remision_id')->references('id')->on('actuados');
            $table->foreign('actuado_recepcion_id')->references('id')->on('actuados');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transferencias');
    }
};
