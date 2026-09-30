# PLAN DE EJECUCIÓN DE LA AUDITORÍA — FASE A FASE

**Archivo:** `docs/auditoria/PLAN_EJECUCION_AUDITORIA.md`
**Derivado de:** `PLAN_MAESTRO_AUDITORIA_Y_FINALIZACION_SISTEMA.md`
**Modalidad:** revisión en profundidad fase por fase, sin alucinar, actualizando los MD de `docs/auditoria/` a medida que se avanza.

---

## 0. PROTOCOLO ANTI-ALUCINACIÓN (regla transversal)

1. **Nada se afirma sin verificarlo en este turno**: ruta → controlador → policy → service → transacción → migración real → test. Si no se verificó, queda como `NO VERIFICADA`, nunca como "funciona".
2. **Ante un hallazgo complejo o "raro"**: no se fuerza un plan de solución. Se **escribe** lo encontrado en `docs/auditoria/BACKLOG_AUDITORIA.md` con evidencia (archivo:línea, query, test), se marca `BLOCKED` o `NEEDS_REVIEW` y se **sigue con la fase**. La solución se planifica después, con lo hallado delante.
3. **Contradicción SRS vs código → `AMBIGÜEDAD`** en el backlog, nunca interpretación propia.
4. Ninguna fase se da por cerrada sin su MD actualizado; ningún hallazgo se cierra sin `php artisan test --compact` en verde.
5. Sin operaciones destructivas (`migrate:fresh`, `wipe`, `truncate`, `DELETE` masivo). La BD de desarrollo contiene datos de prueba, pero aun así se pide confirmación antes de cualquier borrado.

---

## 1. ARTEFACTOS

Se crean/actualizan progresivamente en `docs/auditoria/`:

| Archivo | Fase que lo llena |
| --- | --- |
| `MAPA_FUNCIONAL.md` | Fase 0 (borrador) → Fase 1 |
| `MATRIZ_SRS_IMPLEMENTACION.md` | Fase 2 |
| `MATRIZ_SEGURIDAD.md` | Fase 3 |
| `MAQUINA_ESTADOS.md`, `MATRIZ_ACTUADOS.md` | Fase 4 |
| `MATRIZ_PLAZOS.md` | Fase 5 |
| `MATRIZ_PERMISOS.md`, `MATRIZ_FLUJOS.md` | Fase 6 |
| `MATRIZ_REPORTES.md` | Fase 12 |
| `BACKLOG_AUDITORIA.md` | continuo |
| `PROGRESO.md` | continuo (al cerrar cada fase) |
| `MATRIZ_COBERTURA_FINAL.md` | Fase 18 |
| `evidencias/` | continuo |

Insumos existentes que se **leen primero** (no se duplican ni se copian sin verificar):

- `doc_avances/SCHEMA_CONTEXTO.md`
- `doc_avances/BACKLOG_JIRA.md`
- `doc_avances/README.md` (bitácora de cambios)
- `docs/BACKEND_MODULES_TECHNICAL_GUIDE.md`
- `docs/modulos/autenticacion.md`
- SRS: `docs/Sistemas de Control y Fiscalizacion_ Analisis de requreimiento de Usuario1.docx`

---

## 2. FASE 0 — DESCUBRIMIENTO (bloque detallado)

- **0.1 Entorno**: `php -v`, `composer show --direct`, `config:show database.default`, versión de MySQL, charset/engine real de las tablas. Nada de supuestos.
- **0.2 Baseline de tests**: `php artisan test --compact` (BD de test `control_fiscalizacion_test`) → resultado inicial registrado en `PROGRESO.md`.
- **0.3 Inventario de rutas**: `php artisan route:list` → por cada una: middleware, auth, rol, policy. Tabla cruda en `MAPA_FUNCIONAL.md`.
- **0.4 Modelos vs migraciones**: los modelos contra las migraciones — FKs reales, `$fillable`, relaciones declaradas vs columnas existentes. **Prohibido asumir columnas.**
- **0.5 Capas**: controllers / services / policies / Form Requests / resources / commands → detectar endpoints sin policy, controllers con lógica de negocio, validación inline.
- **0.6 Frontend**: `resources/`, `package.json`, `vite.config.js` → stack de vistas, build pendiente.
- **0.7 Convenciones reales**: patrón de autorización actual (RBAC vs ownership), manejo de transacciones, formato de actuados, enums vs strings.
- **0.8 Cruce de docs previas vs código**: `SCHEMA_CONTEXTO`/`BACKLOG_JIRA` pueden estar desactualizados — se verifican, no se copian.
- **0.9 Extracción del SRS**: convertir el `.docx` a texto para poder citar secciones (RF/RN) en la matriz.
- **0.10 Entregables**: `MAPA_FUNCIONAL.md` (borrador con estados IMPLEMENTADA/PARCIAL/FALTANTE/…), `BACKLOG_AUDITORIA.md` sembrado, `PROGRESO.md` y **primer informe obligatorio** (sección 107 del Plan Maestro). Se entrega al usuario antes de continuar.

