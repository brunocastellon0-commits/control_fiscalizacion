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
        Schema::create('plazos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expediente_id')->constrained('expedientes');
            $table->string('tipo_plazo', 30);
            $table->unsignedBigInteger('parametro_plazo_id')->nullable();
            $table->smallInteger('dias_habiles_otorgados');
            $table->date('fecha_inicio');
            $table->date('fecha_limite');
            $table->string('estado', 20)->default('VIGENTE');
            $table->date('fecha_pausa')->nullable();
            $table->date('fecha_reanudacion')->nullable();
            $table->boolean('fuera_de_plazo')->default(false);
            $table->unsignedBigInteger('actuado_disparador_id');
            $table->unsignedBigInteger('actuado_cierre_id')->nullable();

            $table->foreign('parametro_plazo_id')->references('id')->on('parametros_plazo');
            $table->foreign('actuado_disparador_id')->references('id')->on('actuados');
            $table->foreign('actuado_cierre_id')->references('id')->on('actuados');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plazos');
    }
};
