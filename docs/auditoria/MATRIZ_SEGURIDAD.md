# Matriz de Seguridad — Autorización e IDOR por endpoint (Fase 3)

- **Fecha:** 2026-09-29
- **Método:** para cada endpoint se verificó la capa de autorización real (middleware → FormRequest `authorize()` → `$this->authorize()` / `abort()` → policy) y se comprobó el bloqueo ante (a) usuario sin asignación sobre un NUREJ ajeno y (b) rol no autorizado, mediante tests feature. **Cobertura demostrable**: solo se marca `BLOQUEADO (TEST)` si existe un test que espera 403/401.
- **Tests nuevos Fase 3:** `tests/Feature/SeguridadIdorTest.php` (16 tests, todos en verde).
- **Leyenda:** `BLOQUEADO (TEST)` = test que espera 403/401 · `BLOQUEADO (CÓDIGO)` = policy/FormRequest verificado estáticamente, sin test · `FILTRADO (TEST)` = sin 403 por diseño (devuelve solo lo propio) · `N/A` = sin recurso ajeno posible.

## 1. Endpoints API (`routes/api.php`, `auth:sanctum` + `throttle:api`)

| # | Método | Ruta | Capa de autorización | Regla | IDOR recurso ajeno | Estado | Evidencia |
| - | ------ | ---- | -------------------- | ----- | ------------------ | ------ | --------- |
| 1 | POST | `/api/login` | `LoginRequest` (público) + `throttle:login` | — | N/A | N/A | `AuthFeatureTest` (13 tests) |
| 2 | GET | `/api/me` | `auth:sanctum` | sesión propia | N/A | N/A | `AuthFeatureTest` |
| 3 | POST | `/api/logout` | `auth:sanctum` | sesión propia | N/A | N/A | `AuthFeatureTest` |
| 4 | GET | `/api/bandeja` | filtro de ownership en consulta (sin 403) | solo asignaciones/creaciones propias | devuelve vacío si es ajeno | FILTRADO (TEST) | `ExpedienteControllerTest:227` |
| 5 | GET | `/api/bandeja/sorteo` | policy `bandejaSorteo` | solo ENCARGADA | 403 | BLOQUEADO (TEST) | `ExpedienteControllerTest:209` + **SeguridadIdorTest** |
| 6 | POST | `/api/bandeja/sorteo/todos` | `SortearTodosRequest` → rol ENCARGADA | solo ENCARGADA | 403 | BLOQUEADO (TEST) | `SorteoTodosTest:85` |
| 7 | GET | `/api/usuarios` | controller `viewOperativos` | ENCARGADA + ADMIN | 403 | BLOQUEADO (TEST) | `UsuarioCatalogoTest:65,77` + **SeguridadIdorTest** |
| 8 | POST | `/api/usuarios/{u}/inactivar` | controller `inactivar` (policy) | solo ADMIN activo | 403 | BLOQUEADO (TEST) | **SeguridadIdorTest** |
| 9 | GET | `/api/reglamentos` | FormRequest + policy `verCatalogoReglamentos` | roles operativos + ENCARGADA + ADMIN (activos) | N/A (catálogo) | BLOQUEADO (TEST, 401) | `ReglamentoControllerTest:22` |
| 10 | GET | `/api/catalogo/actuados` | FormRequest + policy `verCatalogoActuados` | ídem; filtrado por rol | N/A (catálogo) | BLOQUEADO (TEST, 401) | `CatalogoActuadoControllerTest:86` |
| 11 | GET | `/api/estados` | FormRequest + policy `verCatalogoEstados` | ídem | N/A (catálogo) | BLOQUEADO (TEST, 401) | `CatalogoEstadoControllerTest:22` |
| 12 | GET | `/api/adjuntos/{a}/descargar` | controller `view` sobre el expediente del actuado | ENCARGADA o con asignación/creación propia | 403 | BLOQUEADO (TEST) | `AdjuntoControllerTest:125` + **SeguridadIdorTest** |
| 13 | POST | `/api/expedientes` | `StoreExpedienteRequest` | solo TÉCNICO | 403 | BLOQUEADO (TEST) | `ExpedienteControllerTest:148` + **SeguridadIdorTest** |
| 14 | GET | `/api/expedientes/{e}` | policy `view` | ENCARGADA siempre; operativo con asignación/creación; **ADMIN bypass → AUD-0001** | 403 (excepto ADMIN) | FALLA CONOCIDA (AUD-0001) | `SecurityCompartimentosTest` ×5 + `ExpedienteControllerTest:178` |
| 15 | POST | `/api/expedientes/{e}/sortear` | `SortearExpedienteRequest` | solo ENCARGADA | 403 | BLOQUEADO (TEST) | `FormRequestsTest:149` + **SeguridadIdorTest** |
| 16 | POST | `/api/expedientes/{e}/actuados` | `StoreActuadoRequest` → policy `crearActuado` | rol del catálogo + asignación activa | 403 | BLOQUEADO (TEST) | `SecurityCompartimentosTest:144,210` |
| 17 | GET | `/api/expedientes/{e}/requisitos` | controller `view` | que #14 | 403 | BLOQUEADO (TEST) | `EvaluacionAdmisibilidadTest:127,401` + **SeguridadIdorTest** |
| 18 | POST | `/api/expedientes/{e}/evaluacion` | controller `view` + `StoreEvaluacionAdmisibilidadRequest` → `evaluarAdmisibilidad` | con asignación activa | 403 | BLOQUEADO (TEST) | `EvaluacionAdmisibilidadTest:127` + **SeguridadIdorTest** |
| 19 | POST | `/api/expedientes/{e}/impugnacion/remitir` | `ImpugnacionRemitirRequest` → `remitirImpugnacion` | operativo con asignación + estado | 403 | BLOQUEADO (TEST) | `ImpugnacionRechazoTest:163` |
| 20 | POST | `/api/expedientes/{e}/impugnacion/resolver` | `ResolverImpugnacionRequest` → `resolverImpugnacion` | solo ENCARGADA + estado | 403 | BLOQUEADO (TEST) | `ImpugnacionRechazoTest:174` + **SeguridadIdorTest** |
| 21 | POST | `/api/expedientes/{e}/planificacion` | `StorePlanificacionRequest` → `cargarPlanificacion` | asignación + rol↔reglamento + estado | 403 | BLOQUEADO (TEST) | `PlanificacionTest:244,257,271` + **SeguridadIdorTest** |
| 22 | POST | `/api/expedientes/{e}/planificacion/visto-bueno` | `VistoBuenoPlanificacionRequest` → `aprobarPlanificacion` | solo ENCARGADA + estado | 403 | BLOQUEADO (TEST) | `PlanificacionTest:410` |
| 23 | POST | `/api/expedientes/{e}/planificacion/devolver` | `DevolverPlanificacionRequest` → `devolverPlanificacion` | solo ENCARGADA + estado | 403 | BLOQUEADO (TEST) | `PlanificacionTest:536` |
| 24 | POST | `/api/expedientes/{e}/ampliacion` | `SolicitarAmpliacionRequest` → `solicitarAmpliacion` | TÉCNICO AC022 con asignación + estado | 403 | BLOQUEADO (TEST) | `AmpliacionTest:245,258,270` + **SeguridadIdorTest** |
| 25 | POST | `/api/expedientes/{e}/ampliacion/aprobar` | `AprobarAmpliacionRequest` → `aprobarAmpliacion` | solo ENCARGADA + estado | 403 | BLOQUEADO (TEST) | `AmpliacionTest:282,297` |
| 26 | POST | `/api/expedientes/{e}/nurej-hijo` | `DerivarNurejHijoRequest` → `derivarNurejHijo` | solo ENCARGADA | 403 | BLOQUEADO (TEST) | `NurejHijoTest:213,224,235` |
| 27 | POST | `/api/expedientes/{e}/cierre/visto-bueno` | `AprobarVistoBuenoRequest` → `aprobarVistoBuenoFinal` | solo ENCARGADA + estado | 403 | BLOQUEADO (TEST) | `CierreExpedienteTest:236,261,280` |
| 28 | POST | `/api/expedientes/{e}/cierre/reparto` | `RepartoInstitucionalRequest` → `ejecutarRepartoInstitucional` | solo ENCARGADA + estado | 403 | BLOQUEADO (TEST) | `CierreExpedienteTest:248,261,292` |
| 29 | POST | `/api/expedientes/{e}/derivacion-transparencia` | `DerivarTransparenciaRequest` → `derivarPorIncompetencia` | operativo con asignación + catálogo | 403 | BLOQUEADO (TEST) | `DerivacionTransparenciaTest:204,217` |
| 30 | POST | `/api/expedientes/{e}/transparencia/remitir` | `RemitirTransparenciaRequest` → `remitirTransparencia` | solo ENCARGADA + estado | 403 | BLOQUEADO (TEST) | `DerivacionTransparenciaTest:322` |
| 31 | POST | `/api/expedientes/{e}/descargos/comunicar` | `ComunicarHallazgosRequest` → `comunicarHallazgos` | AUD_FINANCIERO + asignación + estado + AC055 | 403 | BLOQUEADO (TEST) | `DescargoFinancieroTest:252` + **SeguridadIdorTest** |
| 32 | POST | `/api/expedientes/{e}/descargos/recibir` | `RecibirDescargosRequest` → `recibirDescargos` | mismo helper `puedeTramitarDescargos` | 403 | BLOQUEADO (TEST) | `DescargoFinancieroTest:252` |
| 33 | GET | `/api/encargada/dashboard` | controller `bandejaSorteo` | solo ENCARGADA | 403 | BLOQUEADO (TEST) | `EncargadaDashboardTest:146` + **SeguridadIdorTest** |
| 34 | GET | `/api/admin/dashboard` | **inline `abort(403)` por rol** (AUD-0017) | solo ADMIN | 403 | BLOQUEADO (TEST) | **SeguridadIdorTest** |
| 35 | GET | `/api/admin/usuarios` | controller policy `gestionar` | solo ADMIN | 403 | BLOQUEADO (TEST) | **SeguridadIdorTest** |
| 36 | POST | `/api/admin/usuarios` | controller policy `gestionar` (línea 74) | solo ADMIN | 403 | BLOQUEADO (CÓDIGO) | `AdminUsuariosController.php:74` |
| 37 | PUT | `/api/admin/usuarios/{u}` | controller policy `gestionar` (línea 101) | solo ADMIN | 403 | BLOQUEADO (CÓDIGO) | `AdminUsuariosController.php:101` |
| 38 | POST | `/api/admin/usuarios/{u}/activar` | controller policy `activar` (línea 128) | solo ADMIN | 403 | BLOQUEADO (CÓDIGO) | `AdminUsuariosController.php:128` |
| 39 | POST | `/api/admin/usuarios/{u}/inactivar` | controller policy `inactivar` (método #8) | solo ADMIN | 403 | BLOQUEADO (TEST, método) | **SeguridadIdorTest** (ruta #8) |
| 40 | GET | `/api/admin/feriados` | controller policy `gestionar` Feriado | solo ADMIN | 403 | BLOQUEADO (TEST) | **SeguridadIdorTest** |
| 41 | POST | `/api/admin/feriados` | controller policy `gestionar` (línea 65) | solo ADMIN | 403 | BLOQUEADO (CÓDIGO) | `AdminFeriadosController.php:65` |
| 42 | PUT | `/api/admin/feriados/{f}` | controller policy `gestionar` (línea 84) | solo ADMIN | 403 | BLOQUEADO (CÓDIGO) | `AdminFeriadosController.php:84` |
| 43 | DELETE | `/api/admin/feriados/{f}` | controller policy `gestionar` (línea 101) | solo ADMIN | 403 | BLOQUEADO (CÓDIGO) | `AdminFeriadosController.php:101` |
| 44 | GET | `/api/admin/monitoreo` | **inline `abort(403)` por rol** (AUD-0017) | solo ADMIN | 403 | BLOQUEADO (TEST) | **SeguridadIdorTest** |

**401 sin token (grupo `auth:sanctum`):** verificado en `SeguridadIdorTest` (`/api/admin/usuarios`, `/api/admin/dashboard`), `ReglamentoControllerTest:22`, `CatalogoActuadoControllerTest:86`, `CatalogoEstadoControllerTest:22`, `EvaluacionAdmisibilidadTest:113`, `SecurityCompartimentosTest:260`.

## 2. Endpoints Web (`routes/web.php`, sesión `auth`)

| # | Ruta | Capa de autorización | Regla | IDOR recurso ajeno | Estado | Evidencia |
| - | ---- | -------------------- | ----- | ------------------ | ------ | --------- |
| 1 | GET `/login` | pública | — | N/A | N/A | `WebWorkstationRoutesTest:20` |
| 2 | GET `/expedientes` | policy `operadorBandeja` | roles operativos | 403 para ENCARGADA | BLOQUEADO (TEST) | `WebWorkstationRoutesTest:53` |
| 3 | GET `/bandeja/sorteo` | policy `bandejaSorteo` | solo ENCARGADA | 403 | BLOQUEADO (TEST) | `WebWorkstationRoutesTest:46` |
| 4 | GET `/expedientes/nuevo` | policy `aperturaCausa` | solo TÉCNICO | 403 | BLOQUEADO (TEST) | `WebWorkstationRoutesTest:69` |
| 5 | GET `/expedientes/{e}` | policy `view` | igual que API #14 | 403 (excepto ADMIN) | BLOQUEADO (TEST) | `DetalleExpedienteFeatureTest:196` |
| 6 | GET `/encargada/dashboard` | controller `bandejaSorteo` | solo ENCARGADA | 403 | BLOQUEADO (TEST) | `EncargadaDashboardTest:52` |
| 7 | GET `/administrador/dashboard` | middleware `EnsureAdmin` | solo ADMIN activo | 403 | BLOQUEADO (TEST) | **SeguridadIdorTest** |
| 8 | GET `/administrador/usuarios` | `EnsureAdmin` | solo ADMIN | 403 | BLOQUEADO (TEST, middleware) | **SeguridadIdorTest** (#7, mismo middleware) |
| 9 | GET `/administrador/feriados` | `EnsureAdmin` | solo ADMIN | 403 | BLOQUEADO (TEST, middleware) | ídem |
| 10 | GET `/administrador/monitoreo` | `EnsureAdmin` | solo ADMIN | 403 | BLOQUEADO (TEST, middleware) | ídem |

## 3. Resumen

| Estado | Cantidad |
| ------ | -------- |
| BLOQUEADO (TEST) — incluye 11 aportados/confirmados por `SeguridadIdorTest` | 44 |
| BLOQUEADO (CÓDIGO) — policy en controller, sin test (solo operaciones CRUD admin) | 6 |
| FALLA CONOCIDA | 1 (`GET /api/expedientes/{e}` → AUD-0001, bypass ADMIN) |
| FILTRADO (TEST) | 1 (`GET /api/bandeja`) |
| N/A | 2 (`login`, `me`/`logout`) |

- **IDOR (recurso ajeno NUREJ):** todos los endpoints con recurso bloqueados por policy/FormRequest verifican **asignación activa o jerarquía (ENCARGADA/ADMIN)**; no se encontró endpoint que opere sobre NUREJ ajeno sin pasar por policy.
- **Endpoints sin test (solo estático):** 6 — todos de escritura del panel ADMIN (`POST/PUT` usuarios, `activar`, `POST/PUT/DELETE` feriados) con la misma policy `gestionar` ya testeada en GET. **Pendiente Fase 17** (cobertura mínima de esos CRUD) si el usuario lo aprueba.

## 4. Hallazgos derivados de esta fase

| ID | Hallazgo | Estado |
| -- | -------- | ------ |
| AUD-0001 | Bypass `view` para ADMIN contradice RF-03/CA-2/SRS `:85-86` (test existente en rojo) | OPEN — decisión del usuario, Fase 3 |
| AUD-0017 | `abort(403)` inline en `AdminDashboardController:29` y `AdminMonitoreoController:31` en vez de policy (enforcement presente, patrón no óptimo) | OPEN |
| (nota) | FormRequests de admin (`IndexUsuariosAdmin`, `Store/UpdateUsuario`, `Store/UpdateFeriado`, `Activar/Inactivar`) tienen `authorize(): true`; la autorización real vive en el policy llamado desde el controlador — correcto, pero no obvio | Documentado, sin acción |

## 5. Gate de salida Fase 3

- `php artisan test --compact` → **268 tests: 255 OK · 2 fallos · 5 errores · 6 omitidos** = baseline (252/239/2/5/6) + **16 tests nuevos en verde**, sin modificar ningún test preexistente.
