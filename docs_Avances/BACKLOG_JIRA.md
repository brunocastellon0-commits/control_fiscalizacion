# Backlog Jira — Sistema de Control y Fiscalización

**Consejo de la Magistratura — Cochabamba**
**Unidad de Control y Fiscalización**

> Documento generado a partir del análisis completo del código real (migraciones, modelos,
> servicios, controladores, rutas, tests, vistas) en `docs_Avances`. Jerarquía: **Épica →
> Historia → Sub-tareas `[Backend]` / `[Frontend]`**. Equipo: 2 desarrolladores.

**Leyenda de estado:** ✅ Hecho · 🔶 Parcial (falta terminar) · ⬜ Por hacer

---

## A. ANÁLISIS DE ESTADO ACTUAL vs SRS

### A.1 Lo que está EN VERDE (verificado en código)

| Módulo | Evidencia | Estado |
|---|---|---|
| Auth stateful por roles + sesiones | `AuthController`, `SesionAcceso`, `SeguridadSesionesService`, rate limiting | ✅ Hecho |
| Apertura de Causa (NUREJ, partes, adjunto base) | `ExpedienteService@aperturaCausa`, `NurejGeneratorService`, `ParteResourceTest` | ✅ Hecho |
| Bandeja Sorteo + algoritmo probabilístico | `SorteoAlgorithmService`, `sorteo_pesos`, `SortearTodosRequest` | ✅ Hecho |
| Bandeja Operador + semáforos de plazos | `SemaforoPlazoService`, `bandeja-operador.blade.php` | ✅ Hecho |
| Detalle expediente + cadena de custodia + adjuntos | Triggers MySQL hash SHA-256 (`actuados_triggers`), `ActuadoController`, `CadenaCustodiaTest` | ✅ Hecho |
| Seguridad / Expulsión de usuarios | `auditoria_usuarios`, `SeguridadSesionesService@expulsar`, `inactivar` | ✅ Hecho |
| Cálculo de plazos (hábiles / feriados / suspensiones) | `PlazoCalculatorService` (inyecta feriados + suspensiones) | ✅ Base Hecha (ver gap en CRON) |
| Aislamiento de bandejas (RF-03) | `ExpedientePolicy`, `SecurityCompartimentosTest` | ✅ Hecho |

### A.2 Gaps — lo que falta terminar o construir

| # | Hallazgo | Detalle |
|---|---|---|
| 1 | **Catálogo de actuados incompleto y con bug** | `CatalogoActuadoSeeder` solo tiene **6 de ~30 actuados** del SRS. `ACT_OBSERVACION/ADMISION/RECHAZO` están seedeados **solo para AUD_JURIDICO** — los **Ténicos no podrían evaluar**. Bloquea las Épicas 3/4/5. |
| 2 | **Sin tareas programadas (CRON)** | `routes/console.php` solo tiene `inspire`. No hay comando de vencimiento, archivo por abandono ni alerta fuera-de-plazo. |
| 3 | **Recálculo inexistente** | `PlazoCalculatorService` ya excluye suspensiones al *calcular*, pero los `plazos` ya abiertos NO se recalculan al registrar una suspensión retroactiva. |
| 4 | **Tablas sin flujo (solo migración + modelo)** | `evaluaciones_admisibilidad`, `impugnaciones`, `transferencias`, `catalogo_requisitos`: no tienen controller, ruta ni servicio. |
| 5 | **Tabla EXCUSAS NO EXISTE** | No existe `excusas_recusaciones`. Requiere migración nueva (esquema propuesto en E6, requiere confirmación). |
| 6 | **Sin Planificación ni Visto Bueno** | No hay actuados Cronograma / MPA / VB. Sin esto no arranca el reloj de EJECUCION (RN-04/05). `plazos` ya tiene `fecha_pausa/fecha_reanudacion` (listo para descargos). |
| 7 | **Sin Informes Finales ni Salidas** | No hay flujo VB-Final → Reparto Institucional → Conclusión, ni Remisión a Transparencia, ni Descargos del Auditor Financiero, ni Ampliación de plazo. |
| 8 | **Sin Derivación NUREJ Hijo** | `NurejGeneratorService@generarHijo` **existe como método** pero no hay endpoint ni flujo (RN-10). |
| 9 | **Sin Dashboard / Reportes** | `SemaforoPlazoService@resumenBandeja` implementado **sin endpoint expuesto**. No hay endpoints RF-R01…R09 ni export Excel/PDF/Carátula. |
| 10 | **Sin Actuado de Enmienda (RF-02)** | Sin endpoints para corregir partes conservando el original. |
| 11 | **Sin CRUD admin de catálogos** | Feriados, suspensiones, requisitos, reglamentos, parámetros_plazo, estados/actuados: solo seeders de lectura. |

