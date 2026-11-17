# DISEÑO CORTO — UNIDADES INTER (RF-05) PARA LA TAREA B2.1

**Archivo:** `docs/plan/DISENO_UNIDADES_INTER.md`
**Fecha:** 2026-10-05 (Sprint 1, formaliza el diseño exigido por B0.8)
**Estado:** **LISTO PARA CODIFICACIÓN** — especificación de entrada de la tarea B2.1.
**Origen:** `docs/auditoria/ANEXO_SRS_MODIFICACIONES.md` (enmienda AM-02),
`docs/plan/COORDINACION.md` §5.3 y §6 (CTR-04, CTR-05, CTR-10),
`docs/PLAN_DESARROLLO_REMEDIACION.md` (D-5 refinada), `TAREAS_BRUNO.md` (B2.1, B2.2).
**Alcance de este documento:** diseño de datos, flujo y contratos. No modifica esquema
actual; la implementación ocurre íntegramente en B2.1.

---

## 1. Contexto y objetivos

- Cumplir **RF-05** (SRS `:110`): todo movimiento inter-unidad exige el par obligatorio
  de actuados **Remisión** (origen) / **Recepción** (destino).
- Dar soporte a **RF-R03** (reporte de Recepción Inter-Unidad) y a la frontera de
  **RN-09 Fase 5** (derivación por incompetencia hacia Transparencia → CTR-10, B2.2).
- Unidades externas **sin usuarios en sistema**: sin cuentas, sin login, sin columna
  `unidad_id` en `usuarios` (propuesta original de D-5 descartada; ver AM-02).
- La **Encargada** es la única facultada para remitir y recibir (supervisión distrital).

## 2. Modelo de datos (migraciones nuevas, InnoDB / utf8mb4)

### 2.1 Tabla `unidades`

| Columna | Tipo | Restricciones |
| --- | --- | --- |
| `id` | `bigIncrements` | PK |
| `codigo` | `string` | único, no nulo |
| `nombre` | `string` | no nulo |
| `activa` | `boolean` | default `true` |
| `created_at` / `updated_at` | timestamps | estándar Laravel |

- Catálogo maestro **reutilizable**: transferencias inter-unidad *y* Reparto
  Institucional final (destinos de cierre).
- **Prohibido:** roles, usuarios o sesiones ligados a unidades; `unidad_id` en `usuarios`.

### 2.2 Tabla `transferencias_unidad`

| Columna | Tipo | Restricciones |
| --- | --- | --- |
| `id` | `bigIncrements` | PK |
| `expediente_id` | `bigInteger unsigned` | FK → `expedientes.id` |
| `unidad_id` | `bigInteger unsigned` | FK → `unidades.id` (unidad destino en REMISION, unidad origen en RECEPCION) |
| `tipo` | `enum('REMISION','RECEPCION')` | no nulo |
| `actuado_id` | `bigInteger unsigned` nullable | FK → `actuados.id` (actuado del par remisión/recepción) |
| `motivo` | `string` / `text` | no nulo |
| `fecha` | `datetime` | default actual (no `timestamp`: rango 1970–2038) |
| `estado_previo_id` | `bigInteger unsigned` nullable | FK → `catalogo_estados.id`; **solo en `REMISION`**: estado de la causa al momento de remitir |
| `created_at` / `updated_at` | timestamps | estándar Laravel |

- Foreign keys **reales** con `onDelete` correspondiente; índices en `expediente_id`,
  `unidad_id` y `tipo` (consultas de bandeja y reporte RF-R03).
- **`estado_previo_id`** es la pieza clave: permite que la recepción restaure la causa
  a su fase procesal previa **exacta**.

### 2.3 Modelos Eloquent

- `App\Models\Unidad` — `$fillable = ['codigo', 'nombre', 'activa']`; relaciones
  `transferencias()`.
- `App\Models\TransferenciaUnidad` — `$fillable` estricto (B0.1): `expediente_id`,
  `unidad_id`, `tipo`, `actuado_id`, `motivo`, `fecha`, `estado_previo_id`;
  relaciones `expediente()`, `unidad()`, `actuado()`, `estadoPrevio()`.

## 3. Catálogos nuevos (seeders / migración aditiva)

| Tipo | Código | Habilitación |
| --- | --- | --- |
| Estado | `REMITIDO_UNIDAD_EXTERNA` | Activo; **fuera de bandejas operativas**; causas en este estado = solo lectura |
| Actuado | `ACT_REMISION_UNIDAD` | Habilitado **solo para rol ENCARGADA** (pivote `catalogo_actuado_roles`) |
| Actuado | `ACT_RECEPCION_UNIDAD` | Habilitado **solo para rol ENCARGADA** |

Ubicación: `database/seeders/CatalogoEstadoSeeder.php` y
`database/seeders/CatalogoActuadoSeeder.php` (o migración aditiva de catálogo si los
seeders ya fueron ejecutados en entornos vivos).

## 4. Flujo de estados

