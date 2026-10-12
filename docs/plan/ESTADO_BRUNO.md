# ESTADO — BRUNO (Dominio, Seguridad y Datos)

> **Archivo de retomado entre chats.** Actualizado 2026-10-12. Reemplaza la
> lectura completa de `PROGRESO.md`, `TAREAS_BRUNO.md` y `COORDINACION.md`.
> Contexto completo y reglas de propiedad: `docs/plan/CONTEXTO_BRUNO.md`.

## 1. Repositorio

- **Rama de trabajo:** `bruno` (HEAD `2abf0d1` "B1.6"), 8 commits por delante de
  `main` (`0418cb6`), **sin merge**. `origin/brayan` ya es ancestro (integrado).
- Brayan trabaja en su propia rama: **no tocar sus archivos, no esperar sus
  tareas, no integrar su rama**.
- `git status` puede traer trabajo sin commitear: **conservarlo**, nunca
  checkout/commit/reset/limpieza sin orden explícita.

## 2. Tareas de `docs/plan/TAREAS_BRUNO.md`

- **Cerradas y commiteadas:** B2.0, B0.1, B0.3, B0.8, B3.3, B1.8, B1.1, B1.2,
  B1.3, B1.4, B1.5, B1.6.
- **Cerradas pero SIN commitear** (diffs en el working tree): **B1.7, B2.1,
  B2.2** (+ `docs/plan/DIVERGENCIAS.md` sin trackear).
- **Pendientes en orden de dependencias:**
  1. Sprint 3: `B2.3` (Policies por asignación activa) → `B3.2` (naturaleza de
     causa) → `B2.6` (CTR-06/CTR-07).
  2. Sprint 4: `B2.5` (guard inmutabilidad Actuado) → `B2.4` (auditoría
     append-only) → `B5.0` (9 reportes CTR-08 + ReportePolicy).
  3. Sprint 5: `B8.1`…`B8.7` (tests de dominio) → `B8.8` (cobertura > 40%).

## 3. Baseline de tests (verificado 2026-10-12)

`php artisan test --compact` → **402 tests · 395 OK · 0 fallos · 7 omitidos ·
2193 aserciones**. Cualquier tarea nueva parte de aquí y debe cerrar en 0 fallos.
Formato: `vendor/bin/pint --dirty --format agent`.

## 4. Decisiones y bloqueos abiertos

- **`docs/plan/DIVERGENCIAS.md` §7 — 3 decisiones sin aprobar** (correcciones de
  B2.2: congelar plazos al solicitar incompetencia, bloquear CTR-04 durante
  espera de VB, hacer públicos `congelarPlazos`/`reanudarPlazos`). Plan listo,
  no ejecutado.
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
