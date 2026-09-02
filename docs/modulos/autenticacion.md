# Módulo: Autenticación (Login, Logout, Sesión Stateful)

> **Documento generado por ingeniería inversa.** Todo lo que aquí se describe está
> respaldado por código real leído del repositorio. Donde algo no se encontró,
> se indica explícitamente.

---

## 1. Puntos de entrada (rutas)

### `routes/api.php`

| Método | URI | Controlador | Middlewares | Descripción |
|--------|-----|-------------|------------|-------------|
| `POST` | `/api/login` | `AuthController::login` | `throttle:login` | Login stateful (pública) |
| `POST` | `/api/logout` | `AuthController::logout` | `auth:sanctum`, `throttle:api` | Cierre de sesión |
| `GET` | `/api/me` | `AuthController::me` | `auth:sanctum`, `throttle:api` | Usuario autenticado |

### `routes/web.php`

| Método | URI | Controlador | Middlewares | Descripción |
|--------|-----|-------------|------------|-------------|
| `GET` | `/login` | Closure (vista `auth.login`) | ninguno | Vista Blade del formulario |
| `GET` | `/expedientes` | `WorkstationController::bandejaOperador` | `auth` | Bandeja operador |
| `GET` | `/bandeja/sorteo` | `WorkstationController::bandejaSorteo` | `auth` | Bandeja encargada |
| `GET` | `/expedientes/nuevo` | `WorkstationController::apertura` | `auth` | Apertura causa |
| `GET` | `/expedientes/{expediente}` | `WorkstationController::detalle` | `auth` | Detalle expediente |

> Las rutas `web.php` usan middleware `auth` (guard `web`, driver `session`).
> Las rutas `api.php` usan `auth:sanctum`, que en modo stateful (habilitado por
> `$middleware->statefulApi()` en `bootstrap/app.php`) resuelve la sesión via
> cookies HttpOnly.

---

## 2. Flujo completo del login

```
1.  Browser → GET /sanctum/csrf-cookie          (obtiene cookie de sesión + CSRF)
2.  Browser → POST /api/login  { username, password }
3.      └─ throttle:login                        (5 req/min por IP, AppServiceProvider:25)
4.      └─ LoginRequest::rules()                 (valida: username required, password required)
5.      └─ AuthController::login()
6.          ├─ $request->hasSession()             (rechaza si no hay sesión → 403)
7.          ├─ Usuario::with('rol')->where('username')->first()
8.          ├─ Hash::check($password, $usuario->password_hash)
9.          │   ├─ Si falla → SesionAcceso::create(exitoso=false) → 401
10.         │   └─ Si pasa ↓
11.         ├─ $usuario->activo == false?
12.         │   ├─ Sí → SesionAcceso::create(exitoso=false) → 403
13.         │   └─ No ↓
14.         ├─ SesionAcceso::create(exitoso=true)     (auditoría RNF-01)
15.         ├─ Auth::guard('web')->login($usuario)    (sesión stateful)
16.         └─ JSON 200 { message, usuario: { id, ci, nombre, username, rol } }
```

### Flujo del logout

```
1.  Browser → POST /api/logout   (con cookie de sesión)
2.      └─ auth:sanctum middleware   (resuelve usuario desde sesión)
3.      └─ AuthController::logout()
4.          ├─ SesionAcceso::where(usuario_id, ...)->whereNull('logout_at')
5.          │   ->latest('login_at')->first()?->update(['logout_at' => now()])
6.          ├─ $request->user()->currentAccessToken()
7.          │   └─ Si es PersonalAccessToken → $token->delete()
8.          ├─ Auth::guard('web')->logout()
9.          ├─ $request->session()->invalidate()
10.         ├─ $request->session()->regenerateToken()    (CSRF token)
11.         └─ JSON 200 { message: "Sesión cerrada exitosamente" }
```

### Flujo de /me

```
1.  Browser → GET /api/me   (con cookie de sesión)
2.      └─ auth:sanctum middleware
3.      └─ AuthController::me()
4.          └─ $request->user()->load('rol')
5.          └─ JSON 200 { id, ci, nombres, apellidos, cargo, username, activo, rol: {...} }
```

