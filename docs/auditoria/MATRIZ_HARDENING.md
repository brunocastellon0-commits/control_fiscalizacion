# Matriz de hardening — secretos, debug, rate limiting y exposición de datos (Fase 17)

- **Fecha:** 2026-10-02
- **Estado:** **VALIDADA (2026-10-02)** — validación de consistencia y alcance sin
  re-auditoría; 4 correcciones solo documentales aplicadas al cierre (citas al PLAN
  `:83`→`:84` y `:120`→`:121`, y rango de rutas de las 7 mutaciones acotado en §3).
  Cierre formal en `PROGRESO.md`, sección "Cierre formal de F17".
- **Método:** (a) verificación **estática** con grep sobre `app/`, `routes/`, `config/`, `database/`, `resources/` y sobre lo trackeado en git; (b) verificación **dinámica** con tests feature de las 7 mutaciones `/api/admin/*` y de los 2 gaps de hardening reales; (c) **cero cambios de producción**: solo tests nuevos y documentación.
- **Tests nuevos Fase 17:** `tests/Feature/AdminMutacionesApiTest.php` (5) + `tests/Feature/HardeningApiTest.php` (2) = **7 tests / 144 aserciones**, todos en verde.
- **Alcance:** **no** repite inventarios ni pruebas de F13–F16; solo registra lo medido aquí y **referencia** la evidencia previa.
- **Leyenda:** `(TEST)` = demostrado con test en esta fase · `(CÓDIGO)` = verificado estáticamente con `archivo:línea` · `(previo)` = evidencia de fase previa, solo referenciada.

## 1. Verificación estática

### 1.1 Código de debug entregable — SIN HALLAZGO `(CÓDIGO)`

| Qué se buscó | Árbol | Resultado |
| --- | --- | --- |
| `dd(`, `dump(`, `var_dump(`, `print_r(` | `app/`, `routes/`, `config/`, `database/`, `resources/` (`.php` + `.js`) | **0 coincidencias** |

### 1.2 Logueo — SIN HALLAZGO `(CÓDIGO)`

| Qué se buscó | Árbol | Resultado |
| --- | --- | --- |
| `Log::` | `app/` (recursivo) | **0 coincidencias** — no hay logging de depuración en producción |
| Único logger operativo | `VerificarVencimientoPlazosCommand.php:29-30` | `$this->info()` de consola, sin datos sensibles (evidencia de F13, `MATRIZ_JOBS_CRON.md`) |
| Archivos de log trackeados | `git ls-files -- storage` | solo `.gitignore` (incluido `storage/logs/.gitignore:1` → `*.log`); **ningún `.log` en el repo** |

### 1.3 Secretos y credenciales — SIN HALLAZGO `(CÓDIGO)`

| Qué se verificó | Evidencia | Resultado |
| --- | --- | --- |
| Strings/credenciales hardcodeadas | grep de claves sobre `config/`, `routes/`, `app/` | **0 secretos**; lo único parecido es lectura de cookie `XSRF-TOKEN` en cliente (`login.blade.php:321`, `api-helper.blade.php:18`), comportamiento estándar |
| `.env` fuera del repo | `git ls-files` | trackeado **solo** `.env.example`; `.gitignore:3-6` ignora `.env`, `.env.backup`, `.env.production`, `.env.testing` |
| Plantilla `.env.example` | `:2-4`, `:28` | `APP_KEY=` **vacío** (sin clave fábrica), `DB_PASSWORD=root` y `APP_DEBUG=true` son **valores plantilla de entorno local** (`APP_ENV=local`), no credenciales reales → observación de checklist para despliegue (§7) |
| `.env` local | `APP_ENV=local`, `APP_DEBUG=true` | **correcto para desarrollo**; el paso a producción (`APP_DEBUG=false`) queda como ítem de checklist F18, no como hallazgo |

## 2. Rate limiting `(TEST)`

| Limiter | Registrado en | Límite | Verificación |
| --- | --- | --- | --- |
| `login` | `routes/api.php:26` + `AppServiceProvider.php:25` | 5/min por IP | `(previo)` `AuthFeatureTest.php:219-220` espera **429** |
| `api` | `routes/api.php:29` + `AppServiceProvider.php:27` | 60/min por `user()->id` (o IP si no hay sesión) | **`(TEST)` nuevo:** `HardeningApiTest` → 60 requests `200` seguidas + la 61ª → **429** |
| Rutas web (`routes/web.php`) | — | sin throttle dedicado | `(previo)` no existen endpoints web de escritura (evidencia F16 `AdminHttpCoverageTest`); solo se responde por `GET` → **sin hallazgo**, decisión anotada en §7 |

