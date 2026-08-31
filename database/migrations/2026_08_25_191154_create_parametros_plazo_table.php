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
        Schema::create('parametros_plazo', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('reglamento_id');
            $table->string('tipo_plazo', 30);
            $table->string('subtipo', 40)->nullable();
            $table->smallInteger('dias_habiles');
            $table->string('base_legal', 150)->nullable();
            $table->boolean('activo')->default(true);

            $table->foreign('reglamento_id')->references('id')->on('reglamentos');
            $table->unique(['reglamento_id', 'tipo_plazo', 'subtipo'], 'uk_parametro_regla');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parametros_plazo');
    }
};
