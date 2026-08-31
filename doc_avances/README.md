# Doc Avances — Bitácora de Cambios

Registro cronológico de los cambios realizados sobre el proyecto, para
revisión posterior.

## 2026-08-27 — Seeders de catálogos y datos base

### Objetivo
Generar seeders idempotentes para desbloquear el desarrollo y pruebas del
sistema (catálogos, reglamentos, plazos, feriados, usuarios de prueba y
actuados de flujo). Persisten con `updateOrCreate()` → re-ejecutables.

### Cambios realizados

1. **`app/Models/ParametroPlazo.php`** (corrección bloqueante)
   - `$fillable`: se cambió `'tipo_plazo_id'` → `'tipo_plazo'`.
   - Motivo: la columna real de la migración es `tipo_plazo` (VARCHAR), no
     existía `tipo_plazo_id`. Sin esta corrección el seeder de plazos no
     guardaba datos.
   - Además se corrigió `$table`: `'parametro_plazo'` → `'parametros_plazo'`
     (la tabla real en BD es plural, según la migración).

2. **`database/seeders/RolSeeder.php`** (nuevo)
   - 5 roles: ENCARGADA, TECNICO, AUD_JURIDICO, AUD_FINANCIERO, ADMIN.
   - Idempotencia por `codigo`.

3. **`database/seeders/CatalogoEstadoSeeder.php`** (nuevo)
   - 9 estados; OBSERVADO y RECHAZADO anidados bajo EN_EVALUACION.
   - `es_final = true` en CONCLUIDO y ARCHIVO_DEFINITIVO.
   - Subestados resueltos en segunda pasada (padre resolvido por `codigo`).

4. **`database/seeders/ReglamentoSeeder.php`** (nuevo)
   - AC_022_2018, AC_054_2018, AC_055_2018 (todos v1.0).

5. **`database/seeders/CatalogoRequisitoSeeder.php`** (nuevo)
   - Requisitos de prueba por reglamento (FK `reglamento_id` resolvida por código).

6. **`database/seeders/ParametroPlazoSeeder.php`** (nuevo)
   - Plazos según especificación (EVALUACION/SUBSANACION/EJECUCION_*/DESCARGOS).

7. **`database/seeders/FeriadoSeeder.php`** (nuevo)
   - 5 feriados de referencia 2026 para cálculo de días hábiles.

8. **`database/seeders/UsuarioSeeder.php`** (nuevo)
   - 1 usuario por rol; contraseña `password123` (**bcrypt** / `Hash::make`);
     rol resuelto por `codigo`.

9. **`database/seeders/CatalogoActuadoSeeder.php`** (nuevo)
   - 6 actuados del flujo; FKs `rol_id` y `estado_origen/destino` resueltas.

10. **`database/seeders/DatabaseSeeder.php`** (reemplazado)
    - Se eliminó la llamada al factory de `User` (tabla default de Laravel).
    - Se registran los 8 seeders en orden respetando las Foreign Keys.

### Notas / pendientes detectados (no resueltos en esta tarea)
- `database/factories/UserFactory.php` apunta a la tabla `users` default de
  Laravel; no coincide con el esquema `usuarios`. No se usa actualmente.

### Verificación
- `php artisan db:seed` ejecutado con éxito (todos los seeders DONE).
- Registros cargados: roles=5, estados=9, reglamentos=3, requisitos=7,
  plazos=7, feriados=5, usuarios=5, actuados=6.
- Login de prueba verificado: `Hash::check('password123', password_hash)` → SI.

## 2026-08-27 — Adaptación del software al script SQL real de la BD

### Objetivo
Alinear los modelos Eloquent al esquema real definido en el script SQL MySQL
(fuente de verdad). Se adaptó el software a la BD, no al revés. No se tocaron
migraciones ni seeders (ya coincidían con el script).

### Cambios realizados
1. **`app/Models/Actuado.php`** — `$table` `'actuado'` → `'actuados'`;
   comentario interno "trigger de PostgreSQL" → "trigger de MySQL".
2. **`app/Models/Parte.php`** — `$table` `'parte'` → `'partes'`;
   `$fillable` `cargo_institucional` → `cargo_institucion` (columna real).
3. **`app/Models/EvaluacionAdmisibilidad.php`** — `$table`
   `'evaluacion_admisibilidad'` → `'evaluaciones_admisibilidad'`.
4. **`app/Models/SuspensionPlazo.php`** — `$table` `'suspension_plazo'` →
   `'suspensiones_plazo'`; se eliminó `creadted_at` (typo) de `$fillable` y
   `$casts` (`created_at` es DEFAULT CURRENT_TIMESTAMP en BD, no se inserta).
5. **`app/Models/Plazo.php`** — `$fillable` `'fuera_plazo'` → `'fuera_de_plazo'`.
6. **`app/Models/Impugnacion.php`** — `$fillable` `'actuado:resolucion_id'`
   (typo con `:`) → `'actuado_resolucion_id'`.
7. **`app/Models/Usuario.php`** — `sesiones()` ahora apunta a
   `SesionAcceso::class` (antes referenciaba `Sesion::class` inexistente).
8. **`app/Models/Transferencia.php`** — **nuevo** modelo para la tabla
   `transferencias` (`expediente_id, unidad_destino, actuado_remision_id,
   actuado_recepcion_id, estado`) con relaciones a Expediente y Actuado.

### No se tocaron
- `app/Models/Expediente.php` (se dejó `created_at` en `$fillable`, según
  decisión de no incluir el ajuste opcional).
- Migraciones y seeders (ya alineados con el script).

### Verificación
- `php artisan tinker`: app carga OK; relaciones verificadas:
  `Usuario::sesiones()` → `SesionAcceso`, `Expediente::transferencias()` →
  `Transferencia`.
