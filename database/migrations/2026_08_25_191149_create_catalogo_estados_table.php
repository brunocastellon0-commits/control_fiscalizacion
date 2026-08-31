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
        Schema::create('catalogo_estados', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('codigo', 40)->unique();
            $table->string('nombre', 120);
            $table->unsignedSmallInteger('estado_padre_id')->nullable();
            $table->boolean('es_final')->default(false);

            $table->foreign('estado_padre_id')->references('id')->on('catalogo_estados');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('catalogo_estados');
    }
};
