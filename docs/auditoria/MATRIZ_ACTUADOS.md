# Matriz de actuados — código real (Fase 4)

- **Fecha:** 2026-09-29
- **Fuente:** `database/seeders/CatalogoActuadoSeeder.php` (25 filas) + `catalogo_actuado_roles` (pivotes) + `ActuadoService::MAPA_TIPO_PLAZO`. La comparación SRS ↔ código ya está en `MATRIZ_SRS_IMPLEMENTACION.md` §5; esta matriz documenta la implementación operativa.
- **Columnas:** ID · Actuado · Fase · Rol(es) habilitado(s) (pivote: rol + reglamento) · Automático · Adjunto · Origen → Destino · Plazo que abre · Emisor (endpoint/servicio) · Autorización · Validación de origen · Tests.

| ID | Actuado | Fase | Rol(es) habilitado(s) | Auto | Adj. | Origen → Destino | Plazo | Emisor | Autorización | Val. origen | Tests |
| -- | ------- | ---- | --------------------- | ---- | ---- | ---------------- | ----- | ------ | ------------ | ----------- | ----- |
| A01 | ACT_REGISTRO_DIGITALIZACION | REGISTRO | TECNICO (AC022*) | no | **sí** | — → PENDIENTE_SORTEO | — | POST `/api/expedientes` (`ExpedienteService@aperturaCausa`) | `StoreExpedienteRequest` (rol TÉCNICO) + `aperturaCausa` | creación | `ExpedienteControllerTest` ✅ |
| A02 | ACT_SORTEO_INICIAL | ADMISIBILIDAD | ENCARGADA | no | no | PENDIENTE_SORTEO → EN_EVALUACION | EVALUACION (2/5/5) | POST `.../sortear` (`ExpedienteService@sortear`) | `SortearExpedienteRequest` (ENCARGADA) | ✓ servicio | `ExpedienteControllerTest:250,274` ✅ |
| A03 | ACT_OBSERVACION | ADMISIBILIDAD | TECNICO-AC022 / AUD_JUR-AC054 / AUD_FIN-AC055 | no | no | EN_EVALUACION → EN_SUBSANACION | SUBSANACION (3) | POST `.../evaluacion` (`EvaluacionAdmisibilidadService@evaluar`) | policy `evaluarAdmisibilidad` (asignación) | ✓ servicio `:91-99` | `EvaluacionAdmisibilidadTest` ✅ |
| A04 | ACT_ADMISION | ADMISIBILIDAD | ídem A03 | no | no | EN_EVALUACION → EN_PLANIFICACION | PLANIFICACION (2) | ídem | ídem | ✓ servicio | `EvaluacionAdmisibilidadTest` ✅ |
| A05 | ACT_RECHAZO | ADMISIBILIDAD | ídem A03 | no | no | EN_EVALUACION → RECHAZADO | IMPUGNACION_REMITIR (1) | ídem | ídem | ✓ servicio | `EvaluacionAdmisibilidadTest` ✅ |
| A06 | ACT_CRONOGRAMA_TRABAJO | PLANIFICACION | TECNICO (AC022) | no | **sí** | EN_PLANIFICACION → PENDIENTE_VISTO_BUENO | — | POST `.../planificacion` (`PlanificacionService@cargar`) | policy `cargarPlanificacion` | ✓ policy `:175` | `PlanificacionTest:174` ✅ |
| A07 | ACT_MPA | PLANIFICACION | AUD_JUR (AC054) / AUD_FIN (AC055) | no | **sí** | EN_PLANIFICACION → PENDIENTE_VISTO_BUENO | — (fecha límite explícita) | ídem (MPA) | ídem | ✓ policy | `PlanificacionTest:215` ✅ |
| A08 | ACT_VISTO_BUENO_PLANIFICACION | PLANIFICACION | ENCARGADA | no | no | PENDIENTE_VISTO_BUENO → EN_EJECUCION | EJECUCION (10) | POST `.../planificacion/visto-bueno` | policy `aprobarPlanificacion:210` | ✓ policy | `PlanificacionTest:326,373` ✅ |
| A09 | ACT_DEVOLUCION_OBSERVACION | PLANIFICACION | ENCARGADA | no | no | PENDIENTE_VISTO_BUENO → EN_PLANIFICACION | PLANIFICACION (2) | POST `.../planificacion/devolver` | policy `devolverPlanificacion:221` | ✓ policy | `PlanificacionTest:442,497` ✅ |
| A10 | ACT_SOLICITAR_AMPLIACION | INVESTIGACION | TECNICO (AC022) | no | no | EN_EJECUCION → PENDIENTE_APROBACION_AMPLIACION | — | POST `.../ampliacion` (`AmpliacionService@solicitar`) | policy `solicitarAmpliacion` | ✓ `validarEstado` | `AmpliacionTest:140,245` ✅ |
| A11 | ACT_APROBAR_AMPLIACION | INVESTIGACION | ENCARGADA | no | no | PENDIENTE_APROBACION_AMPLIACION → EN_EJECUCION | (cierra original; abre EJECUCION_AMPLIADA 5) | POST `.../ampliacion/aprobar` | policy `aprobarAmpliacion` | ✓ `validarEstado` | `AmpliacionTest:182,282` ✅ |
| A12 | ACT_INFORME_FINAL | INVESTIGACION | AUD_JUR (AC054) | no | **sí** | EN_EJECUCION → **null (no-op)** | — | **genérico** POST `.../actuados` | policy `crearActuado` | **✗ sin validar** | ninguno específico (AUD-0008) |
| A13a | ACT_INFORME_AUDITORIA_FINANCIERA_CON_RESPONSABILIDAD | INVESTIGACION | AUD_FIN (AC055) | no | **sí** | EN_EJECUCION → PENDIENTE_VISTO_BUENO_FINAL | — | **genérico** + Bloqueo de Salida (`StoreActuadoRequest:53-65`) | policy `crearActuado` + descargos previos | **✗ sin validar** | `DescargoFinancieroTest:211` ✅ |
| A13b | ACT_INFORME_AUDITORIA_FINANCIERA_SIN_RESPONSABILIDAD | INVESTIGACION | AUD_FIN (AC055) | no | **sí** | EN_EJECUCION → PENDIENTE_VISTO_BUENO_FINAL | — | ídem | ídem | **✗ sin validar** | `DescargoFinancieroTest:211` ✅ |
| A14 | ACT_REMITIR_IMPUGNACION | ADMISIBILIDAD | TECNICO / AUD_JUR / AUD_FIN | no | no | RECHAZADO → EN_IMPUGNACION | IMPUGNACION_RESOLVER (3) | POST `.../impugnacion/remitir` | policy `remitirImpugnacion` | ✓ policy `:147-151` | `ImpugnacionRechazoTest:197,163` ✅ |
| A15 | ACT_RESOLUCION_RATIFICA_RECHAZO | ADMISIBILIDAD | ENCARGADA | no | no | EN_IMPUGNACION → ARCHIVO_DEFINITIVO | (cierra plazos) | POST `.../impugnacion/resolver` | policy `resolverImpugnacion` | ✓ `validarEstado:106` | `ImpugnacionRechazoTest:243` ✅ |
| A16 | ACT_RESOLUCION_REVOCA_RECHAZO | ADMISIBILIDAD | ENCARGADA | no | no | EN_IMPUGNACION → **ADMITIDO (sin salida)** | PLANIFICACION (2) | ídem | ídem | ✓ `validarEstado` | `ImpugnacionRechazoTest:285` ✅ (termina en ADMITIDO; sin continuación — AUD-0021) |
| A17 | ACT_CREACION_NUREJ_HIJO | INVESTIGACION | ENCARGADA | no | no | — → — (padre no cambia; hijo → PENDIENTE_SORTEO) | — | POST `.../nurej-hijo` (`NurejHijoService`) | policy `derivarNurejHijo` (ENCARGADA) | ✓ policy | `NurejHijoTest:125,213` ✅ |
| A18 | ACT_VISTO_BUENO_FINAL | INVESTIGACION | ENCARGADA | no | no | PENDIENTE_VISTO_BUENO_FINAL → LISTO_PARA_REPARTO | — | POST `.../cierre/visto-bueno` (`CierreExpedienteService`) | policy `aprobarVistoBuenoFinal` | ✓ `validarEstado:56` | `CierreExpedienteTest:102,236` ✅ |
| A19 | ACT_REPARTO_INSTITUCIONAL | INVESTIGACION | ENCARGADA | no | no | LISTO_PARA_REPARTO → CONCLUIDO_REMITIDO | — | POST `.../cierre/reparto` | policy `ejecutarRepartoInstitucional` | ✓ `validarEstado` | `CierreExpedienteTest:137,248` ✅ |
| A20 | ACT_DERIVACION_INCOMPETENCIA | INVESTIGACION | TECNICO / AUD_JUR / AUD_FIN | no | **sí** | — → PENDIENTE_REMISION_TRANSPARENCIA (congela relojes) | — | POST `.../derivacion-transparencia` (`TransparenciaService@derivar`) | policy `derivarPorIncompetencia` (asignación + catálogo) | ✓ policy | `DerivacionTransparenciaTest:128,217` ✅ |
| A21 | ACT_REMISION_TRANSPARENCIA | INVESTIGACION | ENCARGADA | no | no | PENDIENTE_REMISION_TRANSPARENCIA → DERIVADO_TRANSPARENCIA | — | POST `.../transparencia/remitir` | policy `remitirTransparencia:326` | ✓ policy | `DerivacionTransparenciaTest:249,322` ✅ |
| A22 | ACT_COMUNICACION_HALLAZGOS | INVESTIGACION | AUD_FIN (AC055) | no | **sí** | — → — (no-op; **pausa reloj** + sub-reloj DESCARGOS 5 d) | — | POST `.../descargos/comunicar` (`DescargoFinancieroService`) | policy `comunicarHallazgos` (EN_EJECUCION + AC055) | ✓ `validarEstado` | `DescargoFinancieroTest:157,252` ✅ (AUD-0002 CERRADO 2026-09-30) |
| A23 | ACT_RECEPCION_DESCARGOS | INVESTIGACION | AUD_FIN (AC055) | no | **sí** | — → — (no-op; **reanuda reloj**) | — | POST `.../descargos/recibir` | policy `recibirDescargos` | ✓ `validarEstado` | `DescargoFinancieroTest:157` ✅ |
| A24 | ACT_ARCHIVO_POR_ABANDONO | ADMISIBILIDAD | sistema (ADMIN como emisor) | **sí** | no | EN_SUBSANACION → ARCHIVO_POR_ABANDONO | — | CRON `plazos:verificar-vencidos` → `ArchivoPorAbandonoService` | automático (vencimiento SUBSANACION) | ✓ servicio | `ArchivoPorAbandonoTest` ✅ |

