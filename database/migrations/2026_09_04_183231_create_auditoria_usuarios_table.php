<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Auditoría de acciones administrativas críticas sobre usuarios del
     * sistema (Consejo de la Magistratura): quién, a quién, qué y desde dónde.
     * Registro APPEND-ONLY: no se edita ni borra históricamente.
     */
    public function up(): void
    {
        Schema::create('auditoria_usuarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('usuarios')->onDelete('cascade');
            $table->foreignId('usuario_objetivo_id')->constrained('usuarios')->onDelete('cascade');
            $table->string('accion', 100);
            $table->string('ip_origen', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auditoria_usuarios');
    }
};
