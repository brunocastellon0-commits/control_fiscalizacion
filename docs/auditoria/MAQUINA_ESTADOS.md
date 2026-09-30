# Máquina de estados del expediente — Fase 4

- **Fecha:** 2026-09-29
- **Método:** inventario de estados (seeder real + **BD dev verificada con query**), grafo de transiciones por actuado, validación de origen por endpoint, inmutabilidad y cobertura de tests. Todo contra código/BD verificado, no contra documentación.

## 1. Inventario de estados

### 1.1 Catálogo normativo (`CatalogoEstadoSeeder`, 21 estados)

| Código | Nombre | Padre | Final | ¿Referenciado en `app/`? |
| ------ | ------ | ----- | ----- | ------------------------ |
| PENDIENTE_SORTEO | Pendiente de Sorteo | — | no | ✓ (creación/sorteo) |
| EN_EVALUACION | En Evaluación | — | no | ✓ (evaluación) |
| OBSERVADO | Observado | EN_EVALUACION | no | **no referenciado** (solo subestado de catálogo) |
| RECHAZADO | Rechazado | EN_EVALUACION | no | ✓ (impugnación) |
| ADMITIDO | Admitido | — | no | **solo como destino de REVOCA**; sin arista de salida → §5 |
| EN_INVESTIGACION | En Investigación | — | no | **HUÉRFANO** (ningún código ni actuado lo usa) |
| EN_DESCARGOS | En Descargos | — | no | **HUÉRFANO** (los descargos modelan plazos, no estado) |
| CONCLUIDO | Concluido | — | sí | **HUÉRFANO** |
| ARCHIVO_DEFINITIVO | Archivo Definitivo | — | sí | ✓ (ratificación de rechazo) |
| EN_SUBSANACION | En Subsanación | — | no | ✓ (observación) |
| EN_PLANIFICACION | En Planificación | — | no | ✓ |
| PENDIENTE_VISTO_BUENO | Pendiente de Visto Bueno | — | no | ✓ |
| EN_EJECUCION | En Ejecución | — | no | ✓ |
| PENDIENTE_APROBACION_AMPLIACION | Pend. Aprob. Ampliación | — | no | ✓ |
| PENDIENTE_VISTO_BUENO_FINAL | Pend. Visto Bueno Final | — | no | ✓ (cierre) |
| LISTO_PARA_REPARTO | Listo para Reparto | — | no | ✓ |
| CONCLUIDO_REMITIDO | Concluido y Remitido | — | sí | ✓ (terminal) |
| PENDIENTE_REMISION_TRANSPARENCIA | Pend. Remisión Transp. | — | no | ✓ |
| DERIVADO_TRANSPARENCIA | Derivado a Transparencia | — | sí | ✓ (terminal) |
| ARCHIVO_POR_ABANDONO | Archivo por Abandono | — | sí | ✓ (automático) |
| EN_IMPUGNACION | En Impugnación | — | no | ✓ |

### 1.2 Discrepancia BD dev vs seeder (verificado con query, 2026-09-29)

BD dev (`catalogo_estados`): **16 filas** — le faltan `PENDIENTE_VISTO_BUENO`, `PENDIENTE_APROBACION_AMPLIACION`, `PENDIENTE_VISTO_BUENO_FINAL`, `LISTO_PARA_REPARTO`, `PENDIENTE_REMISION_TRANSPARENCIA`, `EN_IMPUGNACION` y tiene el legacy `EN_VISTO_BUENO_FINAL` (que el seeder borra). BD dev (`catalogo_actuados`): **7 filas** de 25 → faltan 18 (planificación completa, impugnaciones, ampliación, cierre, transparencia, descargos, informes financieros, archivo automático, NUREJ Hijo).

→ **AUD-0023**: la BD dev no está sembrada con los seeders actuales; flujos como planificación/impugnación/cierre fallarían con `firstOrFail` en el entorno de desarrollo. **No se ejecuta `db:seed` sin confirmación** (los seeders de demo no son idempotentes demostradamente).

## 2. Grafo de transiciones real (catálogo `CatalogoActuadoSeeder`, 25 actuados)