---

## 3. Todos los archivos involucrados

| Archivo (ruta completa) | Tipo | Qué hace exactamente en este flujo | Con qué otros archivos se conecta |
|---|---|---|---|
| `routes/api.php` | Rutas | Define los 3 endpoints de auth (`/login`, `/logout`, `/me`) y aplica middlewares `throttle:login` y `auth:sanctum` + `throttle:api` | `AuthController`, `AppServiceProvider` (rate limiters) |
| `routes/web.php` | Rutas | Define la vista `/login` (closure Blade) y rutas workstation protegidas por `auth` | `WorkstationController` |
| `app/Http/Controllers/AuthController.php` | Controller | Implementa `login()`, `logout()`, `me()`. Orquesta validación, auditoría y sesión | `LoginRequest`, `Usuario`, `SesionAcceso`, `Auth` facade, `Hash` facade, `PersonalAccessToken` |
| `app/Http/Controllers/Controller.php` | Base Controller | Clase abstracta base con trait `AuthorizesRequests` (habilita `$this->authorize()` en todos los controllers) | Todos los controllers |
| `app/Http/Controllers/WorkstationController.php` | Controller | Rutas SPA Blade protegidas por middleware `auth`. Usa `$this->authorize()` contra `ExpedientePolicy` | `ExpedientePolicy`, vistas Blade |
| `app/Http/Requests/LoginRequest.php` | FormRequest | Valida campos obligatorios del login: `username` (required\|string), `password` (required\|string) | `AuthController` |
| `app/Models/Usuario.php` | Model | Modelo auth. Extiende `Authenticatable`. Usa `HasApiTokens` (Sanctum). Override `getAuthPassword()` → `password_hash`. Relación `rol()`, `sesiones()` | `Rol`, `SesionAcceso`, `Expediente`, `Actuado`, `Asignacion`, `Adjunto` |
| `app/Models/SesionAcceso.php` | Model | Tabla `sesiones_acceso`. Registro de auditoría de cada intento de login/logout con IP, timestamps y exitoso | `Usuario` |
| `app/Models/Rol.php` | Model | Catálogo de roles. Constantes: `ENCARGADA`, `TECNICO`, `AUD_JURIDICO`, `AUD_FINANCIERO`, `ADMIN`. Relación `usuarios()` | `Usuario` |
| `config/auth.php` | Config | Guard `web` (driver session), provider `users` → `Usuario::class`. Password broker configurado pero sin uso | `Usuario` |
| `config/sanctum.php` | Config | Guard `['web']`, stateful domains (localhost variants), token expiration `null`, middleware stack (EncryptCookies, CSRF, AuthenticateSession) | `bootstrap/app.php` |
| `config/session.php` | Config | Driver `database`, lifetime 120min, httpOnly `true`, sameSite `lax`, serialization `json` | Tabla `sessions` de MySQL |
| `bootstrap/app.php` | Config | `$middleware->statefulApi()` — habilita stack completo de middleware stateful de Sanctum para rutas API | `config/sanctum.php` |
| `app/Providers/AppServiceProvider.php` | Provider | Registra rate limiters: `login` (5/min por IP), `api` (60/min por user ID o IP) | `routes/api.php` |
| `database/migrations/2026_08_25_191145_create_roles_table.php` | Migration | Tabla `roles`: `id` (smallIncrements), `codigo` (unique, 30), `nombre` (80), `descripcion` (text, nullable) | `Rol` model |
| `database/migrations/2026_08_25_191146_create_usuarios_table.php` | Migration | Tabla `usuarios`: `id`, `ci` (unique, 20), `nombres`, `apellidos`, `cargo` (nullable), `username` (unique, 60), `password_hash`, `rol_id` (FK→roles), `activo` (default true), timestamps | `Usuario` model |
| `database/migrations/2026_08_25_191147_create_sesiones_acceso_table.php` | Migration | Tabla `sesiones_acceso`: `id`, `usuario_id` (FK→usuarios), `ip_origen` (45, nullable), `login_at` (timestamp), `logout_at` (nullable), `exitoso` (boolean). Sin timestamps | `SesionAcceso` model |
| `database/migrations/2026_08_27_172654_create_personal_access_tokens_table.php` | Migration | Tabla `personal_access_tokens` de Sanctum: `id`, `tokenable` (morph), `name`, `token` (unique, 64), `abilities`, `last_used_at`, `expires_at`, timestamps | `Usuario` (via `HasApiTokens` trait) |
| `database/seeders/UsuarioSeeder.php` | Seeder | Crea 5 usuarios de prueba (uno por rol) con password `password123` usando `Hash::make()` | `Usuario`, `Rol` |
| `database/factories/UsuarioFactory.php` | Factory | Factory de `Usuario` para tests. Password: `Hash::make('password')` | `Usuario`, `Rol` |
| `app/Policies/ExpedientePolicy.php` | Policy | Autorización por rol y compartimento. Métodos: `view`, `crearActuado`, `bandejaSorteo`, `operadorBandeja`, `aperturaCausa`, `verCatalogoActuados/Reglamentos/Estados` | `Usuario`, `Rol`, `Expediente`, `CatalogoActuado` |
| `app/Policies/UsuarioPolicy.php` | Policy | `viewOperativos()`: solo rol ENCARGADA activa puede ver catálogo de usuarios operativos | `Usuario`, `Rol` |
| `tests/Feature/AuthFeatureTest.php` | Test | 13 tests: login stateful, rechazo sin sesión, /me, auditoría sesiones (éxito/fallo/inexistente/inactivo), logout con revocación, rate limiting | `Usuario`, `SesionAcceso`, `Rol`, `Sanctum` |
| `tests/Feature/SecurityCompartimentosTest.php` | Test | 9 tests RF-03: compartimentos estancos, operador bloqueado ante expedientes ajenos, encargada por jerarquía, inactivo bloqueado, sin token bloqueado | `Usuario`, `Expediente`, `Asignacion`, `Actuado`, `Rol`, `Sanctum` |

