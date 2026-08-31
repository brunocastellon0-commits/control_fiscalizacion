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
        Schema::create('reglamentos', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('codigo', 30);
            $table->string('nombre', 150);
            $table->string('version', 20)->default('1.0');
            $table->date('vigente_desde');
            $table->date('vigente_hasta')->nullable();
            $table->boolean('activo')->default(true);

            $table->unique(['codigo', 'version']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reglamentos');
    }
};