| # | Actuado | Origen → Destino | Emisor (endpoint) | Validación de origen |
| - | ------- | ---------------- | ------------------ | -------------------- |
| 1 | ACT_REGISTRO_DIGITALIZACION | — → PENDIENTE_SORTEO | `ExpedienteService@aperturaCausa` (POST /api/expedientes) | creación (n/a) |
| 2 | ACT_SORTEO_INICIAL | PENDIENTE_SORTEO → EN_EVALUACION | `ExpedienteService@sortear` (POST .../sortear) | ✓ servicio (test `ExpedienteControllerTest:274`) |
| 3 | ACT_OBSERVACION | EN_EVALUACION → EN_SUBSANACION | `EvaluacionAdmisibilidadService@evaluar` (POST .../evaluacion) | ✓ servicio (`:91-99`) |
| 4 | ACT_ADMISION | EN_EVALUACION → EN_PLANIFICACION | ídem | ✓ servicio |
| 5 | ACT_RECHAZO | EN_EVALUACION → RECHAZADO | ídem | ✓ servicio |
| 6 | ACT_CRONOGRAMA_TRABAJO | EN_PLANIFICACION → PENDIENTE_VISTO_BUENO | `PlanificacionService@cargar` (POST .../planificacion) | ✓ policy `cargarPlanificacion:175` |
| 7 | ACT_MPA | EN_PLANIFICACION → PENDIENTE_VISTO_BUENO | ídem | ✓ policy |
| 8 | ACT_VISTO_BUENO_PLANIFICACION | PENDIENTE_VISTO_BUENO → EN_EJECUCION | `PlanificacionService@vistoBueno` | ✓ policy `aprobarPlanificacion:210` |
| 9 | ACT_DEVOLUCION_OBSERVACION | PENDIENTE_VISTO_BUENO → EN_PLANIFICACION | `PlanificacionService@devolver` | ✓ policy `devolverPlanificacion:221` |
| 10 | ACT_SOLICITAR_AMPLIACION | EN_EJECUCION → PENDIENTE_APROBACION_AMPLIACION | `AmpliacionService@solicitar` | ✓ `validarEstado` + policy |
| 11 | ACT_APROBAR_AMPLIACION | PENDIENTE_APROBACION_AMPLIACION → EN_EJECUCION | `AmpliacionService@aprobar` | ✓ `validarEstado` + policy |
| 12 | ACT_INFORME_FINAL (jurídico) | EN_EJECUCION → **null** | endpoint genérico POST .../actuados | **sin validación** (§4) — destino null = **no-op de estado** → §5 |
| 13 | ACT_INFORME_AUDITORIA_FINANCIERA_{CON,SIN}_RESPONSABILIDAD | EN_EJECUCION → PENDIENTE_VISTO_BUENO_FINAL | endpoint genérico (+ Bloqueo de Salida en `StoreActuadoRequest:53-65`) | **sin validación de origen** |
| 14 | ACT_REMITIR_IMPUGNACION | RECHAZADO → EN_IMPUGNACION | `ImpugnacionService@remitir` | ✓ policy `remitirImpugnacion:147-151` |
| 15 | ACT_RESOLUCION_RATIFICA_RECHAZO | EN_IMPUGNACION → ARCHIVO_DEFINITIVO | `ImpugnacionService@resolver` | ✓ `validarEstado:106` |
| 16 | ACT_RESOLUCION_REVOCA_RECHAZO | EN_IMPUGNACION → ADMITIDO | ídem | ✓ `validarEstado` — destino ADMITIDO **sin salida** → §5 |
| 17 | ACT_CREACION_NUREJ_HIJO | — → — (padre no cambia; hijo nace PENDIENTE_SORTEO) | `NurejHijoService` | ✓ policy `derivarNurejHijo` (ENCARGADA) |
| 18 | ACT_VISTO_BUENO_FINAL | PENDIENTE_VISTO_BUENO_FINAL → LISTO_PARA_REPARTO | `CierreExpedienteService@aprobarVistoBueno` | ✓ `validarEstado:56` |
| 19 | ACT_REPARTO_INSTITUCIONAL | LISTO_PARA_REPARTO → CONCLUIDO_REMITIDO | `CierreExpedienteService@ejecutarReparto` | ✓ `validarEstado` |
| 20 | ACT_DERIVACION_INCOMPETENCIA | — → PENDIENTE_REMISION_TRANSPARENCIA | `TransparenciaService@derivar` | ✓ policy (asignación + catálogo); **congela relojes** |
| 21 | ACT_REMISION_TRANSPARENCIA | PENDIENTE_REMISION_TRANSPARENCIA → DERIVADO_TRANSPARENCIA | `TransparenciaService@remitir` | ✓ policy `remitirTransparencia:326` |
| 22 | ACT_COMUNICACION_HALLAZGOS | — → — (no-op; pausa reloj + sub-reloj 5 d) | `DescargoFinancieroService@comunicar` | ✓ `validarEstado` (EN_EJECUCION + AC055) |
| 23 | ACT_RECEPCION_DESCARGOS | — → — (no-op; reanuda reloj) | `DescargoFinancieroService@recibir` | ✓ `validarEstado` |
| 24 | ACT_ARCHIVO_POR_ABANDONO | EN_SUBSANACION → ARCHIVO_POR_ABANDONO | `ArchivoPorAbandonoService` (CRON, automático) | ✓ `es_automatico=true` |

