<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * La inmutabilidad RN-01 se mantiene para todo el contenido del actuado.
     * Se abre una excepción estrictamente tipada para el visto de revisado de
     * la Encargada: solo pueden cambiar `revisado_por` y `revisado_en`.
     * Cualquier modificación de hash, estado, contenido, emisor, fecha o
     * referencias sigue siendo rechazada por el motor.
     */
    public function up(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_actuados_inmutable_update');

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_actuados_inmutable_update
            BEFORE UPDATE ON actuados
            FOR EACH ROW
            BEGIN
                IF NEW.id <> OLD.id
                    OR NEW.expediente_id <> OLD.expediente_id
                    OR NEW.catalogo_actuado_id <> OLD.catalogo_actuado_id
                    OR NOT (NEW.usuario_id <=> OLD.usuario_id)
                    OR NEW.fecha_hora <> OLD.fecha_hora
                    OR NOT (NEW.estado_anterior_id <=> OLD.estado_anterior_id)
                    OR NEW.estado_nuevo_id <> OLD.estado_nuevo_id
                    OR NOT (NEW.contenido <=> OLD.contenido)
                    OR NOT (NEW.actuado_referencia_id <=> OLD.actuado_referencia_id)
                    OR NOT (NEW.ip_origen <=> OLD.ip_origen)
                    OR NOT (NEW.hash_actuado <=> OLD.hash_actuado)
                    OR NOT (NEW.hash_anterior <=> OLD.hash_anterior)
                THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'OPERACIÓN NO PERMITIDA: Los actuados son inmutables por mandato institucional (RN-01). Solo el visto de revisado puede registrarse.';
                END IF;
            END
            SQL);
    }

    /**
     * Restaura la inmutabilidad estricta original (RN-01).
     */
    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_actuados_inmutable_update');

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_actuados_inmutable_update
            BEFORE UPDATE ON actuados
            FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'OPERACIÓN NO PERMITIDA: Los actuados son inmutables por mandato institucional (RN-01). Emita un Actuado de Enmienda.';
            END
            SQL);
    }
};