- `php -l` sin errores de sintaxis; `vendor/bin/pint app\Models` aplicó
  formato PSR-12.

## 2026-08-27 — Revisión y refactor del servicio de cálculo de plazos

### Objetivo
Revisar `CalculadoraPlazosService` y alinearlo a las reglas del proyecto.
Se resolvió el problema principal detectado (lectura repetida de BD / N+1) y
se renombró la nomenclatura a inglés (regla 5). Regla de negocio confirmada
por el usuario: **el día de inicio NO cuenta** (el plazo corre desde el día
hábil siguiente).

### Cambios realizados
1. **`app/Services/CalculadoraPlazosService.php`** → renombrado y refactorizado
   a **`app/Services/PlazoCalculatorService.php`**.
   - Clase: `CalculadoraPlazosService` → `PlazoCalculatorService`.
   - Métodos (español → inglés): `calcularVencimiento()` → `calculateDueDate()`,
     `diasRestantes()` → `daysRemaining()`, `esFechaSuspendida()` →
     `isSuspendedDate()`.
2. **Solución N+1 / queries repetidas** — antes se ejecutaba `Feriado::pluck()`
   y `SuspensionPlazo::all()` en cada invocación de ambos métodos. Ahora el
   constructor acepta `?Collection $feriados` y `?Collection $suspensiones`
   (inyectables para testing) y si no se pasan, se cargan **una sola vez** por
   instancia del servicio (`loadFeriados()` / `loadSuspensiones()`). Elimina
   las queries repetidas y permite testear sin tocar BD.
3. **Limpieza** — se quitó el import `CarbonInterface` sin uso; se usa
   `$fechaInicio->copy()` para no mutar el argumento original.
4. **Lógica de negocio preservada** — `calculateDueDate` NO cuenta el día de
   inicio; `daysRemaining` cuenta días hábiles excluyendo hoy e incluyendo el
   día límite; `isSuspendedDate` usa rango inclusivo `[inicio, fin]`.
   Se verificó contra el script SQL: tablas `feriados` y `suspensiones_plazo`
   con columnas correctas (ya alineadas en la tarea previa).

### Tests agregados
- **`tests/Feature/PlazoCalculatorServiceTest.php`** (nuevo, Pest): 6 casos
  cubriendo salto de fines de semana, feriados, suspensiones, no contar el día
  de inicio, plazo vencido y días restantes. **6 pasados, 7 aserciones.**
- **En pausa:** los tests del servicio de plazos quedan suspendidos hasta
  configurar la BD SQLite de pruebas (opción A: el constructor ya no inyecta
  datos; requiere BD real con `feriados`/`suspensiones_plazo`).

### Archivos eliminados
- `app/Services/CalculadoraPlazosService.php` (reemplazado por la versión en inglés).

### Verificación
- `php artisan test --filter=PlazoCalculatorServiceTest` → todos pasan.
- `vendor/bin/pint --format agent` aplicado sin errores.

## 2026-08-27 — Creación de `ActuadoService` (alineado al schema real)

### Objetivo
Crear el servicio de registro de actuados. El código inicial fue generado por
una IA sin conocer el esquema y contenía **múltiples nombres de columnas
inventados**. Se reescribió completo alineado al script SQL real.

### Errores del código original corregidos
| En la IA (mal) | Columna/modelo real |
|---|---|
| `usuario_emisor_id` | `usuario_id` |
| `descripcion`/`metadatos` (columnas) | `contenido` (JSON) |
| `hash_integridad` | `hash_actuado` (calculado por trigger, no en PHP) |
| `Asignacion.fecha_inicio/fin` | `fecha_asignacion` |
| `Plazo.actuado_origen_id`/`dias_habiles`/`fecha_vencimiento` | `actuado_disparador_id`/`dias_habiles_otorgados`/`fecha_limite` |
| `Calc...Service::calcularVencimiento` | `PlazoCalculatorService::calculateDueDate` |
| `CatalogoActuado.tipo_plazo_asociado` (no existe) | mapeo en PHP (`MAPA_TIPO_PLAZO`) |

### Cambios realizados
- **`app/Services/ActuadoService.php`** (nuevo): `registerActuado()` en
  `DB::transaction()` que: crea el `Actuado` con columnas reales (sin tocar
  hashes, los calcula el trigger MySQL), transiciona el estado del expediente,
  reasigna la bandeja (baja `activa=false` + nueva con `rol_id` del destino y
  `actuado_origen_id`) y abre plazo si el actuado lo dispara.
- **Mapeo plazo** (constante `MAPA_TIPO_PLAZO`, sin tocar esquema):
  - `ACT_SORTEO_INICIAL` → EVALUACION
  - `ACT_OBSERVACION` → SUBSANACION
  - `ACT_ADMISION` → EJECUCION (subtipo JURISDICCIONAL si `via=JURIDICO`, si no ADMINISTRATIVA; fallback a `subtipo=NULL`)
  - resto → null (no abre plazo). Estructura lista para ampliar.

### Referencia técnica (nuevo)
- **`doc_Avances/SCHEMA_CONTEXTO.md`** — documentación canónica de todas las
  tablas del esquema (columnas reales, relaciones, triggers, reglas de negocio
  RN/RF y mapeo de plazos). Para consultar sin gastar tokens re-explicando.

### Verificación
- `php -l` sin errores; `vendor/bin/pint --format agent` OK.
- Smoke: el servicio se resuelve vía contenedor con `PlazoCalculatorService`
  inyectado; el mapa se lee correctamente.
- No se ejecutó `registerActuado` contra la BD real para no insertar datos de prueba.
