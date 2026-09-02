# Sistema de Control y Fiscalización

Sistema **gubernamental** del Consejo de la Magistratura para el control y la fiscalización
de expedientes. La seguridad e integridad de los datos están por encima de la velocidad.
Ante cualquier duda entre *rápido* y *seguro/verificado*, se elige lo segundo.

## Stack

- **Backend:** Laravel (ver `composer.json`) + **MySQL** (InnoDB, `utf8mb4`/`utf8mb4_unicode_ci`).
  NO PostgreSQL/SQLite, ni sintaxis/tipos exclusivos de esos motores.
- **Autenticación:** Laravel Sanctum.
- **Frontend:** Blade + Alpine.js 3.x + Tailwind CSS vía CDN + Font Awesome (sin build de assets).

## Documentación de referencia

- Reglas duras de desarrollo: `.opencode/rules/` (`laravel-skills.md`, `restricciones.md`).
- Plan maestro backend: `.opencode/PLAN_WORKSTATION_EXPEDIENTES.md`.
- Plan de vistas/frontend (Paso 10): `.opencode/PLAN_VISTAS.md`.

---

## Decisiones técnicas y de seguridad

### 1. Autenticación stateful con cookies (NO localStorage)
El sistema maneja datos de fiscalización de alto valor (RF-03 compartimentos estancos,
cadena de custodia, inmutabilidad). Por eso:

- **NO se almacenan tokens Bearer en `localStorage`** (riesgo de exfiltración por XSS).
- Se usa **autenticación stateful con cookies `HttpOnly` + `SameSite` + CSRF**, habilitando el
  modo SPA stateful de Sanctum (`EnsureFrontendRequestsAreStateful`).
- El frontend (Alpine.js) envía las credenciales con `credentials: 'same-origin'` y el header
  `X-XSRF-TOKEN` (leído de la cookie `XSRF-TOKEN`).
- El cierre de sesión es stateful (destruye la sesión/cookie), no revoca un token Bearer.

### 2. Reutilización del backend al 100%
La workstation **no duplica lógica de negocio**: consume los endpoints `/api/*` existentes,
los FormRequests y las Policies. Cualquier regla (apertura de causa, sorteo, emisión de
actuados, RF-03) se valida en el backend; el frontend solo presenta y envía datos.

### 3. Autorización y compartimentos estancos (RF-03)
- Un expediente es visible/operable solo para quien tiene **asignación activa** o es la
  **Encargada** (jerarquía). Cualquier otro (incluido ADMIN) recibe `403`.
- Toda acción que modifica datos pasa por la **Policy** correspondiente
  (`ExpedientePolicy::crearActuado`, `ExpedientePolicy::view`, `bandejaSorteo`), nunca por
  chequeos dispersos de rol en los controladores.

### 4. Cadena de custodia e inmutabilidad
- Los `actuados` registran `hash_anterior`/`hash_actuado` (SHA-256) encadenados por la base
  de datos (triggers MySQL).
- **Los actuados son inmutables**: cualquier `UPDATE`/`DELETE` directo es bloqueado por
  trigger (`SIGNAL SQLSTATE '45000'`). La corrección de un error se hace con un **Actuado
  de Enmienda**, nunca editando el registro.

### 5. Semáforo de plazos
- El semáforo (VERDE/AMARILLO/ROJO/FUERA_DE_PLAZO) se calcula con **días hábiles reales**
  (fines de semana, feriados y suspensiones) mediante `PlazoCalculatorService` +
  `SemaforoPlazoService`. El frontend pinta el color, pero el cómputo vive en el backend.

### 6. Auditoría
- Los accesos se auditan en `sesiones_acceso` (login exitoso/fallido, IP, `logout_at`).
- Cambios sobre datos sensibles se registran sin exponer contraseñas ni tokens.

---

## Estado del proyecto

- **Backend (Paso 0–9):** completado y testeado. Suite verde:
  `php artisan test --compact` → **92 passed / 310 assertions**.
- **Frontend / Workstation (Paso 10):** en desarrollo, base stateful (ver `.opencode/PLAN_VISTAS.md`).

## Instalación rápida

```bash
composer install
cp .env.example .env        # configurar MySQL
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve           # http://localhost:8000
```

Usuarios demo (ver `database/seeders/UsuarioSeeder.php`): `encargada`, `tecnico`,
`aud_juridico`, `aud_financiero`, `admin` (password según seeder).

## Pruebas

```bash
php artisan test --compact
```

Las pruebas usan una base MySQL real (`control_fiscalizacion_test`) para validar triggers y
comportamiento fiel (no SQLite).
