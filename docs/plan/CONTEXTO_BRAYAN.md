# CONTEXTO DE INICIACIÓN Y OPERACIÓN — DESARROLLADOR: BRAYAN

> **Propósito:** Este documento es autosuficiente y de lectura obligatoria para cualquier agente de IA o desarrollador que asuma el rol de **Brayan (Interfaz, Reportes y Entrega)**. Contiene todas las directrices, especificaciones de interfaz, contratos, límites de propiedad y convenciones necesarias para ejecutar sus tareas sin ambigüedades.

---

## 1. QUÉ ES EL PROYECTO

El sistema es la plataforma institucional de **Control y Fiscalización del Consejo de la Magistratura** (Distrito Cochabamba, Estado Plurinacional de Bolivia). Su función es registrar, sortear, evaluar y sustanciar denuncias e investigaciones contra funcionarios judiciales en una red intranet gubernamental aislada (sin internet público).

- **Stack Tecnológico:** Backend en **Laravel** (PHP 8.3+), base de datos **MySQL 8.0+ / 9.0+**, frontend reactivo con **Blade + Alpine.js + Tailwind CSS** (utilizando el helper institucional `apiFetch`), y suite de pruebas con **Pest PHP**.
- **Carga Horaria de Brayan:** **122 horas** estimadas, distribuidas en 5 Sprints (Sprint 1: 21 h, Sprint 2: 19 h, Sprint 3: 26 h, Sprint 4: 28 h, Sprint 5: 28 h).
- **Los 3 Reglamentos Procesales:**
  1. *Acuerdo 022/2018 (Vía Técnica):* Denuncias disciplinarias contra personal judicial ordinario; 2 días para evaluación, 3 para subsanación, 2 para planificación; fase de ejecución: 10 días hábiles (Jurisdiccional) o 15 días hábiles (Administrativa), con +5 días de ampliación excepcional en ambos casos.
  2. *Acuerdo 054/2018 (Vía Auditoría Jurídica):* Control legal de fallos y plazos procesales; 5 días para evaluación; ejecución sujeta a fecha de MPA.
  3. *Acuerdo 055/2018 (Vía Auditoría Financiera):* Revisión de depósitos y valores; fase de descargos de 5 días hábiles normativos tras comunicación de hallazgos.
- **Los 5 Roles Institucionales del Sistema:**
  - `TECNICO`, `AUD_JURIDICO`, `AUD_FINANCIERO`: Operadores con bandejas individuales de causas asignadas (los usuarios no pertenecen a una unidad).
  - `ENCARGADA`: Supervisa la unidad, sortea expedientes, emite vistos buenos y registra la remisión/recepción de causas externas.
  - `ADMIN`: Administra cuentas, feriados y catálogos. **REGLA CRÍTICA:** El Administrador **NO puede ver expedientes de causas, ni descargar adjuntos probatorios, ni mover trámites, ni ver reportes operativos** (retorna HTTP 403 Forbidden).

---

## 2. GLOSARIO DE TÉRMINOS OPERATIVOS

- **NUREJ:** Número Único de Registro Judicial. Código correlativo e irrepetible asignado en la apertura de causa (`YYYY-NNNNN`, RF-01).
- **NUREJ Padre / Hijo:** Derivación procesal asíncrona hacia otra especialidad (`YYYY-NNNNN-X`) con independencia de actuados y línea de tiempo propia (RN-10).
- **Actuado:** Hito procesal inmutable emitido en la causa (RF-02). En la UI se despliega en un timeline cronológico con verificación visual de hashes SHA-256.
- **Visto Bueno (VB):** Aprobación formal emitida por la Encargada sobre cronogramas, ampliaciones, derivaciones por incompetencia o informes finales.
- **MPA:** Memorando de Planificación de Auditoría (Acuerdos 054 y 055).
- **Subsanación:** Plazo de 3 días hábiles otorgado al denunciante cuando faltan requisitos de forma (RN-03).
- **Descargos:** Periodo de 5 días hábiles otorgado a los auditados en causas financieras (AC055) tras comunicar hallazgos.
- **Semáforo de Plazos (Umbrales Reales en Código):** Verificado en `SemaforoPlazoService.php`:
  - **ROJO:** 0 o 1 días hábiles restantes (`diasRestantes <= 1`).
  - **AMARILLO:** Plazos cortos (`<= 3` días) con exactamente 2 días restantes (`diasRestantes === 2`); plazos largos (`> 3` días) cuando días restantes caen en el último tercio (`diasRestantes <= ceil(totalOtorgado / 3)`).
  - **VERDE:** Resto de días hábiles.
  - **FUERA DE PLAZO:** Estado estampado por el cron cuando `fecha_limite < hoy`.
