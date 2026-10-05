# ANEXO SRS — MODIFICACIONES ARQUITECTÓNICAS NORMATIVAS (B0.8)

**Archivo:** `docs/auditoria/ANEXO_SRS_MODIFICACIONES.md`
**Fecha:** 2026-10-05
**Estado:** **REDACTADO (2026-10-05)** — anexo derivado de la auditoría. El SRS fuente
(`docs/auditoria/SRS_EXTRAIDO.txt`) permanece **inmutable**; este documento solo lo complementa.
**Fuente normativa:** `docs/auditoria/SRS_EXTRAIDO.txt` (extracción verificada del `.docx`),
con números de línea como ubicación de las citas.
**Decisiones complementarias citadas:** `docs/plan/COORDINACION.md` §5.3 (punto 3,
"Mecánica de Unidades Externas"); `docs/PLAN_DESARROLLO_REMEDIACION.md` (D-5, §2.1);
`docs/auditoria/MATRIZ_SRS_IMPLEMENTACION.md` §0 (DT-01 y DT-02).
**Método:** (a) verificación estática del árbol real (`.env`, `config/database.php`,
migraciones y triggers); (b) consulta de **solo lectura** contra la BD de desarrollo
(`SELECT VERSION()`, `SHOW VARIABLES`); (c) cero cambios de código, esquema o tests.
**Alcance:** documental únicamente.

---

## 0. Propósito

El SRS original fue redactado sobre un stack (PostgreSQL) y un modelo de unidades que,
durante la auditoría técnica y la coordinación de desarrollo, fueron redelimitados. Este
anexo formaliza las **dos enmiendas arquitectónicas normativas** solicitadas, de modo que
las auditorías gubernamentales encuentren trazabilidad documental completa entre el SRS
original, la decisión adoptada y las tareas que la implementan. Ninguna enmienda altera
requisitos funcionales ni reglas de negocio: todas son **diferencias tecnológicas o de
modelado operativo** sin impacto sobre los RF/RN/RNF del SRS.

---

## 1. Enmienda AM-01 — Motor de base de datos: MySQL en lugar de PostgreSQL

### 1.1 Disposición original del SRS

| Ubicación | Texto original (extracto) |
| --- | --- |
| `SRS_EXTRAIDO.txt:61` | "…framework Laravel y bases de datos relacionales en **PostgreSQL con soporte JSONB** para auditoría…" |
| `SRS_EXTRAIDO.txt:116` (RNF-04) | "Backend desarrollado en PHP (Framework Laravel), Frontend dinámico y Base de Datos en **PostgreSQL**. Se utilizarán tablas relacionales puras… y columnas tipo **JSONB**…" |
| `SRS_EXTRAIDO.txt:187` | "…correspondencia matemática total (100%) entre los datos visualizados en el Dashboard, los archivos exportados a Excel/PDF y las consultas (SQL) directas a la base de datos **PostgreSQL**…" |

### 1.2 Enmienda adoptada

El sistema se construye sobre **MySQL 8.x/9.x con motor InnoDB**, en reemplazo de
PostgreSQL. Es una **diferencia tecnológica aprobada**, no una brecha funcional: la
obligación funcional subyacente (metadatos procesales almacenados de forma flexible,
transacciones nativas, integridad y consistencia de reportes) se cumple íntegramente con
las facilities nativas de MySQL.

### 1.3 Evidencia verificada en el árbol real

| Elemento | Evidencia | Resultado |
| --- | --- | --- |
| Conexión | `.env`: `DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`, `DB_PORT=3306` | MySQL, no PostgreSQL |
| Versión del servidor | `SELECT VERSION()` (solo lectura, 2026-10-05) | **9.7.0** |
| Motor de almacenamiento | `SHOW VARIABLES` → `default_storage_engine` (solo lectura) | **InnoDB** (transacciones y FK requeridas por RNF-02) |
| Charset/collation | `config/database.php`: `utf8mb4` / `utf8mb4_unicode_ci` | Estándar del proyecto |
| Metadatos JSON (RNF-04) | `database/migrations/2026_08_25_191156_create_actuados_table.php:22`: `$table->json('contenido')` | **JSON nativo de MySQL** en `actuados.contenido` |
| Triggers de cadena de custodia | `database/migrations/2026_08_28_182631_create_actuados_triggers.php`, `2026_09_08_000001_add_hash_trigger_lock.php` | Exigen InnoDB; prueban soporte de lógica en BD |

