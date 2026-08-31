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
        Schema::create('catalogo_actuados', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('codigo', 60)->unique();
            $table->string('nombre', 150);
            $table->string('fase', 60);
            $table->unsignedSmallInteger('rol_id')->nullable();
            $table->unsignedSmallInteger('reglamento_id')->nullable();
            $table->unsignedSmallInteger('estado_origen_id')->nullable();
            $table->unsignedSmallInteger('estado_destino_id')->nullable();
            $table->boolean('es_automatico')->default(false);
            $table->boolean('requiere_adjunto')->default(false);
            $table->text('descripcion')->nullable();

            $table->foreign('rol_id')->references('id')->on('roles');
            $table->foreign('reglamento_id')->references('id')->on('reglamentos');
            $table->foreign('estado_origen_id')->references('id')->on('catalogo_estados');
            $table->foreign('estado_destino_id')->references('id')->on('catalogo_estados');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('catalogo_actuados');
    }
};
