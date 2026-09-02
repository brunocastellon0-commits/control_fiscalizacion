# PLAN WORKSTATION /expedientes — Control y Fiscalización

> ### ⚠️ LEER PRIMERO EN TODO CHAT NUEVO
> Este documento es la fuente de verdad del avance del proyecto. Antes de tocar
> cualquier archivo, un chat nuevo debe leer este documento completo.
> **Regla de oro:** el esquema de BD se **reverifica contra las migraciones
> reales** antes de escribir cualquier query, modelo o migración — nunca se
> asume de memoria (ver `.opencode/rules/restricciones.md` §1).

---

## 1. Proyecto y stack (hechos verificados a fecha 2026-08-28)

- **Backend:** Laravel 13.17 · **PHP:** 8.3 · **BD:** MySQL 9.7 (nunca Postgres/SQLite).
- **Auth:** Sanctum (`auth:sanctum`) · **Testing:** Pest 5.
- **BD dev:** `control_fiscalizacion` · **BD test:** `control_fiscalizacion_test`
  (host `127.0.0.1`, puerto `3306`). Config: `.env` (dev) y `.env.testing` (tests,
  MySQL, APP_KEY `base64:5D9uFvtrQar0hAn7/x5hJWuwPFSjn/gbABDyTHbvnyg=`).
- **Reglas duras activas (obligatorias):**
  - `AGENTS.md`
  - `.opencode/rules/laravel-skills.md` (MySQL-first, FKs reales, validación,
    seguridad, **§6 testing obligatorio por entregable**, □nunca borrar tests).
  - `.opencode/rules/restricciones.md` (no alucinar esquema, no debilitar
    seguridad, no `dd()`/`dump()`/debug, no tocar lo no pedido, no reinventar
    jwt/esquema).
- **Repo:** NO es un repositorio git → **no usar `git` ni `vendor/bin/pint --dirty`**.
- **Comandos de trabajo:**
  - Suite completa: `php artisan test --compact` (no correr con `--testsuite` parcial solo).
  - Pint por archivo: `vendor/bin/pint <archivos> --format agent` (no existe `--dirty`).
  - Rutas: `php artisan route:list`

## 2. Esquema de BD verificado (contra migraciones reales)

> Solo figuran tablas/columnas ya confirmadas en este proyecto. Verificar la
> migración real antes de usar cualquier tabla no listada o campo nuevo.

| Tabla | Columnas verificadas | Notas |
|---|---|---|
| `expedientes` | `nurej_code` **varchar(30)** único, `nurej_padre_id`, `via`, `reglamento_id`, `estado_actual_id`, `resumen_hechos`, `fecha_ingreso`, `creado_por`, `created_at` | `nurej_code` <= 30 → **NO usar UUID** ni strings largos (error 1406 en tests). `$timestamps=false`; `fecha_ingreso` y `created_at` son datetime. |
| `actuados` | `expediente_id`, `catalogo_actuado_id`, `usuario_id` (null), `fecha_hora` (useCurrent), `estado_anterior_id` (null), **`estado_nuevo_id` NOT NULL**, `contenido` json, `actuado_referencia_id` (null), `ip_origen`(45), `hash_actuado` char(64), `hash_anterior` char(64) | **Inmutable por triggers** (UPDATE/DELETE → SIGNAL). `hash_anterior` y `hash_actuado` los calcula el trigger `trg_actuados_hash_before_insert` (cadena por `expediente_id` ORDER BY `fecha_hora DESC, id DESC`). `contenido` = cast `array` en modelo. |
| `asignaciones` | `expediente_id`, `usuario_id`, `rol_id`, **`actuado_origen_id` NOT NULL + FK→`actuados`**, `fecha_asignacion`, `activa` | **NO tiene default** → crear SIEMPRE con `actuado_origen_id` real (error 1364). Bandeja activa = `where('activa', true)`. |
| `adjuntos` | `actuado_id`, `nombre_original`, `ruta_almacenamiento`, `hash_sha256`(64), `mime_type`, `tamanio_bytes`, `subido_por`, `subido_at` | Validación archivos Paso 4: PDF `mimes:pdf` máx 20 MB (`max:20480`). |
| `catalogo_actuados` | `codigo`, `nombre`, `fase`, `rol_id`, `reglamento_id`, `estado_origen_id`, `estado_destino_id`, `es_automatico`, `requiere_adjunto`, `descripcion` | `rol_id` es el rol habilitado para emitir el actuado. Sin timestamps. |
| `catalogo_estados` | `codigo`, `nombre` | Estados seed: PENDIENTE_SORTEO, EN_EVALUACION, OBSERVADO, RECHAZADO, ADMITIDO, EN_INVESTIGACION, EN_DESCARGOS, CONCLUIDO, ARCHIVO_DEFINITIVO. |
| `roles` | `codigo`, `nombre` | Roles seed: **ENCARGADA, TECNICO, AUD_JURIDICO, AUD_FINANCIERO, ADMIN**. Modelo con constantes `Rol::CODIGO_*`. |
| `usuarios` | `nombres`, `rol_id`, `activo` | Modelo autenticable del guard Sanctum. |
| `nurej_sequences` | `anio` (PK), `correlativo` | Modelo `$incrementing=false`, `$timestamps=false`. |
| `plazos` | `expediente_id`, `tipo_plazo`, `parametro_plazo_id`, `dias_habiles_otorgados`, `fecha_inicio`, `fecha_limite`, `estado`, `actuado_disparador_id` | `estado` ∈ {VIGENTE, CERRADO, SUSPENDIDO} (ver semáforo). |
| `parametros_plazo` | `reglamento_id`, `tipo_plazo`, `subtipo` (null/JURISDICCIONAL/ADMINISTRATIVA), `dias_habiles` | Consulta por `reglamento_id`+`tipo_plazo`+`subtipo`, fallback subtipo null. |
| `sesiones_acceso` | `usuario_id` (relación `usuario()`) | Auditoría de sesiones (Paso 9). |