### Archivos compartidos con otros módulos

- `Controller.php` — base de todos los controllers del sistema
- `Usuario.php` — modelo compartido por auth, expedientes, actuados, adjuntos, asignaciones
- `Rol.php` — catálogo usado por policies y controllers de todos los módulos
- `ExpedientePolicy.php` — protege endpoints de expedientes, actuados, catálogos y descargas
- `AppServiceProvider.php` — rate limiting global, no solo de auth

---

## 4. Seguridad

### 4.1 Tipo de token y mecanismo de autenticación

**Sanctum stateful (cookies HttpOnly).** No se usan JWT ni Bearer tokens en el
flujo principal. El sistema está diseñado exclusivamente para una SPA workstation
de primera parte.

Evidencia en `AuthController::login()` (`app/Http/Controllers/AuthController.php:18-25`):
```php
// El login es exclusivo de la workstation (SPA stateful). Se rechaza a
// cualquier cliente que no provenga de un frontend con sesión (sin
// cookie de sesión), cerrando así la superficie de tokens Bearer.
if (! $request->hasSession()) {
    return response()->json([
        'message' => 'Autenticación no disponible para este cliente. Utilice la workstation.',
    ], 403);
}
```

El guard utilizado es `web` (session driver), registrado en `config/auth.php:41-46`:
```php
'guards' => [
    'web' => [
        'driver' => 'session',
        'provider' => 'users',
    ],
],
```

### 4.2 Contenido de la sesión / payload

No hay JWT ni payload de claims. La sesión de Laravel se almacena como JSON
serializado en la tabla `sessions` de MySQL (driver `database`). La cookie HttpOnly
contiene solo el ID de sesión; el payload en la tabla incluye el `user_id` serializado.

La respuesta del login (`AuthController::login():75-84`) devuelve información del
usuario al frontend pero **no** un token:
```php
return response()->json([
    'message' => 'Autenticación exitosa',
    'usuario' => [
        'id' => $usuario->id,
        'ci' => $usuario->ci,
        'nombre' => $usuario->nombres.' '.$usuario->apellidos,
        'username' => $usuario->username,
        'rol' => $usuario->rol->codigo,
    ],
], 200);
```

