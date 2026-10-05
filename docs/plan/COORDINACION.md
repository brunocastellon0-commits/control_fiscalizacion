# MANUAL DE COORDINACIÓN Y REGLAS ANTI-CONFLICTO (BRUNO & BRAYAN)

**Proyecto:** Sistema de Control y Fiscalización (Laravel + MySQL)  
**Fecha de Actualización:** 2026-10-05  
**Roles:**
- **Bruno:** Dominio, Seguridad y Datos (110 h)
- **Brayan:** Interfaz, Reportes y Entrega (122 h)  
**Propósito:** Definir la partición de responsabilidades, propiedad exclusiva de archivos, gestión de rutas/archivos calientes, orden de merge y contratos de integración para que dos desarrolladores trabajen en paralelo con agentes de IA con **cero conflictos de merge**.

---

## 1. PRINCIPIOS Y REGLAS DE CONVIVENCIA

1. **Propiedad Exclusiva por Archivo:** Cada archivo en el repositorio tiene **UN SOLO DUEÑO**. Ningún desarrollador debe abrir ramas ni crear PRs que toquen archivos asignados al otro. Si una necesidad técnica requiere modificar un archivo ajeno, el desarrollador se detiene y emite una solicitud formal de contrato.
2. **Desacoplamiento mediante Contratos:** Cuando una tarea de Brayan requiera lógica o datos del backend implementados por Bruno, se diseñará primero el contrato (endpoint, método, request, response, códigos de error). Brayan desarrolla la UI consumiendo ese contrato (utilizando mocks frontend si el backend aún no ha sido mergeado).
3. **Flujo de Ramas y Commits:**
   - Una rama por tarea: `<dev>/<id_tarea>-<slug_descriptivo>` (ej. `bruno/B2.0-fix-admin-bypass`, `brayan/B0.4-headers`).
   - PRs pequeños, atómicos y autofocalizados.
   - Rebase obligatorio diario sobre `origin/main` antes de solicitar revisión.
   - Prohibido mergear ramas con tests en fallo (`php artisan test` debe pasar al 100% y Pint debe estar limpio).
4. **Inmutabilidad de Migraciones:** Una migración mergeada a `main` jamás se edita. Si se requiere ajustar una tabla, se crea una nueva migración incremental aditiva.

---

## 2. ESTRATEGIA PARA ARCHIVOS CALIENTES (HOT FILES)

Los siguientes archivos/directorios representan puntos críticos de concurrencia. Se define una estrategia estricta de partición y aislamiento:

### 2.1 `routes/api.php` y partición modular
- **Problema:** Ambos desarrolladores añaden endpoints frecuentemente.
- **Estrategia:** Desacoplamiento modular en subdirectorios dentro de `routes/api/`:
  - `routes/api/core.php`: Propiedad de **Bruno** (expedientes, actuados, transferencias a unidades externas, auth me/logout, búsqueda CTR-06, bandeja Encargada CTR-07, catálogos de soporte `reglamentos` y `estados`, y endpoint base de datos JSON para los 9 reportes normativos del SRS CTR-08).
  - `routes/api/operativo.php`: Propiedad de **Bruno** (admisibilidad, impugnación, planificación, ampliación, cierre, descargos, enmienda CTR-09, incompetencia CTR-10).
  - `routes/api/admin.php`: Propiedad de **Brayan** (usuarios, feriados, monitoreo, catálogo requisitos admin).
  - `routes/api/reportes.php`: Propiedad de **Brayan** (descargas y exportaciones en Excel y PDF de los 9 reportes normativos RF-R01…R09, y generación de Carátula Oficial PDF).
  - `routes/api/notificaciones.php`: Propiedad de **Brayan** (alertas y notificaciones in-app de plazos).
- **Implementación Inicial:** Bruno crea en la Tarea B0.1 el cargador en `routes/api.php` que incluye mediante `require` estos cinco archivos. A partir de ese commit, cada desarrollador edita **únicamente** sus archivos correspondientes.

### 2.2 `AppServiceProvider.php`
- **Problema:** Registro de singletons, observadores y configuraciones globales.
- **Estrategia:** 
  - **Brayan** es dueño de `AppServiceProvider.php` (para registrar el singleton de plazos `SemaforoPlazoService` en B0.5 y rate limiters/paginación).
  - **Bruno** NO modificará `AppServiceProvider.php`. Para registrar observadores de auditoría y listeners de inmutabilidad creará un proveedor dedicado: `app/Providers/AuditoriaServiceProvider.php` (registrado limpiamente en `bootstrap/providers.php`).