Triggers MySQL activos: `trg_actuados_hash_before_insert`, `trg_actuados_inmutable_update`, `trg_actuados_inmutable_delete` (migración `2026_08_28_182631_create_actuados_triggers.php`, **aplicada**).

## 3. Decisiones de negocio aprobadas (SRS) — NO re-abrir sin permiso explícito

1. **Apertura de causa NO crea asignación** (`usuarioDestinoId: null`). La causa queda en
   `PENDIENTE_SORTEO` **sin bandeja**. La bandeja de sorteo de la Encargada se consulta
   **por estado** (`estado_actual_id = PENDIENTE_SORTEO`). La **primera asignación formal**
   recién se crea con `ACT_SORTEO_INICIAL`.
2. **Semántica de `PlazoCalculatorService::daysRemaining()`** (líneas 85-95): cuenta días
   hábiles **desde el día siguiente a hoy hasta la fecha límite INCLUSIVE** (el día límite
   cuenta). Ej. hoy=vie 2026-08-28 → límite lun 08-31=1, mar 09-01=2, mié 09-02=3, jue
   09-03=4, vie 09-04=5. **No recalibrar.**
3. **Semáforo** (`SemaforoPlazoService`, RF-R01/RF-R05): `FUERA_DE_PLAZO` = `now() >
   fecha_limite(endOfDay)`; `ROJO` = restantes ≤ 1; `AMARILLO` = cortos (≤3 días) con
   exactamente 2 restantes / largos con restantes ≤ `ceil(total/3)`; `VERDE` = resto;
   estados no vigentes (`CERRADO`, `SUSPENDIDO`) → passthrough sin cálculo.
4. **`ExpedientePolicy::crearActuado`** (RF-03 compartimentos estancos / RN-07 jerarquía):
   - Requiere `$user->activo === true`.
   - **RN-07:** `rol.code == ENCARGADA` y `catalogoActuado->rol_id == user->rol_id` →
     autoriza **sin exigir bandeja**.
   - **RF-03:** resto de roles → exige `asignacionActiva.usuario_id == user->id` **Y**
     `catalogoActuado->rol_id == user->rol_id`.
   - **ADMIN no tiene excepción** (se comporta como cualquier rol; pendiente decidir).
5. **Valores validados en Form Requests** (SRS):
   - `via` ∈ {`TECNICO`, `JURIDICO`, `FINANCIERO`} — motores de los Acuerdos 022, 54 y 55.
   - `partes[].tipo` ∈ {`DENUNCIANTE`, `DENUNCIADO`} (RF-02). Permitir `partes` de a 1+.
   - `resumen_hechos` **obligatorio** (`min:10|max:5000`); `descripcion` de actuado
     `min:5|max:10000` (StoreActuado) / `max:1000` (Sortear).
6. **Mapa de plazos** (`ActuadoService::MAPA_TIPO_PLAZO`):
   `ACT_SORTEO_INICIAL → EVALUACION`, `ACT_OBSERVACION → SUBSANACION`,
   `ACT_ADMISION → EJECUCION`. Subtipo de EJECUCION por `via`: `JURIDICO →
   JURISDICCIONAL`, resto → `ADMINISTRATIVA`. Fuera del mapa, no abre plazo.