- **Fuera de Plazo:** Marca persistente estampada por el cron. **No bloquea la continuidad del expediente** (la UI debe permitir al operador seguir emitiendo actuados), pero se exhibe en rojo con advertencia.
- **Unidad:** Catálogo institucional de destinos y orígenes de causas físicas (Transparencia, Ministerio Público, Régimen Disciplinario, y destinos para el Reparto Institucional final). Las unidades externas **NO son usuarios del sistema** (no tienen cuentas ni interfaz de inicio de sesión).
- **Remisión y Recepción Inter-Unidad (RF-05):** Flujo físico registrado exclusivamente por la Encargada. La remisión (`ACT_REMISION_UNIDAD`) transiciona la causa a `REMITIDO_UNIDAD_EXTERNA` (solo lectura, fuera de bandejas operativas normales). El reingreso físico devuelto por la unidad externa se registra con `ACT_RECEPCION_UNIDAD`, reanudando el trámite en su etapa procesal (`EN_EVALUACION` o `EN_PLANIFICACION`) gracias a `estado_previo_id`.

---

## 3. REGLAS DE NEGOCIO CRÍTICAS PARA BRAYAN

Estas son las reglas del SRS oficial y criterios de auditoría que rigen estrictamente tus tareas de interfaz, reportes y soporte:

| Regla | Descripción y Comportamiento Obligatorio en Frontend / Entrega | Tareas Vinculadas |
|---|---|---|
| **RF-04** | *Evaluación Dinámica de Requisitos:* El operador asignado evalúa un checklist parametrizable por reglamento. Si un ítem crítico (`es_critico=true`) se marca como no cumplido, la UI alerta que provocará rechazo u observación. | B4.1, B1.9 |
| **RF-02** | *Actuado de Enmienda:* Interfaz modal para enmendar datos caratulados o procesales (CTR-09), mostrando el dato anterior y capturando el nuevo dato para auditoría. | B4.2 |
| **RF-03 / RNF-01** | *Compartimentos Estancos y Búsqueda Segura:* Operadores solo ven y actúan sobre expedientes asignados a su bandeja activa (los usuarios no pertenecen a una unidad). Administrador bloqueado (HTTP 403) de ver expedientes, actuaciones o reportes. | B0.10, B4.7, B8.11 |
| **RF-05** | *Transferencia Inter-Unidad:* Modal exclusivo de la Encargada para registrar remisión física (CTR-04) y recepción física (CTR-05) con respaldo documental. | B4.9 |
| **RF-06** | *Bandeja de la Encargada:* Pantalla de supervisión donde la Encargada identifica causas pendientes de visto bueno, ampliaciones, impugnaciones o derivaciones. | B4.3 |
| **RF-R01…R09** | *9 Reportes Normativos del SRS:* Emisión en pantalla interactiva (B5.1) y exportación fiel a Excel (`.xlsx`) y PDF (`.pdf`) (B5.2), consumiendo datos de Bruno en `routes/api/core.php` (CTR-08). Ocultos para el rol ADMIN (`ReportePolicy`). | B5.1, B5.2, B8.10 |
| **Criterio Auditoría** | *Notificaciones In-App:* Sistema de alertas visuales en campana superior sobre plazos en riesgo (<= 2 días hábiles) sin requerir correo electrónico. | B3.4 |
| **RNF-03** | *Rendimiento y Paginación:* Cero llamadas a `->get()` en listados masivos; paginación server-side estándar con `LengthAwarePaginator` y consultas SQL agregadas en dashboards. | B0.5, B7.1, B7.3 |
| **RNF-04** | *Validación FormRequests:* Entradas validadas en backend con retorno HTTP 422 y despliegue amigable de errores por campo en la interfaz. | B0.2, B8.9 |
| **CA-3** | *Fuera de Plazo No Bloqueante:* Plazos vencidos estampan alerta visual pero la botonera procesal permanece habilitada para no paralizar la causa. | B4.4, B8.9 |

---

## 3.1 SEMÁFOROS DE PLAZOS Y GUÍA DE ESTILOS VISUALES (TAILWIND CSS)