*(25 códigos contando los dos informes financieros en la fila 13.)*

**Transiciones no modeladas por catálogo:** creación del NUREJ Hijo (`NurejHijoService` setea PENDIENTE_SORTEO directo) y los no-op con `estadoNuevoIdExplicito` (descargos, NUREJ Hijo).

## 3. Plazos que abre cada actuado (`ActuadoService::MAPA_TIPO_PLAZO`)

| Actuado | Plazo abierto | Días (AC022/054/055) |
| ------- | ------------- | -------------------- |
| ACT_SORTEO_INICIAL | EVALUACION | 2 / 5 / 5 |
| ACT_OBSERVACION | SUBSANACION | 3 |
| ACT_ADMISION | PLANIFICACION | 2 |
| ACT_VISTO_BUENO_PLANIFICACION | EJECUCION | 10 (JURISDICCIONAL) |
| ACT_RECHAZO | IMPUGNACION_REMITIR | 1 |
| ACT_REMITIR_IMPUGNACION | IMPUGNACION_RESOLVER | 3 |
| ACT_RESOLUCION_REVOCA_RECHAZO | PLANIFICACION | 2 |
| ACT_DEVOLUCION_OBSERVACION | PLANIFICACION | 2 |
| resto (17 actuados) | ninguno | — |

## 4. Validación del estado de origen — cobertura

| Vía de emisión | ¿Valida `estado_origen_id` vs `estado_actual_id`? |
| -------------- | ------------------------------------------------- |
| Endpoints específicos (evaluación, planificación, ampliación, impugnación, cierre, transparencia, descargos, sorteo) | ✓ cada uno valida su estado (policy `validarEstado` o servicio) |
| **Endpoint genérico `POST /api/expedientes/{e}/actuados`** | **✗ NO valida el origen en ninguna capa** — `StoreActuadoRequest` solo valida rol+asignación (`crearActuado`) y el Bloqueo de Salida **solo para informes financieros**; `ActuadoService@registerActuado` aplica `estado_destino_id` directo (`:88-120`); la columna `catalogo_actuados.estado_origen_id` **no se lee en ningún punto de `app/`** (grep verificado) |

**Consecuencias (AUD-0020, P1):** con el endpoint genérico, un usuario autenticado con permiso de emisión puede (a) forzar saltos de estado (ej. ENCARGADA emite `ACT_REPARTO_INSTITUCIONAL` desde cualquier estado → `CONCLUIDO_REMITIDO`); (b) emitir actuados ya emitidos (duplicar transiciones); (c) cerrar un expediente **sin informe final** en AC022/AC054 (el Bloqueo de Salida solo protege los informes financieros); (d) saltarse el filtro de cierre. Requiere sesión válida + rol del catálogo + (operador) asignación activa.