---

## B. BACKLOG JIRA

**Jerarquía:** Épica → Historia (user story) → Sub-tareas `[Backend]` / `[Frontend]`.

---

### ÉPICA 1 — Motor de Control y Gestión de Plazos (CRON + Suspensiones)

| Clave | Tipo | Título | Prioridad | Estado | Criterios de Aceptación / Tests Pest |
|---|---|---|---|---|---|
| E1-S1 | Historia | Como **Encargada** quiero que los plazos se venzan automáticamente a medianoche para no depender de control manual | **Alta** | ⬜ | **DoD:** comando programado en `routes/console.php` (schedule diario 00:00). Marca `plazos.estado VIGENTE→VENCIDO`; si vence una SUBSANACIÓN sin subsanar → crea `ACT_ARCHIVO_POR_ABANDONO` automático + estado final; si vence EJECUCION → estampa `fuera_de_plazo=true` **sin bloquear** el informe final. `hash_anterior` encadenado por trigger. **Pest:** `PlazosVencidosCommandTest` — se registra plazo vencido y el comando lo marca; archivo por abandono crea actuado y transición; ejecución vencida se estampa fuera-de-plazo y permite continuar. |
| E1-S1-BE | Sub-tarea `[Backend]` | Crear comando `plazos:verificar-vencidos` + `ArchivoPorAbandonoService` + schedule | — | ⬜ | Eventos inmutables solo por `registerActuado`; transaccional. |
| E1-S1-FE | Sub-tarea `[Frontend]` | Alerta visual permanente "FUERA DE PLAZO" (roja) en bandeja operador y detalle cuando `fuera_de_plazo` | — | ⬜ | Etiqueta visible, no bloquea acciones. |
| E1-S2 | Historia | Como **Administrador** quiero registrar suspensiones de plazos (feriados, vacaciones judiciales) y que las fechas límite de plazos abiertos se recalculen solas | **Alta** | ⬜ | **DoD:** CRUD `suspensiones_plazo` (rol ADMIN, validación fechas y motivo ≥3). El registro dispara `PlazoRecalculoService` que actualiza `fecha_limite` de plazos `VIGENTE` usando la calculadora real (descontando la suspensión). **Pest:** `SuspensionPlazoCrudTest` + `RecalculoPlazoTest` — al insertar suspensión de N días, la fecha límite de un plazo abierto se desplaza N días hábiles. |
| E1-S2-BE | Sub-tarea `[Backend]` | Migración no requerida (tabla existe); controller CRUD + `PlazoRecalculoService` + Form Requests + Policy Admin | — | ⬜ | |
| E1-S2-FE | Sub-tarea `[Frontend]` | Página "Suspensiones de Plazo" (listado + alta con motivo/rango) — Alpine + Blade | — | ⬜ | Confirmación de recálculo al guardar. |
| E1-S3 | Historia | Como **Administrador** quiero mantener el calendario de feriados para que la calculadora los descuente siempre | Media | ⬜ | **DoD:** CRUD `feriados` (fecha única, descripción, ámbito). Recalcula plazos abiertos (mismo servicio E1-S2). **Pest:** `FeriadoCrudTest` — alta/baja y efecto en cálculo. |
| E1-S3-BE | Sub-tarea `[Backend]` | CRUD `FeriadoController` + Form Requests + Policy | — | ⬜ | |
| E1-S3-FE | Sub-tarea `[Frontend]` | Formulario feriados integrado en la misma página de suspensiones | — | ⬜ | |

