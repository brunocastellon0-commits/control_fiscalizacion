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
        Schema::create('asignaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expediente_id')->constrained('expedientes');
            $table->foreignId('usuario_id')->constrained('usuarios');
            $table->unsignedSmallInteger('rol_id');
            $table->unsignedBigInteger('actuado_origen_id');
            $table->timestamp('fecha_asignacion')->useCurrent();
            $table->boolean('activa')->default(true);

            $table->foreign('rol_id')->references('id')->on('roles');
            $table->foreign('actuado_origen_id')->references('id')->on('actuados');
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asignaciones');
    }
};
