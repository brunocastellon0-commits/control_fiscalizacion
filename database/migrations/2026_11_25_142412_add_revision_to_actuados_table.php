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
        Schema::table('actuados', function (Blueprint $table) {
            $table->foreignId('revisado_por')->nullable()->after('ip_origen')->constrained('usuarios');
            $table->timestamp('revisado_en')->nullable()->after('revisado_por');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('actuados', function (Blueprint $table) {
            $table->dropForeign(['revisado_por']);
            $table->dropColumn(['revisado_por', 'revisado_en']);
        });
    }
};