---

### ÉPICA 2 — Completar Catálogo de Actuados, Estados y Requisitos (pre-requisito transversal)

| Clave | Tipo | Título | Prioridad | Estado | Criterios de Aceptación / Tests Pest |
|---|---|---|---|---|---|
| E2-S1 | Historia | Como **desarrollador** quiero un catálogo de actuados/estados completo y por perfil para que el flujo entero funcione | **Alta** | 🔶 | **DoD:** Seeder ampliado a los actuados del SRS (~30): Enmienda, Cronograma, MPA, VB Planificación, Ampliación, Comunicación Hallazgos, Recepción Descargos, 4 informe final Técnico, 2 Jurídico, 2 Financiero, Derivación Incompetencia, Archivo Abandono, VB Final, Reparto Institucional, Remisión Transparencia, Resolución Impugnación (Ratifica/Revoca), Remisión/Recepción transferencia, Creación NUREJ Hijo. **Corregir bug:** ACT_ADMISION/OBSERVACION/RECHAZO deben aplicar a TECNICO, AUD_JURIDICO y AUD_FINANCIERO. Completar estados `EN_SUBSANACION`, `EN_PLANIFICACION`, `EN_EJECUCION`, `EN_DESCARGOS`, `VB_PLANIFICACION`, `EN_VISTO_BUENO_FINAL`, `CONCLUIDO_REMITIDO`, `ARCHIVADO`, `TRANSFERRIDO`, más `es_final` correcto. **Pest:** `CatalogoActuadoTest` — cada actuado tiene rol/reglamento/estado destino válido; operador ve solo los de su perfil (extiende `CatalogoActuadoControllerTest`). |
| E2-S1-BE | Sub-tarea `[Backend]` | Ampliar `CatalogoActuadoSeeder`, `CatalogoEstadoSeeder`, `CatalogoRequisitoSeeder` + test de integridad | — | 🔶 | |
| E2-S2 | Historia | Como **Técnico/Auditor** quiero ver el checklist de requisitos de mi reglamento al evaluar una causa | Alta | ⬜ | **DoD:** endpoint `GET /api/catalogo/requisitos?reglamento_id=` con requisitos activos ordenados. Solo roles operativos. **Pest:** `CatalogoRequisitoResourceTest`. |

---

### ÉPICA 3 — Evaluación de Admisibilidad y Subsanación