### 2.3 `bootstrap/app.php`
- **Problema:** Registro de middleware global y manejo de excepciones.
- **Estrategia:** 
  - **Brayan** es dueño de `bootstrap/app.php` (para registrar el middleware `SecurityHeaders` en B0.4).
  - **Bruno** NO modificará `bootstrap/app.php`. La excepción de inmutabilidad `ActuadoInmutableException` (Tarea B2.5) implementará su propio método `render(Request $request)` nativo de Laravel, auto-gestionando su respuesta HTTP 422/409 sin tocar la configuración central.

### 2.4 `composer.json` y `composer.lock`
- **Problema:** Instalación de dependencias externas y sincronización de lockfile.
- **Estrategia:** 
  - **Brayan** es el dueño exclusivo de `composer.json` y `composer.lock` para la instalación de librerías en Bloque 5 (`maatwebsite/excel` y `barryvdh/laravel-dompdf`).
  - Bruno no incorporará paquetes externos. Si el núcleo requiere una librería, Bruno le solicitará a Brayan su instalación.

### 2.5 `.env.example`
- **Problema:** Documentación de nuevas variables de entorno.
- **Estrategia:** 
  - **Brayan** es el dueño de `.env.example`.
  - Bruno entregará a Brayan la lista de variables requeridas por sus tareas (ej. `APP_TIMEZONE=America/La_Paz`) para que Brayan las documente de forma centralizada en B9.2.

### 2.6 `database/seeders/`
- **Problema:** Carga de catálogos y datos iniciales.
- **Estrategia:** Partición de propiedad por seeder:
  - **Bruno:** `CatalogoActuadoSeeder.php`, `CatalogoEstadoSeeder.php`, `RolSeeder.php`, `UnidadSeeder.php`.
  - **Brayan:** `FeriadoSeeder.php`, `CatalogoRequisitoSeeder.php`, `ParametroPlazoSeeder.php`, `ExpedienteDemoSeeder.php`, `UsuariosExtraSeeder.php`.
  - `DatabaseSeeder.php`: Bruno define la estructura orquestadora en B0.1; Brayan solo añade llamadas a sus seeders específicos al final.

### 2.7 `app/Services/` neurálgicos y servicios de plazos
- **Problema:** Servicios neurálgicos del dominio que manejan la máquina de estados, transacciones y cómputo de plazos.
- **Estrategia:** 
  - `ActuadoService.php`, `PlazoCalculatorService.php` y `SemaforoPlazoService.php` son de **propiedad exclusiva de Bruno**. Brayan no edita ni refactoriza estos archivos (solo consume sus métodos o los registra como singleton).
  - La lógica de cierre y condición en `ArchivoPorAbandonoService.php` reside en la Tarea B1.4 de Bruno, incluyendo el bloque `try/catch` individual por expediente para que una causa con inconsistencia no interrumpa el lote.
  - En B6.2, Brayan se enfoca exclusivamente en `VerificarVencimientoPlazosCommand.php` para logging general y validación de dependencias del comando.

### 2.8 `resources/views/expedientes/detalle.blade.php`
- **Problema:** Vista central con múltiples componentes (timeline, actuados, semáforo, botones operativos, modales).
- **Estrategia:** Partición en parciales Blade dentro de `resources/views/expedientes/detalle/`:
  - `header.blade.php`: Encabezado, semáforo y asignación.
  - `timeline.blade.php`: Historial de actuados y verificación de hashes.
  - `modal-actuado.blade.php`: Emisión contextualizada de actuados (CTR-03).
  - `evaluacion.blade.php`: Checklist de admisibilidad (RF-04).
  - `acciones-operativas.blade.php`: Botonera de operaciones procesales.
  - `modal-enmienda.blade.php`: Formulario de enmienda de datos (RF-02 / CTR-09).
  - `remision-unidad.blade.php`: Modal exclusivo para la Encargada (remisión y registro de recepción).
- **Dueño de la vista y parciales:** **Brayan**. Bruno no programa componentes Blade.

### 2.9 `resources/views/layouts/app.blade.php` y partición en parciales
- **Problema:** Layout principal con sidebar, notificaciones, buscador y scripts.
- **Estrategia:** Partición de `layouts/app.blade.php` en parciales Blade dentro de `resources/views/layouts/`:
  - `sidebar.blade.php`: Menú lateral, perfiles de rol y enlaces institucionales (resuelve AUD-0044: ocultar Bandeja de Entrada a ENCARGADA y ADMIN).
  - `notificaciones.blade.php`: Componente de campana y panel de alertas in-app de plazos.
  - `buscador.blade.php`: Barra superior de búsqueda reactiva por NUREJ.