Los tests confirman que no se emite token Bearer (`tests/Feature/AuthFeatureTest.php:53`):
```php
$response->assertJsonMissingPath('token');
```

### 4.3 Validación del token en cada request

**Rutas API (`routes/api.php`):** middleware `auth:sanctum` en el grupo (línea 17).
Sanctum intenta autenticar por session cookie (modo stateful). Si la cookie es
válida, resuelve el usuario desde la tabla `sessions`.

**Rutas web (`routes/web.php`):** middleware `auth` (línea 12), que usa el guard
`web` (session driver) directamente.

**Stack de middleware stateful** habilitado en `bootstrap/app.php:15-17`:
```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->statefulApi();
})
```

Esto instala: `EncryptCookies`, `AddQueuedCookiesToResponse`, `StartSession`,
`ShareErrorsFromSession`, `VerifyCsrfToken`, `AuthenticateSession` para las
rutas API.

### 4.4 Expiración y refresh

- **Sesión:** 120 minutos de lifetime (`config/session.php:35`, `.env`:
  `SESSION_LIFETIME=120`). No expira al cerrar el navegador
  (`SESSION_EXPIRE_ON_CLOSE=false`).
- **Tokens Sanctum:** `expiration => null` en `config/sanctum.php:53` — los tokens
  Bearer (si se crearan) **nunca** expirarían. Pero en el flujo actual no se
  crean tokens; solo se usa la sesión.
- **Password timeout:** `AUTH_PASSWORD_TIMEOUT=10800` (3 horas) en
  `config/auth.php:116` — usado por `ConfirmPassword` middleware (no aplicado a
  ningún endpoint actualmente).

**No hay mecanismo de refresh de sesión automático.** La sesión se mantiene viva
mientras el usuario realice requests dentro del window de 120 minutos.

### 4.5 Almacenamiento server-side del token/sesión

La sesión se persiste en la tabla `sessions` de MySQL (driver `database`).

**No hay blacklist explícita de tokens.** El logout cierra la sesión actual:
- Actualiza `sesiones_acceso.logout_at` (auditoría)
- Revoca `PersonalAccessToken` si existe (solo vía API con Bearer token)
- `Auth::guard('web')->logout()` elimina la sesión del handler
- `$request->session()->invalidate()` destruye la sesión actual
- `$request->session()->regenerateToken()` regenera el CSRF token

### 4.6 Rate limiting

Definido en `app/Providers/AppServiceProvider.php:25-27`:
```php
RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)->by(
    $request->user()?->id ?: $request->ip()
));
```

| Límite | Aplicado en | Clave |
|--------|-------------|-------|
| 5 req/min por IP | `POST /api/login` | `throttle:login` |
| 60 req/min por user ID (o IP si anónimo) | Todas las rutas protegidas de API | `throttle:api` |

**No hay bloqueo de cuenta por intentos fallidos.** Si un usuario intenta
login 100 veces con contraseña incorrecta, solo se aplicará rate limiting
(5/min). No hay lógica que bloquee la cuenta después de N intentos fallidos.

### 4.7 Políticas de autorización (Policies)

No hay `AuthServiceProvider.php` ni definiciones `Gate::`. Las policies se
auto-descubren por convención (Laravel 11+).

**`app/Policies/ExpedientePolicy.php`** — 8 métodos:

| Método | Aplicado en | Lógica |
|--------|-------------|--------|
| `view()` | `GET /api/expedientes/{id}`, `GET /expedientes/{id}` | ENCARGADA ve todo; operador ve solo asignado; creador ve hasta sorteo (RF-03) |
| `crearActuado()` | `POST /api/expedientes/{id}/actuados` | Rol del catálogo debe coincidir + asignación activa (o ENCARGADA por jerarquía) |
| `bandejaSorteo()` | `GET /bandeja/sorteo` | Solo ENCARGADA activa |
| `operadorBandeja()` | `GET /api/bandeja`, `GET /expedientes` | TECNICO, AUD_JURIDICO, AUD_FINANCIERO activos |
| `aperturaCausa()` | `GET /expedientes/nuevo` | Solo TECNICO activo |
| `verCatalogoActuados()` | `GET /api/catalogo/actuados` | Todos los roles activos |
| `verCatalogoReglamentos()` | `GET /api/reglamentos` | Todos los roles activos |
| `verCatalogoEstados()` | `GET /api/estados` | Todos los roles activos |

