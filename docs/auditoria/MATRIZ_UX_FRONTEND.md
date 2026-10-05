# MATRIZ_UX_FRONTEND — Auditoría UX / Frontend (Fase 14)

> Auditoría según Plan Maestro §36 (Auditoría de frontend), §37 (Auditoría de
> UX operativa) y §70 (Frontend vs Backend), con alcance definido en
> `PLAN_EJECUCION_AUDITORIA.md` §3 ("F14 UX/frontend"). Evidencia verificada el
> 2026-10-01 contra el repositorio: rutas (`php artisan route:list`, 54 rutas),
> vistas Blade, controllers, policies y FormRequests. Toda afirmación lleva
> `archivo:línea`. **F14 NO modificó producto** (solo documentación de
> auditoría).

---

## §0 Estado de F14

| Ítem | Valor |
|---|---|
| Estado | **ENTREGADA — PENDIENTE DE VALIDACIÓN** |
| Fecha | 2026-10-01 |
| Artefacto | `docs/auditoria/MATRIZ_UX_FRONTEND.md` (este archivo) |
| Hallazgos nuevos | **18** (AUD-0044 … AUD-0061): 2 P1 · 2 P2 · 14 P3 |
| Observaciones | 9 (O-11 … O-19), NO elevadas a ficha |
| Cambios de producto | **NINGUNO** |
| Gates | ver §17 |

---

## §1 Alcance

- **Dentro:** UI → acción frontend → ruta → backend → autorización →
  validación → resultado → feedback; §36 (botones, roles, estados, errores,
  refresco), §37 (¿el usuario entiende qué hacer?), §70 (cadena completa por
  acción visible); verificación de los 4 ítems abiertos de F14
  (AUD-0033/AUD-0008, AUD-0034, RF-04, `/expedientes` para Encargada).
- **Fuera:** cualquier corrección de producto; rendimiento/índices (F15);
  hardening de secretos y rate limiting (F17); pruebas de navegador (no hay
  `pestphp/pest-plugin-browser` instalado — verificado en `package.json` y
  `composer.json`).

---

## §2 Metodología

1. Inventario real (rutas → controllers → policies → vistas), nada asumido.
2. Por cada vista: seguimiento línea por línea de handlers, llamadas a API,
   ramas de respuesta y estados condicionales de la UI.
3. Cada acción visible → cadena §70 completa, verificada en el backend.
4. Comparación UI ↔ policy/validación ↔ requisito (SRS `docs/auditoria/SRS_EXTRAIDO.txt`).
5. Hecho comprobado vs interpretación vs requisito vs decisión pendiente,
   marcados explícitamente en cada hallazgo.
6. Sin navegador en ejecución: la verificación es **estática** (código +
   `route:list` + lectura de backend). Lo no verificable se declara en §16.

---

## §3 Superficie auditada

**Vistas Blade (13) + layout + partial** (verificado con `Get-ChildItem resources\views -Recurse`):

| Archivo | Líneas |
|---|---|
| `resources/views/welcome.blade.php` | 215 |
| `resources/views/auth/login.blade.php` | 317 |
| `resources/views/layouts/app.blade.php` | 242 |
| `resources/views/partials/api-helper.blade.php` | 46 |
| `resources/views/encargada/dashboard.blade.php` | 230 |
| `resources/views/expedientes/apertura.blade.php` | 268 |
| `resources/views/expedientes/bandeja-operador.blade.php` | 157 |
| `resources/views/expedientes/bandeja-sorteo.blade.php` | 245 |
| `resources/views/expedientes/detalle.blade.php` | 373 |
| `resources/views/administrador/dashboard.blade.php` | 295 |
| `resources/views/administrador/usuarios.blade.php` | 316 |
| `resources/views/administrador/feriados.blade.php` | 603 |
| `resources/views/administrador/monitoreo.blade.php` | 367 |
| `resources/views/administrador/parametros.blade.php` | 219 |

Stack confirmado: **Blade + Alpine.js (CDN) + Tailwind (CDN `cdn.tailwindcss.com`
en `layouts/app.blade.php:8`) + Vite/`package.json`**; **sin Livewire, sin
Inertia, sin `pest-plugin-browser`** (verificado: no hay paquetes en
`composer.json`/`package.json`). Todas las vistas se comunican con el backend
vía `window.apiFetch` (`partials/api-helper.blade.php:4-44`): `Accept:
application/json`, XSRF por cookie, **401 → redirect a `/login` +
excepción** (`:38-41`), otros no-2xx devueltos como `{ok:false,status,data}`.

---

## §4 Inventario de vistas → rutas → backend → acciones

Rutas web (9 activas, `routes/web.php`; `route:list` = 54 rutas en total):