- **Dueño exclusivo del layout y parciales:** **Brayan** (Tarea B0.10, absorbe saneamiento de AUD-0044 y AUD-0057 en layout). Bruno no programa componentes Blade.

---

## 3. ORDEN DE MERGE DE CAMBIOS TRANSVERSALES (FASE CERO)

Para evitar que cambios masivos invaliden ramas paralelas, se establece una secuencia obligatoria en el Bloque 0:

1. **Paso 0.0 (Bruno - Desbloqueo Crítico de Seguridad AUD-0001):**
   - Ejecuta `bruno/B2.0-fix-admin-bypass` sobre `app/Policies/ExpedientePolicy.php` eliminando el bypass indebido de ADMIN.
   - **Dependencias:** Ninguna (sin dependencias).
   - **Impacto:** Pone de inmediato la suite de seguridad en verde (eliminando el único fallo de regresión actual). Merge inmediato a `main`.
2. **Paso 0.1 (Bruno - Prioridad Estructural):**
   - Ejecuta `bruno/B0.1-fillable-models` sobre todos los modelos en `app/Models/` asegurando `$fillable` explícito y columnas sensibles protegidas.
   - Agrega el trait `Illuminate\Notifications\Notifiable` en el modelo `Usuario.php`.
   - Estructura `DatabaseSeeder.php`.
   - Crea el particionamiento de rutas en `routes/api/` (`core.php`, `operativo.php`, `admin.php`, `reportes.php`, `notificaciones.php`) y el archivo `AuditoriaServiceProvider.php`.
   - **Regla:** Brayan NO toca ningún archivo en `app/Models/` hasta que este PR esté mergeado en `main`.
3. **Paso 0.2 (Brayan - FormRequests Existentes):**
   - Ejecuta `brayan/B0.2-form-requests-validacion` sobre los FormRequests existentes en `app/Http/Requests/`.
   - **Permiso acotado:** Brayan puede editar controladores existentes **únicamente** para reemplazar validación inline (`$request->validate()`) por la inyección del FormRequest correspondiente.
   - **Regla general de FormRequests:** Brayan es dueño únicamente de los FormRequests preexistentes; los FormRequests nuevos creados por cada desarrollador pertenecen a su autor (`DerivarNurejHijoRequest` y `StoreExpedienteRequest` pertenecen a Bruno).
   - Asigna explícitamente la regla `expediente_id` en `IndexCatalogoActuadosRequest` para habilitar el contrato CTR-03.
   - **Secuencia:** La rama `brayan/B0.2-form-requests-validacion` **debe mergearse antes de que Bruno inicie B0.3 y B1.1**.
4. **Paso 0.3 (Bruno - Roles en Dominio tras B0.2):**
   - Ejecuta `bruno/B0.3-roles-constantes` consolidando `Rol::CODIGO_*`.
   - **Dependencia obligatoria:** Depende de B0.2 de Brayan para evitar pisar controladores durante la inyección de FormRequests.
   - **Excepción única de propiedad en `app/`:** Bruno está facultado para modificar cualquier archivo de `app/` donde existan cadenas literales de roles según el grep real (`Rol.php`, `ExpedientePolicy.php`, `FeriadoPolicy.php`, `UsuarioPolicy.php`, `SorteoAlgorithmService.php`, `AmpliacionService.php`, `ArchivoPorAbandonoService.php`, `ImpugnacionService.php`, `PlanificacionService.php`, `TransparenciaService.php`, `AdminMonitoreoController.php`, `AdminDashboardController.php`, `UsuarioController.php`, `RolSeeder.php`).
   - Las vistas Blade en `resources/views/` serán saneadas por Brayan en B0.7. Merge a `main`.
5. **Paso 0.4 (Brayan en paralelo sobre infraestructura y vistas):**
   - `brayan/B0.4-headers` (`app/Http/Middleware/SecurityHeaders.php` y `bootstrap/app.php`).
   - `brayan/B0.5-singleton-plazos` (`app/Providers/AppServiceProvider.php`).
   - `brayan/B0.6-indices-rendimiento` (`database/migrations/YYYY_MM_DD_20XXXX_...`).
   - `brayan/B0.7-ux-menores` (`resources/views/`).
   - `brayan/B0.10-layout-partials` (partición de `layouts/app.blade.php`, resolviendo AUD-0044 en sidebar y AUD-0057 en layout).

