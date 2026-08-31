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
        Schema::create('expedientes', function (Blueprint $table) {
            $table->id();
            $table->string('nurej_code', 30)->unique();
            $table->unsignedBigInteger('nurej_padre_id')->nullable();
            $table->string('via', 20);
            $table->unsignedSmallInteger('reglamento_id');
            $table->unsignedSmallInteger('estado_actual_id');
            $table->text('resumen_hechos')->nullable();
            $table->timestamp('fecha_ingreso')->useCurrent();
            $table->foreignId('creado_por')->constrained('usuarios');
            $table->timestamps();

            $table->foreign('nurej_padre_id')->references('id')->on('expedientes');
            $table->foreign('reglamento_id')->references('id')->on('reglamentos');
            $table->foreign('estado_actual_id')->references('id')->on('catalogo_estados');
            
            $table->index('estado_actual_id', 'idx_expedientes_estado');
            $table->index('nurej_padre_id', 'idx_expedientes_padre');
            $table->index('via', 'idx_expedientes_via');
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expedientes');
    }
};