| # | Vista | Ruta web | Controller | Autorización (verificada) | Roles que acceden | Llamadas API (vista:línea) | Acciones visibles |
|---|---|---|---|---|---|---|---|
| 1 | `auth/login` | `GET /login` (`web.php:12-14`, sin auth) | closure | — | públicos | `GET /sanctum/csrf-cookie` (`login:310`), `POST /api/login` (`login:324`) | form login, mostrar/ocultar contraseña, **"¿Olvidaste tu contraseña?" `href="#"`** (`login:274`) |
| 2 | `layouts/app` (incluido en todas) | — | — | — | todos los autenticados | `GET /api/me` (`app:263`), `POST /api/logout` (`app:276`) | "Salir"; sidebar: `/expedientes` **sin gate de rol** (`:135-139`), Encargada (`:140-157`), Técnico (`:159-165`), Admin (`:167-215`) |
| 3 | `expedientes/apertura` | `GET /expedientes/nuevo` (`web.php:21`) | `WorkstationController@apertura` | `aperturaCausa` (`WorkstationController:47` → `ExpedientePolicy:92-95`, solo TECNICO activo) | TECNICO | `GET /api/reglamentos` (`apertura:182`), `POST /api/expedientes` (`apertura:256`) | form alta, partes (agregar/quitar), adjunto, submit, cancelar → `/expedientes` |
| 4 | `expedientes/bandeja-operador` | `GET /expedientes` (`web.php:19`) | `WorkstationController@bandejaOperador` | `operadorBandeja` (`:15` → `ExpedientePolicy:53-64`: TECNICO/AUD_JURIDICO/AUD_FINANCIERO activos) | operativos | `GET /api/bandeja?page=` (`:127`) | abrir tarjeta → `/expedientes/{id}`, Reintentar, paginación |
| 5 | `expedientes/bandeja-sorteo` | `GET /bandeja/sorteo` (`web.php:20`) | `WorkstationController@bandejaSorteo` | `bandejaSorteo` (`:25` → `ExpedientePolicy:48-51`, solo ENCARGADA activa) | ENCARGADA | `GET /api/bandeja/sorteo` (`:174`), `POST /api/expedientes/{id}/sortear` (`:206`), `POST /api/bandeja/sorteo/todos` (`:245`) | Sortear por tarjeta, modal de sorteo, "Sortear todo" (con `confirm`), Reintentar, paginación |
| 6 | `expedientes/detalle` | `GET /expedientes/{expediente}` (`web.php:22`) | `WorkstationController@detalle` | `view` (`:35` → `ExpedientePolicy:66-87`: ENCARGADA siempre; operativo con asignación activa; ADMIN (AUD-0001); creador pre-sorteo) | ENCARGADA, operativo asignado, ADMIN, creador pre-sorteo | `GET /api/expedientes/{id}` (`:279`), `GET /api/catalogo/actuados` (`:297`), `POST /api/expedientes/{id}/actuados` (`:340`), `GET /api/adjuntos/{id}/descargar` (`:185`) | "Emitir Actuado" (modal), Ver cadena de custodia, Ver PDF, navegación |
| 7 | `encargada/dashboard` | `GET /encargada/dashboard` (`web.php:35`) | `Encargada\DashboardController@index` | `bandejaSorteo` (`DashboardController:13`) | ENCARGADA | `GET /api/encargada/dashboard` (`:230`) | Actualizar; enlaces a `/expedientes/{id}` (`:40,161,174`). **Solo lectura: sin acciones POST** |
| 8 | `administrador/dashboard` | `GET /administrador/dashboard` (`web.php:50`) | `Administrador\DashboardController@index` | `auth` + `EnsureAdmin` (`web.php:45`; `EnsureAdmin:20-26`: ADMIN activo) | ADMIN | `GET /api/admin/dashboard` (`dashboard:307`) | Actualizar; enlaces a `/expedientes/{id}` (`:46,210`) |
| 9 | `administrador/usuarios` | `GET /administrador/usuarios` (`web.php:53`) | `Administrador\UsuariosController@index` | `EnsureAdmin` | ADMIN | `GET/POST/PUT /api/admin/usuarios`, `POST .../activar`, `POST .../inactivar` (`usuarios:243,299-302,339`) | Nuevo, Editar, Activar/Inactivar, filtros |
| 10 | `administrador/feriados` | `GET /administrador/feriados` (`web.php:56`) | `Administrador\FeriadosController@index` | `EnsureAdmin` | ADMIN | `GET/POST/PUT/DELETE /api/admin/feriados` (`feriados:613,782-784,870`) | Registrar, Editar, Eliminar (con `confirm`), filtros |
| 11 | `administrador/monitoreo` | `GET /administrador/monitoreo` (`web.php:59`) | `Administrador\MonitoreoController@index` | `EnsureAdmin` | ADMIN | `GET /api/admin/monitoreo` (`monitoreo:462`) | Filtros (búsqueda/estado/vía/…) y enlace a `/expedientes/{id}` (`:348`). **Solo lectura** |
| 12 | `administrador/parametros` | **SIN RUTA** (declaración comentada `web.php:64-86`, en particular `:78-81`; `grep view('administrador.parametros')` en `app/` = 0) | — | — | **inalcanzable** | **ninguna** (`grep apiFetch` = 0) | "Guardar parámetros" (`parametros:238`) |
| 13 | `welcome` | **SIN RUTA** (no hay `GET /` en `route:list`; 0 referencias a `welcome` en rutas/app/tests) | — | — | **inalcanzable** | ninguna | enlaces a `/dashboard` (`welcome:26`, ruta inexistente) y `route('login')` |

**Endpoint API sin uso en ninguna vista:** `GET /api/estados`
(`routes/api.php:47`) y `GET /api/usuarios` (`routes/api.php:37`) —
`grep "/api/estados"` y `"/api/usuarios"` en `resources/views` = 0 (O-15).

---

## §5 Matriz §36 — Auditoría de frontend

Convención: **A** botones sin acción · **B** rol equivocado · **C** estado
inválido · **D** errores sin mensaje · **E** datos que no refrescan.

