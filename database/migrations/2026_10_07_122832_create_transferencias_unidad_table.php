<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Par Remisión/Recepción de transferencias inter-unidad (RF-05 /
     * DISENO_UNIDADES_INTER §2.2). `estado_previo_id` guarda la fase
     * procesal exacta al momento de remitir para restaurarla en la
     * recepción. Tabla nueva e independiente de la legacy `transferencias`
     * (AUD-0010), que queda intacta.
     *
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transferencias_unidad', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('expediente_id');
            $table->unsignedBigInteger('unidad_id');
            $table->enum('tipo', ['REMISION', 'RECEPCION']);
            $table->unsignedBigInteger('actuado_id')->nullable();
            $table->text('motivo');
            $table->datetime('fecha')->useCurrent();
            // catalogo_estados.id es smallint unsigned (convención existente).
            $table->unsignedSmallInteger('estado_previo_id')->nullable();
            $table->timestamps();

            // Índices de bandeja y reporte RF-R03 (declarados antes que las
            // FK para que InnoDB los reutilice y no duplique).
            $table->index('expediente_id', 'idx_transferencias_unidad_expediente');
            $table->index('unidad_id', 'idx_transferencias_unidad_unidad');
            $table->index('tipo', 'idx_transferencias_unidad_tipo');

            $table->foreign('expediente_id')->references('id')->on('expedientes');
            $table->foreign('unidad_id')->references('id')->on('unidades');
            $table->foreign('actuado_id')->references('id')->on('actuados');
            $table->foreign('estado_previo_id')->references('id')->on('catalogo_estados');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transferencias_unidad');
    }
};
