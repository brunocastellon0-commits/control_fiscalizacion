<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla de sesiones del framework (driver SESSION_DRIVER=database).
     *
     * Sustituye a la migración por defecto `create_users_table` de Laravel:
     * este proyecto usa `usuarios` como tabla de autenticación, por lo que
     * únicamente se conserva el esquema de `sessions` (requerido por la
     * autenticación stateful de la workstation). `user_id` queda sin foreign
     * key a propósito: es un índice del framework y no debe romper por
     * usuarios ya eliminados.
     */
    public function up(): void
    {
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sessions');
    }
};