> La prueba de `throttle:api` corre con `CACHE_STORE=array` (`phpunit.xml`), es decir con el **mismo mecanismo de límite** que en producción, pero sin medir el rendimiento del store de caché real (§6).

## 3. Cobertura de las 7 mutaciones del panel administrador `(TEST)`

Rutas de las 7 mutaciones en `routes/api.php:99-102` (usuarios) y `routes/api.php:106-108` (feriados). Cada fila se verifica en **4 condiciones de autorización** —(1) sin sesión → `401`, (2) rol distinto de ADMIN → `403`, (3) ADMIN inactivo → `403`, (4) ADMIN activo → éxito— más efecto persistido y `422` de validación. **28 escenarios de autorización** (7 × 4) + efectos + validaciones.

| # | Método y ruta | Autorización | Reglas (FormRequest) | Respuesta de éxito | Tests (F17) |
| - | --- | --- | --- | --- | --- |
| 1 | `POST /api/admin/usuarios` | `AdminUsuariosController:74` → policy `UsuarioPolicy::gestionar` | `StoreUsuarioRequest:23-30` (ci/username únicos, password `min:8`, `rol_id exists`) | `201` + `UsuarioResource` sin `password_hash` + hash con `Hash::check` | `AdminMutacionesApiTest` |
| 2 | `PUT /api/admin/usuarios/{u}` | `AdminUsuariosController:101` → `gestionar` | `UpdateUsuarioRequest:30-36` (unique con `ignore`, password `nullable min:8`; `activo` no se edita) | `200`; conserva `activo`; rota password solo si viene | `AdminMutacionesApiTest` |
| 3 | `POST /api/admin/usuarios/{u}/activar` | `AdminUsuariosController:128` → `authorize('activar', …)` = `UsuarioPolicy::activar:53-56` (ADMIN activo) | `ActivarUsuarioRequest:21` reglas vacías; negocio en `SeguridadSesionesService@reactivar:72-90` | `200`; `activo=true` + `AuditoriaUsuario::ACCION_ACTIVACION` | `AdminMutacionesApiTest` |
| 4 | `POST /api/admin/usuarios/{u}/inactivar` | `UsuarioController@inactivar:52` → `UsuarioPolicy::inactivar:43-46` | `InactivarUsuarioRequest:25` reglas vacías; negocio en `expulsar:35-63` (422 si inactivo o autodesactivación) | `200` + mensaje; `activo=false`, tokens borrados, sesiones purgadas, `ACCION_INACTIVACION` | `AdminMutacionesApiTest` |
| 5 | `POST /api/admin/feriados` | `AdminFeriadosController:65` → `FeriadoPolicy::gestionar:15-19` (ADMIN activo) | `StoreFeriadoRequest:14-34` (`fecha` date única, `descripcion`/`ambito` máx.) | `201` + persistencia en `feriados` | `AdminMutacionesApiTest` |
| 6 | `PUT /api/admin/feriados/{f}` | `AdminFeriadosController:84` → `gestionar` | `UpdateFeriadoRequest:14-34` (unique con `ignore`) | `200` + cambios reflejados en BD | `AdminMutacionesApiTest` |
| 7 | `DELETE /api/admin/feriados/{f}` | `AdminFeriadosController:101` → `gestionar` | — | `200` + registro eliminado | `AdminMutacionesApiTest` |

Comprobaciones transversales de los escenarios 403: **sin efectos colaterales** (contadores de `usuarios`/`feriados` sin cambios; el objetivo permanece `activo`; el feriado objetivo persiste).

**422 cubiertos:** `ci` duplicada, `password` corta (POST y PUT), `rol_id` inexistente, `fecha` duplicada (POST y PUT).

**No duplicado aquí** (evidencia previa): GETs `/api/admin/*` → `SeguridadIdorTest.php:212-215,238-240` + `ReportesAdminTest.php:122-197`; rutas web `/administrador/*` → `AdminHttpCoverageTest` (F16); `POST /api/usuarios/{u}/inactivar` (URI distinta) → `SeguridadSesionesTest.php:81-132`.

## 4. Exposición de datos `(TEST)` / `(CÓDIGO)`

