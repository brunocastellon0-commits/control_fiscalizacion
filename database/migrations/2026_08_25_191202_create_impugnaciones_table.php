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
        Schema::create('impugnaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expediente_id')->constrained('expedientes');
            $table->unsignedBigInteger('actuado_rechazo_id');
            $table->timestamp('fecha_presentacion')->useCurrent();
            $table->date('fecha_limite_resolucion');
            $table->string('resultado', 20)->default('PENDIENTE');
            $table->unsignedBigInteger('actuado_resolucion_id')->nullable();

            $table->foreign('actuado_rechazo_id')->references('id')->on('actuados');
            $table->foreign('actuado_resolucion_id')->references('id')->on('actuados');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('impugnaciones');
    }
};
