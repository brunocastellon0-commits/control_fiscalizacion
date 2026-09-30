# MAPA FUNCIONAL DEL SISTEMA — Fase 1

**Archivo:** `docs/auditoria/MAPA_FUNCIONAL.md`
**Última actualización:** 2026-09-29 (Fase 1 — Mapa funcional; borrador inicial de Fase 0 conservado como insumo)
**Método:** cada fila se verificó contra código real en este turno: ruta (`route:list --json`) → controlador → autorización (Form Request `authorize()` / policy / middleware / filtro de ownership) → servicio → modelo/tabla (por `use App\Models\` y migraciones) → tests (cruce por grep de la URI en `tests/`). **Ninguna fila se copió de documentación previa.**

**Leyenda — Estado de implementación:** `IMPLEMENTADA` (capas backend completas) · `PARCIAL` · `FALTANTE` · `INCORRECTA` · `INCONSISTENTE` · `NO VERIFICADA` · `FUERA DE ALCANCE`.

**Leyenda — columna Pruebas:** se indica el archivo de test cuyo contenido contiene la ruta (cruce por grep). Marca `(indirecta)` cuando el test contiene la ruta como parte de su semilla de otros escenarios, no como sujeto de aserción. `Ninguna` = ningún archivo de `tests/` referencia la ruta.

**Leyenda — columna Rol:** derivada de la autorización efectivamente ejecutada (política, Form Request o middleware), nunca asumida por nombre de menú.

---

## 1. Inventario técnico verificado

| Elemento | Hallazgo | Evidencia |
| --- | --- | --- |
| PHP | 8.5.9 (ZTS, Windows) | `php -v` |
| Laravel | 13.27.0 | `composer show --direct` |
| Auth | Sanctum 4.3.3 (stateful API + guard `web`) | `composer.json`, `bootstrap/app.php` (`statefulApi()`) |
| Tests | Pest 5.1.2 | `composer show --direct` |
| Motor BD | **MySQL 9.7.0**, `InnoDB`, `utf8mb4_unicode_ci` en las 28 tablas | `information_schema` (query real) |
| BD dev | `control_fiscalizacion` con datos de prueba (15 usuarios, 24 expedientes, 30 actuados) | tinker SELECT |
| BD test | `control_fiscalizacion_test` (`.env.testing`) | `.env.testing` |
| Session driver | `database` (tabla `sessions`) + auditoría propia `sesiones_acceso` | `.env`, `config/session.php` |
| Frontend | Blade + Alpine.js (CDN) + fetch a `/api/*`; Tailwind v4 local **y** `cdn.tailwindcss.com` | `resources/views/layouts/app.blade.php:8-10`, `resources/js/app.js` (3 bytes) |
| Rutas (registro Fase 0) | 58 rutas "activas (13 web + 45 api, excluye vendor)" — **ver reconciliación §1.1** | registro histórico de Fase 0 en `PROGRESO.md` |
| Capas | 27 controllers · 19 services · 3 policies · 31 Form Requests · 10 resources · 1 command | `app/` |
| Módulos | 22 modelos · 31 migraciones · 28 tablas · 13 seeders · 8 factories | `app/Models`, `database/` |
| Programados | 1 tarea diaria: `plazos:verificar-vencidos` (`daily()`) | `routes/console.php:11` |
| Debug | `APP_DEBUG=true` en `.env` local; **no** se hallaron `dd()`/`dump()` en `app/` | grep `app/**/*.php` |
| Transacciones | 24 usos de `DB::transaction` repartidos en los services | grep |
| Soft deletes | ninguno | grep |

### 1.1 Reconciliación objetiva del conteo de rutas (58 vs 54)

No se asume el origen de la diferencia; ambos conteos se reprodujeron hoy con comando explícito:

| Conteo | Comando / método exacto | Resultado | Desglose técnico |
| --- | --- | --- | --- |
| **58** (registro Fase 0) | Conteo de ocurrencias `Route::` en los archivos de rutas: `(Select-String -Path routes\api.php -Pattern 'Route::').Count = 45` + `(Select-String -Path routes\web.php -Pattern 'Route::').Count = 13` → 45 + 13 = **58** | 58 **declaraciones**, no 58 rutas | `routes/api.php`: 45 = 44 rutas + **1 declaración de grupo** (línea 29, `Route::middleware([...])->group`). `routes/web.php`: 13 = 10 rutas + **3 declaraciones de grupo** (líneas 18, 31, 45). 58 − 4 grupos = 54 rutas reales |
| **54** (hoy) | `php artisan route:list --except-vendor` | **54 rutas registradas** por la app | 44 api + 10 web. Método HTTP sobre las 54: GET 25 (+HEAD 25), POST 26, PUT 2, DELETE 1 |
| **59** (hoy) | `php artisan route:list` (sin filtro) | 59 = 54 + **5 rutas vendor** | Vendor: `POST _boost/browser-logs` (Boost), `GET sanctum/csrf-cookie` (Sanctum), `GET storage/{path}`, `PUT storage/{path}` (FilesystemServiceProvider), `GET up` (health check de `bootstrap/app.php:13`) |

Puntos pedidos, verificados uno por uno:

- **Rutas con nombre vs sin nombre:** sobre las 54 de la app → **10 con nombre** (todas web: `login`, `expedientes.bandeja`, `expedientes.bandeja-sorteo`, `expedientes.apertura`, `expedientes.detalle`, `encargada.dashboard`, `administrador.dashboard`, `administrador.usuarios`, `administrador.feriados`, `administrador.monitoreo`) y **44 sin nombre** (todas `api/*`).
- **Mismos URI con varios métodos:** 3 URIs de la app (y 1 vendor): `POST+GET api/admin/feriados`, `PUT+DELETE api/admin/feriados/{feriado}`, `GET+POST api/admin/usuarios`. Por eso **54 rutas = 51 URIs distintas** en la app.
- **Rutas HEAD:** Laravel registra HEAD automáticamente para cada GET y `route:list` lo muestra en la misma fila (`GET|HEAD`); los 25 GET de la app **no** son rutas adicionales (no inflan el conteo).
- **Rutas raíz/fallback:** **no existe** ruta `/` ni `Route::fallback` (grep en `routes/`; `bootstrap/app.php` solo declara `web`, `api` y `health: '/up'`).
- **Rutas excluidas por filtros:** `--except-vendor` excluye exactamente las 5 vendor listadas arriba; `route:list --json` sin filtro da 59.
- **Límite de la evidencia de Fase 0:** el comando literal usado en Fase 0 no quedó registrado en `PROGRESO.md`. Los números sí están registrados (`45 api + 13 web`) y **coinciden exactamente** con el conteo de declaraciones `Route::` por archivo y con ningún resultado posible de `route:list` (59/54). Se documenta sin retro-corrregir el registro de Fase 0: **58 = declaraciones en archivos (incluye 4 grupos); 54 = rutas registradas reales.**

---

## 2. Tabla maestra por módulo

Columnas: Módulo · Funcionalidad · Rol · Ruta UI · Endpoint · Controlador · Servicio · Modelo · Tabla · Estado · Actuado · Pruebas · Estado de implementación · Observaciones.

### 2.1 Autenticación y sesiones

| Módulo | Funcionalidad | Rol | Ruta UI | Endpoint | Controlador | Servicio | Modelo | Tabla | Estado | Actuado | Pruebas | Impl. | Observaciones |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Autenticación | Login por usuario (no email) | Público | `GET /login` (`auth/login.blade.php`) | `POST /api/login` (throttle `login`) | `AuthController@login` | `SeguridadSesionesService` | `Usuario` | `usuarios`, `sesiones_acceso`, `auditoria_usuarios` | — | No | `AuthFeatureTest` | IMPLEMENTADA | Rate limiting en login |
| Autenticación | Logout / cierre de sesión | Autenticado | — | `POST /api/logout` | `AuthController@logout` | `SeguridadSesionesService` | `Usuario` | `sesiones_acceso` | — | No | `AuthFeatureTest`, `SeguridadSesionesTest` | IMPLEMENTADA | — |
| Autenticación | Sesión actual (`me`) | Autenticado | — | `GET /api/me` | `AuthController@me` | — | `Usuario` | `usuarios` | — | No | `AuthFeatureTest`, `SeguridadSesionesTest` | IMPLEMENTADA | Devuelve rol para menú frontend |

### 2.2 Panel del Administrador

| Módulo | Funcionalidad | Rol | Ruta UI | Endpoint | Controlador | Servicio | Modelo | Tabla | Estado | Actuado | Pruebas | Impl. | Observaciones |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Administrador | Dashboard administrativo (KPIs, semáforo) | ADMIN | `GET /administrador/dashboard` (middleware `EnsureAdmin`) | `GET /api/admin/dashboard` | `Administrador\AdminDashboardController@index` | `SemaforoPlazoService` | `Expediente`, `Plazo`, `Usuario`, `Rol`, `Feriado`, `SesionAcceso` | `expedientes`, `plazos`, `usuarios`, `feriados`, `sesiones_acceso` | — | No | Ninguna (sin test HTTP) | IMPLEMENTADA | API con check **inline** `abort(403)` si rol ≠ ADMIN en el controlador (no policy) → AUD-0017 |
| Administrador | Gestión de usuarios (listar/crear/editar) | ADMIN | `GET /administrador/usuarios` (`EnsureAdmin`) | `GET/POST /api/admin/usuarios`, `PUT /api/admin/usuarios/{u}` | `Administrador\AdminUsuariosController` | `SeguridadSesionesService` | `Usuario` | `usuarios` | — | No | Ninguna (sin test HTTP) | IMPLEMENTADA | `UsuarioPolicy::gestionar` (ADMIN) en controller:33/74/101; Form Requests `authorize()=true` (la policy manda) |
| Administrador | Activar usuario | ADMIN | — | `POST /api/admin/usuarios/{u}/activar` | `AdminUsuariosController@activar` | `SeguridadSesionesService` | `Usuario` | `usuarios` | — | No | Ninguna (sin test HTTP) | IMPLEMENTADA | `UsuarioPolicy::activar` (ADMIN) |
| Administrador | Inactivar usuario (vía duplicada) | ADMIN | — | `POST /api/usuarios/{u}/inactivar` **y** `POST /api/admin/usuarios/{u}/inactivar` | `UsuarioController@inactivar` | `SeguridadSesionesService` | `Usuario` | `usuarios`, `sesiones_acceso` | — | No | `SeguridadSesionesTest` (solo 1 de las 2 URIs) | IMPLEMENTADA | Dos URIs al mismo método → AUD-0004 |
| Administrador | CRUD de feriados | ADMIN | `GET /administrador/feriados` (`EnsureAdmin`) | `GET/POST /api/admin/feriados`, `PUT/DELETE /api/admin/feriados/{f}` | `Administrador\AdminFeriadosController` | — | `Feriado` | `feriados` | — | No | Solo unit de `FeriadoPolicy` (sin test HTTP) | IMPLEMENTADA | `FeriadoPolicy::gestionar` (ADMIN) en controller:19/65/84/101 |
| Administrador | Monitoreo de expedientes (soporte) | ADMIN | `GET /administrador/monitoreo` (`EnsureAdmin`) | `GET /api/admin/monitoreo` | `Administrador\AdminMonitoreoController@index` | `SemaforoPlazoService` | `Expediente`, `Asignacion`, `Rol` | `expedientes`, `asignaciones` | — | No | Ninguna (sin test HTTP) | IMPLEMENTADA | Check inline `abort(403)` (controller:27) → AUD-0017 |
| Administrador | Suspensiones de plazo (parámetros) | ADMIN | — | **sin endpoint** | — | — | `SuspensionPlazo` | `suspensiones_plazo` | — | No | — | FALTANTE | Tabla existe, sin CRUD → AUD-0012 |
| Administrador | Administración de catálogos/parámetros | ADMIN | — | **sin endpoint** | — | — | — | `catalogo_*`, `parametros_plazo` | — | No | — | FALTANTE | → AUD-0013 |

### 2.3 Encargada / Jefatura

| Módulo | Funcionalidad | Rol | Ruta UI | Endpoint | Controlador | Servicio | Modelo | Tabla | Estado | Actuado | Pruebas | Impl. | Observaciones |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Encargada | Bandeja de sorteo | ENCARGADA | `GET /bandeja/sorteo` (`expedientes/bandeja-sorteo.blade.php`) | `GET /api/bandeja/sorteo` | `WorkstationController@bandejaSorteo` / `ExpedienteController@bandejaSorteo` | — | `Expediente` | `expedientes` | `PENDIENTE_SORTEO` | No | `SorteoTodosTest`, `WebWorkstationRoutesTest` | IMPLEMENTADA | `ExpedientePolicy::bandejaSorteo` (solo ENCARGADA activa) |
| Encargada | Sorteo individual | ENCARGADA | — | `POST /api/expedientes/{e}/sortear` | `ExpedienteController@sortear` | `ExpedienteService@ejecutarSorteo` + `SorteoAlgorithmService` | `Expediente`, `SorteoPeso`, `Usuario` | `expedientes`, `sorteo_pesos`, `asignaciones` | exige `PENDIENTE_SORTEO` (`ExpedienteService:116`) | Sí (`registerActuado` ExpedienteService:125) | `ExpedienteControllerTest`, `SorteoAlgorithmTest` | IMPLEMENTADA | Concurrencia cubierta por `StressConcurrenciaTest` |
| Encargada | Sorteo masivo (todos los pendientes) | ENCARGADA | — | `POST /api/bandeja/sorteo/todos` | `ExpedienteController@sortearTodos` | `ExpedienteService@sortearTodas` + `SorteoAlgorithmService` | `Expediente`, `SorteoPeso` | `expedientes`, `sorteo_pesos` | exige `PENDIENTE_SORTEO` (`:154-156`) | Sí | `SorteoTodosTest` | IMPLEMENTADA | `SortearTodosRequest` = ENCARGADA |
| Encargada | Dashboard de la Encargada | ENCARGADA | `GET /encargada/dashboard` (`encargada/dashboard.blade.php`) | `GET /api/encargada/dashboard` | `Encargada\DashboardController@index` / `EncargadaDashboardController@index` | `EncargadaDashboardService` | `Expediente`, `Asignacion`, `CatalogoEstado`, `Feriado` | `expedientes`, `asignaciones`, `catalogo_estados`, `feriados` | — | No | `EncargadaDashboardTest` — **5 errores (AUD-0003)** | IMPLEMENTADA (tests rotos) | Autoriza `bandejaSorteo` |
| Encargada | Catálogo de usuarios operativos (para sorteo) | ENCARGADA, ADMIN | — | `GET /api/usuarios` | `UsuarioController@indexOperativos` | — | `Usuario`, `Rol` | `usuarios`, `roles` | — | No | `UsuarioCatalogoTest`, `SeguridadSesionesTest` | IMPLEMENTADA | `UsuarioPolicy::viewOperativos` (ENCARGADA+ADMIN; versión anterior solo ENCARGADA está comentada en la policy) |
| Encargada | Visto bueno a planificación | ENCARGADA | — | `POST /api/expedientes/{e}/planificacion/visto-bueno` | `PlanificacionController@vistoBueno` | `PlanificacionService` | `Expediente`, `CatalogoActuado` | `expedientes`, `actuados` | exige `PENDIENTE_VISTO_BUENO` (policy:210) | Sí (`PlanificacionService:79`) | `PlanificacionTest` | IMPLEMENTADA | `VistoBuenoPlanificacionRequest` |
| Encargada | Devolución de planificación con observaciones | ENCARGADA | — | `POST /api/expedientes/{e}/planificacion/devolver` | `PlanificacionController@devolver` | `PlanificacionService` | `Expediente`, `CatalogoActuado` | `expedientes`, `actuados`, `plazos` | exige `PENDIENTE_VISTO_BUENO` (policy:221) | Sí (`:113`) | `PlanificacionTest` | IMPLEMENTADA | Reabre plazo (2 días hábiles) según catálogo |
| Encargada | Aprobación de ampliación | ENCARGADA | — | `POST /api/expedientes/{e}/ampliacion/aprobar` | `AmpliacionController@aprobar` | `AmpliacionService` | `Expediente`, `Plazo`, `CatalogoActuado` | `expedientes`, `plazos`, `actuados` | exige `PENDIENTE_APROBACION_AMPLIACION` (policy:259) | Sí (`AmpliacionService:113`) | `AmpliacionTest` | IMPLEMENTADA | — |
| Encargada | Impugnación: resolver (ratifica/revoca) | ENCARGADA | — | `POST /api/expedientes/{e}/impugnacion/resolver` | `ImpugnacionController@resolver` | `ImpugnacionService` | `Expediente`, `Impugnacion`, `CatalogoActuado` | `impugnaciones`, `expedientes`, `actuados` | exige `EN_IMPUGNACION` (policy:160) | Sí (`ImpugnacionService:113/142`) | `ImpugnacionRechazoTest` | IMPLEMENTADA | RN-08 |
| Encargada | NUREJ Hijo (derivación) | ENCARGADA | — | `POST /api/expedientes/{e}/nurej-hijo` | `ExpedienteController@derivarNurejHijo` | `NurejHijoService` + `NurejGeneratorService` | `Expediente`, `Parte`, `NurejSequence` | `expedientes`, `partes`, `nurej_sequences` | — (padre conserva estado) | Sí (`NurejHijoService:58`) | `NurejHijoTest`, `NurejGeneratorServiceTest` | IMPLEMENTADA | RN-10 |
| Encargada | Remisión a Transparencia | ENCARGADA | — | `POST /api/expedientes/{e}/transparencia/remitir` | `TransparenciaController@remitir` | `TransparenciaService` | `Expediente`, `Transferencia`, `CatalogoActuado` | `expedientes`, `transferencias`, `actuados` | exige `PENDIENTE_REMISION_TRANSPARENCIA` (policy:326) | Sí (`TransparenciaService:90`) | `DerivacionTransparenciaTest` | IMPLEMENTADA | E5-S5 |
| Encargada | Visto bueno final de cierre | ENCARGADA | — | `POST /api/expedientes/{e}/cierre/visto-bueno` | `CierreExpedienteController@aprobarVistoBueno` | `CierreExpedienteService` | `Expediente`, `CatalogoActuado` | `expedientes`, `actuados` | exige `PENDIENTE_VISTO_BUENO_FINAL` (policy:280) | Sí (`CierreExpedienteService:58`) | `CierreExpedienteTest` | IMPLEMENTADA | E10-S1 |
| Encargada | Reparto institucional (cierre) | ENCARGADA | — | `POST /api/expedientes/{e}/cierre/reparto` | `CierreExpedienteController@ejecutarReparto` | `CierreExpedienteService` | `Expediente`, `CatalogoActuado` | `expedientes`, `actuados` | exige `LISTO_PARA_REPARTO` (policy:291) | Sí (`:92`) | `CierreExpedienteTest` | IMPLEMENTADA | E10-S2 |

### 2.4 Operadores (Técnico · Auditor Jurídico · Auditor Financiero)

| Módulo | Funcionalidad | Rol | Ruta UI | Endpoint | Controlador | Servicio | Modelo | Tabla | Estado | Actuado | Pruebas | Impl. | Observaciones |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Operadores | Apertura de causa (NUREJ Padre) | TECNICO | `GET /expedientes/nuevo` (`expedientes/apertura.blade.php`) | `POST /api/expedientes` | `ExpedienteController@store` | `ExpedienteService@aperturaCausa` + `NurejGeneratorService` | `Expediente`, `Parte`, `NurejSequence` | `expedientes`, `partes`, `nurej_sequences` | crea en `PENDIENTE_SORTEO` (`ExpedienteService:46`) | Sí (`:60`) | `ExpedienteControllerTest`, `ExpedienteResourceTest` | IMPLEMENTADA | `StoreExpedienteRequest` = solo `CODIGO_TECNICO` |
| Operadores | Bandeja del operador | TECNICO, AUD_JURIDICO, AUD_FINANCIERO | `GET /expedientes` (`expedientes/bandeja-operador.blade.php`) | `GET /api/bandeja` | `WorkstationController@bandejaOperador` / `ExpedienteController@bandejaOperador` | — | `Expediente`, `Asignacion` | `expedientes`, `asignaciones` | — | No | `WebWorkstationRoutesTest`, `SemaforoPlazosFeatureTest` | IMPLEMENTADA | `operadorBandeja` + filtro ownership `asignacionActiva.usuario_id` |
| Operadores | Detalle del expediente | ver política `view` | `GET /expedientes/{e}` (`expedientes/detalle.blade.php`) | `GET /api/expedientes/{e}` | `WorkstationController@detalle` / `ExpedienteController@show` | — | `Expediente` | `expedientes` | — | No | `SecurityCompartimentosTest` — **1 fallo (AUD-0001)**, `DetalleExpedienteFeatureTest` | IMPLEMENTADA (brecha de seguridad abierta) | ENCARGADA: sí; asignado: sí; **ADMIN: bypass `return true` (policy:80-82)**; creador hasta sorteo |
| Operadores | Checklist de requisitos del expediente | (política `view`; mismas excepciones) | — | `GET /api/expedientes/{e}/requisitos` | `EvaluacionAdmisibilidadController@requisitos` | — | `CatalogoRequisito` | `catalogo_requisitos` | — | No | `EvaluacionAdmisibilidadTest`, `ActuadoControllerTest` (indirecta) | IMPLEMENTADA | `authorize('view')` en controller:26 → hereda bypass ADMIN de AUD-0001 |
| Operadores | Evaluación de admisibilidad (RF-04) | solo operador **asignado** | — | `POST /api/expedientes/{e}/evaluacion` | `EvaluacionAdmisibilidadController@store` | `EvaluacionAdmisibilidadService` | `EvaluacionAdmisibilidad`, `CatalogoRequisito`, `CatalogoEstado` | `evaluaciones_admisibilidad`, `expedientes` | transiciona según motor (Admitir/Observar/Rechazar) | Sí (`:59`) | `EvaluacionAdmisibilidadTest`, unit `EvaluacionAdmisibilidadService` | IMPLEMENTADA | `evaluarAdmisibilidad` sin excepciones jerárquicas (policy:39-46) |
| Operadores | Carga de planificación (Cronograma AC022 / MPA AC054-055) | operador asignado cuyo rol corresponda al catálogo del reglamento | — | `POST /api/expedientes/{e}/planificacion` | `PlanificacionController@store` | `PlanificacionService` | `Expediente`, `CatalogoActuado`, `Plazo` | `expedientes`, `actuados`, `plazos` | exige `EN_PLANIFICACION` (policy:175) | Sí (`:79`) | `PlanificacionTest` | IMPLEMENTADA | — |
| Operadores | Solicitud de ampliación (+5 días) | TECNICO asignado (catálogo AC022) | — | `POST /api/expedientes/{e}/ampliacion` | `AmpliacionController@solicitar` | `AmpliacionService` | `Expediente`, `Plazo`, `CatalogoActuado` | `expedientes`, `plazos`, `actuados` | exige `EN_EJECUCION` (policy:235) | Sí (`:61`) | `AmpliacionTest` | IMPLEMENTADA | US-2.6 |
| Operadores | Impugnación de rechazo: remitir (RN-08) | operativo asignado | — | `POST /api/expedientes/{e}/impugnacion/remitir` | `ImpugnacionController@remitir` | `ImpugnacionService` | `Impugnacion`, `Expediente` | `impugnaciones`, `expedientes`, `actuados` | exige `RECHAZADO` (policy:149) | Sí (`:65`) | `ImpugnacionRechazoTest` | IMPLEMENTADA | — |
| Operadores | Derivación por incompetencia → Encargada (E5-S5) | operativo asignado (catálogo pivote) | — | `POST /api/expedientes/{e}/derivacion-transparencia` | `TransparenciaController@derivar` | `TransparenciaService` | `Expediente`, `Transferencia`, `CatalogoActuado` | `expedientes`, `transferencias`, `actuados` | — (congela relojes) | Sí (`:55`) | `DerivacionTransparenciaTest` | IMPLEMENTADA | RN-09 |
| Operadores | Registro de actuados (motor universal RF-02) | rol del catálogo del actuado + asignación activa; **ENCARGADA exenta de asignación** | — | `POST /api/expedientes/{e}/actuados` | `ActuadoController@store` | `ActuadoService@registerActuado` (DB::transaction) | `Actuado`, `CatalogoActuado`, `Expediente` | `actuados`, `catalogo_actuados` | transición según catálogo (origen/destino) | Sí (es el motor) | `ActuadoControllerTest`, `ActuadoResourceTest`, `CadenaCustodiaTest`, `CadenaHashConcurrenciaTest` | IMPLEMENTADA | Append-only por triggers MySQL; catálogo incompleto → AUD-0009 |
| Operadores | Descarga de adjuntos | política `view` (misma lógica que detalle) | — | `GET /api/adjuntos/{a}/descargar` | `AdjuntoController@descargar` | `AdjuntoService` | `Adjunto`, `Expediente` | `adjuntos` | — | No | `AdjuntoControllerTest`, `AdjuntoServiceTest` | IMPLEMENTADA | Hereda bypass ADMIN de AUD-0001 (SRS prohíbe a ADMIN ver contenido de archivos) |

### 2.5 Descargos financieros (Auditor Financiero, AC055)

| Módulo | Funcionalidad | Rol | Ruta UI | Endpoint | Controlador | Servicio | Modelo | Tabla | Estado | Actuado | Pruebas | Impl. | Observaciones |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Descargos | Comunicación de hallazgos (pausa reloj) | AUD_FINANCIERO asignado | — | `POST /api/expedientes/{e}/descargos/comunicar` | `DescargoFinancieroController@comunicar` | `DescargoFinancieroService` | `Expediente`, `Plazo`, `CatalogoActuado` | `expedientes`, `plazos`, `actuados` | exige `EN_EJECUCION` + AC055 (policy:362) | Sí (`:85`) | `DescargoFinancieroTest` ✅ (5/5; AUD-0002 CERRADO) | IMPLEMENTADA | Abre sub-reloj DESCARGOS 5 días hábiles (`:270-271`) |
| Descargos | Recepción de descargos (reanuda reloj) | AUD_FINANCIERO asignado | — | `POST /api/expedientes/{e}/descargos/recibir` | `DescargoFinancieroController@recibir` | `DescargoFinancieroService` | `Expediente`, `Plazo` | `expedientes`, `plazos`, `actuados` | exige `EN_EJECUCION` + AC055 | Sí (`:129`) | `DescargoFinancieroTest` | IMPLEMENTADA | Recalcula `fecha_limite` con días restantes (`:285-295`) |

### 2.6 Catálogos de lectura

| Módulo | Funcionalidad | Rol | Ruta UI | Endpoint | Controlador | Servicio | Modelo | Tabla | Estado | Actuado | Pruebas | Impl. | Observaciones |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Catálogos | Reglamentos | ENCARGADA, TECNICO, AUD_JURIDICO, AUD_FINANCIERO, ADMIN | — | `GET /api/reglamentos` | `ReglamentoController@index` | — | `Reglamento` | `reglamentos` | — | No | `ReglamentoControllerTest` | IMPLEMENTADA | `verCatalogoReglamentos` |
| Catálogos | Catálogo de actuados | mismo que reglamentos | — | `GET /api/catalogo/actuados` | `CatalogoActuadoController@index` | — | `CatalogoActuado` | `catalogo_actuados` | — | No | `CatalogoActuadoControllerTest` | IMPLEMENTADA | `verCatalogoActuados` |
| Catálogos | Catálogo de estados | mismo que reglamentos | — | `GET /api/estados` | `CatalogoEstadoController@index` | — | `CatalogoEstado` | `catalogo_estados` | — | No | `CatalogoEstadoControllerTest` | IMPLEMENTADA | `verCatalogoEstados` |
| Catálogos | Catálogo de requisitos por reglamento (E2-S2) | — | — | **sin endpoint** | — | — | `CatalogoRequisito` | `catalogo_requisitos` | — | No | — | FALTANTE | Existe solo por expediente (fila 2.4); falta el endpoint global → AUD-0014 |

### 2.7 Motor de plazos y CRON (sin endpoint HTTP)

| Módulo | Funcionalidad | Rol | Ruta UI | Endpoint | Controlador | Servicio | Modelo | Tabla | Estado | Actuado | Pruebas | Impl. | Observaciones |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Plazos | Verificación diaria de vencimientos | Sistema (CRON `daily()`) | — | `php artisan plazos:verificar-vencidos` (`routes/console.php:11`) | — (command) | `MarcarPlazosVencidosService`, `PlazoCalculatorService` | `Plazo`, `Feriado` | `plazos`, `parametros_plazo`, `feriados` | plazos `VIGENTE`→`VENCIDO` | No | `RelojProcesualTest`, `PlazoCalculatorServiceTest` | IMPLEMENTADA | — |
| Plazos | Archivo por abandono (automático) | Sistema (disparado por vencimiento de subsanación) | — | — (servicio) | — | `ArchivoPorAbandonoService` | `Expediente`, `Plazo`, `CatalogoActuado` | `expedientes`, `plazos`, `actuados` | → `ARCHIVO_POR_ABANDONO` (RN-03) | Sí (`:67`, automático) | `ArchivoPorAbandonoTest` | IMPLEMENTADA | — |
| Plazos | Semáforo de plazos (UI) | roles operativos + Encargada + ADMIN | consumido por bandejas/dashboards | — (servicio) | — | `SemaforoPlazoService` | `Expediente`, `Plazo` | `expedientes`, `plazos` | — | No | `SemaforoPlazoServiceTest`, `SemaforoPlazosFeatureTest`, `SemaforoPenalizacionTest` | IMPLEMENTADA | — |
| Plazos | Cálculo de días hábiles (feriados + suspensiones) | Sistema | — | — (servicio) | — | `PlazoCalculatorService` | `Feriado`, `SuspensionPlazo` | `feriados`, `suspensiones_plazo` | — | No | `PlazoCalculatorServiceTest` | IMPLEMENTADA (auditada Fase 5) | AUD-0002 CERRADO (raíz Fase 5 + fix test 2026-09-30) |
| Plazos | Recálculo retroactivo de plazos abiertos al suspender | ADMIN | — | **sin endpoint** (sin CRUD de suspensiones) | — | — | `SuspensionPlazo` | `suspensiones_plazo` | — | No | — | FALTANTE | → AUD-0012 |

### 2.8 Funcionalidades del SRS sin endpoint (mapa funcional, no inventario de rutas)

| Módulo | Funcionalidad | Rol | Ruta UI | Endpoint | Controlador | Servicio | Modelo | Tabla | Estado | Actuado | Pruebas | Impl. | Observaciones |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Reportes | RF-R01…RF-R09, Carátula PDF, exportaciones | (per rol, por definir en Fase 12) | — | **sin endpoint** | — | — | — | — | — | No | — | FALTANTE | Grep de rutas/controllers sin resultados → AUD-0011 |
| Gobierno de casos | Excusas y recusaciones (Épica 6) | — | — | **sin endpoint** | — | — | — | — | — | No | — | FALTANTE | Sin tabla/modelo/ruta → AUD-0015 |
| Gobierno de casos | Actuado de Enmienda (RF-02) | (por definir) | — | endpoint genérico existe, **pero sin código de actuado "Enmienda"** en `catalogo_actuados` | `ActuadoController@store` | `ActuadoService` | `Actuado` | `actuados`, `catalogo_actuados` | — | No | — | FALTANTE | Ni seeder ni migración lo definen → AUD-0009 |
| Gobierno de casos | Transferencia inter-unidad: Remisión (origen) y Recepción (destino) (RF-05) | — | — | **sin endpoints** (solo existe como salida a Transparencia, fila 2.3) | — | — | `Transferencia` (⚠️ archivo `Transferencia.Php`) | `transferencias` | — | No | — | PARCIAL | Tabla+modelo sin API → AUD-0010; archivo con mayúscula → AUD-0016 |
| Administrador | Informes finales (E5-S3) Técnico / Jurídico / Financiero | roles del catálogo | — | sin endpoint específico; **registrables vía endpoint genérico de actuados** si el catálogo los define | `ActuadoController@store` | `ActuadoService` | `CatalogoActuado`, `Actuado` | `catalogo_actuados`, `actuados` | — (EN_EJECUCION → …) | Sí | `ActuadoControllerTest` (genérico) | PARCIAL | Evidencia Fase 1: el seeder **sí** define `ACT_INFORME_FINAL` (AUD_JURIDICO) e informes financieros con/sin responsabilidad; **no** hay informe final para Técnico (AC022) ni flujo/vista específico → actualizar AUD-0008, cerrar en Fase 2 |

---

## 3. Controladores sin `$this->authorize()` directo (verificado)

La autorización de estos controladores está en el `authorize()` del Form Request correspondiente:

| Controlador | Mecanismo verificado |
| --- | --- |
| `ActuadoController` | `StoreActuadoRequest` → `crearActuado` |
| `AmpliacionController` | `SolicitarAmpliacionRequest` / `AprobarAmpliacionRequest` |
| `CierreExpedienteController` | `AprobarVistoBuenoRequest` / `RepartoInstitucionalRequest` |
| `DescargoFinancieroController` | `ComunicarHallazgosRequest` / `RecibirDescargosRequest` |
| `EvaluacionAdmisibilidadController@store` | `StoreEvaluacionAdmisibilidadRequest` |
| `ImpugnacionController` | `ImpugnacionRemitirRequest` / `ResolverImpugnacionRequest` |
| `PlanificacionController` | `Store/Devolver/VistoBueno` PlanificacionRequest |
| `TransparenciaController` | `DerivarTransparenciaRequest` / `RemitirTransparenciaRequest` |
| `ExpedienteController@derivarNurejHijo` | `DerivarNurejHijoRequest` |
| `ExpedienteController@bandejaOperador` | filtro por `asignacionActiva.usuario_id` |

**Casos sin policy ni Form Request (check inline en el controlador) — hallazgo nuevo:**

| Controlador | Mecanismo verificado |
| --- | --- |
| `Administrador\AdminDashboardController@index` | `abort(403)` si `rol !== ADMIN` en la línea 29 (no policy, no `EnsureAdmin`: la ruta API no lleva ese middleware) → **AUD-0017** |
| `Administrador\AdminMonitoreoController@index` | `abort(403)` si rol ≠ ADMIN o inactivo, en la línea 27 → **AUD-0017** |

Las rutas **web** `/administrador/*` sí están cubiertas por middleware `EnsureAdmin` (`bootstrap`/`routes/web.php:45`), que valida login, activo y rol ADMIN.

Quedan **pendientes de verificación en Fase 3**: que cada `authorize()` devuelva `false` en escenarios cruzados (IDOR) y que no haya endpoints sin ninguna de las dos vías.

---

## 4. Filas `NO VERIFICADA` (justificadas: fase que las cierra)

| Funcionalidad | Fase que la cierra |
| --- | --- |
| Efectividad real de cada `authorize()` (IDOR) | Fase 3 |
| Comportamiento exigido vs implementado para rol ADMIN (AUD-0001) | Fase 3 + decisión del usuario |
| Correctitud de fechas del motor de plazos (AUD-0002) | Fase 4 (según instrucción del usuario) |
| Máquina de estados completa y transiciones | Fase 4 |
| Correctitud de plazos (días hábiles, 022/54/55) | Fase 4/5 |
| Matriz de permisos por rol | Fase 6 |
| Flujos Técnico/Jurídico/Financiero extremo a extremo | Fases 7-9 |
| Consistencia dashboard vs SQL | Fases 10 y 12 |
| Cobertura HTTP de rutas `/administrador/*` y `/api/admin/*` (hoy: 0 tests que las referencien) | Fase 16 |
| Idempotencia de jobs | Fase 13 → **verificada** (`MATRIZ_JOBS_CRON.md` §2; AUD-0042/0043 abiertos) |
| UX y frontend | Fase 14 |
| Rendimiento/N+1/índices | Fase 15 |