El semáforo se calcula en backend vía `SemaforoPlazoService` (singleton en `AppServiceProvider`) y se renderiza en las bandejas y carátula del expediente con la siguiente lógica unificada:

| Estado Visual | Condición Matemática (`diasRestantes`) | Clases Tailwind CSS Sugeridas | Comportamiento en UI |
|---|---|---|---|
| **VERDE (Normal)** | `diasRestantes > 2` (plazos cortos) o en los 2 primeros tercios de plazos largos. | `bg-emerald-100 text-emerald-800 border-emerald-300 dark:bg-emerald-950 dark:text-emerald-300` | Trámite holgado; sin alertas urgentes. |
| **AMARILLO (Atención)** | `diasRestantes === 2` en plazos `<= 3` días; o `diasRestantes <= ceil(total / 3)` en plazos `> 3` días. | `bg-amber-100 text-amber-800 border-amber-300 dark:bg-amber-950 dark:text-amber-300` | Próximo a vencer; dispara notificación in-app de campana (B3.4). |
| **ROJO (Urgente)** | `diasRestantes <= 1` (vence hoy o el siguiente día hábil). | `bg-rose-100 text-rose-800 border-rose-300 font-semibold dark:bg-rose-950 dark:text-rose-300` | Alerta máxima; primer ítem en ordenamiento prioritario de bandejas. |
| **FUERA DE PLAZO** | Marca cron cuando `fecha_limite < hoy` (medianoche hora La Paz). | `bg-red-700 text-white font-bold animate-pulse shadow-sm` | **NO BLOQUEANTE:** La botonera de actuados permanece habilitada. Se despliega badge rojo visible de advertencia. |

---

## 3.2 ESPECIFICACIÓN DETALLADA DE LOS 9 REPORTES SRS (RF-R01 A RF-R09)

En las tareas B5.1 (pantallas), B5.2 (exportación Excel/PDF), B5.3 (carátula) y B8.10 (conciliación) se implementan estrictamente las siguientes estructuras normativas:

1. **RF-R01: Carga Laboral por Usuario**
   - *Propósito:* Monitoreo de productividad y casos pendientes segmentados por perfil (`TECNICO`, `AUD_JURIDICO`, `AUD_FINANCIERO`).
   - *Columnas:* Funcionario/Operador, Rol Institucional, Vía Procesal, Casos Asignados Activos, En Evaluación, En Planificación, En Ejecución, Ampliados, Fuera de Plazo, Total Causas Pendientes.
   - *Filtros:* Rango de fechas de asignación, perfil/rol, usuario específico.
   - *Exportación:* `.xlsx` con totales agregados por fila y `.pdf` horizontal formal.

2. **RF-R02: Solicitudes Registradas (Libro Matriz Digital)**
   - *Propósito:* Libro matriz correlativo de ingresos de denuncias y causas disciplinarias.
   - *Columnas:* NUREJ, Fecha de Ingreso, Denunciante/Parte, Denunciado, Cargo Judicial, Vía Procesal, Naturaleza (`JURISDICCIONAL` / `ADMINISTRATIVA`), Estado Procesal, Operador Asignado.
   - *Filtros:* Rango de fechas de ingreso, vía procesal, estado, naturaleza procesal.
   - *Exportación:* `.xlsx` de datos masivos y `.pdf` institucional con foliado digital.

3. **RF-R03: Recepción Inter-Unidad**
   - *Propósito:* Auditoría y trazabilidad de causas físicas remitidas a unidades externas y recibidas de retorno.
   - *Columnas:* NUREJ, Tipo de Movimiento (`REMISION` / `RECEPCION`), Fecha de Salida/Reingreso, Unidad Externa (Transparencia, Ministerio Público, etc.), Número de Oficio / Resolución, Motivo Institucional, Observaciones de Retorno, Funcionario que Registró (Encargada).
   - *Filtros:* Unidad externa, tipo de movimiento, rango de fechas.
   - *Exportación:* `.xlsx` y `.pdf` para actas de entrega y recepción.

4. **RF-R04: Estado de Evaluación**
   - *Propósito:* Balance y control del checklist de admisibilidad inicial por reglamento.
   - *Columnas:* NUREJ, Operador Asignado, Fecha Evaluación, Resultado (`ADMITIDO`, `OBSERVADO`, `RECHAZADO`), Requisitos Específicos Incumplidos, Estado de Subsanación/Impugnación, Días de Evaluación Empleados.
   - *Filtros:* Rango de fechas, resultado de admisibilidad, operador evaluador.
   - *Exportación:* `.xlsx` y `.pdf`.