| Vista | A | B | C | D | E |
|---|---|---|---|---|---|
| `auth/login` | **AUD-0048** (`href="#"`) | sin hallazgo | sin hallazgo | **AUD-0049** (429 en inglés sin `Retry-After`; respuesta no-JSON → mensaje técnico) | sin hallazgo |
| `layouts/app` | sin hallazgo (handlers existen) | **AUD-0044** (`/expedientes` sin gate) | sin hallazgo | **AUD-0057** (`cargarUsuario`/`cerrarSesion` en silencio) | sin hallazgo |
| `expedientes/apertura` | **AUD-0051** (plantilla `errorGral` inerte) | sin hallazgo (vista y backend = solo TECNICO) | N/A (alta sin estados) | sin hallazgo: ramas `ok/403/422/else/catch` cubiertas (`apertura:260-278`) | sin hallazgo (redirige al detalle, `:263-265`) |
| `expedientes/bandeja-operador` | sin hallazgo | sin hallazgo en UI | N/A | **O-11** (mensaje genérico, sin `status` ni `data.message`, `:128-131`) | **AUD-0050** (`meta.total` nunca se asigna) |
| `expedientes/bandeja-sorteo` | sin hallazgo (handlers y endpoints verificados) | sin hallazgo (todo exige ENCARGADA en backend) | sin violación: backend revalida estado con `lockForUpdate` (`ExpedienteService:114-120`) y responde 422 mostrado en UI | **AUD-0054** (error individual solo dentro del modal) | **AUD-0055** (recarga a página fuera de rango) |
| `expedientes/detalle` | sin hallazgo (todo handler existe) | el catálogo se filtra por rol en servidor (`CatalogoActuadoController:31-54`) | catálogo **sin filtro de estado ni de reglamento** → ver §10 (AUD-0034) y relación con AUD-0020 | sin hallazgo: 403/422/genérico/`catch` cubiertos (`detalle:350-358`) | sin hallazgo: recarga expediente y catálogo tras éxito (`:348-349`) |
| `encargada/dashboard` | sin hallazgo | sin hallazgo | N/A (solo lectura) | sin hallazgo (banner `x-if="error"` + fallback `:231`) | **AUD-0053** (`datosListos` no se reinicia en refresco fallido) |
| `administrador/dashboard` | sin hallazgo | sin hallazgo (ruta + API con check inline, `AdminDashboardController:29-35`) | O-16 (botón "Actualizar" sin `:disabled`) | sin hallazgo (`dashboard:308-314`) | **AUD-0053** |
| `administrador/usuarios` | sin hallazgo | sin hallazgo | **AUD-0056** ("Inactivar" sobre la propia cuenta → 422 siempre) | sin hallazgo (422 inline + toast para el resto) | sin hallazgo (`await this.cargar()` tras guardar/cambiar estado) |
| `administrador/feriados` | sin hallazgo | sin hallazgo | O-12 (editar/eliminar feriados pasados permitido por backend y sin requisito que lo prohíba) | sin hallazgo | sin hallazgo (`await this.cargar()` tras guardar/eliminar) |
| `administrador/monitoreo` | sin hallazgo (O-17: handler `claseSemaforo` muerto, `:544`) | sin hallazgo (API exige ADMIN, `AdminMonitoreoController:31`) | N/A | **AUD-0045** (`error` y `cargando` nunca se renderizan) | **AUD-0046** ("Actualización automática" sin mecanismo) |
| `administrador/parametros` | **AUD-0047** (vista inalcanzable + "Guardar" que informa éxito sin persistir) | no verificable (sin ruta) | O-18 (valor por defecto `dias_fuera_plazo: 0` vs `min="1"` propio) | sin hallazgo posible (sin request) | sin hallazgo posible (sin datos) |
| `welcome` | **AUD-0059** (código muerto + enlace `/dashboard` inexistente) | no aplicable | — | — | — |

**Patrón transversal verificado (no hallazgo):** todo `apiFetch` no-2xx
distinto de 401 resuelve con `ok=false`; las vistas que manejan errores lo hacen
por rama (`403`/`422`/`else`/`catch`). El 401 redirige a `/login`
(`api-helper:38-41`). Los mensajes del backend llegan en inglés en algunos
casos (429, `AuthorizationException`) → **AUD-0049**.

---

## §6 Matriz §37 — UX operativa por flujo

Preguntas del Plan §37 respondidas solo donde la pantalla corresponde; "**Sí**"
solo con evidencia.

| Flujo | ¿Qué expediente es? | ¿Estado? | ¿Plazo? | ¿Qué debo hacer? | ¿Qué pasará si actúo? | ¿Feedback final? | Evidencia |
|---|---|---|---|---|---|---|---|
| Apertura (TECNICO) | N/A (se crea) | N/A | N/A | **Sí** (form con campos obligatorios marcados) | **Sí** (texto "Se generará el NUREJ…", `apertura:152`) | **Sí** (toast + redirección) | `apertura:141-152,261-265` |
| Bandeja operador | **Sí** (NUREJ + estado + semáforo por tarjeta) | **Sí** (badge) | **Sí** (semáforo + "Fuera de plazo") | **Parcial** (abrir tarjeta) | N/A | **Parcial** (solo banner de error genérico) | `bandeja-operador:47-95` |
| Bandeja sorteo (ENCARGADA) | **Sí** (modal con NUREJ + reglamento) | **Sí** (badge) | No aplica | **Sí** (botones explícitos + confirm de lote) | **Sí** (`confirm` en "Sortear todo", `:241`) | **Parcial** → AUD-0054/0055 | `bandeja-sorteo:105-149` |
| Detalle de expediente | **Sí** (encabezado NUREJ, vía, ingreso, reglamento, asignado) | **Sí** (badge + transiciones estado_anterior→estado_nuevo en timeline, `:154-162`) | **Sí** (semáforo principal `:47-59` + tarjeta de plazos `:96-129` con fechas, días hábiles y color) | **Parcial**: solo "Emitir Actuado"; el resto de operaciones no tiene UI (AUD-0061) | **Parcial**: el modal muestra tipo/descripción/adjunto pero no "qué transición ocurrirá" (no se muestra el estado destino del catálogo) | **Sí** para actuados (toast + recarga); N/A para el resto | `detalle:22-59,96-137,340-349` |
| Dashboard Encargada | **Parcial** (listados con NUREJ) | — | **Sí** (semáforo y vencimientos) | **Parcial** (solo "Actualizar" + enlaces; sin acciones de VB/devolución → AUD-0028/AUD-0061) | N/A | **Parcial** (sin toast de éxito; solo banner de error) | `encargada/dashboard:17-40,151-193` |
| Login | N/A | N/A | N/A | **Sí** | N/A | **Parcial** → AUD-0049 | `login:211-224,337-339` |
| Admin (dashboard/usuarios/feriados/monitoreo) | **Sí** (monitoreo muestra NUREJ y estado) | **Sí** | — | **Sí** (botones rotulados) | **Parcial** (`confirm` solo en eliminar feriado `feriados:856-858`) | **Parcial** → AUD-0045 (monitoreo) | ver §5 |

