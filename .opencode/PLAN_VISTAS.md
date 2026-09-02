# PLAN_VISTAS — Workstation `/expedientes` (Blade + Alpine.js + Tailwind CDN)

## Estado general
- **Paso 10** del `PLAN_WORKSTATION_EXPEDIENTES.md`. Documento de trabajo del frontend.
- Backend ya cerrado hasta Paso 9 (suite verde **96 passed / 322 assertions**).
- **FASE 1 CERRADA** (base técnica + auth stateful): Sanctum stateful habilitado, login por
  cookies + redirect por rol, layout + apiFetch + rutas web protegidas. Siguiente: **FASE 2**.

## Decisiones técnicas aprobadas (ver también `README.md` raíz)
1. **Autenticación stateful con cookies HttpOnly + SameSite + CSRF.** NO token Bearer en
   `localStorage` (riesgo de exfiltración por XSS en un sistema de fiscalización/auditoría).
2. **Reutilización al 100% de los endpoints `/api/*`**, FormRequests y Policies existentes
   (sin duplicar lógica de negocio). Se habilita el modo SPA stateful de Sanctum
   (`EnsureFrontendRequestsAreStateful`).
3. **Alpine.js** dispara las peticiones asíncronas con `credentials: 'same-origin'` y el
   header `X-XSRF-TOKEN` (proveniente de la cookie `XSRF-TOKEN`).
4. **Endpoints de catálogos** (usuarios operativos, reglamentos, catálogo de actuados, estados):
   se crean **por fase** solo cuando una pantalla los necesita, con FormRequest/policy y test.
5. **Cierre de sesión** con cookie de sesión (logout stateful), no revocación de token Bearer.
6. `/expediente-demo` era una ruta provisional: tras login se redirige a la **bandeja de
   entrada según rol** (ENCARGADA → sorteo, resto → operador).

## Stack
- Blade (Laravel) + Alpine.js 3.x + Tailwind CSS vía CDN + Font Awesome (mismo patrón que
  `resources/views/auth/login.blade.php`).
- Sin `localStorage` para secretos. Sesión vía cookie HttpOnly.
- Si un cambio frontend no se refleja: `php artisan serve` y revisar consola (sin JS errors).

---

## FASE 1 — Base técnica + autenticación stateful (precedencia máxima)
Sin esto ninguna vista funciona. Orden estricto.

### 1.1 Habilitar SPA stateful de Sanctum (backend)
- **Archivos:** `bootstrap/app.php` (middleware), `routes/api.php`, `config/sanctum.php` (ya ok).
- **Cambio:** middleware `EnsureFrontendRequestsAreStateful` en el grupo `api`.
- **Dependencias:** ninguna.
- **Roles:** n/a (infraestructura).
- **Riesgo:** toca autenticación → **aprobado**. Respaldo con tests de auth adaptados a cookies.
- **Verificación:** `php artisan route:list` y suite verde.

### 1.2 Login stateful (cookies) + redirect por rol
- **Vista:** reutilizar `auth/login.blade.php` (ya Alpine).
- **Controlador:** adaptar `AuthController::login` para sesión/CSRF manteniendo auditoría
  `sesiones_acceso`, rate limit y guard de inactivos. Logout stateful (cookie de sesión).
- **Cambios clave en la vista:** `fetch` con `credentials: 'same-origin'`, lectura de cookie
  `XSRF-TOKEN`, **eliminar** `localStorage.setItem('auth_token'...)`. Tras éxito redirige por
  rol: ENCARGADA → `/bandeja/sorteo`, resto → `/expedientes`.
- **Dependencias:** 1.1.
- **Roles:** todos.
- **Interactividad Alpine:** `loginForm()` con CSRF cookie, manejo 401/403/429, redirección por rol.

### 1.3 Layout base + helper `apiFetch` + rutas web de entrada
- **Vistas:** `resources/views/layouts/app.blade.php`, `resources/views/partials/api-helper.blade.php`.
- **Rutas web (`routes/web.php`):** rutas de la workstation protegidas por sesión (guard `web`
  / middleware de la estación), redirigen a `/login` si no hay sesión.
- **Dependencias:** 1.1, 1.2.
- **Roles:** todos.
- **Interactividad Alpine:** navbar con usuario+rol, logout, toggle sidebar, sistema de toasts
  (401 → `/login`, 403 → toast de acceso denegado, 422 → errores de validación).