7. **Actuados=immutables**: nunca UPDATE/DELETE; para corregir se emite un nuevo actuado.

## 4. Las 10 tareas / pasos

> Estado: ✅ DONE · ⏭ NEXT · ⬜ PENDING. Los Pasos 4 y 5 de esta lista corresponden
> al trabajo realizado en la sesión donde el chat los tituló "Paso 4 (Form Requests
> + ExpedientePolicy)"; están **completos y en verde**.

### ✅ Paso 0 — Cimientos
- Fix `app/Models/Plazo.php` (`public $timestamps = false`).
- Migración triggers `2026_08_28_182631_create_actuados_triggers.php`.
- `ActuadoService::registerActuado()` hace `refresh()` tras `create()` para exponer hashes.
- §6 "Testing" reescrita en `.opencode/rules/laravel-skills.md` (cobertura obligatoria).
- Infra test: `.env.testing`, BD `control_fiscalizacion_test`, `phpunit.xml` sin SQLite,
  `tests/Pest.php` con `RefreshDatabase` solo en Feature; Unit también la declara.
- `PlazoCalculatorService` con constructor inyectable.

### ✅ Paso 1 — NUREJ
- Migración `2026_08_28_183208_create_nurej_sequences_table.php` (aplicada a dev).
- `app/Models/NurejSequence.php` (PK `anio`, `$incrementing=false`, `$timestamps=false`).
- `app/Services/NurejGeneratorService.php`: `generarPadre()` y `generarHijo()` con
  `DB::transaction` + `lockForUpdate()` + `insertOrIgnore()`. Formato `sprintf('%d-%05d',
  $anio, $correlativo)`.
- Excepción `app/Exceptions/CannotDeriveNurejException.php` (RN-10: derivar de un hijo → lanza).
- Factories: `RolFactory`, `UsuarioFactory`, `ReglamentoFactory`, `CatalogoEstadoFactory`.
- Tests: `tests/Unit/NurejGeneratorServiceTest.php` (4 tests). Riesgos resueltos:
  PK `anio` (R1), `select('id','nurej_code','nurej_padre_id')` (R2), RN-10 (R3).

### ✅ Paso 2 — ExpedienteService (apertura de causa)
- `app/Services/ExpedienteService.php::aperturaCausa()` — **en una sola transacción**:
  NUREJ padre + `Expediente` PENDIENTE_SORTEO + actuado `ACT_REGISTRO_DIGITALIZACION`
  (reusa `ActuadoService`, cadena de custodia por trigger) + partes versionadas
  (`actuado_origen_id`, `vigente_desde`, `es_version_actual=true`).
- **Sin asignación** (`usuarioDestinoId: null`). Si algo falla se revierte todo (no se
  quema NUREJ). Inyección: `NurejGeneratorService` + `ActuadoService`.
- Tests: `tests/Unit/ExpedienteServiceTest.php` (happy path sin bandeja + atomicidad
  vía Mockery de `ActuadoService`).

### ✅ Paso 3 — SemaforoPlazoService
- `app/Services/SemaforoPlazoService.php`: `evaluarPlazo(Plazo): array` y
  `resumenBandeja(Collection|array): array`.
- Semántica calibrada en §3.3. Su contextura: estados no vigentes → passthrough.
- Tests: `tests/Unit/SemaforoPlazoServiceTest.php` (8 tests).

### ✅ Paso 4 — Form Requests (StoreExpediente, Sortear, StoreActuado)
- `app/Models/Rol.php`: constantes `CODIGO_ENCARGADA/TECNICO/AUD_JURIDICO/AUD_FINANCIERO/ADMIN`
  (iguales a `roles.codigo`).
- `app/Http/Requests/StoreExpedienteRequest.php` — `authorize()`: rol `TECNICO`.
  Reglas: `via in:TECNICO,JURIDICO,FINANCIERO` · `reglamento_id exists:reglamentos,id` ·
  `resumen_hechos required|string|min:10|max:5000` · `partes required|array|min:1` con
  `tipo in:DENUNCIANTE,DENUNCIADO`, `nombre_completo max:200`, `documento_identidad
  nullable|max:30`, `cargo_institucion nullable|max:150` · `adjunto nullable|file|mimes:pdf|max:20480`.
- `app/Http/Requests/SortearExpedienteRequest.php` — `authorize()`: rol `ENCARGADA`.
  `usuario_destino_id required|integer` + `Rule::exists('usuarios','id')` con closure
  (`activo=true` y `rol_id` ∈ `{TECNICO, AUD_JURIDICO, AUD_FINANCIERO}`) ·
  `descripcion nullable|string|max:1000`.
