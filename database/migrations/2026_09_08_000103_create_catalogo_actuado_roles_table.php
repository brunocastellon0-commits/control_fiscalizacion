<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Asigna a cada actuado del catálogo los roles habilitados para emitirlo.
     * Un `reglamento_id` explícito restringe el actuado a ese acuerdo concreto
     * (perfil operativo); `NULL` lo habilita para cualquier acuerdo del rol.
     */
    public function up(): void
    {
        Schema::create('catalogo_actuado_roles', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('catalogo_actuado_id');
            $table->unsignedSmallInteger('rol_id');
            $table->unsignedSmallInteger('reglamento_id')->nullable();

            // MySQL no admite NULL en primary keys: el "reglamento NULL =
            // cualquier acuerdo" se resuelve con un índice único + control a
            // nivel de aplicación en el seeder (delete+insert por actuado).
            $table->unique(['catalogo_actuado_id', 'rol_id', 'reglamento_id'], 'cat_act_roles_act_rol_reg_unique');

            $table->foreign('catalogo_actuado_id')->references('id')->on('catalogo_actuados')->onDelete('cascade');
            $table->foreign('rol_id')->references('id')->on('roles');
            $table->foreign('reglamento_id')->references('id')->on('reglamentos')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('catalogo_actuado_roles');
    }
};