### 1.4 Equivalencias funcionales SRS (PostgreSQL) → implementación real (MySQL)

| Concepto del SRS | Implementación en MySQL | Cumplimiento |
| --- | --- | --- |
| Columnas `JSONB` (RNF-04) | Tipo `JSON` nativo de MySQL (`actuados.contenido`) | Metadatos procesales flexibles garantizados |
| Arrays nativos / `RETURNING` / `ILIKE` | No usados; búsquedas por collation `_ci` y `LOWER()` | Prohibidos por convención del proyecto (MySQL puro) |
| Transacciones nativas (RNF-02) | `DB::transaction()` sobre InnoDB | Rollback garantizado |
| Hash de seguridad en actuados (RNF-02) | SHA-256 encadenado `hash_anterior`/`hash_actuado` + triggers | Cadena de custodia activa |
| Consistencia Dashboard/Excel/SQL (`:187`) | Mismo criterio aplicado sobre MySQL | Correspondencia 100% exigible igual |

### 1.5 Relación con la auditoría

La discrepancia ya está clasificada en `docs/auditoria/MATRIZ_SRS_IMPLEMENTACION.md` §0
como **DT-01** y **DT-02** (`DIFERENCIA TECNOLÓGICA`, con nota "Documentar en Fase 18").
Este anexo es esa documentación formal. **No convertir en brecha funcional.**

---

## 2. Enmienda AM-02 — Unidades organizacionales externas sin usuarios en sistema

### 2.1 Disposición original del SRS

| Ubicación | Texto original (extracto) |
| --- | --- |
| `SRS_EXTRAIDO.txt:110` (RF-05) | "Transferencia Inter-Unidad: El movimiento de un expediente a otra jurisdicción o unidad externa exigirá obligatoriamente un actuado de **'Remisión'** (origen) y el trámite no se considerará concluido en esa fase hasta la generación del actuado de **'Recepción'** (destino)." |
| `SRS_EXTRAIDO.txt:121` (RF-R03) | "Recepción Inter-Unidad: Auditoría de trámites derivados y aceptados." (reporte) |
| `SRS_EXTRAIDO.txt:168` (RN-09, Fase 5) | Derivación por Incompetencia hacia la **Unidad de Transparencia** (Ley Nº 974) para la vía fiscal/penal. |
| `SRS_EXTRAIDO.txt:20` | Las derivaciones "…y las **transferencias a otras unidades**, se realizaban de manera presencial…". |

El SRS no especifica el modelo técnico de las unidades externas; solo exige el par
Remisión/Recepción como puerta obligatoria de entrada y salida.

### 2.2 Enmienda adoptada (modelo refinado)

Las unidades externas (Transparencia, Ministerio Público, Régimen Disciplinario, etc.)
**NO son usuarios del sistema**:

- No tienen cuentas, ni inicio de sesión, ni registro en el modelo `Usuario`.
- **No existe columna `unidad_id` en `usuarios`** (la propuesta original de la decisión
  D-5 en `PLAN_DESARROLLO_REMEDIACION.md` §2.1 —"campo `unidad_id` en `usuarios`"—
  queda **expresamente descartada** y sustituida por el refinamiento de
  `COORDINACION.md` §5.3).
- Existe un **catálogo `unidades`** (id, código, nombre, activa) usado como destino y
  origen de causas en soporte físico/oficio; es **reutilizable** por el Reparto
  Institucional final.
- Cada movimiento se registra en la tabla **`transferencias_unidad`**, que guarda el
  **`estado_previo_id`** de la causa al momento de la remisión.
- La **Encargada de la Unidad** es la única facultada para emitir los actuados
  `ACT_REMISION_UNIDAD` (origen) y `ACT_RECEPCION_UNIDAD` (destino), cumpliendo el par
  exigido por RF-05.
- Al remitir, la causa transiciona a **`REMITIDO_UNIDAD_EXTERNA`**: estado de
  **solo lectura**, fuera de las bandejas operativas normales.