5. **RF-R05: Notificaciones Pendientes**
   - *Propósito:* Seguimiento perentorio del plazo de subsanación de 3 días hábiles otorgado al denunciante.
   - *Columnas:* NUREJ, Denunciante Notificado, Fecha Emisión Observación, Fecha Límite de Vencimiento (23:59:59 La Paz), Días Hábiles Restantes, Semáforo de Riesgo, Estado Procesal (`EN_SUBSANACION` / `ARCHIVADO_ABANDONO`).
   - *Filtros:* Semáforo (rojo/amarillo), operador, rango de fechas.
   - *Exportación:* `.xlsx` y `.pdf`.

6. **RF-R06: Resoluciones Finales**
   - *Propósito:* Compendio de informes finales aprobados con visto bueno y salidas de Reparto Institucional.
   - *Columnas:* NUREJ, Tipo de Informe Final Emitido, Fecha Emisión Operador, Fecha VB Encargada, Resultado Sustantivo (Demanda Disciplinaria, Remisión Sumariante, Remisión Ministerio Público, Archivo de Obrados), Unidad Externa de Destino.
   - *Filtros:* Rango de fechas de resolución, vía procesal, resultado institucional.
   - *Exportación:* `.xlsx` y `.pdf`.

7. **RF-R07: Estadístico por Vía**
   - *Propósito:* Métricas globales e indicadores de gestión y carga procesal del distrito.
   - *Columnas / Métricas:* Vía Procesal (Técnica AC022, Jurídica AC054, Financiera AC055), Total Ingresadas, % Admitidas, % Observadas, % Rechazadas, Tiempo Promedio de Resolución (días hábiles), % de Causas Ampliadas (+5d), % Causas Fuera de Plazo.
   - *Filtros:* Rango anual o semestral, vía procesal.
   - *Exportación:* `.xlsx` con fórmulas de porcentaje y `.pdf` con tarjetas ejecutivas.

8. **RF-R08: Carátula Oficial (PDF Foja Cero)**
   - *Propósito:* Impresión oficial de la carátula de expediente para el legajo físico (B5.3).
   - *Estructura Visual:* Encabezado oficial con Escudo de Bolivia y Consejo de la Magistratura, código NUREJ en tipografía destacada, Partes procesales, Vía y Naturaleza, Fecha y hora de apertura, Semáforo y estado actual, Hash SHA-256 de custodia procesal y recuadro reservado para firmas/sellos.

9. **RF-R09: Trazabilidad de Derivaciones Padre-Hijo**
   - *Propósito:* Control de causas complejas que derivaron en la apertura de un expediente derivado independiente.
   - *Columnas:* NUREJ Padre, Vía Padre, Operador Padre, NUREJ Hijo (`-X`), Vía Destino Hijo, Reglamento Destino, Fecha de Derivación, Estado Actual Hijo, Operador Asignado Hijo.
   - *Filtros:* Rango de fechas, vía padre, vía hijo.
   - *Exportación:* `.xlsx` y `.pdf`.

---

## 3.3 ARQUITECTURA DE VISTAS Y PARCIALES BLADE

- `resources/views/layouts/app.blade.php`: Cascarón que incluye:
  - `layouts/sidebar.blade.php`: Navegación según roles (`TECNICO`, `AUD_JURIDICO`, `AUD_FINANCIERO`, `ENCARGADA`, `ADMIN`), resolviendo AUD-0044 (oculta Bandeja de Entrada a ENCARGADA y ADMIN).
  - `layouts/notificaciones.blade.php`: Campana reactiva de plazos por vencer.
  - `layouts/buscador.blade.php`: Buscador reactivo superior por NUREJ.
- `resources/views/expedientes/detalle.blade.php`: Vista maestra que orquesta:
  - `detalle/header.blade.php`: Encabezado, semáforo y datos generales.
  - `detalle/timeline.blade.php`: Historial inmutable con verificación de hash SHA-256.
  - `detalle/evaluacion.blade.php`: Checklist dinámico de admisibilidad (RF-04).
  - `detalle/acciones-operativas.blade.php`: Botonera para las 14 operaciones procesales (incluye VB de Incompetencia de la Encargada).
  - `detalle/modal-enmienda.blade.php`: Modal de corrección de datos con auditoría (RF-02 / CTR-09).
  - `detalle/modal-actuado.blade.php`: Modal contextualizado por reglamento y estado (CTR-03).
  - `detalle/remision-unidad.blade.php`: Modal exclusivo para la Encargada (remisión y recepción física B4.9).