**Cierre de Fase 1:** `php artisan test --compact` verde (**96/322**) con auth adaptado a cookies.
- **Hecho:** `bootstrap/app.php` `statefulApi()`; `AuthController::login` inicia sesión web
  (web guard) solo si hay sesión + mantiene token para clientes API; `logout` soporta session y
  token; `login.blade.php` usa CSRF-cookie + redirige por rol; `layouts/app.blade.php` +
  `partials/api-helper.blade.php` + 3 rutas web protegidas; `WebWorkstationRoutesTest` (4 tests).

---

## FASE 2 — Bandejas de entrada según rol (pantallas raíz)
- **Vistas:** `resources/views/expedientes/bandeja-sorteo.blade.php` (ENCARGADA),
  `resources/views/expedientes/bandeja-operador.blade.php` (técnico/auditor).
- **Endpoints:** `GET /api/bandeja/sorteo`, `GET /api/bandeja` (paginados: `data.data`, `data.meta`);
  sorteo: `POST /api/expedientes/{id}/sortear`.
- **Dependencias:** 1.3, catálogo de usuarios operativos (crear en esta fase).
- **Roles:** ENCARGADA (sorteo), técnico/auditores (operador). 403 ajenos.
- **Interactividad Alpine:** listas + paginación (`?page=`), badges `sem_plazo` por
  `codigo_color`, **modal de sorteo** (select de operador destino → `POST .../sortear`),
  refresh tras sortear. Bandeja operador: tarjetas con semáforo, click → detalle.

## FASE 3 — Detalle de expediente `/expedientes/{id}` (pantalla central)
- **Vista:** `resources/views/expedientes/detalle.blade.php`.
- **Endpoints:** `GET /api/expedientes/{id}` (RF-03), `POST /api/expedientes/{id}/actuados`,
  catálogo de actuados del rol (crear en esta fase).
- **Dependencias:** Fase 2, 1.3.
- **Roles:** operador asignado + ENCARGADA. 403 ajenos.
- **Interactividad Alpine:** bloques/tabs (resumen/partes/actuados/plazos); **cadena de custodia**
  expandible (hashes SHA-256 encadenados); **semáforo por plazo** (badges VERDE/AMARILLO/ROJO/
  FUERA_DE_PLAZO, tooltip días restantes); **modal "Emitir actuado"** (catálogo del rol, descripción,
  adjunto con `FormData`) → `POST` → refresh de la cadena; toasts de éxito/error (422 campo a campo).

## FASE 4 — Apertura de causa `/expedientes/nuevo` (rol TECNICO)
- **Vista:** `resources/views/expedientes/apertura.blade.php`.
- **Endpoints:** `POST /api/expedientes` (adjunto obligatorio), `GET /api/reglamentos` (crear).
- **Dependencias:** 1.3, Fase 2, catálogo de reglamentos (crear).
- **Roles:** solo TECNICO.
- **Interactividad Alpine:** lista dinámica de **partes** (agregar/quitar filas), **subida de
  adjunto** obligatorio (validación tipo/tamaño, vista previa del nombre), envío `FormData`,
  422 campo a campo, éxito → redirige al detalle del nuevo expediente.

## FASE 5 — Endpoints de catálogos (de lectura, autenticados) — incremental
- Se crean cuando una fase los pide (con test y policy):
  `GET /api/usuarios?rol=operativo` (destino sorteos), `GET /api/reglamentos`,
  `GET /api/catalogo/actuados` (catálogo del rol), `GET /api/estados` (filtros).

---

## Orden cronológico de ejecución
| Fase | Entregable | Precedencia | Rol |
|---|---|---|---|
| 1.1 | Enable Sanctum stateful (backend) | — | — |
| 1.2 | Login stateful + redirect por rol | 1.1 | todos |
| 1.3 | Layout + apiFetch + rutas web entrada | 1.1,1.2 | todos |
| 2 | Bandejas entrada + modal sorteo | 1.3, catálogos | ENCARGADA/TÉCNICO-AUD |
| 3 | Detalle + emitir actuado + custodia + semáforo | 2, catálogo | operador+ENCARGADA |
| 4 | Apertura de causa (partes + adjunto) | 1.3,2, catálogo | TÉCNICO |
| 5 | Endpoints catálogos (incremental) | según fase | — |

## Verificación por fase
- Revisar en navegador con `php artisan serve` (sin JS errors en consola).
- Al final de cada fase con backend PHP: `php artisan test --compact` verde + `vendor/bin/pint`.