- `app/Http/Requests/StoreActuadoRequest.php` — `authorize()`: resuelve `{expediente}` de
  la ruta, `CatalogoActuado` del body `catalogo_actuado_id`, delega en
  `$this->user()->can('crearActuado', [$expediente, $catalogoActuado])`. Reglas:
  `catalogo_actuado_id exists:catalogo_actuados,id` · `descripcion required|string|min:5|max:10000` ·
  `usuario_destino_id nullable|integer|exists:usuarios,id` · `adjunto mimes:pdf|max:20480`.
- **Gotcha:** `Rule::mimes()` **no existe** → usar el string `'mimes:pdf'`.
- Tests: `tests/Feature/FormRequestsTest.php` (10 tests vía rutas temporales `/ _test/...`).
  **Gotcha:** `nurej_code` varchar(30) → usar `'NUREJ-'.Str::upper(Str::random(8))`;
  `asignaciones.actuado_origen_id` NOT NULL → crear actuado origen real (ver helper
  `paso4AsignarBandeja`).

### ✅ Paso 5 — ExpedientePolicy
- `app/Policies/ExpedientePolicy.php::crearActuado(Usuario, Expediente, CatalogoActuado)`.
  Semántica RF-03/RN-07 en §3.4. Auto-descubierta por Laravel (Paso 4 dependía de ella).
- Arranca con una sola ability; se ampliará si Pasos 6-7 lo requieren (ver §5 abiertos).

### ✅ Paso 6 — API Resources
Crear recursos Eloquent (`php artisan make:resource`) para la API del workstation.
Lineamientos:
- `app/Http/Resources/ExpedienteResource.php`: `id`, `nurej_code`, `nurej_padre_id`, `via`,
  `reglamento` (anidado: codigo/nombre), `estado_actual` (codigo/nombre), `resumen_hechos`,
  `fecha_ingreso`, `creado_por`, `asignacion_activa` (usuario + rol) y **semáforo** del
  plazo vigente `PlazoResource`/`SemaforoPlazoService::evaluarPlazo`. Cargar relaciones con
  `->load()`/`->with()` (anti-N+1), **nunca `SELECT *`**.
- `app/Http/Resources/ActuadoResource.php`: `id`, `fecha_hora`, `catalogo_actuado` (codigo/nombre),
  `estado_anterior`/`estado_nuevo`, `descripcion` desde `contenido`, `hash_anterior`,
  `hash_actuado`, `usuario`, `adjuntos`.
- `app/Http/Resources/ParteResource.php` (anidado en expediente) y
  `app/Http/Resources/PlazoResource.php` (con semáforo) si aportan.
- **No crear documentación extra**; mínimo necesario para los DTOs que usarán Pasos 7 y 10.
- Tests: tests pequeños para transformación (datos/estructura) cuando aplique; el grueso
  de cobertura HTTP va en el Paso 9.
- Cierre: `vendor/bin/pint <archivos>` + `php artisan test --compact`.

### ✅ Paso 7 — Controladores + rutas Sanctum + throttle
- `routes/api.php` reescrito: `POST /login` con `throttle:login`; grupo
  `auth:sanctum` + `throttle:api` con `me`, `logout`, `GET /bandeja/sorteo`,
  `GET /bandeja`, `POST /expedientes`, `GET /expedientes/{expediente}`,
  `POST /expedientes/{expediente}/sortear`, `POST /expedientes/{expediente}/actuados`.
- `AppServiceProvider::boot()`: `RateLimiter::for('login')` (5/min) y `::for('api')`
  (60/min), ambos con `Request $request`.
- `app/Services/AdjuntoService.php` (NUEVO): `guardarParaActuado()` — hash_sha256 del
  contenido ORIGINAL antes de guardar, `storeAs('adjuntos/{expediente_id}/{hash}.ext')`,
  dentro de la transacción del caller. **Auto-limpia el archivo físico si `Adjunto::create`
  falla** (limpieza localizada donde se escribe el archivo). `subido_at` se resuelve con
  `useCurrent()` en BD → `->refresh()`.
- `ActuadoService::registerActuado()` recibe `?UploadedFile $adjunto`; valida
  `requiere_adjunto && $adjunto === null` → `ValidationException` (422) ANTES de crear el
  actuado (nunca un actuado inmutable sin su adjunto obligatorio). Inyecta `AdjuntoService`.
- `ExpedienteService::aperturaCausa(..., ?UploadedFile $adjunto)` pasa `adjunto:` a
  `registerActuado` (NO acopla `AdjuntoService`).