- `resources/views/financiero/dashboard.blade.php`: Tablero para Auditoría Financiera enfocado en control de descargos (5d) y plazos AC055 (B4.6).
- `resources/views/encargada/bandeja-supervision.blade.php`: Bandeja de supervisión de la Encargada (CTR-07).
- `resources/views/reportes/index.blade.php` y `tabla.blade.php`: Vistas interactivas de los 9 reportes SRS con filtros y botones de descarga Excel y PDF.

---

## 4. CAMBIOS RESPECTO AL SRS ORIGINAL Y DECISIONES VIGENTES

1. **Zona Horaria Institucional:** Se fijó `America/La_Paz` (Bolivia, UTC-4). El scheduler corta a las 23:59:59 hora local.
2. **Motor MySQL:** El sistema corre en MySQL 8.0+ / 9.0+. Las migraciones y optimizaciones deben usar tipos e índices de MySQL (sin fechas fijadas a priori en nombres de archivo).
3. **Mecánica de Unidades Externas (RF-05):** Las unidades externas **NO son usuarios del sistema** (no tienen cuentas ni inicio de sesión; no existe menú ni bandeja para Transparencia en el sidebar). La Encargada dispone de modales exclusivos en `detalle/remision-unidad.blade.php` para registrar la Remisión física (`ACT_REMISION_UNIDAD`, CTR-04) y el reingreso físico de la causa con su Recepción (`ACT_RECEPCION_UNIDAD`, CTR-05), con soporte de adjuntos en PDF.
4. **Verificación Previa de "Montos" en Auditoría Financiera (B4.6):**
   - Se verificó en la base de datos que **NO existe campo de montos** en el esquema.
   - El Dashboard Financiero se redefinió para enfocarse en: control de descargos comunicados vs recepcionados, plazos de 5 días hábiles normativos, causas en riesgo de vencimiento y causas pendientes de visto bueno final bajo el Acuerdo 055.
5. **Decisión PENDIENTE (NO IMPLEMENTAR):**
   - **AUD-0011 (D-10):** Doble confirmación financiera en pausa por validación normativa. **Brayan no debe programar pantallas ni modales de esta función.**

---

## 5. MAPA DE DOCUMENTOS DE REFERENCIA

| Ruta del Archivo | Qué Contiene | Cuándo Consultarlo (Tareas) |
|---|---|---|
| [`COORDINACION.md`](COORDINACION.md) | Reglas de merge, contratos CTR y archivos calientes. | **Siempre** antes de crear ramas o vistas. |
| [`TAREAS_BRAYAN.md`](TAREAS_BRAYAN.md) | Tu desglose detallado de tareas con criterios y horas (122 h). | **Siempre** para verificar alcance de tarea. |
| [`docs/plan/DISENO_UNIDADES_INTER.md`](DISENO_UNIDADES_INTER.md) | Diseño corto de catálogo de unidades y transferencias. | En B4.9. |
| [`docs/auditoria/MATRIZ_UX_FRONTEND.md`](../auditoria/MATRIZ_UX_FRONTEND.md) | Inventario de vistas Blade, llamadas API y fallos UX. | En B0.7, B4.1, B4.2, B4.5. |
| [`docs/auditoria/MATRIZ_REPORTES.md`](../auditoria/MATRIZ_REPORTES.md) | Matriz de reportes R01 a R09 y filtros requeridos. | En B5.1, B5.2, B8.10. |
| [`docs/auditoria/MATRIZ_RENDIMIENTO.md`](../auditoria/MATRIZ_RENDIMIENTO.md) | Consultas N+1, índices faltantes y optimización dashboard. | En B0.5, B0.6, B7.1, B7.3. |
| [`docs/auditoria/MATRIZ_JOBS_CRON.md`](../auditoria/MATRIZ_JOBS_CRON.md) | Scheduler de plazos, concurrencia y tolerancia a fallos. | En B6.1, B6.2. |
| [`docs/auditoria/MATRIZ_BANDEJAS.md`](../auditoria/MATRIZ_BANDEJAS.md) | Mecánica de bandejas de operadores y Encargada. | En B4.3, B4.4. |
| [`docs/auditoria/SRS_EXTRAIDO.txt`](../auditoria/SRS_EXTRAIDO.txt) | Especificación original de requisitos y reportes. | Para verificar textos y requisitos RF-xx. |

