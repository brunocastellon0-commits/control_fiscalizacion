# PLAN DE TAREAS — DESARROLLADOR: BRAYAN

> **Lectura Obligatoria Previa:** Antes de ejecutar cualquier tarea de este plan, es indispensable leer exhaustivamente [**`CONTEXTO_BRAYAN.md`**](CONTEXTO_BRAYAN.md) y [**`COORDINACION.md`**](COORDINACION.md).

**Rol:** Interfaz, Reportes y Entrega (Validaciones, UI/UX, Notificaciones, Jobs, Reportes, Exportación y Despliegue)  
**Total Horas Estimadas:** 122 horas  
**Estructura de Ramas:** `brayan/<id_tarea>-<slug_descriptivo>`  

---

## RESUMEN DE SPRINTS Y DISTRIBUCIÓN DE HORAS

| Sprint / Periodo | Enfoque Principal | Tareas Asignadas | Horas Estimadas |
|---|---|---|---|
| **Sprint 1** | Soporte Inicial, Headers, Layout Modular y UX Menores | B0.2, B0.4, B0.5, B0.6, B0.7, B0.10 | 21 h |
| **Sprint 2** | Feriados Bolivia, Notificaciones y CRUD de Requisitos | B3.1, B3.4, B1.9, B6.1 | 19 h |
| **Sprint 3** | Vistas Operativas, Enmienda, Incompetencia y Bandejas | B4.1, B4.2, B4.3, B4.4, B4.5, B4.7 | 26 h |
| **Sprint 4** | Apertura con Naturaleza, Remisión Inter-Unidad, Reportes SRS y Exportación | B3.5, B4.9, B4.6, B5.1, B5.2, B5.3 | 28 h |
| **Sprint 5** | Optimización, Caché Dividida, Documentación, Tests Cruzados y Cierre | B6.2, B7.1, B7.2, B7.3, B8.9, B8.10, B8.11, B9.1-5, B9.6, B9.7 | 28 h |

---

## DETALLE DE TAREAS POR SPRINT

### SPRINT 1: SOPORTE INICIAL, HEADERS, LAYOUT MODULAR Y UX MENORES

#### Tarea B0.2: FormRequests existentes, validación backend y regla expediente_id
- **ID:** B0.2
- **Título:** Fortalecimiento de FormRequests existentes y desacoplamiento de validación inline
- **Referencia Auditoría:** AUD-0003 (P1) / RNF-04 / laravel-skills.md §3 / Contrato CTR-03
- **Descripción:** Auditar y actualizar los FormRequests preexistentes en `app/Http/Requests/`. **Permiso especial de controladores:** Brayan puede editar controladores existentes **exclusivamente** para sustituir `$request->validate()` inline por la inyección del FormRequest tipado. Incorporar explícitamente en `app/Http/Requests/IndexCatalogoActuadosRequest.php` la regla de validación `'expediente_id' => ['nullable', 'integer', 'exists:expedientes,id']` para habilitar el contrato CTR-03.
- **Documentos a leer:**
  - `docs/auditoria/MAPA_FUNCIONAL.md` (§2 "Controladores y Requests").
  - [COORDINACION.md](COORDINACION.md) (§3 "Paso 0.2").
- **Archivos que toca:**
  - `app/Http/Requests/*.php` (todos los requests existentes, incluyendo `IndexCatalogoActuadosRequest`)
  - Controladores en `app/Http/Controllers/*.php` (únicamente líneas de validación inline)
  - `tests/Feature/FormRequestsCoverageTest.php` (creación)
- **No tocar:**
  - `app/Http/Requests/DerivarNurejHijoRequest.php` (propiedad de Bruno en B1.5).
  - `app/Http/Requests/StoreExpedienteRequest.php` (propiedad de Bruno en B3.2).
  - Lógica de negocio en servicios (`app/Services/**`).
- **Dependencias:**
  - De sí mismo: Ninguna.
  - De Bruno: Esta rama se mergea antes de que Bruno inicie B0.3 y B1.1.
- **Horas Estimadas:** 5 h
- **Criterios de Aceptación:**
  - Cero llamadas a `$request->validate()` en controladores existentes.
  - `IndexCatalogoActuadosRequest` valida el parámetro opcional `expediente_id`.
  - Cobertura completa en `FormRequestsCoverageTest.php`.
- **Rama Sugerida:** `brayan/B0.2-form-requests-validacion`

---

