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
        Schema::create('adjuntos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actuado_id')->constrained('actuados');
            $table->string('nombre_original');
            $table->text('ruta_almacenamiento');
            $table->char('hash_sha256', 64);
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('tamanio_bytes')->nullable();
            $table->foreignId('subido_por')->constrained('usuarios');
            $table->timestamp('subido_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('adjuntos');
    }
};