---

## 6. PROPIEDAD ESTRICTA DE ARCHIVOS (BRAYAN)

### Archivos y Directorios que BRAYAN SÍ puede modificar:
- `resources/views/**` (todas las vistas Blade, parciales en `detalle/`, layout particionado en `layouts/` y componentes).
- `app/Http/Requests/*.php` (todos los FormRequests existentes; **excepto** `DerivarNurejHijoRequest` y `StoreExpedienteRequest`).
- `app/Http/Middleware/SecurityHeaders.php`.
- `app/Providers/AppServiceProvider.php` (registro de singleton y rate limits).
- `bootstrap/app.php` (registro de middleware global).
- `composer.json` y `composer.lock` (instalación de paquetes de exportación en B5.2).
- `.env.example` (documentación central de variables; Bruno le suministra las suyas).
- `routes/api/admin.php`, `routes/api/reportes.php` y `routes/api/notificaciones.php`.
- `routes/web.php` (rutas de vistas de workstation y reportes).
- `routes/console.php` (scheduler de comandos).
- `app/Console/Commands/VerificarVencimientoPlazosCommand.php` (solo logging y dependencias).
- `app/Services/CatalogoCacheService.php` (creación en B7.2 e invalidación en admin).
- `app/Http/Controllers/Administrador/**`, `app/Http/Controllers/ReporteController.php`, `NotificacionController.php`, `FinancieroDashboardController.php`, `EncargadaBandejaController.php`.
- Controladores en `app/Http/Controllers/*.php` exclusivamente para sustituir validación inline por FormRequest en B0.2.
- `app/Notifications/PlazoPorVencerNotification.php`.
- `app/Exports/*.php` (exportadores Excel).
- `database/seeders/FeriadoSeeder.php`, `CatalogoRequisitoSeeder.php`, `ParametroPlazoSeeder.php`.
- `database/migrations/` (migraciones vespertinas con prefijo `20XXXX`, incluyendo tabla de caché).
- `tests/Feature/` (tests de UI, FormRequests, reportes, cron e índices B8.9 a B8.11).

### Archivos y Directorios que BRAYAN TIENE PROHIBIDO TOCAR:
- `app/Models/*.php` (propiedad exclusiva de **Bruno**).
- `app/Policies/*.php` (propiedad exclusiva de **Bruno**).
- `app/Services/ActuadoService.php` (propiedad exclusiva de **Bruno**).
- `app/Services/ArchivoPorAbandonoService.php` (propiedad de **Bruno**).
- `app/Services/SemaforoPlazoService.php` y `PlazoCalculatorService.php` (propiedad de **Bruno**).
- `app/Services/` de dominio (`ExpedienteService`, `NurejHijoService`, `TransferenciaUnidadService`, `ReportesQueryService`).
- `app/Http/Controllers/ReglamentoController.php` y `CatalogoEstadoController.php` (propiedad de **Bruno**).
- `app/Http/Requests/DerivarNurejHijoRequest.php` y `StoreExpedienteRequest.php` (propiedad de **Bruno**).
- `routes/api/core.php` y `routes/api/operativo.php` (propiedad de **Bruno**).
- `app/Providers/AuditoriaServiceProvider.php` (propiedad de **Bruno**).

---

## 7. CONTRATOS QUE BRAYAN CONSUME DEL BACKEND DE BRUNO