- Al recibir, la causa **restaura exactamente** su `estado_previo_id` y reactiva sus
  plazos (reanudación del cómputo).
- Las transferencias alimentan el reporte **RF-R03** (Recepción Inter-Unidad).

### 2.3 Distinción crítica: Unidades externas ≠ Reparto Institucional

| Aspecto | Unidades externas / `REMITIDO_UNIDAD_EXTERNA` | Reparto Institucional |
| --- | --- | --- |
| Naturaleza | Remisión **temporal** a un destino externo con retorno posible | **Cierre procesal definitivo** de la causa |
| Estados | Transita a `REMITIDO_UNIDAD_EXTERNA` (solo lectura) | Termina en `CONCLUIDO` / `ARCHIVO` / resolución final firme |
| Registro | `transferencias_unidad` + actuado de remisión/recepción | Actuado de cierre (`ACT_REPARTO_INSTITUCIONAL`) |
| Bandejas | Fuera de bandejas operativas hasta la recepción | Sale definitivamente del circuito operativo |
| Verificación | Blindaje de solo lectura (tareas B2.3, B8.7) | **B1.6 debe verificar taxativamente** que ningún estado requerido por el Reparto Institucional sea inactivado |

**Regla inamovible:** el Reparto Institucional **nunca** transiciona a
`REMITIDO_UNIDAD_EXTERNA` ni usa `transferencias_unidad`.

### 2.4 Relación con otras decisiones

- **D-5** (`PLAN_DESARROLLO_REMEDIACION.md`): alcance ampliado a unidades
  organizacionales; el detalle de implementación queda fijado por `COORDINACION.md` §5.3
  y por `docs/plan/DISENO_UNIDADES_INTER.md`.
- **CTR-04 / CTR-05 / CTR-10** (`COORDINACION.md` §6): contratos de remisión,
  recepción e incompetencia que materializan esta enmienda (tareas B2.1 y B2.2).

---

## 3. Tabla resumen de enmiendas

| ID | Enmienda | Ubicación SRS | Cambio adoptado | Estado | Tareas implementadoras |
| --- | --- | --- | --- | --- | --- |
| AM-01 | Motor de BD MySQL (8.x/9.x) en lugar de PostgreSQL con JSONB | `:61`, `:116` (RNF-04), `:187` | MySQL 9.7.0 InnoDB + tipo `JSON` nativo; sin `jsonb`/arrays/`ILIKE`/`RETURNING` | **APROVADA** — diferencia tecnológica, no brecha (DT-01/DT-02) | Verificado en Fase 0; respaldado por migraciones/triggers existentes |
| AM-02 | Unidades externas sin usuarios en sistema | `:110` (RF-05), `:121` (RF-R03), `:168` (RN-09 Fase 5), `:20` | Catálogo `unidades` + `transferencias_unidad` con `estado_previo_id`; actuados par remisión/recepción exclusivos de la Encargada; estado `REMITIDO_UNIDAD_EXTERNA`; **sin `unidad_id` en `usuarios`**; distinción con Reparto Institucional | **APROVADA** (COORDINACION §5.3) | **B0.8** (este anexo y el diseño), **B2.1** (backend), **B2.2** (incompetencia), **B1.6** (verificación de estados del Reparto), B8.7 (tests de solo lectura) |

---

## 4. Trazabilidad

- **Diseño corto de unidades (habilita la codificación en Sprint 3):**
  `docs/plan/DISENO_UNIDADES_INTER.md` (creado junto con este anexo en B0.8).
- **Decisiones base:** `docs/plan/COORDINACION.md` §5.3 y §6 (CTR-04, CTR-05, CTR-10);
  `docs/PLAN_DESARROLLO_REMEDIACION.md` (D-5, D-6f).
- **Clasificación de diferencias tecnológicas:** `docs/auditoria/MATRIZ_SRS_IMPLEMENTACION.md` §0.
- **SRS fuente inmutable:** `docs/auditoria/SRS_EXTRAIDO.txt` — no fue modificado.

> Los criterios de aceptación del SRS (inmutabilidad, privacidad de bandejas, auditoría
> de plazos, consistencia de reportes) siguen vigentes y aplican **sin cambios** sobre
> MySQL y sobre el modelo de unidades aquí definido.
