<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Replica los 3 triggers de inmutabilidad y cadena de custodia (RN-01,
     * RNF-02) definidos en el modelo de datos relacional del Sistema de
     * Control y Fiscalización. El hash encadenado se resuelve en la BD.
     */
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_actuados_inmutable_update
            BEFORE UPDATE ON actuados
            FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'OPERACIÓN NO PERMITIDA: Los actuados son inmutables por mandato institucional (RN-01). Emita un Actuado de Enmienda.';
            END
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_actuados_inmutable_delete
            BEFORE DELETE ON actuados
            FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'OPERACIÓN NO PERMITIDA: El borrado físico de actuados está prohibido por la Restricción Legal Crítica.';
            END
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_actuados_hash_before_insert
            BEFORE INSERT ON actuados
            FOR EACH ROW
            BEGIN
                DECLARE v_hash_prev CHAR(64);

                SELECT hash_actuado INTO v_hash_prev
                FROM actuados
                WHERE expediente_id = NEW.expediente_id
                ORDER BY fecha_hora DESC, id DESC
                LIMIT 1;

                SET NEW.hash_anterior = v_hash_prev;

                SET NEW.hash_actuado = SHA2(
                    CONCAT(
                        IFNULL(v_hash_prev, ''),
                        CAST(NEW.expediente_id AS CHAR),
                        CAST(NEW.catalogo_actuado_id AS CHAR),
                        IFNULL(CAST(NEW.usuario_id AS CHAR), 'SYSTEM'),
                        CAST(NEW.fecha_hora AS CHAR),
                        CAST(NEW.contenido AS CHAR)
                    ),
                    256
                );
            END
            SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_actuados_hash_before_insert');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_actuados_inmutable_delete');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_actuados_inmutable_update');
    }
};
