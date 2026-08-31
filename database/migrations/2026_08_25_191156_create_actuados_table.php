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
        Schema::create('actuados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expediente_id')->constrained('expedientes');
            $table->unsignedSmallInteger('catalogo_actuado_id');
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->timestamp('fecha_hora')->useCurrent();
            $table->unsignedSmallInteger('estado_anterior_id')->nullable();
            $table->unsignedSmallInteger('estado_nuevo_id');
            $table->json('contenido');
            $table->unsignedBigInteger('actuado_referencia_id')->nullable();
            $table->string('ip_origen', 45)->nullable();
            $table->char('hash_actuado', 64);
            $table->char('hash_anterior', 64)->nullable();

            $table->foreign('catalogo_actuado_id')->references('id')->on('catalogo_actuados');
            $table->foreign('usuario_id')->references('id')->on('usuarios');
            $table->foreign('estado_anterior_id')->references('id')->on('catalogo_estados');
            $table->foreign('estado_nuevo_id')->references('id')->on('catalogo_estados');
            $table->foreign('actuado_referencia_id')->references('id')->on('actuados');

            $table->index(['expediente_id', 'fecha_hora'], 'idx_actuados_exp_fecha');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('actuados');
    }
};