---

## 4. CONVENCIONES DE MIGRACIONES Y BASE DE DATOS

1. **Motor de Base de Datos:** **MySQL 8.0+ / 9.0+** obligatorio. Tipos de datos, índices, sintaxis JSON y triggers deben ser compatibles con MySQL (prohibido código exclusivo de PostgreSQL o SQLite).
2. **Prefijos Horarios para Evitar Colisiones:**
   - **Bruno:** Horas de la mañana: `YYYY_MM_DD_10XXXX_nombre_migracion.php` a `YYYY_MM_DD_14XXXX_...`
   - **Brayan:** Horas de la tarde/noche: `YYYY_MM_DD_18XXXX_nombre_migracion.php` a `YYYY_MM_DD_22XXXX_...`
   - **Regla de Naming:** No fijar fechas del calendario en la documentación del plan; cada desarrollador estampará la marca de tiempo real al momento de ejecutar `php artisan make:migration`.
3. **Regla de Inmutabilidad:** Prohibido modificar migraciones existentes en `database/migrations/`. Todo cambio de esquema requiere una migración aditiva.

---

## 5. SUPUESTOS Y DECISIONES TÉCNICAS BASE

1. **Zona Horaria Institucional:**
   - Configuración: `APP_TIMEZONE=America/La_Paz` (Bolivia, UTC-4).
   - Mitigación: Bruno verificará que las marcas de tiempo guardadas en MySQL mantengan integridad visual sin desplazamientos horarios arbitrarios.
2. **Calendario y Feriados:**
   - Ámbito: Feriados Nacionales de Bolivia + Feriado Departamental de Cochabamba (14 de Septiembre).
3. **Mecánica de Unidades Externas (RF-05) y Distinción del Reparto Institucional:**
   - Las unidades externas (Transparencia, Ministerio Público, Régimen Disciplinario) **NO son usuarios del sistema**. No tienen cuentas ni inicio de sesión; no pertenecen al modelo `Usuario`.
   - Son destinos y orígenes de causas en soporte físico/oficio. El catálogo `unidades` sirve tanto para transferencias inter-unidad como para el Reparto Institucional final.
   - La tabla `transferencias_unidad` incorpora `estado_previo_id` (FK a `catalogo_estados`) al momento de la remisión, permitiendo que la recepción (CTR-05) restaure la causa a su estado procesal previo exacto.
   - La **Encargada** es la única funcionaria facultada para registrar la **Remisión** (`ACT_REMISION_UNIDAD`) y la **Recepción** (`ACT_RECEPCION_UNIDAD`).
   - Un expediente remitido transiciona al estado `REMITIDO_UNIDAD_EXTERNA` (solo lectura, fuera de bandejas operativas normales).
   - **Distinción Crítica con Reparto Institucional:** El *Reparto Institucional* es un **cierre procesal definitivo** (`CONCLUIDO` / `ARCHIVO` / resolución final firme) y NO transiciona a `REMITIDO_UNIDAD_EXTERNA`. B1.6 verificará taxativamente que ningún estado requerido por el Reparto Institucional sea inactivado.
4. **Verificación de Datos Financieros (B4.6):**
   - Se confirmó mediante auditoría de base de datos que el campo o tabla de "montos" NO existe en el esquema. El Dashboard Financiero (B4.6) se redefine alrededor del control de descargos y plazos normativos (Acuerdo 055).