\* El pivote de A01 no especifica reglamento (`reglamento_id=null`) pese a ser un actuado de TÉCNICO-AC022: comportamiento actual, documentado sin cambio.

## Estadística

- **25 actuados** en catálogo: 24 con emisor identificado, 1 automático (A24).
- **Con validación de estado de origen:** 21 (todos los flujos por endpoint específico + CRON).
- **Sin validación de origen (solo endpoint genérico):** 3 — A12, A13a, A13b (ver `MAQUINA_ESTADOS.md` §4, AUD-0020).
- **Requieren adjunto:** 9 de 25 (A01, A06, A07, A12, A13a, A13b, A20, A22, A23).
- **Abren plazo:** 8 de 25 (`MAPA_TIPO_PLAZO`, `ActuadoService:33-43`).
- **Pivotes de rol↔reglamento:** tabla `catalogo_actuado_roles` (borrada y reinsertada por el seeder, `:160-174`).

## Pendientes ligados a esta matriz

| Hallazgo | Qué falta | Fase |
| -------- | --------- | ---- |
| AUD-0008 | Informes Técnico (4) y jurídico diferenciados (2) inexistentes; A12 no-op de estado | 2/9 |
| AUD-0009 | Sin actuado de **Enmienda** (RF-02); mensaje del trigger lo menciona | 6+ |
| AUD-0020 | Origen no validado en el genérico — decisión 2026-09-30: matriz `actuado→origen→destino` antes de fix | 6/16 |
| AUD-0021 | Huecos del grafo (A12 destino null; A16 → ADMITIDO sin salida) — decisión 2026-09-30: matriz `estado→actuados→destinos→roles` antes de corregir | 6 |
| AUD-0022 | Tests no cubren el catálogo real (fixtures propias) | 16 |
| AUD-0023 | BD dev desactualizada (7/25 actuados, 16/21 estados) — decisión 2026-09-30: sync idempotente, NO `db:seed` global sin autorización | ambiente |