- `ExpedienteController` (NUEVO): `store` (201), `bandejaSorteo` (policy `bandejaSorteo`,
  filtra `PENDIENTE_SORTEO`, paginado 15), `bandejaOperador` (asignación activa del usuario),
  `show` (policy `view`, RF-03), `sortear` (valida `PENDIENTE_SORTEO` → 422 si no; emite
  `ACT_SORTEO_INICIAL`). Helper `relacionesDetalle()` anti-N+1.
- `ActuadoController` (NUEVO): `store` (policy vía `StoreActuadoRequest`, 201 + `ActuadoResource`).
- `ExpedientePolicy`: agregadas `bandejaSorteo(Usuario)` y `view(Usuario, Expediente)` (RF-03).
- `StoreExpedienteRequest.adjunto` ahora `required`; `StoreActuadoRequest.adjunto` condicional
  con `Rule::requiredIf($catalogoActuado?->requiere_adjunto ?? false)`.
- **`App\Http\Controllers\Controller` base ahora usa el trait `AuthorizesRequests`** (no lo
  traía por defecto; sin él, `$this->authorize()` lanzaba `Call to undefined method`).
- **Resources single devueltos directos** (`new XResource(...)`, envueltos en `{"data":...}`)
  en vez de `->resolve($request)` (JSON plano sin envoltura); consistente con
  `XResource::collection`.
- Tests: `AdjuntoServiceTest` (hashing/nombrado por hash/limpieza en rollback),
  `ActuadoServiceTest` (feliz, sin adjunto no requerido, 422 sin persistir, hash custodia),
  `ExpedienteControllerTest` (10: apertura 201/422/403, RF-03 detalle/bandeja, sorteo ± estado),
  `ActuadoControllerTest` (3: actuado con asignación, RF-03 403, adjunto requerido 422).
- Cierre: `vendor/bin/pint <archivos>` + `php artisan test --compact` (todo verde).

### ✅ Paso 8 — ExpedienteDemoSeeder
- `database/seeders/ExpedienteDemoSeeder.php` (NUEVO): seeder de datos demo vía
  **service** (no inserts sueltos) para respetar NUREJ, cadena de custodia y estados.
- 5 expedientes creados: 3 en `PENDIENTE_SORTEO` (bandeja de sorteo de Encargada),
  1 en `EN_EVALUACION` con plazo `EVALUACION` abierto y asignación a `aud_juridico`
  (bandeja operador), 1 en `OBSERVADO` con cadena de custodia completa (3 actuados:
  registro→sorteo→observación, hashes encadenados).
- **Adjuntos dummy reales:** PDF mínimo creado vía `UploadedFile` desde archivo temporal
  (`tempnam` + `file_put_contents`), no `fake()`; el `AdjuntoService` guarda el archivo
  real en `storage/app/adjuntos/` y calcula SHA-256 real.
- **Idempotencia:** guard-clause con `[DEMO]` en `resumen_hechos`; si ya existen
  expedientes con ese tag, se salta sin borrar nada (no-destructivo, sobrevive a
  `migrate:fresh`).
- **Auto-dependencias:** si faltan seeders base (roles/usuarios/catálogos/reglamentos/
  parámetros de plazo), los llama automáticamente antes de crear los expedientes.
- `DatabaseSeeder` actualizado: `ExpedienteDemoSeeder` al final de la cadena.
- Verificado: `migrate:fresh --seed` crea los 5 expedientes sin errores;
  re-ejecución standalone del seeder se salta correctamente (guard-clause).
- No hay tests nuevos (§8: seeds solo para dev, los tests NUNCA dependen de seeders).

### ✅ Paso 9 — Feature Tests (Auth, Security, Chain, Semaforo)
- 4 archivos nuevos de test, cubriendo Auth, RF-03, cadena de custodia y semáforo:
  - `AuthFeatureTest` (10): login exitoso/fallido/inexistente/inactivo, auditoría en
    `sesiones_acceso` (exitoso/falido, logout_at), `/me` autenticado y sin token,
    logout (revoca token + `logout_at`), token revocado deja de servir, **rate limit
    login 5/min → 429**, aislamiento con `RateLimiter::clear('login')` en `beforeEach`.
  - `SecurityCompartimentosTest` (9): RF-03 estanco — operador sin asignación → 403
    (ver/actuar), asignación en otro expediente no da acceso al ajeno, **ADMIN sin
    missión → 403 (SIN bypass indebido)**, ENCARGADA ve todo por jerarquía, usuario
    inactivo → 403, petición sin token → 401.
  - `CadenaCustodiaTest` (7): primer `hash_anterior`=null, encadenado 1→2→3,
    **reproducción fiel del SHA-256** del trigger (contrato exacto), UPDATE directo
    → excepción `QueryException` (trigger inmutable), DELETE directo → excepción.
  - `SemaforoPlazosFeatureTest` (6): API expone `sem_plazo` VERDE/AMARILLO/ROJO/
    FUERA_DE_PLAZO vía `Carbon::setTestNow()`, feriado excluido del cómputo, sin
    plazo → `sem_plazo=null`.