5. **Estado de Existencia de Rutas y Archivos Citados en el Repositorio:**
   - Se comprobó mediante `ls` / `Test-Path` el estado real de todas las rutas referenciadas en este plan:
     - **Archivos existentes comprobados:** Modelos base en `app/Models/`, controladores existentes en `app/Http/Controllers/`, migraciones base en `database/migrations/`, seeders existentes en `database/seeders/`, vistas base en `resources/views/`, suite de tests F1-F18 en `tests/Feature/`.
     - **Archivos y rutas a ser creados durante la ejecución (marcar como nuevos en cada tarea):**
       - Modularización de rutas: `routes/api/core.php`, `routes/api/operativo.php`, `routes/api/admin.php`, `routes/api/reportes.php`, `routes/api/notificaciones.php`.
       - Providers y servicios nuevos: `app/Providers/AuditoriaServiceProvider.php`, `app/Services/TransferenciaUnidadService.php`, `app/Services/ReportesQueryService.php`, `app/Services/CatalogoCacheService.php`, `app/Policies/ReportePolicy.php`, etc.
       - Documentos de diseño y anexos: `docs/auditoria/ANEXO_SRS_MODIFICACIONES.md`, `docs/plan/DISENO_UNIDADES_INTER.md`, `docs/seguridad/CHECKLIST_DESPLIEGUE_SEGURIDAD.md`, `CHANGELOG.md`.
       - Parciales Blade: `resources/views/expedientes/detalle/*.blade.php`, `resources/views/layouts/sidebar.blade.php`, `resources/views/layouts/notificaciones.blade.php`, `resources/views/layouts/buscador.blade.php`, vistas de reportes `resources/views/reportes/*.blade.php`, `resources/views/expedientes/caratula-pdf.blade.php`.
       - Tests unitarios y feature nuevos correspondientes a cada tarea (`tests/Unit/ModelSecurityTest.php`, `tests/Feature/TimezoneBoliviaTest.php`, etc.).

---

## 6. MATRIZ DE CONTRATOS ENTRE DESARROLLADORES

Bruno verificará y mantendrá operativos los siguientes contratos en el backend antes de que Brayan proceda a integrar la UI final:

| ID Contrato | Funcionalidad | Método y Endpoint | Request Payload | Response Esperada | Dueño Backend | Dueño Frontend |
|---|---|---|---|---|---|---|
| **CTR-01** | Checklist Requisitos (RF-04) | `GET /api/expedientes/{e}/requisitos` | Query: ninguno | `200 OK`: `[{id, codigo, nombre, es_critico, cumplido}]` | Bruno (B1.1) | Brayan (B4.1) |
| **CTR-02** | Guardar Evaluación Admisibilidad | `POST /api/expedientes/{e}/evaluacion` | `{evaluaciones: [{requisito_id, cumplido, observacion}]}` | `200 OK`: `{resultado: ADMISION\|OBSERVACION\|RECHAZO, actuado_id}` | Bruno (B1.1) | Brayan (B4.1) |
| **CTR-03** | Catálogo Actuados Contextualizado | `GET /api/catalogo/actuados?expediente_id={e}` | Query: `expediente_id` | `200 OK`: `[{id, codigo, nombre, requiere_adjunto, estado_destino}]` | Bruno (B1.1) | Brayan (B4.5) |
| **CTR-04** | Remisión a Unidad Externa | `POST /api/expedientes/{e}/remitir-unidad` | `{unidad_id: int, motivo: string, adjunto?: file}` | `201 Created`: `{transferencia_id, estado: REMITIDO_UNIDAD_EXTERNA}` (guarda `estado_previo_id`) | Bruno (B2.1) | Brayan (B4.9) |
| **CTR-05** | Registrar Recepción de Unidad | `POST /api/expedientes/{e}/registrar-recepcion` | `{unidad_origen_id: int, motivo: string, adjunto?: file}` | `200 OK`: `{estado: 'EN_EVALUACION'\|'EN_PLANIFICACION'}` (restaura `estado_previo_id`) | Bruno (B2.1) | Brayan (B4.9) |
| **CTR-06** | Búsqueda NUREJ Avanzada | `GET /api/expedientes/buscar?q={query}` | Query: `q` (mín. 3 caracteres) | `200 OK`: `[{id, nurej_code, via, estado, asignado_a}]` (respetando asignación activa) | Bruno (B2.6) | Brayan (B4.7) |
| **CTR-07** | Bandeja Supervisión Encargada | `GET /api/encargada/bandeja-supervision` | Query: `categoria, page` | `200 OK`: Paginator `{data: [{nurej, partes, plazo_restante, accion_pendiente}]}` | Bruno (B2.6) | Brayan (B4.3) |
| **CTR-08** | Consultas de 9 Reportes SRS | `GET /api/reportes/{codigo_reporte}` | Query: `fecha_desde, fecha_hasta, usuario_id, estado` | `200 OK`: `{meta, filtros_aplicados, data: [...]}` para R01…R09 (en `core.php`, protegido por `ReportePolicy`) | Bruno (B5.0) | Brayan (B5.1) |
| **CTR-09** | Actuado de Enmienda (RF-02) | `POST /api/expedientes/{e}/enmienda` | `{campo_modificado: string, valor_nuevo: string, motivo: string}` | `200 OK`: `{actuado_enmienda_id, expediente_actualizado}` | Bruno (B1.7) | Brayan (B4.2) |
| **CTR-10** | Incompetencia Procesal (Solicitud y VB) | `POST /api/expedientes/{e}/incompetencia/solicitar` y `POST .../visto-bueno` | Solicitud: `{unidad_destino_id, motivo}` / VB: `{aprobado: bool, observaciones?}` | `200 OK`: `{estado: REMITIDO_UNIDAD_EXTERNA\|EN_EVALUACION}` | Bruno (B2.2) | Brayan (B4.2) |