Todos verifican `$user->activo` primero (retorno `false` si inactivo).

**`app/Policies/UsuarioPolicy.php`** — 1 método:

| Método | Aplicado en | Lógica |
|--------|-------------|--------|
| `viewOperativos()` | `GET /api/usuarios` | Solo ENCARGADA activa |

### 4.8 Credenciales inválidas / cuentas bloqueadas

| Escenario | Código HTTP | Mensaje | Evidencia |
|-----------|-------------|---------|-----------|
| Usuario no existe o contraseña incorrecta | 401 | `"Credenciales inválidas."` | `AuthController::login():44-46` |
| Cuenta desactivada (`activo=false`) | 403 | `"Usuario inactivo. Contacte al Administrador."` | `AuthController::login():58-60` |
| Cliente sin sesión (no SPA) | 403 | `"Autenticación no disponible para este cliente. Utilice la workstation."` | `AuthController::login():22-24` |
| Sin autenticación | 401 | ( respuesta JSON de Sanctum ) | Test: `tests/Feature/AuthFeatureTest.php:159` |
| Rate limiting excedido | 429 | ( respuesta de Laravel ) | Test: `tests/Feature/AuthFeatureTest.php:209-220` |

**Importante:** cuando el usuario existe pero la contraseña falla, SÍ se registra
el intento fallido en `sesiones_acceso` (`AuthController::login():35-41`). Cuando
el usuario no existe, NO se registra nada (`AuthController::login():35` — solo
entra al `if ($usuario)`).

---

## 5. Integridad criptográfica y cadena de custodia

**Este módulo NO interactúa con `hash_anterior`/`hash_actuado` ni con triggers
de integridad de la cadena de custodia.**

Los campos de cadena de custodia (`hash_actuado`, `hash_anterior`) pertenecen a
la tabla `actuados` y son gestionados exclusivamente por:
- Trigger `trg_actuados_hash_before_insert` (BEFORE INSERT en `actuados`)
- Trigger `trg_actuados_inmutable_update` (BEFORE UPDATE — bloquea)
- Trigger `trg_actuados_inmutable_delete` (BEFORE DELETE — bloquea)

Definidos en `database/migrations/2026_08_28_182631_create_actuados_triggers.php`.
El modelo `Actuado` excluye `hash_actuado` y `hash_anterior` de `$fillable`.

Sin embargo, el módulo de autenticación **sí impacta indirectamente** la cadena
de custodia porque el campo `usuario_id` en la tabla `actuados` registra quién
emitió cada actuado. Este `usuario_id` se obtiene del usuario autenticado vía
`$request->user()->id` en `ActuadoController`. Si la autenticación fuera
comprometida, se podrían emitir actuados bajo identidad falsa.

---

## 6. Lógica de negocio

### 6.1 Autenticación exclusiva SPA stateful

El login rechaza explícitamente cualquier cliente sin sesión HTTP
(`AuthController::login():21`). Esto cierra la superficie de ataque de tokens
Bearer para clientes externos. El sistema solo acepta conexiones desde la
workstation (SPA Blade servida por Laravel).

### 6.2 Autenticación por username (no email)

El campo de identificación es `username` (columna `VARCHAR(60) UNIQUE` en
`usuarios`). No hay autenticación por email. La tabla `usuarios` tiene campo
`ci` (cédula de identidad) pero no se usa para login.

### 6.3 Campo de contraseña personalizado

La columna se llama `password_hash` (no `password`). El modelo `Usuario` puentea
esto con `getAuthPassword()` (`app/Models/Usuario.php:37-40`):
```php
public function getAuthPassword(): string
{
    return $this->password_hash;
}
```

Esto permite que `Hash::check()` y `Auth::guard('web')->login()` funcionen
correctamente con el nombre de columna no estándar.