| Punto | Evidencia | Resultado |
| --- | --- | --- |
| `GET /api/me` | `AuthController@me:128-131` devuelve el modelo crudo (`->load('rol')`) | **sin `password_hash`** (test nuevo: `assertJsonMissingPath` + verificación de cuerpo crudo) — depende de `$hidden`, por eso también se afirma la irradiación directamente |
| Modelo | `Usuario.php:27-28` `protected $hidden = ['password_hash']` | `(CÓDIGO)` protegido en toda serialización |
| Recursos | `UsuarioResource.php:21-34` (lista blanca de campos, sin hash) | `(CÓDIGO)` sin filtración |
| Claves en cliente | `login.blade.php` / `api-helper.blade.php` | solo lectura de `XSRF-TOKEN`, sin secretos (§1.3) |
| `console.log` en frontend | `resources/views/administrador/monitoreo.blade.php:469` (1 ocurrencia) | **(previo)** corresponde a **AUD-0058 (F14)**; ya fichado, **no duplicado** aquí |

## 5. Gaps de hardening medidos y estado

| Gap | Estado |
| --- | --- |
| `throttle:api` sin prueba de 429 | **CERRADO en F17** (`HardeningApiTest`: 61ª request → 429) |
| `/api/me` sin prueba de no filtración de hash | **CERRADO en F17** (`HardeningApiTest`) |
| 7 mutaciones `/api/admin/*` sin cobertura de autorización | **CERRADO en F17** (`AdminMutacionesApiTest`) |

## 6. Límites de esta verificación (transparencia)

- El rate limit se probó con `CACHE_STORE=array` (`phpunit.xml`): valida la **lógica** del limiter (5/min login, 60/min API) pero no el comportamiento del store de caché de producción.
- Solo se audita lo trackeado en git + `.env.example`; un `.env` de servidor real queda fuera del alcance de esta fase.
- El límite de 60/min se mide con el **mismo usuario autenticado**; el caso sin sesión se limita por IP (definido en `AppServiceProvider.php:27`, no ejercitado en esta fase).

## 7. Hallazgos, observaciones y decisiones

- **Hallazgos nuevos: 0** → **AUD-0068 NO creada** (sigue reservada; duplicado exacto con AUD-0030, `BACKLOG_AUDITORIA.md:386-392`); `MATRIZ_SEGURIDAD.md` y `BACKLOG_AUDITORIA.md` intactos.
- **Decisión pendiente referenciada (no resuelta aquí):** **AUD-0001** — bypass de `ExpedientePolicy` para ADMIN (`ExpedientePolicy.php:80-82`); sin tocar, el fallo de suite se conserva como deuda conocida.
- **Observaciones sin ficha (sin acción en F17):**
  - **O-A:** `POST /api/admin/usuarios/{u}/inactivar` y `POST /api/usuarios/{u}/inactivar` son **la misma acción** en dos URIs (`UsuarioController@inactivar`); ambas quedan cubiertas, pero unificar la superficie es decisión de diseño pendiente.
  - **O-B:** `.env.example` con `APP_DEBUG=true` / `DB_PASSWORD=root` como valores plantilla → ítem de **checklist de despliegue (F18)**: exiger `APP_DEBUG=false` en producción.
- El **conflicto de asignación** de las mutaciones `/api/admin/*` (`MATRIZ_SEGURIDAD.md:85` → F17 vs `MAPA_FUNCIONAL.md:196`) queda **resuelto para cobertura de pruebas** por esta fase; la fila de la matriz de seguridad no se modifica (fuera de alcance).

## 8. Gates de F17

- `vendor/bin/pint --dirty --format agent` → **passed**.
- `php artisan test --compact` → **310 tests · 303 OK · 1 fallo (AUD-0001, deuda conocida, `SecurityCompartimentosTest`) · 0 errores · 6 omitidos · 1453 aserciones**.
- Delta vs baseline de F16 (303 · 296 · 1 · 0 · 6 · 1309): **+7 tests, +7 OK, +144 aserciones (= los tests nuevos de F17), 0 regresiones**, mismo único fallo y mismos 6 omitidos (5 `RUN_STRESS_TESTS` + 1 `RUN_CONCURRENCY_TEST`).

## 9. Archivos modificados / creados

| Archivo | Acción |
| --- | --- |
| `tests/Feature/AdminMutacionesApiTest.php` | creado (5 tests) |
| `tests/Feature/HardeningApiTest.php` | creado (2 tests) |
| `docs/auditoria/MATRIZ_HARDENING.md` | creado (este archivo) |
| `docs/auditoria/PLAN_EJECUCION_AUDITORIA.md` | fila F17 (§5) + fila de artefacto (§1) + corrección F13 (`:121`) |
| `docs/auditoria/PROGRESO.md` | sección Fase 17 |