| Clave | Tipo | Título | Prioridad | Estado | Criterios de Aceptación / Tests Pest |
|---|---|---|---|---|---|
| E3-S1 | Historia | Como **Técnico/Auditor asignado** quiero evaluar requisitos con checklist y emitir automáticamente Admisión/Observación/Rechazo | **Alta** | ⬜ | **DoD:** endpoint `POST /api/expedientes/{id}/evaluacion` — registra en `evaluaciones_admisibilidad` (requisito/cumple/actuado), conserva historial inmutable. Regla automática: **todos cumplen → ACT_ADMISION**; **faltan subsanables → ACT_OBSERVACION** (abre plazo SUBSANACION 3d, ya mapeado en `ActuadoService`); **requisito no subsanable → ACT_RECHAZO** (habilita impugnación Épica 7). Guardado vía `registerActuado`. Cambio de estado `EN_EVALUACION` → `ADMITIDO/OBSERVADO/RECHAZADO`. **Pest:** `EvaluacionAdmisibilidadTest` — emisión correcta por combinación; solo el asignado puede evaluar (403 si ajeno); observación abre SUBSANACION; actuados encadenados. |
| E3-S1-BE | Sub-tarea `[Backend]` | `EvaluacionController` + `EvaluacionAdmisibilidadService` (regla automática) + Form Request + ampliar mapas de plazos | — | ⬜ | |
| E3-S1-FE | Sub-tarea `[Frontend]` | Checklist de requisitos en Detalle Expediente (Alpine: check / no-check + botón "Evaluar y emitir" con modal de confirmación y adjunto opcional) | — | ⬜ | |
| E3-S2 | Historia | Como **interesado** quiero saber que tengo 3 días para subsanar y que el sistema archive por abandono si no lo hago | Media | ⬜ | **DoD:** contador de SUBSANACION visible en detalle; al vencer el cron de E1-S1 genera `ACT_ARCHIVO_POR_ABANDONO` (estado final `ARCHIVADO`). **Pest:** `SubsanacionAbandonoTest` — subsanación vencida sin respuesta → estado ARCHIVADO por actuado automático. |
| E3-S3 | Historia | Como **Técnico** quiero registrar la subsanación recibida para reanudar la evaluación | Alta | ⬜ | **DoD:** endpoint emitir actuado de subsanación con adjunto (pausa/reanudación según SRS — ver E5). **Pest:** adjunto obligatorio; transición OBSERVADO → ADMITIDO. |

---

### ÉPICA 4 — Planificación (Cronograma / MPA) y Visto Bueno Jerárquico

| Clave | Tipo | Título | Prioridad | Estado | Criterios de Aceptación / Tests Pest |
|---|---|---|---|---|---|
| E4-S1 | Historia | Como **Técnico** quiero cargar mi Cronograma de Trabajo en 2 días para que se active la planificación | Alta | ⬜ | **DoD:** `ACT_CRONOGRAMA_TRABAJO` (adjunto obligatorio) → `EN_PLANIFICACION`. Plazo PLANIFICACION 2d (RN-04). **Pest:** `CronogramaFeatureTest`. |
| E4-S1-BE | Sub-tarea `[Backend]` | Alta de actuado cronograma en `ActuadoService` (mapa plazo PLANIFICACION para TECNICO) | — | ⬜ | |
| E4-S1-FE | Sub-tarea `[Frontend]` | Formulario cronograma + upload en detalle | — | ⬜ | |
| E4-S2 | Historia | Como **Auditor** quiero cargar mi MPA con fecha límite dinámica para que el sistema respete mi cronograma | Alta | ⬜ | **DoD:** `ACT_MPA` solicita adjunto + fecha_limite propuesta; crea plazo de EJECUCION con `fecha_limite` dinámica (no los 10/15 fijos). **Pest:** `MpaPlazoDinamicoTest` — la fecha límite aprobada es la del MPA. |
| E4-S3 | Historia | Como **Encargada** quiero dar Visto Bueno a la planificación para que arranque oficialmente el cronograma de investigación | **Alta** | ⬜ | **DoD:** `ACT_VISTO_BUENO_PLANIFICACION` → inicia reloj EJECUCION (10/15d Técnico según vía; MPA para auditores). Encargada puede devolver con observación (`ACT_DEVOLUCION_OBSERVACION`). **Pest:** `VBIniciaEjecucionTest` — tras VB el plazo EJECUCION está vigente y con fecha correcta según vía; VB solo Encargada (403 otros). |
| E4-S3-FE | Sub-tarea `[Frontend]` | Bandeja VB de Encargada (aprobar / observar cronograma o MPA con comentario) | — | ⬜ | |

---

### ÉPICA 5 — Ejecución, Descargos, Informes Finales y Salidas