### 6.4 Hashing de contraseñas

Se usa `Hash::make()` (bcrypt, el default de Laravel) en todos los puntos de
creación:
- `UsuarioSeeder::run()` — `Hash::make('password123')` para usuarios de prueba
- `UsuarioFactory::definition()` — `Hash::make('password')` para tests
- Tests feature — `Hash::make('password')` en setup manual

En `.env.example` se configura `BCRYPT_ROUNDS=12`.

### 6.5 Auditoría de acceso (RNF-01)

Cada intento de login (exitoso o fallido) se registra en `sesiones_acceso`:
- **Login exitoso:** `exitoso=true`, `logout_at=null`
- **Login fallido (contraseña):** `exitoso=false`, `logout_at=null`
- **Usuario inactivo:** `exitoso=false`, `logout_at=null`
- **Usuario inexistente:** NO se registra nada
- **Logout:** se actualiza `logout_at` con `now()` en el registro más reciente
  sin cerrar del usuario

### 6.6 Roles del sistema

Constantes definidas en `app/Models/Rol.php:13-21`:

| Constante | Código | Acceso principal |
|-----------|--------|------------------|
| `CODIGO_ENCARGADA` | `ENCARGADA` | Bandeja de sorteo, todos los expedientes, catálogo de usuarios operativos |
| `CODIGO_TECNICO` | `TECNICO` | Bandeja de operador, apertura de causas, actuados de registro |
| `CODIGO_AUD_JURIDICO` | `AUD_JURIDICO` | Bandeja de operador, actuados de admisibilidad |
| `CODIGO_AUD_FINANCIERO` | `AUD_FINANCIERO` | Bandeja de operador, actuados financieros |
| `CODIGO_ADMIN` | `ADMIN` | Acceso a catálogos, sin acceso directo a expedientes (no tiene `view`) |

### 6.7 No hay registro ni reset de contraseña

El sistema **no tiene** endpoint de registro de usuarios ni de reset/recovery de
contraseña. Los usuarios se crean vía seeder o directamente en la base de datos.
Las tablas `users` y `password_reset_tokens` son scaffolding de Laravel no utilizado.

---

## 7. Configuración y variables de entorno

| Variable | Valor por defecto | Archivo de config | Para qué sirve |
|----------|-------------------|-------------------|----------------|
| `AUTH_GUARD` | `web` | `config/auth.php:20` | Guard por defecto de autenticación |
| `AUTH_PASSWORD_BROKER` | `users` | `config/auth.php:21` | Broker de reset de contraseña (no usado) |
| `AUTH_PASSWORD_TIMEOUT` | `10800` (3h) | `config/auth.php:116` | Timeout de confirmación de contraseña |
| `SESSION_DRIVER` | `database` | `config/session.php:21` | Driver de almacenamiento de sesiones |
| `SESSION_LIFETIME` | `120` (min) | `config/session.php:35` | Tiempo de vida de la sesión |
| `SESSION_EXPIRE_ON_CLOSE` | `false` | `config/session.php:37` | Si expira al cerrar navegador |
| `SESSION_ENCRYPT` | `false` | `config/session.php` | Si encripta datos de sesión |
| `SESSION_HTTP_ONLY` | `true` | `config/session.php` | Cookie accesible solo vía HTTP (no JS) |
| `SESSION_SAME_SITE` | `lax` | `config/session.php` | Política SameSite de la cookie |
| `SESSION_SECURE_COOKIE` | `true` | `.env.example:37` | Solo envía cookie por HTTPS |
| `SESSION_PATH` | `/` | `.env.example:33` | Path de la cookie |
| `SESSION_DOMAIN` | `null` | `.env.example:34` | Dominio de la cookie |
| `SANCTUM_STATEFUL_DOMAINS` | `localhost,localhost:3000,127.0.0.1,...` | `config/sanctum.php:21-26` | Dominios que usan modo stateful |
| `SANCTUM_TOKEN_PREFIX` | `""` | `config/sanctum.php:68` | Prefijo de tokens API |
| `BCRYPT_ROUNDS` | `12` | `.env.example:16` | Rounds de bcrypt para hashing |