---

## §7 Matriz §70 — Frontend vs Backend por acción visible

Cadena: `UI → handler → ruta → controller → autorización → validación →
operación → respuesta → actualización UI → mensaje`.

| Acción visible | ¿UI real? | ¿Ruta? | ¿Backend? | ¿Autorizada? | ¿Validada? | ¿Estado validado? | ¿Errores manejados? | ¿UI interpreta? | ¿Refresca? | ¿Feedback? | Veredicto |
|---|---|---|---|---|---|---|---|---|---|---|---|
| Login (`login:227`) | Sí | `POST /api/login` (`api.php:26`) | `AuthController@login` | público + `throttle:login` | `LoginRequest` | N/A | Parcial | Parcial | N/A | Sí | **AUD-0049** |
| Cerrar sesión (`app:123`) | Sí | `POST /api/logout` (`api.php:31`) | `AuthController@logout` | sesión | N/A | N/A | **No** (catch vacío, `app:277-279`) | No | N/A | No | **AUD-0057** |
| Alta de expediente (`apertura:141`) | Sí | `POST /api/expedientes` (`api.php:52`) | `ExpedienteController@store` | `StoreExpedienteRequest:11-14` = TECNICO | `StoreExpedienteRequest` | N/A | Sí | Sí | Sí (redirige) | Sí | OK (pero límite de partes solo cliente → **AUD-0052**) |
| Abrir bandeja operador (`bandeja-operador:47`) | Sí | `GET /expedientes` | `WorkstationController@bandejaOperador` | `operadorBandeja` | N/A | N/A | Parcial | Parcial | N/A | Parcial | **O-11**; endpoint `GET /api/bandeja` sin `authorize` (ya documentado en `MATRIZ_SEGURIDAD.md:15`, F3) |
| Sortear (`bandeja-sorteo:80,144`) | Sí | `POST .../sortear` (`api.php:54`) | `ExpedienteController@sortear` | `SortearExpedienteRequest` = ENCARGADA | Sí | **Sí** (backend revalida con lock, `ExpedienteService:114-120`) | Sí (inline) | Sí | Sí | Sí | **AUD-0054/0055** |
| Sortear todos (`:19`) | Sí | `POST /api/bandeja/sorteo/todos` (`api.php:34`) | `ExpedienteController@sortearTodos` | `SortearTodosRequest` = ENCARGADA | Sí | Sí (422 si no hay pendientes, `ExpedienteService:161-165`) | Sí (banner) | Sí | Sí (`:248` → página 1) | Sí | OK |
| Ver detalle (`bandeja-operador:47`) | Sí | `GET /expedientes/{id}` | `WorkstationController@detalle` | `view` | N/A | N/A | Sí (`detalle:282-287`) | Sí | N/A | Sí | OK |
| Emitir actuado (`detalle:133,242`) | Sí | `POST .../actuados` (`api.php:55`) | `ActuadoController@store` | `StoreActuadoRequest` → `crearActuado` | Sí | **NO en UI** y **NO en backend** (AUD-0020: `estado_origen_id` sin validar) | Sí | Sí | Sí (`:348-349`) | Sí | Funciona, pero catálogo sin contexto de expediente → **AUD-0034**; sin filtro de estado → AUD-0020 |
| Ver cadena de custodia (`detalle:176`) | Sí | (cliente, datos ya cargados) | — | — | — | — | — | Sí | — | Sí | OK |
| Descargar adjunto (`detalle:185`) | Sí | `GET /api/adjuntos/{adjunto}/descargar` (`api.php:50`) | `AdjuntoController@descargar` | policy RF-03 | — | — | Parcial (target `_blank`; sin manejo de 403 visible en pestaña nueva) | Parcial | N/A | Parcial | O-19 (feedback de error en pestaña nueva no verificable estáticamente) |
| Actualizar dashboards (`dashboard:13`, `encargada:12`) | Sí | `GET /api/admin/dashboard`, `GET /api/encargada/dashboard` | controllers con check | `EnsureAdmin` / `bandejaSorteo` | — | — | Sí | Sí | Sí | **No** (sin toast de éxito; aceptado) | **AUD-0053** |
| Gestionar usuarios (`usuarios:107-117`) | Sí | `GET/POST/PUT/activar/inactivar` (`api.php:98-102`) | `AdminUsuariosController` | `EnsureAdmin` + policies | `Store/UpdateUsuarioRequest` | **NO en UI** (inactivar la propia cuenta → 422 del servidor) | Sí | Sí | Sí | Sí | **AUD-0056** |
| Gestionar feriados (`feriados:293-317`) | Sí | `GET/POST/PUT/DELETE` (`api.php:105-108`) | `AdminFeriadosController` | `FeriadoPolicy` | `Store/UpdateFeriadoRequest` | **NO** (fecha pasada permitida; sin requisito que lo prohíba) | Sí | Sí | Sí | Sí | O-12 |
| Monitoreo (`monitoreo:73-348`) | Sí | `GET /api/admin/monitoreo` (`api.php:111`) | `AdminMonitoreoController` | `abort(403)` inline (AUD-0017) | filtros | N/A | **NO renderizado** | No | Solo con filtros | **No** | **AUD-0045/0046** |
| Guardar parámetros (`parametros:238`) | **No alcanzable** | **no existe** | **no existe** | — | — | — | — | informa éxito falso | — | **falso** | **AUD-0047** |
| **Operaciones sin UI:** `POST .../requisitos|evaluacion` (`api.php:58-59`), `impugnacion/*` (`:62-63`), `planificacion` + `visto-bueno` + `devolver` (`:66-68`), `ampliacion` + `aprobar` (`:71-72`), `nurej-hijo` (`:75`), `cierre/visto-bueno` + `cierre/reparto` (`:78-79`), `derivacion-transparencia` + `transparencia/remitir` (`:82-83`), `descargos/comunicar|recibir` (`:86-87`) | **No** (grep de esos términos en `resources/views` = 0) | Sí | Sí | policy-protegidos (verificados en fases 3/7/8/9) | Sí | Sí | — | — | — | — | **AUD-0060 (RF-04), AUD-0061; AUD-0035/0028/0039 ya existentes** |