## 5. Huecos del grafo (AUD-0021, P1)

1. **`EN_EJECUCION → PENDIENTE_VISTO_BUENO_FINAL` no existe para AC022/AC054.** El único actuado de informe de esos perfiles (`ACT_INFORME_FINAL`, rol jurídico) tiene `estado_destino_id = null` → no-op. Solo los informes **financieros** (AC055) llegan a `PENDIENTE_VISTO_BUENO_FINAL`. Por tanto `CierreExpedienteService@aprobarVistoBueno` (exige ese estado, `:56`) **nunca es alcanzable por arista válida en la vía Técnico/Jurídico** — solo sembrando el estado a mano (como hacen los tests) o vía el bypass AUD-0020.
2. **`ADMITIDO` no tiene arista de salida.** Tras la revocación (`ImpugnacionService:142-153` → ADMITIDO + plazo PLANIFICACION + bandeja al operador), ningún actuado del catálogo tiene `estado_origen = ADMITIDO` y `cargarPlanificacion` exige `EN_PLANIFICACION` (policy `:175`) → 403. La vía formal queda cerrada; el puente solo existe si se emite `ACT_ADMISION`/`ACT_CRONOGRAMA` por el genérico (rely on AUD-0020).
3. **Estados huérfanos:** `EN_INVESTIGACION`, `EN_DESCARGOS`, `CONCLUIDO` no son destino ni origen de ningún actuado real (el código de la app no los referencia). `OBSERVADO` es solo subestado declarativo de `EN_EVALUACION` (la observación real lleva a `EN_SUBSANACION`).
4. **SRS:** el SRS no exige explícitamente estos estados; AMBIGÜEDAD entre "Admitido" como estado intermedio (SRS RF-04 menciona Admitidos/Observados/Rechazados en reportes) y su uso real. Documentar, no cambiar sin decisión.

## 6. Inmutabilidad (requisito Fase 4)

- Triggers MySQL `BEFORE UPDATE`/`BEFORE DELETE` sobre `actuados` (`2026_08_28_182631_create_actuados_triggers.php` + `add_hash_trigger_lock`) que rechazan la operación y encadenan `hash_anterior`/`hash_actuado`.
- **Test que espera el fallo: YA EXISTE** — `CadenaCustodiaTest:137` (UPDATE → `QueryException` y contenido sin cambios) y `CadenaCustodiaTest:153` (DELETE → `QueryException` y el registro persiste). Requisito del plan cubierto sin crear tests nuevos.
- REST-NO-DELETE del SRS `:177`: cumplida y testeada.

## 7. Cobertura de tests vs grafo real (AUD-0022, P2)

- **Ningún test ejecuta los seeders de catálogo** (`CatalogoEstadoSeeder`/`CatalogoActuadoSeeder` no aparecen en `tests/`, grep verificado); cada test crea sus propias filas con `factory()`/`create()`.
- **Fixtures divergentes del catálogo real:** p.ej. `RelojProcesualTest` afirma `ACT_ADMISION → ADMITIDO` y `VB → EN_INVESTIGACION` con catálogos propios, mientras el seeder real dice `→ EN_PLANIFICACION` y `→ EN_EJECUCION`. Los tests pasan validando **sus** grafos, no el grafo de producción (los huecos de §5 no están cubiertos: ningún test encadena revocación→planificación ni informe AC022→cierre).

## 8. Resumen de la fase

| Ítem | Resultado |
| ---- | --------- |
| Estados inventariados | 21 normativos; 16 en BD dev (AUD-0023) |
| Actuados inventariados | 25 normativos; 7 en BD dev (AUD-0023) |
| Validación de transiciones | ✓ en endpoints específicos; **✗ en el genérico** (AUD-0020) |
| Huecos del grafo | 2 críticos + 3 huérfanos (AUD-0021) |
| Inmutabilidad + test de fallo | ✓ (tests preexistentes `CadenaCustodiaTest:137,153`) |
| Tests nuevos creados | 0 (el requisito ya estaba cubierto) |
| AUD-0001 / AUD-0002 | abiertos / abiertos (análisis de plazos en Fase 5) — sin tocar |