---

## 7. ESPECIFICACIÓN DE LOS 9 REPORTES SRS (RF-R01 A RF-R09)

En las tareas B5.0, B5.1, B5.2 y B8.10 se utilizarán estrictamente los 9 reportes normativos definidos en el SRS oficial:
- **RF-R01:** Carga Laboral por Usuario (casos pendientes segmentados por perfil Técnico, Jurídico, Financiero).
- **RF-R02:** Solicitudes Registradas (libro matriz digital de ingresos de causas).
- **RF-R03:** Recepción Inter-Unidad (auditoría y trazabilidad de trámites derivados y devueltos).
- **RF-R04:** Estado de Evaluación (balance de casos admitidos, observados y rechazados).
- **RF-R05:** Notificaciones Pendientes (seguimiento de expedientes en plazo de subsanación).
- **RF-R06:** Resoluciones Finales (salidas institucionales: demanda disciplinaria, sumariante o archivo).
- **RF-R07:** Estadístico por Vía (indicadores de rendimiento y distribución de denuncias).
- **RF-R08:** Carátula Oficial (generación automática de la foja 0 en formato PDF).
- **RF-R09:** Trazabilidad de Derivaciones Padre-Hijo (vínculo procesal entre expediente raíz y derivados).

---

## 8. PUNTOS DE SINCRONIZACIÓN Y GATES POR SPRINT

Para garantizar estabilidad continua, el avance se rige por 5 puertas de enlace (Gates) obligatorias:

```
[SPRINT 1] ──► GATE 1: Fin de Fase Cero y Eliminación de Bypass ADMIN
               - B2.0 (corrección bypass ADMIN AUD-0001 en ExpedientePolicy) mergeado de inicio -> suite de seguridad en verde.
               - B0.1 ($fillable modelos, Notifiable, seeders y loader modular routes/api/) mergeado.
               - B0.2 (FormRequests existentes y CTR-03 expediente_id) mergeado antes de B1.1.
               - B0.3 (roles como constantes en app/) mergeado tras B0.2.
               - B0.8 (Anexo técnico SRS MySQL y diseño corto de unidades en DISENO_UNIDADES_INTER.md) listo.
               - B3.3 (configuración America/La_Paz y verificación de fechas) y B1.8 (inmutabilidad de normativa RN-06) mergeados.
               - B0.4 (SecurityHeaders), B0.5 (singleton SemaforoPlazoService), B0.6 (índices), B0.7 (UX menores) y B0.10 (layout partials con AUD-0044/0057) de Brayan mergeados.
               - Suite completa en VERDE (310 tests pasando, 0 fallos).
               
[SPRINT 2] ──► GATE 2: Máquina de Estados, Grafo Procesal, Subsanación y Plazos
               - B1.1 a B1.5 (catálogo completo, grafo procesal, cierres de plazos, subsanación con try/catch en B1.4, NUREJ hijo) mergeados.
               - B3.1 (feriados Bolivia), B3.4 (notificaciones api/), B6.1 (scheduler concurrencia) y B4.8 (panel notificaciones) mergeados.
               - Contratos CTR-01, CTR-02 y CTR-03 100% operativos.
               
[SPRINT 3] ──► GATE 3: Unidades, Incompetencia, Autorización por Asignación y Operaciones UI
               - B1.6 (inactivación segura de huérfanos sin tocar Reparto Institucional) mergeado.
               - B1.7 (actuado de enmienda RF-02 y CTR-09) mergeado.
               - B2.1 (catálogo unidades, transferencias_unidad con estado_previo_id y estado REMITIDO) mergeado.
               - B2.2 (incompetencia procesal: solicitud y VB Encargada CTR-10 con congelamiento de plazos) mergeado.
               - B3.2 (naturaleza administrativa backend) mergeado -> B3.5 (selector en apertura) mergeado.
               - B2.3 (Policies de asignación activa y protección de causas en REMITIDO) mergeado.
               - B2.6 (búsqueda CTR-06 y supervisión CTR-07) mergeado -> B4.3 y B4.7 operativos.
               - B4.1 y B4.2 (UI de las operaciones operativas, incluyendo VB de incompetencia de la Encargada) integrados.
               - B4.9 (modal de remisión y registro de recepción para Encargada) integrado.
               
[SPRINT 4] ──► GATE 4: Reportes SRS, Seguridad y Auditoría Append-Only
               - B5.0 (consultas SQL base R01 a R09 en core.php CTR-08 y ReportePolicy con ADMIN 403) mergeado.
               - B5.1 (pantallas 9 reportes) y B5.2 (Excel y PDF) integrados.
               - B2.4 (auditoría append-only con Gate::after y listener RequestHandled en AuditoriaServiceProvider con conexión separada) mergeado.
               - B2.5 (guard inmutabilidad en Actuado sin updates post-insert; No tocar bootstrap/app.php) mergeado.
               - Dashboard financiero redefinido (B4.6) y carátula PDF (B5.3) operativos.
               
[SPRINT 5] ──► GATE 5: Conciliación, Cobertura, Hardening y Entrega Final
               - B8.1 a B8.8 (suite de dominio, plazos y cobertura > 40%) en verde.
               - B7.1 (dashboard admin optimizado), B7.2 (CatalogoCacheService dividido: invalidación en Brayan, consumo en Bruno), B7.3 (paginación server-side).
               - B8.9 a B8.11 (verificación de búsqueda respetando asignación activa sin unidad de usuario) en verde.
               - B8.10 (conciliación Dashboard = Excel = PDF = SQL al 100%) validada.
               - Documentación técnica, checklist de despliegue y protocolo UAT entregados.
```