#### Tarea B0.4: Hardening de headers HTTP (Middleware `SecurityHeaders` y `bootstrap/app.php`)
- **ID:** B0.4
- **Título:** Middleware de cabeceras de seguridad web y protección contra clickjacking
- **Referencia Auditoría:** AUD-0030, AUD-0031, AUD-0032 (P2) / RNF-01
- **Descripción:** Crear el middleware `App\Http\Middleware\SecurityHeaders` que inyecte en todas las respuestas HTTP las cabeceras requeridas: `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `X-XSS-Protection: 1; mode=block`, `Referrer-Policy: strict-origin-when-cross-origin`, `Strict-Transport-Security: max-age=31536000; includeSubDomains` y una directiva base de `Content-Security-Policy`. Registrarlo globalmente en `bootstrap/app.php`.
- **Documentos a leer:**
  - `docs/auditoria/MATRIZ_HARDENING.md` (§3 "Cabeceras HTTP").
  - [COORDINACION.md](COORDINACION.md) (§2.3).
- **Archivos que toca:**
  - `app/Http/Middleware/SecurityHeaders.php` (creación)
  - `bootstrap/app.php`
  - `tests/Feature/SecurityHeadersTest.php` (creación)
- **No tocar:**
  - `routes/api/**`.
- **Dependencias:**
  - De sí mismo: Ninguna.
  - De Bruno: Ninguna.
- **Horas Estimadas:** 2 h
- **Criterios de Aceptación:**
  - Cualquier petición HTTP a la aplicación devuelve las cabeceras de seguridad.
  - Test en verde verificando cabeceras presentes.
- **Rama Sugerida:** `brayan/B0.4-headers`

---

#### Tarea B0.5: Registro singleton de `SemaforoPlazoService` en `AppServiceProvider`
- **ID:** B0.5
- **Título:** Optimización de cálculo de plazos mediante inyección singleton
- **Referencia Auditoría:** AUD-0062 (P2) / RNF-03
- **Descripción:** Registrar `SemaforoPlazoService` como servicio singleton en `AppServiceProvider@register`. Esto elimina la instanciación redundante en bucles de bandejas, reduciendo en un 60% las consultas SQL repetitivas.
  - **Propiedad del archivo:** La clase `app/Services/SemaforoPlazoService.php` pertenece a Bruno. Brayan únicamente edita `AppServiceProvider.php` para su enlace como singleton.
- **Documentos a leer:**
  - `docs/auditoria/MATRIZ_RENDIMIENTO.md` (§7 "Medición dinámica" y §12 "AUD-0062").
  - [COORDINACION.md](COORDINACION.md) (§2.2).
- **Archivos que toca:**
  - `app/Providers/AppServiceProvider.php`
  - `tests/Feature/SemaforoSingletonQueryTest.php` (creación)
- **No tocar:**
  - `app/Services/PlazoCalculatorService.php` (propiedad de Bruno).
  - `app/Services/SemaforoPlazoService.php` (propiedad de Bruno).
  - `app/Providers/AuditoriaServiceProvider.php` (propiedad de Bruno).
- **Dependencias:**
  - De sí mismo: Ninguna.
  - De Bruno: Ninguna.
- **Horas Estimadas:** 2 h
- **Criterios de Aceptación:**
  - `app(SemaforoPlazoService::class) === app(SemaforoPlazoService::class)` es verdadero.
  - La carga de la bandeja de operador reduce significativamente su conteo de consultas.
- **Rama Sugerida:** `brayan/B0.5-singleton-plazos`

---

#### Tarea B0.6: Migraciones de índices para optimización de consultas SQL
- **ID:** B0.6
- **Título:** Creación de índices compuestos para acelerar bandejas y búsquedas
- **Referencia Auditoría:** AUD-0060, AUD-0061 (P2) / RNF-03
- **Descripción:** Crear migraciones aditivas de MySQL agregando índices compuestos críticos:
  1. `idx_expedientes_estado_ingreso` sobre `expedientes(estado_actual_id, fecha_ingreso)`.
  2. `idx_asignaciones_activa_usuario` sobre `asignaciones(usuario_id, activa, fecha_asignacion)`.
  3. `idx_actuados_expediente_fecha` sobre `actuados(expediente_id, fecha_hora)`.
  4. `idx_plazos_expediente_estado` sobre `plazos(expediente_id, estado, fecha_limite)`.
- **Documentos a leer:**
  - `docs/auditoria/MATRIZ_RENDIMIENTO.md` (§6 "EXPLAIN benchmarks").
- **Archivos que toca:**
  - `database/migrations/YYYY_MM_DD_20XXXX_add_performance_indexes.php` (creación con prefijo nocturno)
  - `tests/Feature/IndicesPerformanceTest.php` (creación)
- **No tocar:**
  - Migraciones preexistentes en `database/migrations/`.
- **Dependencias:**
  - De sí mismo: Ninguna.
  - De Bruno: Ninguna.
- **Horas Estimadas:** 3 h
- **Criterios de Aceptación:**
  - Las consultas `EXPLAIN` pasan de `type=ALL` a `type=ref/range`.
  - Migración reversible sin errores en `down()`.
- **Rama Sugerida:** `brayan/B0.6-indices-rendimiento`

---

#### Tarea B0.7: Saneamiento de UX menor en vistas de autenticación y bandejas
- **ID:** B0.7
- **Título:** Corrección integral de inconsistencias visuales en vistas específicas
- **Referencia Auditoría:** AUD-0045 a AUD-0056, AUD-0058, AUD-0059 (P3)
- **Descripción:** Resolver los hallazgos menores de interfaz en vistas de trabajo y limpiar cadenas de roles en Blade:
  - AUD-0045/46: Renderizar mensajes de error e indicador de carga en monitoreo y remover texto inerte de actualización automática.
  - AUD-0048/49: Quitar enlace inerte "¿Olvidaste tu contraseña?" en login y formatear amigablemente errores 429 de rate limiting.
  - AUD-0050: Corregir asignación de `meta.total` en bandeja de operador (`this.meta = j.meta`).
  - AUD-0051/53: Saneamiento de variables inertes en apertura y dashboards.
  - AUD-0054/55: Manejo visual de errores en sorteo individual y control de paginación vacía.
  - AUD-0056: Ocultar botón "Inactivar" sobre la propia cuenta en módulo de usuarios.
  - AUD-0058: Eliminación de `console.log` residuales en vistas.
  - AUD-0059: Eliminar `welcome.blade.php` huérfana.
  - **Limpieza de Roles en Vistas:** Reemplazar cadenas dispersas en `resources/views/` por comparaciones consistentes con constantes o atributos tipados devueltos por `/api/me`.
- **Documentos a leer:**
  - `docs/auditoria/MATRIZ_UX_FRONTEND.md` (§5, §12 y §13).
  - [COORDINACION.md](COORDINACION.md) (§3 "Paso 0.3").
- **Archivos que toca:**
  - `resources/views/auth/login.blade.php`
  - `resources/views/expedientes/bandeja-operador.blade.php`
  - `resources/views/expedientes/bandeja-sorteo.blade.php`
  - `resources/views/expedientes/apertura.blade.php`
  - `resources/views/administrador/monitoreo.blade.php`
  - `resources/views/administrador/usuarios.blade.php`
  - `resources/views/administrador/dashboard.blade.php`
  - `resources/views/encargada/dashboard.blade.php`
  - `resources/views/welcome.blade.php` (eliminación)
  - `tests/Feature/UxFeedbackCorrectionTest.php` (creación)
- **No tocar:**
  - `resources/views/layouts/app.blade.php` (gestionado modularmente en B0.10).
  - Archivos en `app/**` (propiedad de Bruno).
- **Dependencias:**
  - De sí mismo: Ninguna.
  - De Bruno: Ninguna.
- **Horas Estimadas:** 4 h
- **Criterios de Aceptación:**
  - Navegación fluida sin botones inertes ni contadores en cero falsos.
  - Consola del navegador libre de errores de JavaScript y de logs de depuración.
- **Rama Sugerida:** `brayan/B0.7-ux-menores`

---

#### Tarea B0.10: Partición de `layouts/app.blade.php` en parciales modulares y resolución de layout
- **ID:** B0.10
- **Título:** Desacoplamiento de layout en parciales y resolución de AUD-0044 y AUD-0057
- **Referencia Auditoría:** AUD-0044, AUD-0057 (P2/P3) / Arquitectura Frontend
- **Descripción:** Refactorizar `resources/views/layouts/app.blade.php` dividiendo su estructura en parciales dedicados dentro de `resources/views/layouts/`:
  1. `sidebar.blade.php`: Menú lateral, perfiles de rol y enlaces institucionales. **Resuelve AUD-0044:** Ocultar enlace "Bandeja de entrada" para roles `ENCARGADA` y `ADMIN` (evitando 403).
  2. `notificaciones.blade.php`: Campana reactiva y contenedor de avisos de plazos.
  3. `buscador.blade.php`: Barra superior de búsqueda reactiva por NUREJ.
  4. **Resuelve AUD-0057:** Manejo de errores silenciosos en `cargarUsuario()` y `cerrarSesion()` en layout, mostrando feedback visual en caso de desconexión.
  `layouts/app.blade.php` se convierte en un cascarón limpio que incluye estos componentes mediante directivas `@include`.
- **Documentos a leer:**
  - [COORDINACION.md](COORDINACION.md) (§2.9).
  - `docs/auditoria/BACKLOG_AUDITORIA.md` (AUD-0044 y AUD-0057).
- **Archivos que toca:**
  - `resources/views/layouts/app.blade.php`
  - `resources/views/layouts/sidebar.blade.php` (creación)
  - `resources/views/layouts/notificaciones.blade.php` (creación)
  - `resources/views/layouts/buscador.blade.php` (creación)
  - `tests/Feature/LayoutPartialsTest.php` (creación)
- **No tocar:**
  - `app/Providers/AppServiceProvider.php`.
- **Dependencias:**
  - De sí mismo: B0.7 mergeado.
  - De Bruno: Ninguna.
- **Horas Estimadas:** 5 h
- **Criterios de Aceptación:**
  - La Encargada y el Administrador no ven enlaces a rutas operativas que arrojen 403.
  - Fallos de sesión se notifican claramente al usuario sin errores silenciosos.
- **Rama Sugerida:** `brayan/B0.10-layout-partials`

---

### SPRINT 2: FERIADOS BOLIVIA, NOTIFICACIONES Y CRUD DE REQUISITOS

#### Tarea B3.1: Feriados oficiales de Bolivia y Cochabamba + parametrización de plazos
- **ID:** B3.1
- **Título:** Carga de calendario laboral boliviano y parametrización de evaluación
- **Referencia Auditoría:** AUD-0013, AUD-0014 (P0/P1), AUD-0019 (P2) / D-2
- **Descripción:** Actualizar `FeriadoSeeder.php` con los feriados nacionales oficiales de Bolivia y el feriado departamental de Cochabamba (14 de Septiembre, efeméride departamental). Actualizar `ParametroPlazoSeeder.php` para soportar el rango de evaluación de 3 a 5 días según complejidad.
- **Documentos a leer:**
  - `docs/auditoria/MATRIZ_PLAZOS.md` (§1 "Motor de cálculo" y §8 "O-1 feriados").
  - [COORDINACION.md](COORDINACION.md) (§5.2).
- **Archivos que toca:**
  - `database/seeders/FeriadoSeeder.php`
  - `database/seeders/ParametroPlazoSeeder.php`
  - `app/Http/Controllers/Administrador/AdminFeriadosController.php`
  - `resources/views/administrador/feriados.blade.php`
  - `tests/Feature/FeriadosBoliviaTest.php` (creación)
- **No tocar:**
  - `app/Services/PlazoCalculatorService.php` (propiedad de Bruno).
- **Dependencias:**
  - De sí mismo: B0.2 mergeado.
  - De Bruno: Ninguna.
- **Horas Estimadas:** 5 h
- **Criterios de Aceptación:**
  - Feriados nacionales y de Cochabamba cargados en base de datos.
  - Test en verde confirmando que los feriados son excluidos del cómputo.
- **Rama Sugerida:** `brayan/B3.1-feriados-bolivia`

---

#### Tarea B3.4: Sistema de alertas y notificaciones in-app de plazos
- **ID:** B3.4
- **Título:** Notificaciones visuales de plazos en riesgo en la interfaz de usuario
- **Referencia Auditoría:** AUD-0015 (P1) / D-4 / criterio derivado de la auditoría
- **Descripción:** Diseñar y construir el sistema de alertas de plazos por vencer (≤ 2 días hábiles) en la interfaz:
  1. Componente en `resources/views/layouts/notificaciones.blade.php` con contador reactivo de expedientes en riesgo.
  2. Endpoints en `routes/api/notificaciones.php` (`GET /api/notificaciones/resumen`, `POST /api/notificaciones/marcar-leida`).
  3. No requiere envío de correo SMTP; se apoya en el modelo `Usuario` y su trait `Notifiable`.
- **Documentos a leer:**
  - `docs/auditoria/MATRIZ_PLAZOS.md` (§4 "Alertas").
  - [COORDINACION.md](COORDINACION.md) (§2.1 `notificaciones.php`).
- **Archivos que toca:**
  - `routes/api/notificaciones.php` (creación)
  - `app/Http/Controllers/NotificacionController.php` (creación)
  - `resources/views/layouts/notificaciones.blade.php`
  - `tests/Feature/NotificacionesPlazosTest.php` (creación)
- **No tocar:**
  - `app/Models/Usuario.php` (Bruno añade `Notifiable` en B0.1).
- **Dependencias:**
  - De sí mismo: B0.10 mergeado.
  - De Bruno: B0.1 mergeado.
- **Horas Estimadas:** 5 h
- **Criterios de Aceptación:**
  - La campana superior despliega notificaciones de causas en riesgo de vencimiento.
- **Rama Sugerida:** `brayan/B3.4-notificaciones-in-app`

---

#### Tarea B1.9: CRUD administrativo de requisitos de admisibilidad
- **ID:** B1.9
- **Título:** Gestión dinámica de catálogo de requisitos por reglamento
- **Referencia Auditoría:** AUD-0002 (P1) / RF-04
- **Descripción:** Implementar la interfaz y controlador administrativo para el mantenimiento del catálogo de requisitos de admisibilidad (`CatalogoRequisito`): crear, editar texto, asociar a reglamento y marcar como crítico/no crítico (`es_critico`).
- **Documentos a leer:**
  - `docs/auditoria/MATRIZ_ACTUADOS.md` (§2).
- **Archivos que toca:**
  - `app/Http/Controllers/Administrador/AdminRequisitosController.php` (creación)
  - `resources/views/administrador/requisitos.blade.php` (creación)
  - `routes/api/admin.php`
  - `tests/Feature/AdminRequisitosCrudTest.php` (creación)
- **No tocar:**
  - `app/Models/CatalogoRequisito.php` (propiedad de Bruno).
- **Dependencias:**
  - De sí mismo: B0.2 mergeado.
  - De Bruno: Ninguna.
- **Horas Estimadas:** 5 h
- **Criterios de Aceptación:**
  - El Administrador puede gestionar requisitos dinámicamente sin tocar código.
- **Rama Sugerida:** `brayan/B1.9-crud-requisitos`

---

#### Tarea B6.1: Scheduler de tareas programadas y prevención de solapamiento
- **ID:** B6.1
- **Título:** Configuración de Cron institucional en Laravel y locking de jobs
- **Referencia Auditoría:** AUD-0039, AUD-0040 (P1) / MATRIZ_JOBS_CRON §2
- **Descripción:** Configurar `routes/console.php` para calendarizar las tareas programadas institucionales:
  1. `expedientes:verificar-vencimientos` ejecutado a las 00:05 de cada día hábil con `withoutOverlapping()`.
  2. Ajustar la periodicidad y zona horaria (`America/La_Paz`).
- **Documentos a leer:**
  - `docs/auditoria/MATRIZ_JOBS_CRON.md` (§2 "Frecuencias").
- **Archivos que toca:**
  - `routes/console.php`
  - `tests/Feature/SchedulerPlazosConcurrenciaTest.php` (creación)
- **No tocar:**
  - `app/Console/Commands/VerificarVencimientoPlazosCommand.php` (se toca en B6.2).
- **Dependencias:**
  - De sí mismo: Ninguna.
  - De Bruno: B3.3 (Timezone) mergeado.
- **Horas Estimadas:** 4 h
- **Criterios de Aceptación:**
  - Las tareas programadas corren con `withoutOverlapping()` y en hora boliviana.
- **Rama Sugerida:** `brayan/B6.1-scheduler-cron`

---

### SPRINT 3: VISTAS OPERATIVAS, ENMIENDA, INCOMPETENCIA Y BANDEJAS

#### Tarea B4.1: Interfaz interactiva de evaluación de admisibilidad
- **ID:** B4.1
- **Título:** Checklist reactivo de requisitos de admisibilidad (RF-04 / CTR-01 y CTR-02)
- **Referencia Auditoría:** AUD-0002, AUD-0030 (P1) / RF-04 / Contratos CTR-01 y CTR-02
- **Descripción:** Construir el componente `resources/views/expedientes/detalle/evaluacion.blade.php` integrado en la vista de detalle. Consume `GET /api/expedientes/{id}/requisitos` (CTR-01) desplegando checkboxes dinámicos según el reglamento de la causa. Al guardar (`POST /api/expedientes/{id}/evaluacion`, CTR-02), calcula automáticamente si corresponde Admisión, Observación o Rechazo.
- **Documentos a leer:**
  - `docs/auditoria/FLUJO_TECNICO.md` (§2 "Admisibilidad").
  - [COORDINACION.md](COORDINACION.md) (§6 Contratos CTR-01 y CTR-02).
- **Archivos que toca:**
  - `resources/views/expedientes/detalle/evaluacion.blade.php` (creación)
  - `resources/views/expedientes/detalle.blade.php`
  - `tests/Feature/UiEvaluacionAdmisibilidadTest.php` (creación)
- **No tocar:**
  - `app/Http/Controllers/EvaluacionAdmisibilidadController.php` (backend de Bruno).
- **Dependencias:**
  - De sí mismo: B0.10 mergeado.
  - De Bruno: Bruno proporciona contratos CTR-01 y CTR-02 en B1.1.
- **Horas Estimadas:** 6 h
- **Criterios de Aceptación:**
  - El checklist opera dinámicamente y refleja los requisitos del reglamento del expediente.
- **Rama Sugerida:** `brayan/B4.1-ui-evaluacion-admisibilidad`

---

#### Tarea B4.2: Interfaz unificada de operaciones procesales, enmienda e incompetencia
- **ID:** B4.2
- **Título:** Botonera contextual de operaciones, modal de enmienda y visto bueno de incompetencia
- **Referencia Auditoría:** AUD-0033 (P1) / RF-02 / Contratos CTR-09 y CTR-10
- **Descripción:** Implementar `resources/views/expedientes/detalle/acciones-operativas.blade.php` y `modal-enmienda.blade.php`.
  - Agrupa las operaciones procesales contextualizadas por rol y estado:
    1. Registro y Apertura.
    2. Envío a Sorteo y Sorteo individual/lote.
    3. Evaluación de Admisibilidad (admisión/observación/rechazo).
    4. Subsanación y aceptación de subsanación.
    5. Impugnación del rechazo (remisión y resolución de Encargada).
    6. Formulación de Planificación y Cronograma.
    7. Visto Bueno de Planificación por Encargada.
    8. Devolución de Planificación con observaciones.
    9. Solicitud de Ampliación de plazo en ejecución.
    10. Aprobación de Ampliación por Encargada.
    11. Derivación de NUREJ Hijo con selector de especialidad (`POST .../nurej-hijo`).
    12. Emisión de Informe Final y Visto Bueno de Cierre.
    13. Solicitud de Derivación por Incompetencia inter-unidad (`POST .../incompetencia/solicitar`, CTR-10).
    14. **Visto Bueno de Incompetencia de la Encargada** (`POST .../incompetencia/visto-bueno`, CTR-10).
  - **Modal de Enmienda (RF-02):** Formulario para registrar corrección de datos caratulados consumiendo `POST /api/expedientes/{e}/enmienda` (CTR-09), visualizando dato original y nuevo.
- **Documentos a leer:**
  - `docs/auditoria/MATRIZ_UX_FRONTEND.md` (§7 y §8).
  - [COORDINACION.md](COORDINACION.md) (§6 Contratos CTR-09 y CTR-10).
- **Archivos que toca:**
  - `resources/views/expedientes/detalle.blade.php`
  - `resources/views/expedientes/detalle/acciones-operativas.blade.php` (creación)
  - `resources/views/expedientes/detalle/modal-enmienda.blade.php` (creación)
  - `tests/Feature/UiAccionesOperativasTest.php` (creación)
- **No tocar:**
  - `app/Services/ActuadoService.php` (propiedad de Bruno).
  - Controladores en `app/Http/Controllers/*.php` del backend de Bruno.
- **Dependencias:**
  - De sí mismo: B0.10 mergeado.
  - De Bruno: B1.2, B1.5 (NUREJ Hijo), B1.7 (Enmienda CTR-09) y B2.2 (Incompetencia CTR-10) mergeados.
- **Horas Estimadas:** 7 h
- **Criterios de Aceptación:**
  - Cada operación procesale tiene modal o botón contextual con validación de adjunto.
  - La Encargada dispone del botón para aprobar/rechazar incompetencia (CTR-10).
  - La enmienda registra cambios sin alterar destructivamente el timeline.
- **Rama Sugerida:** `brayan/B4.2-ui-operaciones-detalle`

---

#### Tarea B4.3: Bandeja de supervisión y gestión de la Encargada
- **ID:** B4.3
- **Título:** Bandeja operativa de control y pendientes para rol Encargada
- **Referencia Auditoría:** AUD-0006 (P0), AUD-0028 (P1) / Contrato CTR-07
- **Descripción:** Construir la vista `resources/views/encargada/bandeja-supervision.blade.php` y su controlador web en `app/Http/Controllers/Encargada/EncargadaBandejaController.php`. Consume el endpoint CTR-07 agrupando causas pendientes de acción directa de la Encargada (Visto Bueno de planificaciones, ampliaciones por resolver, impugnaciones recibidas, informes finales para visto bueno y causas por derivar), mostrando semáforo de urgencia, días restantes y enlace directo a la acción.
- **Documentos a leer:**
  - `docs/auditoria/FLUJO_ENCARGADA.md` (§2 "VB flow").
  - [COORDINACION.md](COORDINACION.md) (§6 Contrato CTR-07).
- **Archivos que toca:**
  - `resources/views/encargada/bandeja-supervision.blade.php` (creación)
  - `resources/views/layouts/sidebar.blade.php` (enlace en menú de Encargada)
  - `app/Http/Controllers/Encargada/EncargadaBandejaController.php` (creación)
  - `routes/web.php`
  - `tests/Feature/EncargadaBandejaSupervisionTest.php` (creación)
- **No tocar:**
  - `app/Http/Controllers/Encargada/EncargadaSupervisionController.php` (API backend de Bruno).
- **Dependencias:**
  - De sí mismo: B0.10 mergeado.
  - De Bruno: Bruno proporciona el contrato CTR-07 en el backend (Tarea B2.6 en Sprint 3).
- **Horas Estimadas:** 5 h
- **Criterios de Aceptación:**
  - La Encargada cuenta con una bandeja de trabajo donde identifica con un clic sus tareas procesales pendientes.
- **Rama Sugerida:** `brayan/B4.3-bandeja-encargada-supervision`

---

#### Tarea B4.4: Bandeja técnica con filtros avanzados por estado
- **ID:** B4.4
- **Título:** Filtros reactivos por estado procesal en bandeja de operador
- **Referencia Auditoría:** MATRIZ_BANDEJAS.md §3 / RF-03 / criterio derivado de la auditoría
- **Descripción:** Actualizar `resources/views/expedientes/bandeja-operador.blade.php` para incorporar una barra de filtros superiores por estado (`EN_EVALUACION`, `EN_PLANIFICACION`, `EN_EJECUCION`, etc.), selector de ordenamiento (más antiguo primero, más urgente por semáforo) y buscador local por código NUREJ o parte interesada.
- **Documentos a leer:**
  - `docs/auditoria/MATRIZ_BANDEJAS.md` (§3 "Bandeja matrix").
- **Archivos que toca:**
  - `resources/views/expedientes/bandeja-operador.blade.php`
  - `tests/Feature/BandejaOperadorFiltrosTest.php` (creación)
- **No tocar:**
  - `app/Services/ExpedienteService.php` (backend de Bruno).
- **Dependencias:**
  - De sí mismo: B0.7 mergeado.
  - De Bruno: Ninguna.
- **Horas Estimadas:** 3 h
- **Criterios de Aceptación:**
  - El operador puede filtrar sus causas asignadas según la etapa en la que se encuentren.
- **Rama Sugerida:** `brayan/B4.4-filtros-bandeja-operador`

---

#### Tarea B4.5: Catálogo de actuados contextualizado por expediente y estado
- **ID:** B4.5
- **Título:** Modal de emisión de actuado filtrado por reglamento y estado actual
- **Referencia Auditoría:** AUD-0034 (P2) / Contrato CTR-03
- **Descripción:** Actualizar el modal de emisión en `resources/views/expedientes/detalle/modal-actuado.blade.php` para que invoque `GET /api/catalogo/actuados?expediente_id={id}`. El selector de tipo de actuado solo ofrecerá los actos permitidos para el reglamento de la causa y cuyo `estado_origen_id` coincida con el estado actual del expediente.
- **Documentos a leer:**
  - `docs/auditoria/MATRIZ_UX_FRONTEND.md` (§9 "Verificación AUD-0034").
  - [COORDINACION.md](COORDINACION.md) (§6 Contrato CTR-03).
- **Archivos que toca:**
  - `resources/views/expedientes/detalle/modal-actuado.blade.php` (creación / desacople)
  - `tests/Feature/UiCatalogoContextualizadoTest.php` (creación)
- **No tocar:**
  - `app/Http/Controllers/CatalogoActuadoController.php` (backend de Bruno).
- **Dependencias:**
  - De sí mismo: B0.7 mergeado.
  - De Bruno: Bruno proporciona el endpoint CTR-03 filtrado en B1.1.
- **Horas Estimadas:** 3 h
- **Criterios de Aceptación:**
  - El modal no muestra actuados incompatibles con la etapa procesal actual.
- **Rama Sugerida:** `brayan/B4.5-modal-actuados-contextualizado`

---

#### Tarea B4.7: Búsqueda avanzada por NUREJ en parcial buscador
- **ID:** B4.7
- **Título:** Componente de búsqueda rápida por NUREJ en layout superior
- **Referencia Auditoría:** AUD-0004, AUD-0067 (P1) / RF-03 / Contrato CTR-06
- **Descripción:** Implementar el campo de búsqueda global en `resources/views/layouts/buscador.blade.php` con sugerencias dinámicas (`Alpine.js`). Consume el endpoint CTR-06 buscando por prefijo de NUREJ respetando rigurosamente los compartimentos de asignación del usuario.
- **Documentos a leer:**
  - `docs/auditoria/SRS_EXTRAIDO.txt` (RF-03).
  - [COORDINACION.md](COORDINACION.md) (§6 Contrato CTR-06).
- **Archivos que toca:**
  - `resources/views/layouts/buscador.blade.php`
  - `tests/Feature/UiBusquedaNurejTest.php` (creación)
- **No tocar:**
  - `app/Http/Controllers/ExpedienteBusquedaController.php` (backend de Bruno).
- **Dependencias:**
  - De sí mismo: B0.10 mergeado.
  - De Bruno: Bruno implementa el endpoint CTR-06 con restricciones de política en B2.6 (Sprint 3).
- **Horas Estimadas:** 2 h
- **Criterios de Aceptación:**
  - Al ingresar 3 caracteres del NUREJ se despliegan coincidencias inmediatas autorizadas.
- **Rama Sugerida:** `brayan/B4.7-busqueda-nurej-ui`

---

### SPRINT 4: APERTURA CON NATURALEZA, REMISIÓN INTER-UNIDAD, REPORTES SRS Y EXPORTACIÓN

#### Tarea B3.5: Selector de naturaleza procesal en formulario de apertura de causa
- **ID:** B3.5
- **Título:** Campo selector de naturaleza jurisdiccional vs administrativa en apertura
- **Referencia Auditoría:** AUD-0024 / SRS RN-05 (`:147`)
- **Descripción:** Actualizar `resources/views/expedientes/apertura.blade.php` para incorporar el selector de campo `naturaleza` (`JURISDICCIONAL` vs `ADMINISTRATIVA`), permitiendo al operador técnico clasificar la denuncia desde el ingreso. Envía el atributo al endpoint `POST /api/expedientes` cumpliendo con la validación de `StoreExpedienteRequest` de Bruno.
- **Documentos a leer:**
  - `docs/auditoria/SRS_EXTRAIDO.txt` (RN-05 `:147`).
  - [TAREAS_BRUNO.md](TAREAS_BRUNO.md) (Tarea B3.2).
- **Archivos que toca:**
  - `resources/views/expedientes/apertura.blade.php`
  - `tests/Feature/UiAperturaNaturalezaTest.php` (creación)
- **No tocar:**
  - `app/Http/Requests/StoreExpedienteRequest.php` (propiedad de Bruno en B3.2).
  - `app/Services/ExpedienteService.php`.
- **Dependencias:**
  - De sí mismo: B0.7 mergeado.
  - De Bruno: Requiere que B3.2 de Bruno esté mergeado.
- **Horas Estimadas:** 2 h
- **Criterios de Aceptación:**
  - La interfaz de apertura permite elegir la naturaleza y envía el payload correcto al servidor.
- **Rama Sugerida:** `brayan/B3.5-ui-apertura-naturaleza`

---

#### Tarea B4.9: UI de remisión y registro de recepción de causas inter-unidad (Encargada)
- **ID:** B4.9
- **Título:** Modal e interfaz de remisión y registro de recepción de causas externas
- **Referencia Auditoría:** D-5 / Contratos CTR-04 y CTR-05 / RF-05
- **Descripción:** Construir la interfaz `resources/views/expedientes/detalle/remision-unidad.blade.php` (integrada en `detalle.blade.php`), con acceso exclusivo para el rol **Encargada**:
  1. *Remisión física:* Modal con selector de unidad externa destinataria (`unidades`), motivo institucional y adjunto opcional (`POST /api/expedientes/{e}/remitir-unidad`, CTR-04), transicionando a `REMITIDO_UNIDAD_EXTERNA`.
  2. *Registro de recepción física:* Modal para registrar el reingreso físico de la causa devuelta por la unidad externa (`POST /api/expedientes/{e}/registrar-recepcion`, CTR-05), capturando unidad de origen, motivo del retorno y adjunto opcional, restaurando el estado previo de la causa.
- **Documentos a leer:**
  - [DISENO_UNIDADES_INTER.md](DISENO_UNIDADES_INTER.md).
  - [COORDINACION.md](COORDINACION.md) (§5.3 y §6 Contratos CTR-04 y CTR-05).
- **Archivos que toca:**
  - `resources/views/expedientes/detalle/remision-unidad.blade.php` (creación)
  - `resources/views/expedientes/detalle.blade.php`
  - `tests/Feature/UiRemisionRecepcionUnidadTest.php` (creación)
- **No tocar:**
  - `app/Services/TransferenciaUnidadService.php` (backend de Bruno).
- **Dependencias:**
  - De sí mismo: B4.2 mergeado.
  - De Bruno: Bruno implementa los endpoints CTR-04 y CTR-05 en B2.1.
- **Horas Estimadas:** 3 h
- **Criterios de Aceptación:**
  - La Encargada puede remitir una causa a una unidad externa y registrar su recepción física con adjuntos.
- **Rama Sugerida:** `brayan/B4.9-ui-remision-recepcion`

---

#### Tarea B4.6: Dashboard financiero redefinido (descargos y plazos del Acuerdo 055)
- **ID:** B4.6
- **Título:** Tablero operativo y estadístico para Auditoría Financiera (AC055)
- **Referencia Auditoría:** AUD-0005 (P1) / AC055
- **Descripción:** Tras verificar en la base de datos que el dato de "montos" no existe en el esquema, redefinir y construir el dashboard en `resources/views/financiero/dashboard.blade.php` y su controlador web `app/Http/Controllers/Financiero/FinancieroDashboardController.php`. Se enfoca en: control de descargos comunicados vs recepcionados, causas en plazo de aclaración (5 días hábiles normativos), causas en riesgo de vencimiento y expedientes financieros pendientes de emisión de informe final.
- **Documentos a leer:**
  - `docs/auditoria/FLUJO_FINANCIERO.md` (§1 "Happy path" y §4 "AUD-0034").
  - `docs/auditoria/MATRIZ_PLAZOS.md` (§2.1 "AC055 Descargos").
- **Archivos que toca:**
  - `resources/views/financiero/dashboard.blade.php` (creación)
  - `app/Http/Controllers/Financiero/FinancieroDashboardController.php` (creación)
  - `resources/views/layouts/sidebar.blade.php` (sidebar para rol `AUD_FINANCIERO`)
  - `routes/web.php`
  - `tests/Feature/FinancieroDashboardTest.php` (creación)
- **No tocar:**
  - `app/Services/DescargoFinancieroService.php` (backend de Bruno).
- **Dependencias:**
  - De sí mismo: B0.10 y B4.2 mergeados.
  - De Bruno: B1.2 (Flujo financiero con descargos) mergeado.
- **Horas Estimadas:** 3 h
- **Criterios de Aceptación:**
  - El auditor financiero dispone de su propio panel especializado con métricas de descargos y plazos.
- **Rama Sugerida:** `brayan/B4.6-dashboard-financiero`

---

#### Tarea B5.1: Módulo de reportes institucionales con los 9 reportes exactos del SRS
- **ID:** B5.1
- **Título:** Interfaz de usuario interactiva para los 9 reportes normativos del SRS oficial
- **Referencia Auditoría:** AUD-0036, AUD-0037 (P2) / RF-R01…R09 / Contrato CTR-08
- **Descripción:** Diseñar y construir en `resources/views/reportes/` la interfaz unificada de reportes institucionales. Consume los datos JSON expuestos por Bruno en `routes/api/core.php` vía `GET /api/reportes/{codigo}` (CTR-08). Implementa pestañas y filtros interactivos por fecha, usuario y estado para los 9 reportes oficiales:
  1. RF-R01: Carga Laboral por Usuario.
  2. RF-R02: Solicitudes Registradas (libro matriz).
  3. RF-R03: Recepción Inter-Unidad.
  4. RF-R04: Estado de Evaluación.
  5. RF-R05: Notificaciones Pendientes.
  6. RF-R06: Resoluciones Finales.
  7. RF-R07: Estadístico por Vía.
  8. RF-R08: Carátula Oficial.
  9. RF-R09: Trazabilidad Padre-Hijo.
  Respeta `ReportePolicy` (ocultando menú de reportes y retornando 403 a usuarios con rol ADMIN).
- **Documentos a leer:**
  - `docs/auditoria/SRS_EXTRAIDO.txt` (RF-R01 a RF-R09 `:119-127`).
  - `docs/auditoria/MATRIZ_REPORTES.md`.
  - [COORDINACION.md](COORDINACION.md) (§6 Contrato CTR-08 y §7).
- **Archivos que toca:**
  - `resources/views/reportes/index.blade.php` (creación)
  - `resources/views/reportes/tabla.blade.php` (creación)
  - `resources/views/layouts/sidebar.blade.php` (enlace en menú)
  - `routes/web.php`
  - `tests/Feature/ReportesPantallaTest.php` (creación)
- **No tocar:**
  - `app/Services/ReportesQueryService.php` (backend de Bruno).
  - `routes/api/core.php`.
- **Dependencias:**
  - De sí mismo: B0.10 mergeado.
  - De Bruno: Bruno publica contrato CTR-08 en B5.0 al inicio del Sprint 4.
- **Horas Estimadas:** 9 h
- **Criterios de Aceptación:**
  - Los 9 reportes son navegables en pantalla con filtros operativos.
  - El rol ADMIN no puede acceder a las pantallas de reportes.
- **Rama Sugerida:** `brayan/B5.1-reportes-pantallas`

---

#### Tarea B5.2: Motor de exportación a hojas de cálculo Excel y documentos PDF
- **ID:** B5.2
- **Título:** Descarga de reportes en formatos Excel (.xlsx) y PDF (.pdf) para los 9 reportes
- **Referencia Auditoría:** AUD-0012 (P1) / D-3 / RF-R01…R09
- **Descripción:** Instalar y configurar en `composer.json` las dependencias `maatwebsite/excel` y `barryvdh/laravel-dompdf`. Implementar en `routes/api/reportes.php` y `app/Http/Controllers/ReporteController.php` los endpoints de descarga (`/api/reportes/{codigo}/exportar/excel` y `/api/reportes/{codigo}/exportar/pdf`) para cada uno de los 9 reportes normativos del SRS, aplicando formato institucional oficial, encabezados con escudo y sellos de agua.
- **Documentos a leer:**
  - `docs/auditoria/MATRIZ_REPORTES.md` (§1 "Export capabilities").
  - [COORDINACION.md](COORDINACION.md) (§2.1 y §2.4).
- **Archivos que toca:**
  - `composer.json` y `composer.lock`
  - `app/Http/Controllers/ReporteController.php` (creación)
  - `app/Exports/ReporteExport.php` (creación)
  - `routes/api/reportes.php`
  - `tests/Feature/ExportacionReportesTest.php` (creación)
- **No tocar:**
  - `app/Services/ReportesQueryService.php` (backend de Bruno).
- **Dependencias:**
  - De sí mismo: B5.1 mergeado.
  - De Bruno: B5.0 mergeado.
- **Horas Estimadas:** 9 h
- **Criterios de Aceptación:**
  - Cada reporte puede descargarse en formato `.xlsx` y `.pdf` idéntico a las consultas de pantalla.
- **Rama Sugerida:** `brayan/B5.2-exportacion-excel-pdf`

---

#### Tarea B5.3: Generador de Carátula Oficial PDF (RF-R08)
- **ID:** B5.3
- **Título:** Generación de Foja Cero / Carátula institucional descargable en PDF
- **Referencia Auditoría:** AUD-0012 / RF-R08
- **Descripción:** Diseñar la plantilla Blade `resources/views/expedientes/caratula-pdf.blade.php` para la impresión de la Carátula Oficial de la causa (Foja 0) con datos de identificación: NUREJ Padre, NUREJ derivados, fecha de ingreso, vía procesal, partes denunciante/denunciada y QR o sello de custodia. Crear el endpoint `GET /api/reportes/caratula/{expediente}/pdf` en `routes/api/reportes.php`.
- **Documentos a leer:**
  - `docs/auditoria/SRS_EXTRAIDO.txt` (RF-R08 `:126`).
- **Archivos que toca:**
  - `resources/views/expedientes/caratula-pdf.blade.php` (creación)
  - `app/Http/Controllers/ReporteController.php`
  - `routes/api/reportes.php`
  - `tests/Feature/CaratulaOficialPdfTest.php` (creación)
- **No tocar:**
  - `routes/api/core.php`.
- **Dependencias:**
  - De sí mismo: B5.2 (DomPDF instalado) mergeado.
  - De Bruno: Ninguna.
- **Horas Estimadas:** 3 h
- **Criterios de Aceptación:**
  - La carátula se genera en formato A4 estándar con datos actualizados de la causa.
- **Rama Sugerida:** `brayan/B5.3-caratula-oficial-pdf`

---

### SPRINT 5: OPTIMIZACIÓN, CACHÉ DIVIDIDA, DOCUMENTACIÓN, TESTS CRUZADOS Y CIERRE

#### Tarea B6.2: Tolerancia a fallos en `VerificarVencimientoPlazosCommand`
- **ID:** B6.2
- **Título:** Logging estructurado y validación de dependencias en el comando de vencimientos
- **Referencia Auditoría:** AUD-0029, AUD-0042 (P2) / MATRIZ_JOBS_CRON §5
- **Descripción:** Modificar `VerificarVencimientoPlazosCommand` para envolver la invocación general a los servicios de plazos con validación previa de dependencias y captura estructurada en logs.
  - **División de responsabilidades:** El bloque `try/catch` individual por expediente reside en `ArchivoPorAbandonoService::archivarVencidos()` (propiedad de Bruno en B1.4). Brayan en este comando únicamente gestiona el logging de inicio, resumen y cierre de la corrida, asegurando que el comando no aborte con exit code 1.
- **Documentos a leer:**
  - `docs/auditoria/MATRIZ_JOBS_CRON.md` (§5 y §6).
- **Archivos que toca:**
  - `app/Console/Commands/VerificarVencimientoPlazosCommand.php`
  - `tests/Feature/CronDegradacionControladaTest.php` (creación)
- **No tocar:**
  - `app/Services/ArchivoPorAbandonoService.php` (propiedad de Bruno en B1.4).
- **Dependencias:**
  - De sí mismo: B6.1 mergeado.
  - De Bruno: B1.4 mergeado.
- **Horas Estimadas:** 2 h
- **Criterios de Aceptación:**
  - El comando emite logs estructurados y finaliza limpiamente.
- **Rama Sugerida:** `brayan/B6.2-cron-tolerancia-fallos`

---

#### Tarea B7.1: Optimización de Dashboard Admin y consultas agregadas
- **ID:** B7.1
- **Título:** Reescritura de métricas en SQL puro y erradicación de sobrecarga
- **Referencia Auditoría:** AUD-0063 (P2) / `MATRIZ_RENDIMIENTO.md` §12
- **Descripción:** Refactorizar `AdminDashboardController` para sustituir las cargas masivas en memoria (`Expediente::all()`) por consultas de agregación SQL directas (`selectRaw`, `count()`, `groupBy`), replicando la arquitectura de alto rendimiento implementada en `EncargadaDashboardService`.
- **Documentos a leer:**
  - `docs/auditoria/MATRIZ_RENDIMIENTO.md` (§5 y §12).
- **Archivos que toca:**
  - `app/Http/Controllers/Administrador/AdminDashboardController.php`
  - `tests/Feature/AdminDashboardPerformanceTest.php` (creación)
- **No tocar:**
  - `app/Services/EncargadaDashboardService.php` (servicio de referencia, no tocar).
- **Dependencias:**
  - De sí mismo: B0.6 (índices) mergeado.
  - De Bruno: Ninguna.
- **Horas Estimadas:** 3 h
- **Criterios de Aceptación:**
  - El dashboard de administración responde en menos de 200 ms con cero hydration masivo.
- **Rama Sugerida:** `brayan/B7.1-admin-dashboard-performance`

---

#### Tarea B7.2: Implementación de driver de Caché de base de datos y `CatalogoCacheService`
- **ID:** B7.2
- **Título:** Driver de base de datos para caché, servicio CatalogoCacheService e invalidación
- **Referencia Auditoría:** AUD-0020 (P2) / D-8
- **Descripción:** Crear la migración de tabla de caché de base de datos (`YYYY_MM_DD_20XXXX_ensure_cache_table.php`). Configurar `CACHE_STORE=database` en el entorno. Crear el servicio `App\Services\CatalogoCacheService` encargado de almacenar en caché relacional lecturas intensivas que rara vez mutan (feriados, parámetros de plazos, reglamentos y estados). Implementar la invalidación automática de caché en los controladores de administración (`AdminFeriadosController`).
  - **División de responsabilidades:** Brayan crea `CatalogoCacheService` e invalida la caché en sus controladores de administración. NO toca `ReglamentoController` ni `CatalogoEstadoController` (propiedad de Bruno, quien consumirá este servicio en B8.8).
- **Documentos a leer:**
  - `docs/PLAN_DESARROLLO_REMEDIACION.md` (§2 Decisión D-8).
  - [COORDINACION.md](COORDINACION.md) (§10.1).
- **Archivos que toca:**
  - `config/cache.php`
  - `database/migrations/YYYY_MM_DD_20XXXX_ensure_cache_table.php` (creación)
  - `app/Services/CatalogoCacheService.php` (creación)
  - `app/Http/Controllers/Administrador/AdminFeriadosController.php` (invalidación)
  - `tests/Feature/CatalogoCacheTest.php` (creación)
- **No tocar:**
  - `app/Http/Controllers/ReglamentoController.php` (propiedad de Bruno).
  - `app/Http/Controllers/CatalogoEstadoController.php` (propiedad de Bruno).
  - `app/Services/PlazoCalculatorService.php` (propiedad de Bruno).
- **Dependencias:**
  - De sí mismo: B0.5 mergeado.
  - De Bruno: Ninguna.
- **Horas Estimadas:** 3 h
- **Criterios de Aceptación:**
  - La tabla `cache` opera con driver `database`.
  - Modificar un feriado en administración invalida la caché de forma inmediata.
- **Rama Sugerida:** `brayan/B7.2-cache-database`

---

#### Tarea B7.3: Paginación server-side faltante en endpoints administrativos
- **ID:** B7.3
- **Título:** Paginación server-side en monitoreo y usuarios
- **Referencia Auditoría:** AUD-0064, AUD-0065 (P2) / RNF-03
- **Descripción:** Actualizar `AdminMonitoreoController` y `AdminUsuariosController` para asegurar paginación server-side estricta (`paginate(15)` o `cursorPaginate()`), eliminando cualquier consulta `->get()` o `->all()` que pueda comprometer la memoria del servidor con el crecimiento del padrón de causas.
- **Documentos a leer:**
  - `docs/auditoria/MATRIZ_RENDIMIENTO.md` (§8 "Paginación").
- **Archivos que toca:**
  - `app/Http/Controllers/Administrador/AdminMonitoreoController.php`
  - `app/Http/Controllers/Administrador/AdminUsuariosController.php`
  - `tests/Feature/AdminPaginacionServerSideTest.php` (creación)
- **No tocar:**
  - Endpoints de expedientes del backend de Bruno.
- **Dependencias:**
  - De sí mismo: B0.2 mergeado.
  - De Bruno: Ninguna.
- **Horas Estimadas:** 3 h
- **Criterios de Aceptación:**
  - Todos los listados administrativos responden con metadatos de paginación (`meta.current_page`, `meta.last_page`).
- **Rama Sugerida:** `brayan/B7.3-paginacion-admin`

---

#### Tarea B8.9: Suite de pruebas de validación de formularios y FormRequests
- **ID:** B8.9
- **Título:** Cobertura de validaciones positivas y negativas en FormRequests
- **Referencia Auditoría:** AUD-0003 (P1) / RNF-04
- **Descripción:** Diseñar y codificar en `tests/Feature/FormRequestsCoverageTest.php` pruebas exhaustivas que sometan a validación todos los FormRequests preexistentes y los campos de las vistas frontend: payloads vacíos, tipos de datos incompatibles, cadenas excedidas y verificaciones `exists` en base de datos.
- **Documentos a leer:**
  - `docs/auditoria/MAPA_FUNCIONAL.md`.
- **Archivos que toca:**
  - `tests/Feature/FormRequestsCoverageTest.php`
- **No tocar:**
  - `tests/Feature/MatrizAutorizacionCompletaTest.php` (propiedad de Bruno).
- **Dependencias:**
  - De sí mismo: B0.2 mergeado.
  - De Bruno: Ninguna.
- **Horas Estimadas:** 3 h
- **Criterios de Aceptación:**
  - Cobertura completa de aserciones `assertSessionHasErrors` y `assertJsonValidationErrors`.
- **Rama Sugerida:** `brayan/B8.9-test-form-requests`

---

#### Tarea B8.10: Suite de conciliación de reportes (Pantalla = Excel = PDF = SQL)
- **ID:** B8.10
- **Título:** Pruebas de paridad matemática entre consultas SQL, pantallas y archivos exportados
- **Referencia Auditoría:** Adición solicitada (criterio derivado de la auditoría) / RF-R01…R09
- **Descripción:** Programar pruebas en `tests/Feature/ConciliacionReportesTest.php` que ejecuten cada uno de los 9 reportes SRS y comparen celda por celda y cifra por cifra: (1) El resultado crudo retornado por el API SQL de Bruno (CTR-08); (2) La tabla renderizada en Blade; (3) El archivo exportado en Excel; (4) El documento PDF generado. Deben coincidir con 0 discrepancias numéricas.
- **Documentos a leer:**
  - `docs/auditoria/MATRIZ_REPORTES.md`.
  - [COORDINACION.md](COORDINACION.md) (§7).
- **Archivos que toca:**
  - `tests/Feature/ConciliacionReportesTest.php` (creación)
- **No tocar:**
  - `app/Services/ReportesQueryService.php`.
- **Dependencias:**
  - De sí mismo: B5.1, B5.2 y B5.3 mergeados.
  - De Bruno: B5.0 mergeado.
- **Horas Estimadas:** 3 h
- **Criterios de Aceptación:**
  - Conciliación de datos 100% verificada para los 9 reportes normativos.
- **Rama Sugerida:** `brayan/B8.10-test-conciliacion-reportes`

---

#### Tarea B8.11: Pruebas de interfaz de búsqueda que respeta asignación
- **ID:** B8.11
- **Título:** Verificación de interfaz de búsqueda y compartimentos en frontend
- **Referencia Auditoría:** Adición solicitada (criterio derivado de la auditoría) / RF-03
- **Descripción:** Programar pruebas en `tests/Feature/UiBusquedaAsignacionTest.php` que verifiquen que el componente de búsqueda solo despliega al operador sugerencias de causas asignadas activamente a su propia bandeja (los usuarios no pertenecen a una unidad), impidiendo que el autocompletado revele códigos NUREJ o datos de causas ajenas.
- **Documentos a leer:**
  - `docs/auditoria/MATRIZ_SEGURIDAD.md`.
- **Archivos que toca:**
  - `tests/Feature/UiBusquedaAsignacionTest.php` (creación)
- **No tocar:**
  - `app/Http/Controllers/ExpedienteBusquedaController.php`.
- **Dependencias:**
  - De sí mismo: B4.7 mergeado.
  - De Bruno: B2.6 mergeado.
- **Horas Estimadas:** 2 h
- **Criterios de Aceptación:**
  - El componente de búsqueda respeta rigurosamente los compartimentos estancos por asignación activa.
- **Rama Sugerida:** `brayan/B8.11-test-ui-busqueda-asignacion`

---

#### Tarea B9.1 a B9.5: Documentación técnica, `.env.example`, CHANGELOG, identidad y tooltips
- **ID:** B9.1-5
- **Título:** Paquete de entrega de documentación, configuración institucional y ayudas
- **Referencia Auditoría:** AUD-0038, AUD-0045, AUD-0046, AUD-0047, AUD-0048 (P3)
- **Descripción:** Consolidar los entregables de cierre del sistema:
  - B9.1: Actualizar `docs/BACKEND_MODULES_TECHNICAL_GUIDE.md` con las nuevas rutas y módulos.
  - B9.2: Actualizar `.env.example` documentando todas las variables (incluyendo las suministradas por Bruno como `APP_TIMEZONE`).
  - B9.3: Redactar `CHANGELOG.md` institucional con el registro de versiones y remediaciones.
  - B9.4: Incorporar favicon institucional del Estado Plurinacional y paleta gráfica oficial.
  - B9.5: Integrar tooltips de ayuda contextual en modales complejos de la interfaz.
- **Documentos a leer:**
  - `docs/BACKEND_MODULES_TECHNICAL_GUIDE.md`.
  - [COORDINACION.md](COORDINACION.md) (§2.5).
- **Archivos que toca:**
  - `docs/BACKEND_MODULES_TECHNICAL_GUIDE.md`
  - `.env.example`
  - `CHANGELOG.md` (creación)
  - `resources/views/layouts/app.blade.php`
  - `public/favicon.ico`
- **No tocar:**
  - `docs/auditoria/**`.
- **Dependencias:**
  - De sí mismo: B0.10 y B5.2 mergeados.
  - De Bruno: Bruno le entrega la lista de variables de entorno de sus módulos.
- **Horas Estimadas:** 4 h
- **Criterios de Aceptación:**
  - Repositorio listo para entrega formal y pase a auditoría final.
- **Rama Sugerida:** `brayan/B9.1-5-documentacion-entrega`

---

#### Tarea B9.6: Checklist de despliegue y hardening de base de datos MySQL
- **ID:** B9.6
- **Título:** Guía de seguridad de infraestructura, permisos de base de datos y despliegue
- **Referencia Auditoría:** Adición solicitada (criterio derivado de la auditoría) / restricciones.md
- **Descripción:** Redactar en `docs/seguridad/CHECKLIST_DESPLIEGUE_SEGURIDAD.md` los lineamientos de hardening para entorno de producción gubernamental: (1) Instrucciones para conceder al usuario de la aplicación en MySQL únicamente privilegios `SELECT`, `INSERT`, `UPDATE` (en tablas permitidas) y `DELETE`, revocando taxativamente privilegios `DROP`, `ALTER`, `SUPER` y `TRIGGER`; (2) Configuración estricta de `.env` (`APP_DEBUG=false`, `APP_ENV=production`); (3) Restricciones de red y certificados TLS/SSL obligatorios.
- **Documentos a leer:**
  - `.opencode/rules/restricciones.md`.
  - `docs/auditoria/MATRIZ_HARDENING.md`.
- **Archivos que toca:**
  - `docs/seguridad/CHECKLIST_DESPLIEGUE_SEGURIDAD.md` (creación)
- **No tocar:**
  - Archivos de código de la aplicación.
- **Dependencias:**
  - De sí mismo: Ninguna.
  - De Bruno: Ninguna.
- **Horas Estimadas:** 3 h
- **Criterios de Aceptación:**
  - Guía completa de pase a producción validada contra el perfil de seguridad del Estado.
- **Rama Sugerida:** `brayan/B9.6-checklist-despliegue`

---

#### Tarea B9.7: Protocolo UAT de aceptación de usuario para los 5 roles
- **ID:** B9.7
- **Título:** Batería de pruebas de aceptación formal para roles del sistema
- **Referencia Auditoría:** Adición solicitada (criterio derivado de la auditoría) / Aceptación Final
- **Descripción:** Elaborar el protocolo de pruebas de aceptación de usuario (UAT) en `docs/auditoria/PROTOCOLO_UAT_FINAL.md`. Cubre escenarios paso a paso para los 5 roles del sistema (`ADMIN`, `ENCARGADA`, `TECNICO`, `AUD_JURIDICO`, `AUD_FINANCIERO`), certificando que cada rol solo puede ejecutar sus competencias normativas y que no existen usuarios de unidades externas en sistema.
- **Documentos a leer:**
  - `docs/auditoria/MAPA_FUNCIONAL.md`.
  - [COORDINACION.md](COORDINACION.md) (§5.3).
- **Archivos que toca:**
  - `docs/auditoria/PROTOCOLO_UAT_FINAL.md` (creación)
- **No tocar:**
  - Archivos de código.
- **Dependencias:**
  - De sí mismo: B4.1 a B4.9 mergeados.
  - De Bruno: B2.3 mergeado.
- **Horas Estimadas:** 2 h
- **Criterios de Aceptación:**
  - Guía de pruebas de usuario lista para firma de actas de entrega institucional.
- **Rama Sugerida:** `brayan/B9.7-protocolo-uat`