---

## 8. Diagrama de flujo

```
┌─────────┐     ┌──────────────┐     ┌──────────────────┐
│ Browser  │────▶│ GET          │────▶│ Devuelve vista   │
│          │     │ /login       │     │ auth.login       │
└─────────┘     └──────────────┘     └──────────────────┘
      │
      │  GET /sanctum/csrf-cookie
      │  (obtiene cookie de sesión + XSRF-TOKEN)
      ▼
┌──────────────┐
│ POST         │
│ /api/login   │
│ {username,   │
│  password}   │
└──────┬───────┘
       │
       ▼
┌──────────────────────────────┐
│ throttle:login               │  ◀── 5 req/min por IP
│ (AppServiceProvider:25)      │
└──────┬───────────────────────┘
       │
       ▼
┌──────────────────────────────┐
│ LoginRequest::rules()        │  ◀── username: required|string
│ (FormRequest validation)     │      password: required|string
└──────┬───────────────────────┘
       │
       ▼
┌──────────────────────────────┐
│ AuthController::login()      │
│                              │
│  1. hasSession()?            │──No──▶ 403 "Utilice la workstation"
│                              │
│  2. Usuario::where(username) │──No──▶ 401 "Credenciales inválidas"
│     Hash::check()            │
│                              │
│  3. $usuario->activo?        │──No──▶ 403 "Usuario inactivo"
│                              │
│  4. SesionAcceso::create()   │  ◀── auditoría RNF-01
│     (exitoso=true)           │
│                              │
│  5. Auth::guard('web')       │
│     ->login($usuario)        │  ◀── sesión stateful en MySQL
│                              │
│  6. JSON 200                 │──▶ Browser recibe datos del usuario
│     { message, usuario }     │     y queda autenticado vía cookie
└──────────────────────────────┘
       │
       │  Todas las requests siguientes incluyen cookie HttpOnly
       ▼
┌──────────────────────────────┐
│ Rutas protegidas             │
│ (auth:sanctum / auth)        │
│                              │
│  GET /api/me                 │──▶ JSON { usuario con rol }
│  POST /api/logout            │──▶ Cierra sesión + CSRF regen
│  GET /api/expedientes/*      │──▶ Policy: view, crearActuado, etc.
│  GET /expedientes/*          │──▶ Policy: expediente operador, etc.
└──────────────────────────────┘
```

---

## 9. Cómo probar / debuggear

### Tests existentes

| Archivo | Tests | Qué cubre |
|---------|-------|-----------|
| `tests/Feature/AuthFeatureTest.php` | 13 | Login stateful, rechazo sin sesión, /me, auditoría de sesiones (éxito, fallo, usuario inexistente, inactivo), logout con revocación de token, rate limiting (5/min) |
| `tests/Feature/SecurityCompartimentosTest.php` | 9 | RF-03 compartimentos estancos: operador bloqueado ante ajeno, detalle con asignación activa, admin sin bypass indebido, encargada por jerarquía, inactivo bloqueado, sin token bloqueado |

**Para ejecutar solo los tests de auth:**
```bash
php artisan test --compact --filter=AuthFeatureTest
```

**Para ejecutar solo los tests de compartimentos:**
```bash
php artisan test --compact --filter=SecurityCompartimentosTest
```

**Suite completa:**
```bash
php artisan test --compact
```

### Tips de debug

1. **Si el login falla sin mensagem clara:** revisa que la cookie de sesión
   esté habilitada en el navegador y que `SESSION_DOMAIN` no esté configurada
   a un dominio distinto al que sirve la app.

2. **Si `/api/me` devuelve 401 pese a estar logueado:** verifica que el
   dominio esté en `SANCTUM_STATEFUL_DOMAINS` (`config/sanctum.php:21`). Si
   usas un puerto distinto a 8000, agrégalo.

3. **Si el login devuelve 403 "Utilice la workstation":** la petición no tiene
   sesión HTTP. Asegúrate de que el SPA está servido por la misma instancia de
   Laravel (no es un frontend separado en otro puerto sin configurar
   `SANCTUM_STATEFUL_DOMAINS`).