---

## 9. SECCIÓN: PENDIENTE DE DECISIÓN

### Tarea P-01: Doble Confirmación Financiera (AUD-0011)
- **Estado:** PENDIENTE DE VALIDACIÓN NORMATIVA.
- **Motivo de Pausa:** Se requiere comprobar en el texto del Reglamento AC055 si la aprobación de descargos financieros exige formalmente un flujo mancomunado (dos funcionarios distintos en sistema) o si es suficiente una reautenticación por contraseña ("sudo mode" con token temporal de 5 minutos).
- **Asignación:** **Sin asignar**. Ningún desarrollador implementará código para esta función hasta que la Jefatura/Usuario emita la definición final.

---

## 10. VERIFICACIÓN REAL DE PROPIEDAD Y SOLAPAMIENTO DE ARCHIVOS

### 10.1 Asignación Real por Componente

| Componente / Archivo | Dueño | Mecanismo de Control |
|---|---|---|
| `app/Models/Usuario.php` | **Bruno** | Bruno añade trait `Notifiable` en B0.1; Brayan consume la relación. |
| `app/Models/Unidad.php` | **Bruno** | Modelo de catálogo para remisiones y reparto institucional. |
| `app/Models/TransferenciaUnidad.php` | **Bruno** | Registro histórico de remisiones y recepciones físicas con `estado_previo_id`. |
| `app/Models/*.php` (resto de modelos) | **Bruno** | Propiedad exclusiva de Bruno. |
| `app/Policies/ExpedientePolicy.php` | **Bruno** | Bruno repara bypass ADMIN en B2.0 y centraliza reglas por asignación activa y protección en REMITIDO en B2.3. |
| `app/Policies/ReportePolicy.php` | **Bruno** | Bruno crea policy para reportes R01…R09, dashboards y carátula (ADMIN 403) en B5.0. |
| `app/Policies/*.php` (demás policies) | **Bruno** | Propiedad exclusiva de Bruno. |
| `app/Services/ActuadoService.php` | **Bruno** | Servicio neurálgico de dominio. Brayan no lo edita. |
| `app/Services/ArchivoPorAbandonoService.php` | **Bruno** | Modificado en B1.4 por Bruno para condicionar estado a `EN_SUBSANACION` y try/catch individual por expediente. |
| `app/Services/PlazoCalculatorService.php` | **Bruno** | Motor matemático de plazos hábiles. Propiedad de Bruno. |
| `app/Services/SemaforoPlazoService.php` | **Bruno** | Servicio de semáforo de plazos. Brayan solo lo registra como singleton en B0.5. |
| `app/Services/ReportesQueryService.php` | **Bruno** | Lógica SQL agregada para los 9 reportes normativos en B5.0. |
| `app/Services/` (Transferencias, Sorteo, Enmienda) | **Bruno** | Lógica transaccional de dominio. |
| `app/Services/CatalogoCacheService.php` | **Brayan** | Creado por Brayan en B7.2; Bruno lo consume en sus controladores y servicios. |
| `app/Services/` (Feriados, Export) | **Brayan** | Servicios auxiliares e infraestructura. |
| `app/Http/Controllers/` (Core y Dominio: `ExpedienteController`, `ActuadoController`, `PlanificacionController`, `AmpliacionController`, `CierreExpedienteController`, `DescargoFinancieroController`, `ImpugnacionController`, `EvaluacionAdmisibilidadController`, `TransferenciaUnidadController`, `ReglamentoController`, `CatalogoEstadoController`, `CatalogoActuadoController`, `AdjuntoController`) | **Bruno** | Controladores neurálgicos del flujo procesal y de lectura de catálogos base. |
| `app/Http/Controllers/Administrador/` (`AdminDashboardController`, `AdminUsuariosController`, `AdminFeriadosController`, `AdminMonitoreoController`) | **Brayan** | Controladores del módulo de administración del sistema. |
| `app/Http/Controllers/ReporteController.php` | **Brayan** | Controlador de exportaciones en Excel y PDF y generación de carátula. |
| `app/Http/Controllers/UsuarioController.php` | **Bruno / Brayan** | Bruno maneja endpoints operativos; Brayan sustituye validación inline en B0.2. |
| `app/Http/Requests/DerivarNurejHijoRequest.php` | **Bruno** | Asignado a Bruno (control de especialidad en B1.5). |
| `app/Http/Requests/StoreExpedienteRequest.php` | **Bruno** | Asignado a Bruno (campo `naturaleza` en B3.2). |
| `app/Http/Requests/*.php` (demás requests preexistentes) | **Brayan** | FormRequests asignados a Brayan en B0.2 (incluyendo `IndexCatalogoActuadosRequest`). |
| `app/Http/Middleware/SecurityHeaders.php` | **Brayan** | Propiedad exclusiva de Brayan. |
| `app/Providers/AppServiceProvider.php` | **Brayan** | Registra singletons (`SemaforoPlazoService`) y rate limits. |
| `app/Providers/AuditoriaServiceProvider.php` | **Bruno** | Provider desacoplado para listeners de auditoría (`Gate::after` y `RequestHandled`). |
| `bootstrap/app.php` | **Brayan** | Brayan registra middleware. Bruno no lo toca (`ActuadoInmutableException` tiene su propio `render()`). |
| `composer.json` y `composer.lock` | **Brayan** | Brayan gestiona dependencias externas en B5.2. |
| `.env.example` | **Brayan** | Brayan documenta variables; Bruno le suministra las suyas. |
| `database/seeders/DatabaseSeeder.php` | **Secuenciado** | Bruno estructura el llamado inicial en B0.1; Brayan agrega sus seeders al final. |
| `routes/api.php` | **Loader** | Creado por Bruno en B0.1; incluye `routes/api/*.php`. |
| `routes/api/core.php` y `operativo.php` | **Bruno** | Rutas de dominio, contratos backend y consulta JSON de reportes CTR-08. |
| `routes/api/admin.php`, `reportes.php`, `notificaciones.php` | **Brayan** | Rutas de administración, exportaciones de reportes y alertas. |
| `resources/views/**` (todas las vistas) | **Brayan** | Propiedad exclusiva de Brayan (incluyendo selector en apertura y layout particionado). |
| `database/migrations/` | **Prefijos** | Bruno: prefijo `10XXXX`; Brayan: prefijo `20XXXX` (sin fechas fijas en nombres). |
| `tests/Feature/` (Dominio) | **Bruno** | Tests B8.1 a B8.8 propios. |
| `tests/Feature/` (UI, UX, Reportes) | **Brayan** | Tests B8.9 a B8.11 propios. |

### 10.2 Veredicto de Concurrencia
- **Archivos con edición concurrente no controlada:** **0**.
- **Puntos de contacto controlados:** 2 archivos estructurados secuencialmente (`routes/api.php` como loader y `DatabaseSeeder.php` como orquestador de seeders independientes) y la edición puntual de controladores en B0.2 estrictamente para inyectar FormRequests antes de que inicie el Sprint 2.
- **Riesgo de colisión en Git:** **Bajo** (mitigado y controlado bajo el cumplimiento de este manual, con puntos de sincronización y gates explícitos).
