<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Indices compuestos aditivos para bandejas y busquedas (AUD-0060 / AUD-0061).
     *
     * No se crea idx_actuados_expediente_fecha porque actuados ya cuenta con
     * idx_actuados_exp_fecha (expediente_id, fecha_hora).
     */
    public function up(): void
    {
        if (! Schema::hasIndex('expedientes', 'idx_expedientes_estado_ingreso')) {
            Schema::table('expedientes', function (Blueprint $table): void {
                $table->index(['estado_actual_id', 'fecha_ingreso'], 'idx_expedientes_estado_ingreso');
            });
        }

        if (! Schema::hasIndex('asignaciones', 'idx_asignaciones_activa_usuario')) {
            Schema::table('asignaciones', function (Blueprint $table): void {
                $table->index(['usuario_id', 'activa', 'fecha_asignacion'], 'idx_asignaciones_activa_usuario');
            });
        }

        if (! Schema::hasIndex('plazos', 'idx_plazos_expediente_estado')) {
            Schema::table('plazos', function (Blueprint $table): void {
                $table->index(['expediente_id', 'estado', 'fecha_limite'], 'idx_plazos_expediente_estado');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * InnoDB puede eliminar el indice implicito de una FK cuando el indice
     * compuesto lo reemplaza. Por eso, antes de borrar el compuesto, se
     * restaura el indice de la FK si ya no existe.
     */
    public function down(): void
    {
        if (Schema::hasIndex('expedientes', 'idx_expedientes_estado_ingreso')) {
            Schema::table('expedientes', function (Blueprint $table): void {
                $table->dropIndex('idx_expedientes_estado_ingreso');
            });
        }

        if (! Schema::hasIndex('asignaciones', 'asignaciones_usuario_id_foreign')) {
            Schema::table('asignaciones', function (Blueprint $table): void {
                $table->index('usuario_id', 'asignaciones_usuario_id_foreign');
            });
        }

        if (Schema::hasIndex('asignaciones', 'idx_asignaciones_activa_usuario')) {
            Schema::table('asignaciones', function (Blueprint $table): void {
                $table->dropIndex('idx_asignaciones_activa_usuario');
            });
        }

        if (! Schema::hasIndex('plazos', 'plazos_expediente_id_foreign')) {
            Schema::table('plazos', function (Blueprint $table): void {
                $table->index('expediente_id', 'plazos_expediente_id_foreign');
            });
        }

        if (Schema::hasIndex('plazos', 'idx_plazos_expediente_estado')) {
            Schema::table('plazos', function (Blueprint $table): void {
                $table->dropIndex('idx_plazos_expediente_estado');
            });
        }
    }
};