- Todas las semillas con factories aisladas (patrón Paso 6/7), nunca seeders (regla §6/§8).
- Inmutabilidad testada contra **MySQL real** (triggers activos en el test DB).
- Verificado: suite completa **92 passed / 310 assertions** (61→92, +31 tests).
- Base de línea actualizada en §5/§6.

### ⏭ Paso 10 — Workstation `/expedientes` (Blade + Alpine + Tailwind CDN) (SIGUIENTE)
- Frontend recién **al final** (backend-first). Blade + Alpine.js + Tailwind vía CDN
  (ver skill tailwindcss-development si se usa).
- Sesión web (no solo API), vistas de: bandeja de sorteo (Encargada), bandeja operador
  (por asignación activa), detalle de expediente (expediente, partes, actuados con hashes,
  plazos con semáforo) y formularios de apertura/actuado.
- Si el cambio no se ve en el front: pedir `npm run build` / `npm run dev` / `composer run dev`.

## 5. Registro de verificación y gotchas por paso

| Paso | Estado al | Suite asociada | Notas / gotchas |
|---|---|---|---|
| 0 | ✅ | Unit+Feature | `Plazo::$timestamps=false`; triggers SOLO en MySQL real (no SQLite). |
| 1 | ✅ | `NurejGeneratorServiceTest` | PK compuesta efectiva vía `anio` PK; RN-10 heredar de hijo → `CannotDeriveNurejException`. |
| 2 | ✅ | `ExpedienteServiceTest` | `usuarioDestinoId:null`; transacción completa (no quema NUREJ). |
| 3 | ✅ | `SemaforoPlazoServiceTest` | Fecha límite INCLUSIVE; cuadrar con feriados. |
| 4 | ✅ | `FormRequestsTest` | `Rule::mimes` no existe; `nurej_code`<=30; `actuado_origen_id` NOT NULL. |
| 5 | ✅ | (`FormRequestsTest`) | Auto-descubrimiento de policy; ADMIN sin excepción. |
| 6 | ✅ | 4 resources + 4 test files (10 tests) | `->resolve(request())`, NO `->toArray(request())` (toArray retiene `MissingValue` crudo); `$parte->refresh()` tras `Parte::create()` por `useCurrent()`. No `SELECT *`; anti-N+1 con `with()`. |
| 7 | ✅ | `AdjuntoServiceTest` (3) + `ActuadoServiceTest` (3) + `ExpedienteControllerTest` (10) + `ActuadoControllerTest` (3); ver nota 6.1 | `AuthorizesRequests` faltaba en `Controller` base; resources single devueltos directos (envoltura `data`, no `->resolve()`); `AdjuntoService` auto-limpia archivo en rollback; rate limiting login 5/min + api 60/min; capturar `estado_anterior` antes de mutar la instancia en tests de `registerActuado`. |
| 8 | ✅ | — (sin tests nuevos) | Idempotencia por `[DEMO]` en `resumen_hechos` (no-destructivo); auto-dependencia de seeders base; adjuntos dummy reales con `UploadedFile` + `tempnam`; `DatabaseSeeder` incluye `ExpedienteDemoSeeder` al final. |
| 9 | ✅ | `AuthFeatureTest` (10) + `SecurityCompartimentosTest` (9) + `CadenaCustodiaTest` (7) + `SemaforoPlazosFeatureTest` (6); ver nota 6.1 | Tests por archivo en MySQL real con factories aisladas, no seeders. Gotchas: (a) las helpers de semilla de otro archivo de test NO se cargan al correr un archivo suelto → helpers locales por archivo; (b) reproducción del hash: MySQL guarda JSON/CAST con formato propio (`{"k": v}` con espacio tras `:`) y timestamp con precisión de segundos → leer la fila cruda con `DB::table`, no `json_encode` del modelo; (c) el guard de Sanctum cachea `$user` entre requests del mismo test → `$this->app['auth']->forgetGuards()` entre llamadas para validar token revocado; (d) `withToken($realToken)` en vez de `Sanctum::actingAs` para logout real. |
| 10 | ⬜ | — | Tailwind CDN; verificar build para ver cambios. |