```
        (estado X: fase operativa normal)
                    │
   POST /expedientes/{e}/remitir-unidad   [CTR-04, solo Encargada]
   • guarda estado_previo_id = X          (solo en la fila tipo REMISION)
   • crea transferencia tipo REMISION (unidad_id = destino)
   • emite actuado ACT_REMISION_UNIDAD (hash SHA-256 encadenado)
   • congela plazos vigentes (SUSPENDIDO) y desactiva la bandeja del operador
                    ▼
        REMITIDO_UNIDAD_EXTERNA
   • solo lectura para todos los operadores (403 al emitir actuados)
   • fuera de bandejas operativas normales
                    │
   POST /expedientes/{e}/registrar-recepcion   [CTR-05, solo Encargada]
   • crea transferencia tipo RECEPCION (unidad_id = origen)
   • emite actuado ACT_RECEPCION_UNIDAD (hash encadenado)
   • restaura estado = estado_previo_id de la última REMISION
   • reanuda el cómputo de plazos (sin pérdida de días restantes)
   • reactiva la bandeja del operador asignado
                    ▼
        estado X (fase operativa normal, retomada)
```

Invariantes:

1. Toda REMISION tiene una RECEPCION posterior (RF-05: el trámite no concluye en la
   fase de remisión); el reporte RF-R03 audita los pares.
2. La recepción usa **el último** `estado_previo_id` de REMISION abierto del expediente.
3. Cada paso emite su actuado con hash encadenado (cadena de custodia intacta).
4. `REMITIDO_UNIDAD_EXTERNA` es terminal solo hasta la recepción: no admite actuados
   operativos, sorteo, planificación ni cierre.

## 5. Contratos API (COORDINACION §6)

### CTR-04 — `POST /api/expedientes/{e}/remitir-unidad` (B2.1)

- Request: `{unidad_id: int, motivo: string, adjunto?: file}`
- Respuesta: `201 Created` → `{transferencia_id, estado: "REMITIDO_UNIDAD_EXTERNA"}`
- Efectos: persiste `estado_previo_id`, actuado `ACT_REMISION_UNIDAD`, transición y
  congelación de plazos.
- Autorización: **solo Encargada** (policy + `can:`); operadores y ADMIN → `403`.

### CTR-05 — `POST /api/expedientes/{e}/registrar-recepcion` (B2.1)

- Request: `{unidad_origen_id: int, motivo: string, adjunto?: file}`
- Respuesta: `200 OK` → `{estado: "<estado restaurado>"}` (p. ej. `EN_EVALUACION`,
  `EN_PLANIFICACION`)
- Efectos: transferencia tipo RECEPCION, actuado `ACT_RECEPCION_UNIDAD`, restauración
  exacta de `estado_previo_id` y reanudación de plazos.
- Autorización: **solo Encargada**.

### CTR-10 — incompetencia (B2.2, relacionado)

- `POST .../incompetencia/solicitar` (operador) → `POST .../incompetencia/visto-bueno`
  (Encargada) → congela plazos y remite a `REMITIDO_UNIDAD_EXTERNA`; la devolución se
  registra con CTR-05. Implementación en B2.2 sobre este mismo modelo.

## 6. Distinción con el Reparto Institucional

| | Unidades externas (este diseño) | Reparto Institucional |
| --- | --- | --- |
| Naturaleza | Remisión **temporal** con retorno | **Cierre definitivo** |
| Estado destino | `REMITIDO_UNIDAD_EXTERNA` | `CONCLUIDO` / `ARCHIVO` / resolución firme |
| Tabla | Usa `transferencias_unidad` | **No** usa `transferencias_unidad` |
| Reutiliza catálogo `unidades` | Sí (destino de remisión) | Sí (destino de cierre) |

**Regla:** el Reparto Institucional jamás transita por `REMITIDO_UNIDAD_EXTERNA`.
B1.6 debe verificar taxativamente que la inactivación de estados huérfanos **no** toque
estados requeridos por el Reparto (D-6f).

## 7. Criterios de aceptación y mapa hacia B2.1

Criterios del plan (TAREAS_BRUNO, B2.1):

1. Causa transiciona a `REMITIDO_UNIDAD_EXTERNA` al remitir con CTR-04 y guarda
   `estado_previo_id`.
2. Recepción con CTR-05 restaura la causa a su estado procesal original exacto.

Archivos que creará B2.1 (no se crean en B0.8):

| Archivo | Tipo |
| --- | --- |
| `database/migrations/…_create_unidades_table.php` | nuevo |
| `database/migrations/…_create_transferencias_unidad_table.php` | nuevo |
| `app/Models/Unidad.php` | nuevo |
| `app/Models/TransferenciaUnidad.php` | nuevo |
| `database/seeders/UnidadSeeder.php` | nuevo |
| `app/Services/TransferenciaUnidadService.php` | nuevo |
| `app/Http/Controllers/TransferenciaUnidadController.php` | nuevo |
| `database/seeders/CatalogoEstadoSeeder.php` / `CatalogoActuadoSeeder.php` | edición aditiva |
| `routes/api/core.php` | edición (CTR-04, CTR-05) |
| `tests/Feature/TransferenciaUnidadesTest.php` | nuevo |

Dependencias: B0.1 ✅, B0.8 (este documento), B1.2 (máquina de estados) mergeados.
Entregable a Brayan: contratos CTR-04/CTR-05 publicados para la UI B4.9.

## 8. Decisiones y riesgos

- `fecha` como `datetime` (no `timestamp`) por el límite 2038 — regla del proyecto.
- `tipo` como `enum` nativo de MySQL (solo dos valores estables).
- Si el catálogo `catalogo_estados`/`catalogo_actuado_roles` ya tiene datos vivos,
  agregar vía seeders idempotentes (`updateOrCreate`), nunca re-borrando.
- Sin `unidad_id` en `usuarios` ni en `expedientes`: el vínculo vive exclusivamente en
  `transferencias_unidad` (historial trazable, no estado mutable disperso).