---

## §8 Verificación AUD-0033 / AUD-0008 (y su par de interfaz AUD-0035)

**Pregunta:** ¿qué exigen RN-08/RN-09, existe UI, existe backend, y la ausencia
de UI es incumplimiento demostrable o decisión pendiente?

1. **Requisito (no interpretado, citado):**
   - `SRS_EXTRAIDO.txt:156` "RN-08: Impugnación del Rechazo Inicial";
     `:324` el rechazo "Habilita el módulo de Impugnación para el interesado";
     `:330-339` actos de remitir/resolver con impactos de estado.
   - `SRS_EXTRAIDO.txt:159` "RN-09: Emisión de Informe Final, Fase de
     Descargos y Salida"; `:161` descargos de 5 días "para que los auditados
     presenten descargos"; `:163` "Fase 3 (Filtro del Encargado): Emite
     Devolución por Observación o Visto Bueno".
2. **Backend:** **existe y está probado.** `POST
   /api/expedientes/{e}/impugnacion/remitir|resolver` (`routes/api.php:62-63`)
   → `ImpugnacionController` (`ImpugnacionService`); informe jurídico →
   `POST .../actuados` con `ACT_INFORME_FINAL` **pero** `estado_nuevo_id` NOT
   NULL vs destino `null` → 500 reproducido con test (**AUD-0033**,
   `FlujoJuridicoTest`); informes Técnico/Jurídico faltantes en catálogo
   (**AUD-0008**: SRS `:374-413` pide 4 Técnico / 2 Jurídico / 2 Financiero;
   catálogo real 0/1/2 — `CatalogoActuadoSeeder:62,82-83`).
3. **UI:** **no existe.** `grep -i 'impugnaci|informe_final|resolver'` sobre
   `resources/views/**/*.blade.php` = **0 coincidencias** (reproducido en F14;
   única acción del detalle = modal genérico `detalle.blade.php:133-135,207-244`).
   Emitir `ACT_REMITIR_IMPUGNACION` por el modal genérico **no** crea la fila
   en `impugnaciones` (`ImpugnacionService:83-89`) → `resolver` fallaría con
   `firstOrFail` (`:179-185`).