**Baseline actual de la suite:** `php artisan test --compact` → **92 passed / 310 assertions**
(baseline previo 61/238 + 31 nuevos del Paso 9 en verde). Partir SIEMPRE de verde.

## 6. Cómo reanudar desde cualquier punto

1. Leer este documento (siempre).
2. `php artisan test --compact` → confirmar **92 passed / 310 assertions** (o mayor, siempre verde).
3. Ir a la fila de su paso en la tabla §5 / secciones §4:
   - si `⏭ NEXT` → tiene plan/lineamientos en §4; ejecutar.
   - si `⬜ PENDING` → leer lineamientos; avanzar en orden (no saltar pasos).
4. Reverificar las migraciones reales de TODAS las tablas que el paso vaya a tocar
   (nunca asumir de memoria; `.opencode/rules/restricciones.md` §1).
5. Respetar a rajatabla las decisiones de §3 (no re-negociar sin permiso explícito) y
   las reglas duras de AGENTS.md / laravel-skills.md / restricciones.md.
6. Por cada entregable: tests Pest nuevos + `vendor/bin/pint <archivos>` + suite completa verde.
7. NO salir del alcance: si un archivo fuera del paso también necesita cambio, **informar**,
   no cambiarlo sin avisar.

## 7. Pendientes abiertos y aclaraciones

- **Paso 6 — BLOQ.1 `whenLoaded` incluía claves de relaciones no cargadas (RESUELTO):**
  - Síntoma: `tests/Feature/ActuadoResourceTest.php` → `it('omite relaciones anidadas cuando no estan cargadas')`
    esperaba que clave `tipo_actuado` (y `estado_anterior`, `estado_nuevo`, `usuario`, `adjuntos`) quedara
    **ausente** del JSON sin carga, pero aparecía.
  - **NO era bug de Laravel** (descartado con evidencia, no hipótesis):
    - `relationLoaded('tipoActuado')` → `false` (correcto); sin `$with`, accessors ni appends en el modelo.
    - `toArray()` retorna el array **crudo** con la clave presente como instancia `MissingValue`
      (`is_null=false`, `instanceof MissingValue=true`). No pasa por `filter()`/`removeMissingValues()`.
    - `resolve()` (camino real de serialización HTTP) → `filter()` → `removeMissingValues()` **sí la elimina**.
    - Verificado en tinker: keys de `toArray()` incluían `tipo_actuado`; keys de `resolve()` = `id, fecha_hora,
      descripcion, hash_anterior, hash_actuado`.
  - Causa raíz: **los tests llamaban `toArray(request())`** en vez de `resolve(request())`.
  - Fix: cambiar `->toArray(request())` → `->resolve(request())` en los 4 test files
    (`Actuado/Expediente/Plazo/ParteResourceTest`, 10 ocurrencias). Producción intacta: los Resources aún
    no se usan en controllers; Laravel resolverá vía `resolve()` al devolver `new XResource($model)`.
- **Paso 6 — BLOQ.2 `ParteResourceTest` `vigente_desde` null (RESUELTO):**
  - Causa: `Parte::create()` sin `vigente_desde` usa `useCurrent()` en BD (migración `partes`), pero el
    atributo en memoria queda `null` hasta `->refresh()` (mismo caso que `hash_actuado` en actuados).
  - Fix aplicado: `$parte->refresh()` tras cada `Parte::create()` en los 2 tests antes de serializar.
- **Paso 7 — BLOQ.1 `Controller` base sin `AuthorizesRequests` (RESUELTO):**
  - Síntoma: `ExpedienteController::show/bandejaSorteo` lanzaban
    `Call to undefined method App\Http\Controllers\ExpedienteController::authorize()` (500).
  - Causa: `app/Http/Controllers/Controller.php` (base) NO usaba el trait `AuthorizesRequests`,
    así que `$this->authorize()` no existía.
  - Fix: `use AuthorizesRequests;` en el `Controller` base (patrón Laravel estándar).
- **Paso 7 — BLOQ.2 resources single sin envoltura `data` (RESUELTO):**
  - Síntoma: los feature tests de controller obtenían `data.nurej_code` = `null` en `store/show/sortear`.
  - Causa: los endpoints usaban `response()->json((new XResource(...))->resolve($request))`, que
    devuelve el array plano SIN la clave `data`; en cambio `XResource::collection` sí la envuelve.
  - Fix: devolver los resources single **directos** (`new XResource(...)->response()->setStatusCode(201)`
    o `new XResource(...)`) en `ExpedienteController` y `ActuadoController`, que Laravel envuelve en `data`.
    Consistente con la serialización HTTP real (camino `resolve()`).