**Gate de salida Fase 0:** informe de estado general entregado y aprobado.

---

## 3. FASES 1-18 (cada una: revisión → MD → gate)

- **F1 Mapa funcional**: completar `MAPA_FUNCIONAL.md` módulo a módulo (Encargada, Técnico, Jurídico, Financiero, Admin). *Gate: cero filas `NO VERIFICADA` sin justificación.*
- **F2 Matriz SRS→código**: requisito por requisito del docx → `MATRIZ_SRS_IMPLEMENTACION.md`; las diferencias tecnológicas (p. ej. referencias a PostgreSQL) se marcan como tales, no como brecha.
- **F3 Seguridad/autorización**: prueba de IDOR por endpoint (GET/POST/PUT/PATCH/DELETE/download/export/search) contra NUREJ ajeno → `MATRIZ_SEGURIDAD.md` + tests feature nuevos.
- **F4 Estados y actuados**: inventario de estados en código/BD/frontend, transiciones y catálogo de actuados → `MAQUINA_ESTADOS.md` + `MATRIZ_ACTUADOS.md`; verificación de inmutabilidad (triggers `create_actuados_triggers`, `add_hash_trigger_lock`) con test que **espere el fallo** de UPDATE/DELETE.
- **F5 Plazos**: `PlazoCalculatorService`, `parametros_plazo`, feriados, suspensiones, versionado normativo → `MATRIZ_PLAZOS.md` + tests de días hábiles (lunes…feriado+fin de semana; 022/2018, 54/2018, 55/2018).
- **F6 Bandejas y roles**: 5 roles × acciones → `MATRIZ_PERMISOS.md` y `MATRIZ_FLUJOS.md` (qué entra/sale de cada bandeja y qué plazo dispara).
- **F7 Flujo Técnico**, **F8 Jurídico**, **F9 Financiero**: happy path + error + no autorizado + estado inválido + concurrencia; descargos financieros (5 días, pausa/reanudación de reloj).
- **F10 Encargada/dashboard**: bandeja con contexto completo, VB de cronograma y MPA; `EncargadaDashboardService` contra SQL real.
- **F11 NUREJ Padre/Hijo**: independencia de actuados y filtrado de línea de tiempo.
- **F12 Reportes**: dashboard vs API vs Excel/PDF vs SQL → `MATRIZ_REPORTES.md`.
- **F13 Jobs/cron**: `VerificarVencimientoPlazosCommand`, `MarcarPlazosVencidosService`, `ArchivoPorAbandonoService` → idempotencia, timezone, duplicados.
- **F14 UX/frontend**: botones sin acción, visibles para rol equivocado, errores sin mensaje.
- **F15 Rendimiento**: N+1, índices vs `EXPLAIN`, paginación server-side.
- **F16 Pruebas**: escenario integral mínimo (Registro→…→Salida) + suites negativas; cobertura de prioridades (seguridad, plazos, actuados).
- **F17 Hardening**: secretos, `dd()`/debug, rate limiting, exposición de datos, logs.
- **F18 Auditoría final**: `MATRIZ_COBERTURA_FINAL.md` + checklist de producción (sección 79 del Plan Maestro) + segunda y tercera ronda si quedan P0/P1/P2.

---

## 4. CADENCIA DE TRABAJO

Por cada hallazgo (regla "uno por uno"):

```text
leer → reproducir → causa → impacto
  → (si la solución no es obvia: documentar en BACKLOG y seguir)
  → implementar → vendor/bin/pint --dirty → php artisan test --compact
  → actualizar BACKLOG/PROGRESO → siguiente
```

Commits solo si lo pide el usuario.

---

## 5. ESTADO DE AVANCE