| Clave | Tipo | Título | Prioridad | Estado | Criterios de Aceptación / Tests Pest |
|---|---|---|---|---|---|
| E5-S1 | Historia | Como **Técnico** quiero ampliar +5 días mi plazo de investigación cuando se justifique | Media | ⬜ | **DoD:** `ACT_AMPLIACION_PLAZO` suma 5 días hábiles al plazo EJECUCION vigente (usa calculadora). Máximo 1 ampliación por caso. **Pest:** `AmpliacionPlazoTest`. |
| E5-S2 | Historia | Como **Auditor Financiero** quiero comunicar hallazgos y dar 5 días de descargos pausando el reloj | **Alta** | ⬜ | **DoD:** `ACT_COMUNICACION_HALLAZGOS` pausa el plazo EJECUCION (`fecha_pausa`) y abre sub-reloj DESCARGOS 5d (exclusivo AUD_FINANCIERO). `ACT_RECEPCION_DESCARGOS` lo cierra y reanuda (`fecha_reanudacion`). **Pest:** `FaseDescargosTest` — reloj pausado/reanudado correcto; informe final financiero bloqueado si no hubo descargos. |
| E5-S3 | Historia | Como **operador** quiero emitir mi Informe Final correspondiente a mi motor | **Alta** | ⬜ | **DoD:** 8 actuados de informe final restringidos por perfil (4 Técnico, 2 Jurídico, 2 Financiero), adjunto obligatorio, contenido JSON con conclusiones. El catálogo expone SOLO los del perfil. **Pest:** `InformeFinalPerfilTest` — un Técnico no ve/emite actuados jurídicos; informe con recomendación de auditoría habilita NUREJ Hijo (Épica 9). |
| E5-S4 | Historia | Como **Encargada** quiero dar Visto Bueno Final y ejecutar la salida institucional | **Alta** | ⬜ | **DoD:** estado `VB_FINAL` tras informe; `ACT_VISTO_BUENO_FINAL`; `ACT_REPARTO_INSTITUCIONAL` exige destino (Juzgado Disciplinario / Sumariante / Asesoría Legal) → estado `CONCLUIDO_REMITIDO` (es_final). **Pest:** `CierreExpedienteTest` — sin VB no hay salida; destino obligatorio; estado final inmutable. |
| E5-S5 | Historia | Como **operador** quiero derivar por incompetencia penal hacia Transparencia para sellar el caso | Alta | ⬜ | **DoD:** `ACT_DERIVACION_INCOMPETENCIA` congela plazos y exige VB inmediato de Encargada → `ACT_REMISION_TRANSPARENCIA` (cierre). **Pest:** `DerivacionTransparenciaTest`. |
| E5-S5-FE | Sub-tarea `[Frontend]` | Flujo de informes en detalle (wizard por perfil, firma VB, selección de destino final) | — | ⬜ | |

---

### ÉPICA 6 — Excusas y Recusaciones

| Clave | Tipo | Título | Prioridad | Estado | Criterios de Aceptación / Tests Pest |
|---|---|---|---|---|---|
| E6-S1 | Historia | Como **Auditor/Técnico** quiero excusarme de una causa para garantizar imparcialidad | **Alta** | ⬜ | **DoD:** **migración nueva `excusas_recusaciones`** (no existe; propuesta: `expediente_id`, `solicitante_id`, `auditado_id`, `tipo[EXCUSA/RECUSACION]`, `motivo`, `actuado_origen_id`, `resuelto`, `created_at`) — **requiere confirmación del esquema**. `ACT_EXCUSA` devuelve la causa a `PENDIENTE_SORTEO` sin cerrarla. |
| E6-S2 | Historia | Como **Encargada** quiero re-sortear la causa excluyendo al excusado | **Alta** | ⬜ | **DoD:** re-sorteo probabilístico (`SorteoAlgorithmService`) que **excluye** al auditado excusado y **no incrementa** su peso. Nuevo actuado `ACT_SORTEO_DERIVADO`. **Pest:** `ResorteoExclusionTest` — el excusado nunca es asignado; el resto mantiene probabilidad por pesos. |
| E6-S1-FE | Sub-tarea `[Frontend]` | Botón "Excusa / Recusación" en detalle con motivo + modal de re-sorteo para Encargada | — | ⬜ | |