- **Paso 7 — BLOQ.3 limpieza de archivo huérfano (RESUELTO):**
  - El `catch` original en `ActuadoService` solo limpiaba si `guardarParaActuado` RETORNABA con éxito;
    si el propio `Adjunto::create` fallaba a mitad de método, el archivo quedaba huérfano.
  - Fix: la limpieza vive en `AdjuntoService::guardarParaActuado` (try/catch alrededor de `Adjunto::create`,
    borra el archivo físico antes de relanzar). `ActuadoService` quedó sin try/catch (ya no aplica código muerto).
- **Paso 8 — Adjuntos dummy reales (aprobado):** el seeder genera PDFs mínimos con
  `tempnam()` + `file_put_contents('%PDF-1.4...')` y construye `UploadedFile` real. El
  `AdjuntoService` guarda en `storage/app/adjuntos/` y calcula SHA-256. No usa `fake()`.
- **Paso 8 — Idempotencia:** guard-clause con `resumen_hechos LIKE '%[DEMO]%'`. No usa
  archivo flag (que sobrevive a `migrate:fresh` y puede quedar huérfano). No-destructivo.
- **Paso 8 — Auto-dependencia:** `ExpedienteDemoSeeder` llama a seeders base si las tablas
  están vacías (`Rol::count()===0` → `RolSeeder`, etc.), para poder correrse standalone
  con `db:seed --class=ExpedienteDemoSeeder` sin necesidad del `DatabaseSeeder` completo.
- **Paso 9 — Reproducción fiel del hash del trigger:** MySQL almacena JSON y `CAST(... AS CHAR)`
  con formato propio — el JSON lleva un espacio tras los dos puntos (`{"descripcion": "..."}`) y el
  `timestamp` se guarda con precisión de segundos. Para reproducir el SHA-256 hay que **leer la fila
  cruda con `DB::table(...)`** (`expediente_id`, `catalogo_actuado_id`, `usuario_id`, `fecha_hora`
  como string, `contenido` como string cruda), NO `json_encode()` del modelo ni `format()` del Carbon.
- **Paso 9 — Helpers de semilla por archivo:** al correr un archivo de test suelto, Pest NO carga las
  helpers (`paso7SemillaController`, etc.) definidas en otro archivo de test. Cada archivo nuevo define
  sus propios helpers locales (`paso9Semilla*`), incluso si repiten lógica.
- **Paso 9 — Logout real con token:** usar `$this->withToken($realPlainTextToken)` (no `Sanctum::actingAs`)
  para que `currentAccessToken()` apunte al registro real y `->delete()` lo revoque de verdad.
- **Paso 9 — Cache del guard de Sanctum en un mismo test:** el guard (singleton del contenedor) cachea
  `$user` entre requests del mismo test. Para validar que un token revocado ya NO autentica, llamar
  `$this->app['auth']->forgetGuards()` entre la petición de logout y la de verificación.
- **Paso 7 — nota de tests `registerActuado`:** capturar `$estadoAnteriorEsperado = $expediente->estado_actual_id`
  ANTES de llamar al servicio, porque `registerActuado` muta `estado_actual_id` sobre la misma instancia.
- **Esquema (§2) — pendiente de verificar e incorporar:** durante la revisión del Paso 6 se detectaron
  columnas/tablas que el plan §2 no documenta (p.ej. campos de `usuarios` y `plazos`, y tablas completas
  no listadas). Queda **PENDIENTE** ampliar §2 como tarea aparte, verificando contra las **migraciones
  reales** del proyecto al momento en que se necesiten (Paso 8 o más adelante), no contra un SQL de
  referencia que puede diferir de lo aplicado en BD.
- **ADMIN** en `ExpedientePolicy::crearActuado` no tiene excepción (decidido); si más
  adelante se quiere superpoder, tratarlo aparte.
- **Catálogo de `partes.tipo`**: solo se validan `DENUNCIANTE`/`DENUNCIADO` (evidencia del
  SRS RF-02). Si el SRS define más valores, ajustar `StoreExpedienteRequest` con permiso.
- **`via`**: solo `TECNICO/JURIDICO/FINANCIERO` aprobados (Acuerdos 022, 54, 55).
- **Esquema a reverificar antes de Pasos 8-10**: `reglamentos`, `usuarios`,
  `catalogo_estados`, `parametros_plazo`, `plazos`, `sesiones_acceso` y cualquier columna
  lista solo parcialmente en §2.
- **Feridos/días hábiles**: `PlazoCalculatorService` usa un calendario; el Paso 9 debe
  probar días hábiles reales con feriados.