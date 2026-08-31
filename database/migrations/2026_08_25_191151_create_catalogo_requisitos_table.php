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
        Schema::create('catalogo_requisitos', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('reglamento_id');
            $table->string('descripcion', 300);
            $table->smallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);

            $table->foreign('reglamento_id')->references('id')->on('reglamentos');
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('catalogo_requisitos');
    }
};