4. **Veredicto:**
   - **Hecho comprobado:** requisito existe; backend parcialmente existe;
     interfaz ausente; camino alternativo por modal genérico no produce el
     registro que la fase posterior exige.
   - **Decisión previa registrada (no reinterpretada):** AUD-0035 = P1 OPEN
     ("endpoints funcionales NO sustituyen la interfaz; diseñar la UI contra el
     flujo normativo; NO implementar todavía"); AUD-0033 = P1 OPEN ("NO hacer
     `estado_nuevo_id` nullable hasta completar el análisis del grafo");
     AUD-0008 = P1 OPEN (catálogo de informes incompleto).
   - **Conclusión de F14:** la ausencia de UI **no es un bug nuevo**: está
     documentada y decidida. F14 **no cambia** su estado; agrega la evidencia
     de que **tampoco existe UI para RN-09** (descargos/VB/devolución) →
     **AUD-0061**. Ninguna de las tres se cierra.

---

## §9 Verificación AUD-0034

| Punto | Evidencia verificada |
|---|---|
| Código de la vista | `resources/views/expedientes/detalle.blade.php:297` → `apiFetch('/api/catalogo/actuados')` **sin** `expediente_id` |
| Flujo de datos | `CatalogoActuadoController@index:26-54` filtra por `es_automatico=false` + rol del usuario (pivote `catalogo_actuado_roles` o fallback `rol_id`); **el filtro por reglamento solo se aplica si llega `expediente_id`** (`:37-44`) — la vista no lo envía |
| Validación | `IndexCatalogoActuadosRequest:17-22` valida únicamente `estado_origen_id` (**no** `expediente_id`) |
| Backend de emisión | `ActuadoController@store` → `StoreActuadoRequest` → `ExpedientePolicy::crearActuado` (`:17-33`) incluye `perteneceAlRolConReglamento` (`CatalogoActuado:56-72`) → **403** si el actuado es de otro reglamento |
| ¿Datos incorrectos? | **No hay fuga de datos**: la respuesta está filtrada por rol y el endpoint no devuelve datos de expedientes ajenos (análisis de Fase 9, `FLUJO_FINANCIERO.md §4`). El problema es que **el modal ofrece acciones que serán rechazadas** (otro reglamento del mismo rol) y **acciones incompatibles con el estado actual** (tampoco se envía `estado_origen_id`) |
| ¿Requisito que obligue al filtro? | El docblock del propio controller lo promete: "evitando ofrecer acciones que provocarían un 403" (`CatalogoActuadoController:17-19`). El SRS no tiene una frase específica sobre el filtro; **el respaldo es la consistencia interna + la decisión previa del usuario** |
| Decisión previa | Backlog AUD-0034: P2, "problema funcional, no de seguridad; fix autorizado conceptualmente (catálogo contextualizado al expediente/reglamento + validación en servidor); NO aplicar mientras se cruce con AUD-0033/0020/0021" |

**Conclusión F14:** AUD-0034 **confirmada y ampliada con evidencia nueva**: la
vista tampoco envía `estado_origen_id` (el endpoint lo soporta:
`CatalogoActuadoController:49-51`), por lo que el modal muestra actuados
incompatibles con el estado actual — esto último ya estaba registrado como
**AUD-0020** (backend no valida estado origen). **Sin cambio de estado de la
ficha; sin corrección.**

---

## §10 Verificación RF-04

| Punto | Evidencia |
|---|---|
| Definición real | `SRS_EXTRAIDO.txt:109`: "RF-04 Evaluación Dinámica de Requisitos: El profesional asignado (Técnico, Auditor Jurídico o Financiero) **podrá seleccionar** desde un catálogo dinámico y parametrizable qué requisitos de admisibilidad se cumplen o faltan según su reglamento, conservando el sistema un registro histórico inmutable de estas evaluaciones." |
| Backend | **Existe y está probado:** `GET /api/expedientes/{e}/requisitos` (`routes/api.php:58` → `EvaluacionAdmisibilidadController@requisitos:23-33`, policy `view`); `POST /api/expedientes/{e}/evaluacion` (`:59` → `@store:44-61`, `StoreEvaluacionAdmisibilidadRequest` → `evaluarAdmisibilidad`); servicio `EvaluacionAdmisibilidadService`; tests `EvaluacionAdmisibilidadTest` (+ unit). |
| Datos | Tablas `catalogo_requisitos` y `evaluaciones_admisibilidad` existentes (migraciones/seeders referenciados por el servicio). |
| UI | **No existe.** `grep -i 'evaluacion|requisito'` sobre `resources/views/**/*.blade.php` → solo coincidencias ajenas (`detalle.blade.php:218,320` = `verificarRequisitoAdjunto` del modal de actuados). Ninguna vista llama a `/requisitos` ni a `/evaluacion`. |
| ¿Incumple el requisito? | **Hecho comprobado:** RF-04 describe una capacidad **del profesional asignado** ("podrá seleccionar"). Sin interfaz, esa capacidad **no es ejecutable por el usuario** (solo vía HTTP directo con sesión válida). El backend, en cambio, **sí implementa** el requisito. Estado previo en `MATRIZ_SRS_IMPLEMENTACION.md:29` = "IMPLEMENTADA (FE pendiente: sin UI de checklist)" con acción explícita "**UI en Fase 14**". |
| ¿Es bug o falta de alcance? | Es una **falta de interfaz verificada**, análoga al criterio ya adoptado por el usuario para AUD-0035 ("los endpoints funcionales NO sustituyen la interfaz"). **No se inventa requisito nuevo:** se cita RF-04. |
| Hallazgo nuevo | **AUD-0060 (P1)** — RF-04 sin interfaz. Relacionado con **AUD-0014** (endpoint global de catálogo de requisitos, distinto) y con AUD-0061. |

---

## §11 Verificación `/expedientes` para la Encargada

| Punto | Evidencia |
|---|---|
| Ruta | `routes/web.php:19` → `WorkstationController@bandejaOperador` → `$this->authorize('operadorBandeja', Expediente::class)` (`WorkstationController.php:15`) |
| Policy | `ExpedientePolicy:53-64` → solo `TECNICO`, `AUD_JURIDICO`, `AUD_FINANCIERO` activos. **ENCARGADA y ADMIN quedan excluidos.** |
| Navegación | El sidebar muestra el enlace **"Bandeja de entrada" a todos los roles**: `layouts/app.blade.php:135-139` no tiene `template x-if` (los demás links sí: Encargada `:140`, Técnico `:159`, Admin `:167`) |
| Comportamiento real | ENCARGADA/ADMIN ven el enlace y reciben **403** (no hay `resources/views/errors/403.blade.php` → página de error genérica de Laravel) |
| Test | `tests/Feature/WebWorkstationRoutesTest.php:53-57` — "deniega 403 a la encargada en la bandeja operativa de /expedientes" |
| ¿Backend sin UI o UI sin backend? | **Backend protegido correctamente**; el problema es de **UI** (enlace visible para roles que la política rechaza) |
| Decisiones previas relacionadas | `MATRIZ_BANDEJAS.md:71,107` (documenta el 403 de la ruta); **AUD-0028** (P1, "sin bandeja de supervisión/acción para la Encargada"; decisión: diseñar la superficie desde los casos de uso). El enlace visible-ROTO no estaba registrado como ficha |
| Hallazgo nuevo | **AUD-0044 (P2)** — enlace visible para roles que reciben 403 |

---

## §12 Hallazgos nuevos (registrados en `BACKLOG_AUDITORIA.md`)

| ID | Sev. | § | Título | Evidencia clave |
|---|---|---|---|---|
| AUD-0044 | P2 | §36B | Sidebar "Bandeja de entrada" visible para ENCARGADA/ADMIN → 403 | `layouts/app.blade.php:135-139`; `WorkstationController:15`; `ExpedientePolicy:53-64`; `WebWorkstationRoutesTest:53-57` |
| AUD-0045 | P2 | §36D | Monitoreo: fallo de carga sin mensaje renderizado → "No se encontraron expedientes" (y sin indicador de carga) | `monitoreo:506-509` asigna `error`; 0 renders en el markup; `:369-375`; `cargando` sin `x-if` |
| AUD-0046 | P3 | §36E | Monitoreo: rótulo "Actualización automática" sin mecanismo de auto-refresco | `monitoreo:21-23`; `grep setInterval` en vistas admin = 0; carga solo en `:6` y filtros |
| AUD-0047 | P3 | §36A/§70 | Vista `administrador\parametros` inalcanzable y "Guardar" que informa éxito sin persistir | `web.php:64-86` (ruta comentada `:78-81`); `grep view('administrador.parametros')` = 0; `parametros:287-305` |
| AUD-0048 | P3 | §36A | Login: "¿Olvidaste tu contraseña?" con `href="#"` sin ruta ni endpoint | `login:274-278`; `route:list` sin rutas de recuperación |
| AUD-0049 | P3 | §36D | Login: errores sin adaptar (429 en inglés sin `Retry-After`; respuesta no-JSON → mensaje técnico crudo) | `login:335,338,372-374`; `AppServiceProvider:25` (`5/min`) |
| AUD-0050 | P3 | §36E | Bandeja operador: `meta.total` nunca asignado → contador siempre vacío | `bandeja-operador:16,119,133-139` vs `bandeja-sorteo:184` |
| AUD-0051 | P3 | §36A | Apertura: plantilla `errorGral` jamás activada (UI inerte) | `apertura:14-19,170,224` (solo asignaciones `null`) |
| AUD-0052 | P3 | §38/§70 | Apertura: límite de 10 partes solo en cliente (sin `max` en servidor; valor sin respaldo en SRS) | `apertura:71,198`; `StoreExpedienteRequest:25`; SRS sin mención |
| AUD-0053 | P3 | §36E | Dashboards admin y Encargada: refresco fallido conserva datos previos junto al banner de error | `administrador/dashboard:304-316`; `encargada/dashboard:226-235` (`datosListos` solo se pone en `true`) |
| AUD-0054 | P3 | §36D | Sorteo individual: el error queda invisible si el usuario cierra el modal durante la petición | `bandeja-sorteo:106,116,140` (cierre no deshabilitado) vs `:135-136,222-227` (error solo dentro del modal; sin `apiToast`) |
| AUD-0055 | P3 | §36E | Sorteo: recarga a página fuera de rango tras sortear el último ítem de la última página → estado vacío + contador > 0 | `bandeja-sorteo:219`; `LengthAwarePaginator` no recorta la página pedida |
| AUD-0056 | P3 | §36C | Usuarios: "Inactivar" visible sobre la propia cuenta → 422 siempre | `usuarios:110-113`; `SeguridadSesionesService:44-48` |
| AUD-0057 | P3 | §36D | Layout: `cargarUsuario()` y `cerrarSesion()` fallan en silencio | `layouts/app.blade.php:261-272,274-281` |
| AUD-0058 | P3 | §40/F17 | Salidas de consola con datos en vistas entregadas | `monitoreo:469` (`console.log` de la respuesta), `:500` (`console.error`), `feriados:645` |
| AUD-0059 | P3 | §36A | `welcome.blade.php` inalcanzable con enlace a `/dashboard` inexistente | `route:list` sin `GET /`; 0 referencias; `welcome:26` |
| AUD-0060 | P1 | §70/RF-04 | RF-04 sin interfaz: la evaluación de admisibilidad no es ejecutable desde la UI | `SRS:109`; `EvaluacionAdmisibilidadController:23-61`; grep UI = 0 |
| AUD-0061 | P1 | §70 | Operaciones con endpoints dedicados sin interfaz: planificación/VB/devolución, ampliación, cierre/reparto, transparencia, descargos (RN-04/05, RN-09, US-2.6) | `routes/api.php:58-87`; grep en `resources/views` = 0 |

---

## §13 Observaciones NO elevadas a hallazgo

| ID | Observación | Por qué no es ficha |
|---|---|---|
| O-11 | `bandeja-operador:128-131` no distingue `status` ni muestra `data.message` (mensaje genérico + Reintentar) | Hay mensaje visible; es calidad de mensaje, no ausencia de feedback |
| O-12 | Feriados pasados editables/eliminables (`feriados:293-317` + backend sin restricción temporal) | **No existe requisito que lo prohíba** (grep SRS sin prohibición) → no se inventa requisito |
| O-13 | Un ADMIN puede editarse a sí mismo (incluido su rol) vía `PUT /api/admin/usuarios/{id}` | No hay requisito que lo prohíba; puede ser intencional → **decisión pendiente** |
| O-14 | `apertura:223-241` sin guarda anti-doble envío en el mismo frame | No ejecutado en navegador; probabilidad baja |
| O-15 | `GET /api/estados` y `GET /api/usuarios` sin uso en ninguna vista | Funcionalidad sin consumidor; sin impacto de usuario |
| O-16 | Botón "Actualizar" del dashboard admin sin `:disabled` durante la carga | Permite cargas concurrentes sin efecto de datos |
| O-17 | `monitoreo:544` define `claseSemaforo` sin invocarlo (código muerto) | Sin impacto funcional |
| O-18 | `parametros:81` `min="1"` vs valor por defecto `dias_fuera_plazo: 0` | Vista inalcanzable (cubierto por AUD-0047) |
| O-19 | Descarga de adjunto en pestaña nueva sin manejo visible de 403 | No verificable estáticamente (§16) |

**Confirmaciones de hallazgos existentes (sin nueva ficha):**
- `GET /api/bandeja` sin `authorize` de rol → ya registrado en
  `MATRIZ_SEGURIDAD.md:15` (Fase 3: "filtro de ownership en consulta (sin 403)").
- CDNs públicos en el layout (`layouts/app.blade.php:8-10`) → **AUD-0006** (P2, abierto).
- Frontend sin paginación server-side real más allá de `?page=` y sin búsqueda
  global → relacionado con **AUD-0018** (RNF-03).

---

## §14 Hallazgos descartados / no reproducibles

| Sospecha | Veredicto | Evidencia |
|---|---|---|
| "Botón Emitir Actuado visible para roles sin permiso" | **Descartado** | El catálogo se filtra por rol en servidor (`CatalogoActuadoController:31-54`) y el botón se oculta si la lista está vacía (`detalle:133`); la emisión exige `crearActuado` (`StoreActuadoRequest`) |
| "Botón Emitir Actuado habilitado en estado inválido" | **No es nuevo** | El backend tampoco valida el estado de origen → ya registrado como **AUD-0020** (P1, decisión tomada) |
| "Sorteo permite ejecutar en estado incorrecto" | **Descartado** | El listado filtra `PENDIENTE_SORTEO` (`ExpedienteController:57-63`) y el servicio revalida con `lockForUpdate` respondiendo 422 (`ExpedienteService:114-120`) mostrado en la UI |
| "Acciones de la Encargada en el dashboard no funcionan" | **Descartado (no existen)** | `encargada/dashboard.blade.php` es 100% lectura: 0 llamadas POST en la vista |
| "Endpoints llamados por la UI que no existen" | **Descartado** | Correspondencia 1:1 de las 18 llamadas `apiFetch` de vistas contra `route:list` (54 rutas) |
| "Errores de validación sin mostrar `errors`" | **Descartado** | `apertura:269`, `usuarios:305-307`, `feriados:804-813`, `detalle:353` muestran `data.errors` |
| "Fallo nuevo de suite introducido por F14" | **Descartado** | F14 no modificó producto (solo `.md`); gates en §17 |

---

## §15 Decisiones pendientes (para el usuario)

1. **AUD-0060 / AUD-0061 (alcance de UI):** ¿se diseña la superficie de
   operaciones sin interfaz (RF-04, planificación, ampliación, cierre,
   transparencia, descargos) siguiendo el criterio ya adoptado en AUD-0035
   ("diseñar la UI contra el flujo normativo, no botones aislados")? — F14 no
   decide ni implementa.
2. **AUD-0047:** la vista de parámetros es código inalcanzable con un "Guardar"
   que informa éxito sin persistir. ¿eliminarla, habilitarla (requiere API +
   diseño) o mantenerla congelada hasta Fase de remediación?
3. **AUD-0044:** ¿se gatea el enlace por rol en el sidebar, o se habilita la
   ruta para ENCARGADA/ADMIN? (esta última opción tocaría autorización →
   requiere confirmación explícita).
4. **O-13:** ¿se prohíbe al ADMIN auto-editarse el rol?
5. **AUD-0052:** ¿el límite de 10 partes es una regla de negocio real? No está
   en el SRS; si lo es, debe validarse en servidor.

---

## §16 Limitaciones de la auditoría

1. **Sin ejecución en navegador:** no hay `pest-plugin-browser` ni navegador
   en el entorno; todo el análisis es estático (código + `route:list` +
   lectura de backend). **No verificable con la superficie disponible:**
   render real de Alpine/Tailwind, tiempos de respuesta, focus/teclado,
   comportamiento responsive, y el resultado visual de clases Tailwind
   conflictivas (O-17/AUD-0050 son deducciones del código, no capturas).
2. **`APP_DEBUG`/`.env` no leídos** (sensible): el escenario "500 en login
   muestra el mensaje crudo de la excepción" queda como **hipótesis de
   código**, no verificada en entorno.
3. **Sin ejecución de flujos con datos reales:** los estados "imposibles de
   ver" (p. ej. página fuera de rango, AUD-0055) se dedujeron del código +
   comportamiento del paginador de Laravel, no de una sesión en BD.
4. **Auditoría de estilo/consistencia visual (§36 "estilos, responsive")**: no
   evaluada por no ser comprobable estáticamente; queda fuera de F14.
5. Los informes de vistas `administrador/*`, `auth/login`, `welcome`,
   `encargada/dashboard` y `expedientes/{apertura,bandejas}` fueron producidos
   con revisión asistida y **verificados por muestreo** (más de 60 referencias
   `archivo:línea` re-consultadas en el repositorio antes de publicar cada
   hallazgo de §12).

---

## §17 Conclusión de F14 + Gates

**Conclusión factual:**

1. La superficie frontend real es de **13 vistas + 1 layout + 1 partial**;
   **2 vistas son inalcanzables** (`parametros`, `welcome`) y **1 enlace del
   sidebar falla con 403 para 2 de los 5 roles** (AUD-0047, AUD-0059, AUD-0044).
2. De las 18 llamadas `apiFetch` de las vistas, **todas corresponden a rutas
   existentes**; no se detectó ninguna llamada a endpoint inexistente.
3. La cadena §70 está completa y correcta para: apertura, sorteo (individual y
   lote), detalle/lectura, actuados genéricos, y las 4 pantallas admin con
   datos (usuarios, feriados, monitoreo, dashboard). Los fallos detectados son
   de **feedback** (AUD-0045, 0049, 0054, 0057) y de **consistencia de datos
   en pantalla** (AUD-0050, 0053, 0055), no de autorización.
4. **Ningún endpoint con política quedó sin protección en backend**; la
   autorización falla solo del lado de la navegación (AUD-0044).
5. **11 operaciones con endpoint dedicado no tienen interfaz** (AUD-0060,
   AUD-0061); las de ámbito jurídico ya estaban decididas (AUD-0033/0035/0008)
   y F14 no altera sus decisiones.
6. AUD-0034 **confirmada** y ampliada con la ausencia de `estado_origen_id` en
   la llamada de la vista (consistente con AUD-0020).

**Gates (ejecutados al cierre de F14):**

| Gate | Resultado |
|---|---|
| `vendor/bin/pint --dirty --format agent` | **OK** (sin PHP sucio: F14 no tocó código PHP) |
| `php artisan test --compact` | **296 tests: 289 OK · 1 fallo (AUD-0001, deuda aceptada desde F12) · 0 errores · 6 omitidos** — idéntico al baseline de F13; sin regresiones |

**Cambios de producto en F14: NINGUNO** (solo se creó este `.md` y se
actualizaron `BACKLOG_AUDITORIA.md`, `PROGRESO.md` y
`PLAN_EJECUCION_AUDITORIA.md`).