---

### ÉPICA 7 — Impugnaciones

| Clave | Tipo | Título | Prioridad | Estado | Criterios de Aceptación / Tests Pest |
|---|---|---|---|---|---|
| E7-S1 | Historia | Como **interesado** quiero impugnar un rechazo inicial para que se revise mi caso | Alta | ⬜ | **DoD:** tras ACT_RECHAZO se registra `impugnaciones` (fecha de presentación → `PENDIENTE`). Turno de **1 día** para que el operador remita a la Encargada (RN-08); turno de **3 días** en bandeja Encargada (`fecha_limite_resolucion`). **Pest:** `ImpugnacionPlazosTest` — fechas 1d / 3d correctas con días hábiles. |
| E7-S2 | Historia | Como **Encargada** quiero resolver la impugnación ratificando o revocando el rechazo | **Alta** | ⬜ | **DoD:** `ACT_RESOLUCION_IMPUGNACION_RATIFICA` → estado final `ARCHIVO_DEFINITIVO`; `ACT_RESOLUCION_IMPUGNACION_REVOCA` → `ADMITIDO` y devuelve a **bandeja del operador original** (nueva asignación activa). **Pest:** `ResolucionImpugnacionTest` — revoca reabre al operador original; ratifica cierra. |
| E7-S1-FE | Sub-tarea `[Frontend]` | Pantalla impugnación (registro) + bandeja resolución con contador de 3 días | — | ⬜ | |

---

### ÉPICA 8 — Transferencias Inter-Unidad y Derivación por Incompetencia

| Clave | Tipo | Título | Prioridad | Estado | Criterios de Aceptación / Tests Pest |
|---|---|---|---|---|---|
| E8-S1 | Historia | Como **Encargada** quiero transferir un expediente a otra unidad cuando se determine incompetencia | **Alta** | ⬜ | **DoD:** `ACT_REMISION` (origen, obligatorio por RF-05) → `transferencias` estado PENDIENTE; no está concluida hasta `ACT_RECEPCION` (destino). **Pest:** `TransferenciaTest` — sin recepción el trámite sigue en transferencia; ambos actuados auditables. |
| E8-S1-BE | Sub-tarea `[Backend]` | `TransferenciaController` + `TransferenciaService` (pares remisión / recepción) | — | ⬜ | |
| E8-S1-FE | Sub-tarea `[Frontend]` | Formulario de transferencia (unidad destino) y UI de confirmación de recepción | — | ⬜ | |

---

### ÉPICA 9 — Trazabilidad Padre-Hijo (NUREJ Hijo)

| Clave | Tipo | Título | Prioridad | Estado | Criterios de Aceptación / Tests Pest |
|---|---|---|---|---|---|
| E9-S1 | Historia | Como **Encargada** quiero crear un NUREJ Hijo cuando un informe lo recomienda | **Alta** | 🔶 | **DoD:** `NurejGeneratorService@generarHijo` ya existe (método) → exponer endpoint de creación (`ACT_CREACION_NUREJ_HIJO`) solo disponible tras informe final con recomendación de auditoría. Hijo = flujo **independiente** (no hereda actuados del Padre, RN-10); cierra Padre. **Pest:** `NurejHijoTest` — formato `YYYY-NNNNN-X`; historial independiente; salidas asíncronas permitidas. |
| E9-S1-FE | Sub-tarea `[Frontend]` | Botón "Derivar a auditoría (NUREJ Hijo)" en detalle de Encargada | — | ⬜ | |

---

### ÉPICA 10 — Dashboard y Métricas de la Encargada

