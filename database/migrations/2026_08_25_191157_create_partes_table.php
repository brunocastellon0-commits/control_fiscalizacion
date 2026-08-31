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
        Schema::create('partes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expediente_id')->constrained('expedientes');
            $table->string('tipo', 20);
            $table->string('nombre_completo', 200);
            $table->string('documento_identidad', 30)->nullable();
            $table->string('cargo_institucion', 150)->nullable();
            $table->unsignedBigInteger('actuado_origen_id')->nullable();
            $table->timestamp('vigente_desde')->useCurrent();
            $table->timestamp('vigente_hasta')->nullable();
            $table->boolean('es_version_actual')->default(true);

            $table->foreign('actuado_origen_id')->references('id')->on('actuados');
            $table->index(['expediente_id', 'es_version_actual'], 'idx_partes_expediente');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('partes');
    }
};