| Fase | Estado | Nota |
| --- | --- | --- |
| Fase 0 | CERRADA (2026-09-29) | ver `PROGRESO.md`; 16 hallazgos en `BACKLOG_AUDITORIA.md` |
| Fase 1 | CERRADA (2026-09-29) | `MAPA_FUNCIONAL.md` con tabla maestra 14 columnas; reconciliación 58/54 en §1.1; AUD-0017 nuevo |
| Fase 2 | CERRADA (2026-09-29) | `MATRIZ_SRS_IMPLEMENTACION.md`; DT-01/02 documentados; AUD-0018/0019 nuevos |
| Fase 3 | CERRADA (2026-09-29) | `MATRIZ_SEGURIDAD.md` (54 endpoints) + `SeguridadIdorTest` (16 tests nuevos); IDOR bloqueado salvo bypass ADMIN (AUD-0001) |
| Fase 4 | CERRADA (2026-09-29) | `MAQUINA_ESTADOS.md` + `MATRIZ_ACTUADOS.md`; inmutabilidad ya testeada; AUD-0020/0021/0022/0023 nuevos |
| Fase 5 | CERRADA (2026-09-29) | `MATRIZ_PLAZOS.md`; **AUD-0002 raíz completada** (H1 confirmada; **fix del test aplicado 2026-09-30 → ficha CERRADO**); AUD-0024/0025/0026 nuevos; AUD-0023 ampliado (8/17 parámetros) |
| Fase 6 | CERRADA (2026-09-29) | `MATRIZ_BANDEJAS.md` (matriz Plan §16 + §8.1); AUD-0027/0028/0029 nuevos |
| Fase 7 | CERRADA (2026-09-29) | `FLUJO_TECNICO.md` (Plan §11 sorteo + §12 flujo); AUD-0030/0031/0032 nuevos; O-6 |
| Fase 8 | CERRADA (2026-09-30) | `FLUJO_JURIDICO.md` + `FlujoJuridicoTest` (9 tests); AUD-0033 (P1 informe jurídico no emisible, verificado con test), AUD-0034/0035 nuevos |
| Fase 9 | CERRADA y APROBADA (2026-09-30) | `FLUJO_FINANCIERO.md` + `FlujoFinancieroTest` (4 tests: estado inválido 403, 422 sin reloj vigente, 422 reloj no suspendido, Bloqueo de Salida del informe SIN); AUD-0034 análisis completado (fix aprobado a futuro, sin autorización para tocar producto); O-8/O-9 → fichas **AUD-0036/AUD-0037** (observaciones P3); **AUD-0002 CERRADO** (fix del test) |
| Fase 10 | VALIDADA Y CERRADA (2026-09-30) | `FLUJO_ENCARGADA.md` + `FlujoEncargadaTest` (3 tests: 401 sin sesión, contexto completo del tablero, doble VB/devolución 403); dashboard **coincide con SQL real** en 13 campos (BD dev, solo lectura); VB cronograma/MPA cubierto por `PlanificacionTest` (15); **AUD-0003 CERRADO** (fix autorizado y aplicado en `EncargadaDashboardTest:149-150`); sin hallazgos nuevos de producto; suite 284: 277 OK · 1 fallo (AUD-0001) · 0 errores · 6 omitidos |
| Fase 11 | VALIDADA Y CERRADA (2026-09-30) | `FLUJO_NUREJ.md` + `FlujoNurejTest` (4 tests: independencia de línea de tiempo RN-10, hijo sin plazos/asignaciones + correlativo `-2` RF-01, partes copiadas independientes, sub-derivar → 422); esquema UNIQUE/FK verificado; **AUD-0038 CERRADO** (fix autorizado: 422 vía `bootstrap/app.php`); **AUD-0039 confirmada P2** (análisis de diseño obligatorio antes de fix, en fase siguiente); AUD-0040 (observación flake no reproducida); suite 288: 281 OK · 1 fallo (AUD-0001) · 0 errores · 6 omitidos |
| Fase 12 | **VALIDADA Y CERRADA (2026-09-30)** | `MATRIZ_REPORTES.md` (RF-R01…R09: pantalla 5/9, Excel 0/9, PDF 0/9; consistencia SQL↔dashboard↔monitoreo verificada en BD dev; §6 cierre de validación) + `ReportesAdminTest` (5 tests/34 aserciones); **AUD-0039: Opción B adoptada**; **AUD-0041 fix aplicado y ficha CERRADA**; cierre con decisiones: AUD-0001 = deuda conocida (fallo de suite conservado, sin maquillar; semántica ADMIN → hardening), AUD-0040 = monitorización, O-6 = aceptada provisionalmente, O-7 = sub-caso AUD-0023, AUD-0030/0031/0032 en lista de decisiones (no bloquean F12); **sin cambios de producto por la validación**; `NEEDS_REVIEW` = ninguno; suite cierre: 296: 289 OK · 1 fallo (AUD-0001, deuda) · 0 errores · 6 omitidos |
| Fase 13 | ENTREGADA (2026-09-30, pendiente validación de fase) | `MATRIZ_JOBS_CRON.md` (idempotencia ✓, **AUD-0043** corte UTC 21:00 ART reproducido, **AUD-0042** job crashe­a en BD dev `exit=1`, mapeo dependencias = acción AUD-0037(a), O-10 sin lock) + `VerificarVencimientoPlazosTest` (3 tests/17 aserciones); sin cambios de producto; suite 296: 289 OK · 1 fallo (AUD-0001) · 0 errores · 6 omitidos |
| Fases 14-18 | PENDIENTE | |