| ID | Endpoint y Método | Datos Esperados | Mock Sugerido para Desarrollo de UI |
|---|---|---|---|
| **CTR-01** | `GET /api/expedientes/{e}/requisitos` | `[{id, codigo, nombre, es_critico, cumplido}]` | Retornar array JSON estático en `init()` de Alpine. |
| **CTR-02** | `POST /api/expedientes/{e}/evaluacion` | `{resultado: ADMISION\|OBSERVACION\|RECHAZO}` | Simular respuesta `setTimeout` con `{resultado: 'ADMISION'}`. |
| **CTR-03** | `GET /api/catalogo/actuados?expediente_id={e}` | `[{id, codigo, nombre, requiere_adjunto}]` | Filtrar array estático de `CatalogoActuadoSeeder`. |
| **CTR-04** | `POST /api/expedientes/{e}/remitir-unidad` | `{unidad_id, motivo, adjunto?}` | Retornar `{ok: true, transferencia_id: 1, estado: 'REMITIDO_UNIDAD_EXTERNA'}`. |
| **CTR-05** | `POST /api/expedientes/{e}/registrar-recepcion` | `{unidad_origen_id, motivo, adjunto?}` | Retornar `{ok: true, estado: 'EN_EVALUACION'\|'EN_PLANIFICACION'}`. |
| **CTR-06** | `GET /api/expedientes/buscar?q={query}` | `[{id, nurej_code, via, estado}]` | Retornar coincidencias de causas asignadas al usuario. |
| **CTR-07** | `GET /api/encargada/bandeja-supervision` | Paginator con `{data: [{nurej, plazo_restante}]}` | Paginator mock con 5 registros de prueba. |
| **CTR-08** | `GET /api/reportes/{codigo}` (en `core.php`) | `{meta, filtros_aplicados, data: [...]}` | Array de filas tabulares acordes al reporte R01..R09 (ADMIN 403). |
| **CTR-09** | `POST /api/expedientes/{e}/enmienda` | `{campo_modificado, valor_nuevo, motivo}` | Retornar `{ok: true, actuado_enmienda_id: 1}`. |
| **CTR-10** | `POST /api/expedientes/{e}/incompetencia/solicitar` y `.../visto-bueno` | Solicitud y VB Encargada | Retornar `{ok: true, estado: 'REMITIDO_UNIDAD_EXTERNA'}`. |

---

## 8. CONVENCIONES TÉCNICAS Y RECOMENDACIONES

- **Ramas Git:** Nombrar estrictamente `brayan/<id_tarea>-<slug>` (ej. `brayan/B4.1-ui-evaluacion-admisibilidad`).
- **Migraciones:** Usar prefijo horario vespertino: `YYYY_MM_DD_20XXXX_nombre.php` (sin fechas fijas del calendario).
- **Llamadas a API en Blade:** Usar siempre `window.apiFetch(url, options)`. Incluye automáticamente cookies de sesión, token CSRF y cabecera `Accept: application/json`.
- **Notificaciones Toasts:** Invocar `window.apiToast(mensaje, tipo, detalle)` para feedback visual (verde éxito, rojo error).
- **Almacenamiento Local:** Prohibido guardar datos de causas o tokens en `localStorage` o `sessionStorage`.
- **Formateo:** Correr siempre `vendor/bin/pint --dirty --format agent` antes de finalizar.
- **Ejecución de Tests:**
  - Correr prueba aislada: `vendor/bin/pest tests/Feature/TuTest.php`.
  - Correr suite completa: `php artisan test --compact`.

---

## 9. DEFINICIÓN DE TERMINADO (DoD) DE BRAYAN

Una tarea se considera 100% finalizada únicamente si:
1. Las vistas renderizan correctamente y no producen errores en la consola de JavaScript.
2. Todos los tests nuevos escritos para la tarea pasan en verde (`PASS`).
3. La suite general de pruebas se mantiene en verde (cero regresiones).
4. `vendor/bin/pint --dirty --format agent` no reporta problemas de estilo.
5. La rama tiene rebase limpio sobre `main` sin conflictos.
6. Se entrega un reporte final listando: archivos tocados, tests ejecutados y contratos consumidos.

---

## 10. PROMPT DE ARRANQUE PARA SESIONES DE BRAYAN

```text
Hola. Actúas como el agente de IA para BRAYAN (Interfaz, Reportes y Entrega) en el proyecto control_fiscalizacion.
Antes de hacer nada, lee atentamente docs/plan/CONTEXTO_BRAYAN.md y docs/plan/COORDINACION.md.
Hoy trabajaremos exclusivamente la tarea [ID_DE_TAREA] de docs/plan/TAREAS_BRAYAN.md.
Reglas estrictas:
1. Respeta la propiedad de archivos de Brayan; no toques modelos en app/Models/ ni servicios de dominio de Bruno.
2. Si requieres lógica o datos del backend que aún no existen, consume el contrato CTR correspondiente utilizando mocks.
3. Todo código PHP debe seguir el estilo de Pint y las vistas Blade deben ser reactivas y limpias.
4. Al finalizar, reporta los archivos modificados, los tests ejecutados y cualquier supuesto técnico.
¿Entendido? Confirma y empezamos con la tarea indicada.
```
