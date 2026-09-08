<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Recrea el trigger de hash de actuados agregando un bloqueo pesimista
     * (SELECT ... FOR UPDATE) sobre la fila del expediente al inicio del
     * cuerpo. Como el trigger se ejecuta dentro de la transacción del INSERT,
     * esto serializa los inserts sobre un mismo expediente a nivel de motor
     * de BD, garantizando la integridad de la cadena de hashes incluso si
     * código futuro inserta en `actuados` sin pasar por ActuadoService.
     */
    public function up(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_actuados_hash_before_insert');

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_actuados_hash_before_insert
            BEFORE INSERT ON actuados
            FOR EACH ROW
            BEGIN
                DECLARE v_hash_prev CHAR(64);

                SELECT id INTO @lock_dummy
                FROM expedientes
                WHERE id = NEW.expediente_id
                FOR UPDATE;

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
     *
     * Restaura el trigger original (sin el lock) para que el rollback
     * recupere el estado previo definido en create_actuados_triggers.
     */
    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_actuados_hash_before_insert');

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
};