| Clave | Tipo | Título | Prioridad | Estado | Criterios de Aceptación / Tests Pest |
|---|---|---|---|---|---|
| E10-S1 | Historia | Como **Encargada** quiero un dashboard con carga laboral, plazos vencidos e historial de sorteos | **Alta** | 🔶 | **DoD:** endpoints agregados (RF-R01, R02, R07) + exposición de `resumenBandeja` (hoy sin ruta). Filtros por fecha/usuario/estado. Coincidencia 100% con SQL directo (criterio de aceptación del SRS). **Pest:** `DashboardEndpointTest` (métricas concuerdan con `SemaforoPlazoService`); autorización Encargada/Admin (403 otros). |
| E10-S1-BE | Sub-tarea `[Backend]` | `DashboardController` + `DashboardService` (carga por perfil, vencidos, sorteos) | — | ⬜ | |
| E10-S1-FE | Sub-tarea `[Frontend]` | Página dashboard con cards / semáforos, tabla de vencidos y gráficos (Alpine / Tailwind) | — | ⬜ | |
| E10-S2 | Historia | Como **Encargada** quiero exportar reportes en Excel/PDF y generar la Carátula Oficial | Media | ⬜ | **DoD:** RF-R02…R09 con export xlsx / pdf (requiere dependencias nuevas → **confirmación**); RF-R08 Carátula en PDF con NUREJ (foja 0). **Pest:** `ReporteExportTest` — mismos datos que el endpoint JSON; NUREJ no editable. |

---

### ÉPICA 11 — Correcciones (Actuado de Enmienda, RF-02)

| Clave | Tipo | Título | Prioridad | Estado | Criterios de Aceptación / Tests Pest |
|---|---|---|---|---|---|
| E11-S1 | Historia | Como **operador** quiero corregir partes conservando el dato original | Media | ⬜ | **DoD:** modificar una parte exige `ACT_ENMIENDA` (guarda original + nuevo en contenido JSON); la parte usa `vigente_hasta / es_version_actual` (ya diseñado). Nunca UPDATE silencioso. **Pest:** `ActuadoEnmiendaTest` — original preservado en actuado; parte versionada queda visible. |

---

### ÉPICA 12 — Administración de Catálogos (Admin)

| Clave | Tipo | Título | Prioridad | Estado | Criterios de Aceptación / Tests Pest |
|---|---|---|---|---|---|
| E12-S1 | Historia | Como **Administrador** quiero administrar reglamentos, parámetros de plazos y requisitos | Alta | ⬜ | **DoD:** CRUD `reglamentos` (versionado RN-06: `vigente_desde / hasta` conservando transitoriedad), `parametros_plazo`, `catalogo_requisitos`. Solo admin activo. **Pest:** `AdminCatalogoTest` — permisos y validaciones; no se pueden duplicar reglas activas (uk existente). |
| E12-S1-BE | Sub-tarea `[Backend]` | Controllers CRUD + Form Requests + Policy Admin | — | ⬜ | |
| E12-S1-FE | Sub-tarea `[Frontend]` | Páginas admin (tablas + formularios) | — | ⬜ | |

---

## C. Resumen de prioridades sugeridas (plan de sprints)

| Sprint | Alcance |
|---|---|
| **Sprint 1** | Épica 2 (catálogo completo + fix Técnico) → Épica 1 (CRON + suspensiones) → Épica 3 (admisibilidad). Desbloquea el flujo entero. |
| **Sprint 2** | Épica 4 (planificación → VB) + Épica 5 (ejecución → informes → salidas) + Épica 7 (impugnaciones). |
| **Sprint 3** | Épicas 6, 8, 9 (excusas, transferencias, NUREJ Hijo). |
| **Sprint 4** | Épicas 10, 11, 12 (dashboard/reportes, enmienda, catálogos admin). |

### Riesgos a resolver antes de codificar

1. **Migración `excusas_recusaciones`** — confirmar esquema propuesto (E6).
2. **Dependencias Excel/PDF** para E10-S2 (requiere aprobación de nuevas dependencias).
3. **Bug del catálogo de actuados para TECNICO** — debe corregirse primero (E2-S1).