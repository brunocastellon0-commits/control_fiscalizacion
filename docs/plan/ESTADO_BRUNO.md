# ESTADO — BRUNO (Dominio, Seguridad y Datos)

> **Archivo de retomado entre chats.** Actualizado 2026-10-22. Reemplaza la
> lectura completa de `PROGRESO.md`, `TAREAS_BRUNO.md` y `COORDINACION.md`.
> Contexto completo y reglas de propiedad: `docs/plan/CONTEXTO_BRUNO.md`.

## 1. Repositorio

- **Rama de trabajo:** `bruno`. `main` es ancestro de `bruno`: el código de la
  rama **no se ha mergeado** a `main`. Último merge documental a `main`:
  2026-10-22 (solo `docs/plan/`, sin código ni pruebas).
- `origin/brayan` ya es ancestro (integrado).
- Brayan trabaja en su propia rama: **no tocar sus archivos, no esperar sus
  tareas, no integrar su rama**.
- `git status` puede traer trabajo sin commitear: **conservarlo**, nunca
  checkout/commit/reset/limpieza sin orden explícita.

## 2. Tareas de `docs/plan/TAREAS_BRUNO.md`

- **Cerradas y commiteadas:** B2.0, B0.1, B0.3, B0.8, B3.3, B1.8, B1.1, B1.2,
  B1.3, B1.4, B1.5, B1.6, B1.7, B2.1, B2.2, B2.3 y B3.2.
- **B2.3 (2026-10-14):** decisión del usuario **A** — sin middlewares `can:`
  masivos; autorización vía FormRequest/`$this->authorize()`. Auditoría de las
  33 rutas de `core.php`/`operativo.php` + 3 huecos corregidos (sorteo
  individual, sorteo lote y apertura sin check de `activo`; fixes en
  `ExpedienteController` y `StoreExpedienteRequest`) + 3 tests nuevos en
  `ExpedientePolicyStrictTest`.
- **B3.2 (2026-10-22):** naturaleza de causa
  (`JURISDICCIONAL`/`ADMINISTRATIVA`) cerrada. Migración
  `2026_10_14_154521_add_naturaleza_to_expedientes_table` verificada como
  `Ran` en testing y `Pending` en desarrollo (no ejecutar en desarrollo).
- **B2.6 (siguiente, NO implementado):** contratos CTR-06/CTR-07 y extensión de
  historial por técnico aprobados y documentados para Brayan; los endpoints
  todavía no existen.
- **Pendientes en orden de dependencias:**
  1. Sprint 3: `B2.6` (CTR-06/CTR-07 + historial por técnico).
  2. Sprint 4: `B2.5` (guard inmutabilidad Actuado) → `B2.4` (auditoría
     append-only) → `B5.0` (9 reportes CTR-08 + ReportePolicy).
  3. Sprint 5: `B8.1`…`B8.7` (tests de dominio) → `B8.8` (cobertura > 40%).

## 3. Baseline de tests (verificado 2026-10-22)

`php artisan test --compact` → **426 tests · 419 OK · 0 fallos · 7 omitidos ·
2285 aserciones**. Cualquier tarea nueva parte de aquí y debe cerrar en 0 fallos.
Los dos fallos previos de `PlanificacionTest` (fechas fijas contra el reloj real)
se corrigieron con `Carbon::setTestNow('2026-09-10 10:00:00')` y
`Carbon::setTestNow()`. Formato: `vendor/bin/pint --dirty --format agent`.

## 4. Decisiones y bloqueos abiertos

- **B2.6:** aprobado CTR-06 paginado a 20, CTR-07 paginado a 15 con
  `requiere_accion`, devoluciones informativas e historial por técnico como
  endpoint separado (`CTR-06-H`). Implementación pendiente.
- **B8.8 bloqueada por Brayan:** `app/Services/CatalogoCacheService.php` (B7.2)
  no existe en el repo.
- **B8.3 desbloqueada:** `FeriadoSeeder` y `ParametroPlazoSeeder` ya existen.
- AUD-0011 (doble confirmación financiera): **pausada, no implementar**.

## 5. Reglas activas (resumen — mandan los archivos completos)

- `.opencode/rules/restricciones.md`: no alucinar esquema de BD (verificar
  migraciones siempre), seguridad sin excepciones, alcance estricto, ahorro de
  contexto, checklist de cierre.
- `.opencode/rules/laravel-skills.md`: MySQL/InnoDB, FKs reales, `$fillable`,
  Form Requests, Policies, `DB::transaction`, cobertura de tests obligatoria.
- Propiedad de archivos: `resources/views/**`, `AppServiceProvider`,
  `bootstrap/app.php`, `composer.json`, `.env.example`, `routes/api/admin|reportes|notificaciones.php`
  y FormRequests preexistentes → **Brayan**.
- `docs/auditoria/PROGRESO.md` solo se modifica cuando la tarea lo autoriza.

## 6. Cómo continuar en un chat nuevo

1. Estás en la rama `bruno`; confirma con `git status --short`.
2. Lee solo la sección de la tarea en `TAREAS_BRUNO.md` (`grep` por ID, p. ej. `B2.3`).
3. Ejecuta la tarea, corre sus tests + `pint --dirty`, y solo si la tarea lo
   autoriza actualiza `PROGRESO.md`/`BACKLOG_AUDITORIA.md`.