4. **Si la sesión expira inesperadamente:** revisa `SESSION_LIFETIME` en `.env`
   (default 120 min) y `SESSION_DRIVER` (debe ser `database`, no `file` si hay
   múltiples servidores).

5. **Para inspeccionar la tabla de sesiones en MySQL:**
   ```sql
   SELECT id, user_id, ip_address, last_activity FROM sessions;
   ```

6. **Para inspeccionar auditoría de accesos:**
   ```sql
   SELECT sa.*, u.username
   FROM sesiones_acceso sa
   JOIN usuarios u ON u.id = sa.usuario_id
   ORDER BY sa.login_at DESC
   LIMIT 20;
   ```

7. **Si los tests fallan por sesión:** los tests usan driver `array` (no
   database). `phpunit.xml` configura `SESSION_DRIVER=array`. Los tests de
   auth simulan frontend con `pasoActuarComoFrontend()` que envía headers
   `Origin` y `Referer` a `http://localhost`.

---

## 10. Preguntas abiertas / puntos no claros

### 10.1 Import sin usar en `config/auth.php:3`

```php
use App\Models\User;  // ← no existe esta clase
use App\Models\Usuario;
```

`App\Models\User` se importa pero nunca se usa. Es un artefacto del scaffold
de Laravel. No hay archivo `app/Models/User.php`. **Acción sugerida:** eliminar
el import muerto.

### 10.2 Tabla `users` sin usar

La migración `0001_01_01_000000_create_users_table.php` crea las tablas
`users`, `password_reset_tokens` y `sessions`. La tabla `users` no es
utilizada por ninguna parte de la aplicación. Las tablas `sessions` y
`password_reset_tokens` sí se usan (sessions por el driver de sesión,
password_reset_tokens como scaffold pero sin flujo de reset).

**Acción sugerida:** evaluar si se puede eliminar la tabla `users` o si
debe mantenerse por compatibilidad con paquetes de terceros.

### 10.3 No hay endpoint de registro de usuarios

Los usuarios se crean únicamente vía `UsuarioSeeder` o directamente en BD.
No existe controlador, ruta ni vista para crear usuarios desde la aplicación.

### 10.4 No hay flujo de reset/recovery de contraseña

Aunque `config/auth.php` configura un password broker (líneas 96-103), no
hay controlador, ruta ni vista que implemente el flujo de recuperación de
contraseña. La tabla `password_reset_tokens` existe pero no se usa.

### 10.5 No hay bloqueo de cuenta por intentos fallidos

Si un usuario intenta login con contraseña incorrecta múltiples veces, solo
se aplica rate limiting (5/min por IP). No hay lógica que cuente intentos
fallidos consecutivos y bloquee la cuenta temporal o permanentemente.

### 10.6 No hay invalidación de todas las sesiones de un usuario

Cuando un admin desactiva un usuario (`activo=false`), las sesiones activas
existentes no se invalidan. El usuario seguiría autenticado hasta que la
sesión expire por timeout (120 min) o haga logout.

### 10.7 No hay middleware custom

El directorio `app/Http/Middleware/` no existe. Todo el middleware es
framework-provided. Esto es válido para Laravel 11+ pero limita la
personalización.

### 10.8 Logout no valida existencia de sesión

En `AuthController::logout():90-94`, el operador `?->update()` maneja el caso
de que no haya sesión abierta, pero `Auth::guard('web')->logout()` se ejecuta
siempre, incluso si el usuario no tenía sesión (podría generar una excepción
en algunos escenarios edge-case).

### 10.9 `password_reset_tokens` tiene configuración pero sin uso

`config/auth.php:96-103` define `expire: 60` y `throttle: 60` para el
broker de contraseñas. Esto no tiene efecto práctico ya que no hay flujo
de reset implementado.

### 10.10 No hay logging de autenticación a archivo

Los intentos de login se registran en `sesiones_acceso` (tabla de MySQL),
pero no hay `Log::info()` o similar que envíe estos eventos a un canal
de logging. Si la BD fuera inaccesible, se perdería el registro de
auditoría de acceso.
