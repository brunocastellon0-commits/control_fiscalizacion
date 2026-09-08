<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pesos desacoplados del sorteo probabilístico por especialidad.
     *
     * Un registro por pareja (usuario, reglamento) con el peso acumulado
     * (+1 por cada sorteo ganado). El peso NO deriva de COUNT() de
     * expedientes, lo que permite ajustes manuales posteriores (balanceo
     * administrativo) — por eso `peso` es signed: admitirá decrementos.
     */
    public function up(): void
    {
        Schema::create('sorteo_pesos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('usuarios')->onDelete('cascade');
            $table->unsignedSmallInteger('reglamento_id');
            $table->integer('peso')->default(0);
            $table->timestamps();

            $table->unique(['usuario_id', 'reglamento_id'], 'uk_usuario_reglamento');
            $table->foreign('reglamento_id')->references('id')->on('reglamentos')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sorteo_pesos');
    }
};
