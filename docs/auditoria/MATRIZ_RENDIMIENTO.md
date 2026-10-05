# MATRIZ_RENDIMIENTO — Auditoría de Rendimiento (Fase 15)

> Auditoría según `PLAN_EJECUCION_AUDITORIA.md` §3 ("F15 Rendimiento: N+1,
> índices vs `EXPLAIN`, paginación server-side") y Plan Maestro **§55**
> (`PLAN_MAESTRO_AUDITORIA_Y_FINALIZACION_SISTEMA.md:2201-2233`).
> Evidencia verificada el **2026-10-01** contra el repositorio y la BD de
> desarrollo (MySQL **9.7.0**, `php artisan config:show`/`db:table`/`EXPLAIN`).
> **F15 NO modificó producto**: sin índices nuevos, sin migraciones, sin
> `paginate()` nuevo, sin cambios en controllers/resources/frontend/tests.
> Toda recomendación queda como **PROPUESTA / PENDIENTE DE DECISIÓN**.

---

## §0 Estado de F15

| Ítem | Valor |
|---|---|
| Estado | **ENTREGADA — PENDIENTE DE VALIDACIÓN** |
| Fecha | 2026-10-01 |
| Entregable | `docs/auditoria/MATRIZ_RENDIMIENTO.md` (este archivo) |
| Hallazgos nuevos | **6** (AUD-0062 … AUD-0067): 2 P2 · 4 P3 |
| Observaciones | O-20, O-21 (sin ficha) |
| Cambios de producto | **NINGUNO** |
| Gates | ver §16 |

---

## §1 Alcance (plan existente, no redefinido)

Del Plan §79 y Maestro §55: N+1 queries · consultas repetidas · paginación ·
índices (usados/faltantes, orden de compuestos, justificación por patrón real
de consulta) · joins · reportes pesados · exportaciones · dashboard · carga de
bandejas · búsquedas por NUREJ · `EXPLAIN` · "no cargar miles de expedientes al
navegador" · "no agregar índices indiscriminadamente".

**Prohibido en F15 (§13 del prompt de ejecución):** crear índices, migraciones,
`paginate()` nuevo, alterar consultas, tocar controllers/resources/frontend/
tests. **Permitido:** lectura, inspección de BD, consultas de lectura, `EXPLAIN`,
medición de queries, tests existentes, documentación.

---

## §2 Método y herramientas de medición

| Técnica | Uso en F15 |
|---|---|
| `php artisan db:table <tabla>` | esquema e índices reales (no asumidos) |
| `EXPLAIN FORMAT=TRADITIONAL` vía `DB::select('EXPLAIN …')` | plan de ejecución (MySQL 9.7 devuelve formato árbol por defecto; se forzó `FORMAT=TRADITIONAL` para obtener `type/key/rows/Extra`) |
| `DB::listen` + `php artisan tinker --execute` | conteo de queries por petición en modo **solo lectura** (sin tocar código productivo) |
| Grep/read de controllers, resources, services, migraciones | riesgo N+1 estático, inventario de `->get()` |

Línea base de comparación: consultas "propias" de un endpoint = carga principal +
eager loads declarados.

---

## §3 Superficie auditada

- **Controllers:** `ExpedienteController`, `Administrador\AdminDashboardController`,
  `Administrador\AdminMonitoreoController`, `Administrador\AdminUsuariosController`,
  `Administrador\AdminFeriadosController`, `UsuarioController`,
  `CatalogoActuadoController`, `CatalogoEstadoController`, `ReglamentoController`,
  `EvaluacionAdmisibilidadController`, `AdjuntoController` (lectura).
- **Resources:** `ExpedienteResource`, `PlazoResource`, `ActuadoResource`,
  `ParteResource`.
- **Services:** `EncargadaDashboardService`, `SemaforoPlazoService`,
  `PlazoCalculatorService`, `SorteoAlgorithmService`, `ExpedienteService`,
  `ArchivoPorAbandonoService`, `MarcarPlazosVencidosService`,
  `EvaluacionAdmisibilidadService`, `NurejHijoService`.
- **Migraciones/BD:** `expedientes`, `asignaciones`, `actuados`, `plazos`,
  `sesiones_acceso`, `partes`, catálogos (`db:table` real, §4).
- **Reportes:** **no existen endpoints de exportación** — F12
  (`MATRIZ_REPORTES.md:54-55`): "pantalla = parcial en 5/9; Excel = 0/9;
  PDF = 0/9" → no hay exportaciones pesadas que auditar; los "reportes" en
  operación son los agregados de dashboards/monitoreo (§11).

---

## §4 Índices reales vs columnas filtradas/ordenadas (esquema verificado)

| Tabla | Índices reales (`db:table`, 2026-10-01) | Uso real en consultas | Veredicto |
|---|---|---|---|
| `expedientes` | PK; UNIQUE `nurej_code`; `idx_expedientes_estado (estado_actual_id)`; `idx_expedientes_padre`; `idx_expedientes_via`; FK `reglamento_id`, `creado_por` (`mig 2026_08_25_191155:30-32` + FKs) | WHERE `estado_actual_id` ✓ (bandeja sorteo, monitoreo); WHERE `nurej_code =` ✓; **ORDER BY `fecha_ingreso` ✗** (ambas bandejas + monitoreo + "últimos" del dashboard); WHERE `via` (GROUP BY) ✓; LIKE `%…%` ✗ | Falta índice para el patrón de orden por `fecha_ingreso` → **AUD-0064** |
| `asignaciones` | PK; `idx_asignaciones_exp_activa (expediente_id, activa)`; UNIQUE `uq_asignacion_activa (activa_key)`; FK `usuario_id`, `rol_id`, `actuado_origen_id` | `whereHas(asignacionActiva…usuario_id)` ✓ (EXPLAIN usa `asignaciones_usuario_id_foreign`, subquery MATERIALIZED); carga por expediente ✓ | Suficiente para los patrones actuales |
| `actuados` | PK; **`idx_actuados_exp_fecha (expediente_id, fecha_hora)`**; FKs (catalogo, estados, usuario, referencia) | Timeline `WHERE expediente_id ORDER BY fecha_hora` ✓ (`type=ref`, sin filesort) | Índice compuesto correcto y en uso |
| `plazos` | PK; solo FKs: `expediente_id`, `parametro_plazo_id`, `actuado_disparador_id`, `actuado_cierre_id` | `WHERE expediente_id` ✓ (FK); **`WHERE estado='VIGENTE' AND fecha_limite<CURDATE()` ✗ → `type=ALL`** (cron diario); **sin UNIQUE `(expediente_id, tipo_plazo)`** | Índice de cron faltante → **AUD-0066**; unicidad → **AUD-0036** (sin cambios) |
| `sesiones_acceso` | PK; FK `usuario_id` (**no hay índice en `login_at` ni `(exitoso, login_at)`**) | `ORDER BY login_at DESC LIMIT 6` y `WHERE exitoso=0 AND login_at>=…` en el dashboard admin (`AdminDashboardController:159-174`) → **`type=ALL` + `Using filesort`** | Falta índice justificado por patrón real → **AUD-0065** |
| `partes` | PK; `idx_partes_expediente (expediente_id, es_version_actual)`; FK | `WHERE expediente_id AND es_version_actual` ✓ (`type=ref`) | OK |
| catálogos (`catalogo_*`, `reglamentos`, `feriados`, `usuarios`) | UNIQUE por código/CI/username/fecha (§ migraciones) | búsquedas de catálogo por id/código ✓ | OK |

**Regla §55 aplicada:** ningún índice propuesto sin consulta real que lo
justifique; los tres propuestos (§12) tienen patrón de consulta identificado y
`EXPLAIN` que muestra el acceso actual (`type=ALL` o `Using filesort`).

---

## §5 Matriz principal — endpoint/área vs criterios §55

Convención paginación: **S** = `paginate()` server-side; **N** = `get()` sin
límite; **N\*** = `get()` sin límite pero acotado por diseño/catálogo.

| Área / Endpoint | Consulta principal | Filtros | ORDER BY | Joins / eager | Índices usados | Pag. | N+1 | Consultas repetidas | Evidencia | Resultado | Hallazgo | Recomendación |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| `GET /api/bandeja` (operador) | `Expediente` + `whereHas(asignacionActiva)` | `usuario_id`, `activa` | `fecha_ingreso DESC` (sin índice) | 12 relaciones eager (`ExpedienteController:171-186`) | asignaciones FK ✓; expedientes sin índice de orden | **S** (`:76`, 15/pág) | **SÍ** — ver §7 | **26 de 43 queries** = `feriados`+`suspensiones` repetidas | `ExpedienteController:70-77`; medición §7 | Page correcta; **60% de queries redundantes** | **AUD-0062**, AUD-0064, AUD-0018 | Cachear/unificar `PlazoCalculatorService` (PROPUESTA) |
| `GET /api/bandeja/sorteo` | `WHERE estado_actual_id=PENDIENTE_SORTEO` | estado | `fecha_ingreso DESC` | mismas 12 relaciones | `idx_expedientes_estado` ✓ + filesort | **S** (`:63`) | **SÍ** (mismo camino de resources) | ídem | `ExpedienteController:53-65`; EXPLAIN §6 | igual que arriba | **AUD-0062**, AUD-0064 | ídem |
| `GET /api/expedientes/{id}` (detalle) | load de 12 relaciones + resources | policy `view` | timeline por `fecha_hora` | `actuados.*` eager | `idx_actuados_exp_fecha` ✓, `idx_partes_expediente` ✓ | N/A (1 fila) | **SÍ**: 1 + 2×N plazos | 2 por plazo serializado | medición §7 (`detalle_show_full`: 12 queries) | Correcto; escala con # de plazos | **AUD-0062** | ídem |
| `GET /api/admin/monitoreo` | `Expediente::query()->with(7 relaciones)` + filtros | `buscar` (LIKE %…%), `responsable`, `orden`; `estado` **post-load en PHP** (`:302-312`) | `fecha_ingreso` (sin índice) | eager ✓ | `idx_expedientes_estado` ✓; LIKE ✗ | **N** (`:113` `$query->get()`) | **NO** (eager completo, medido11 queries) | no | `AdminMonitoreoController:34-113,302-312` | Sin N+1; **carga todos los expedientes** por diseño (semáforo/resumen computados en PHP) | AUD-0018 (ampliado, §9), **AUD-0067**, AUD-0064 | Paginación server-side requiere rediseño de resumen/estado computado (PROPUESTA) |
| `GET /api/admin/dashboard` | 12+ agregados | activo/rol/estado/vía | varios | `withCount` ✓ | índices de catálogo ✓ | N/A (agregados) | **NO** | 5 pasadas sobre `expedientes` (count, group, not-exists, **get completo**, take 5) | `AdminDashboardController:42-136`; medición §7 (23-24 queries) | Funciona con datos chicos; **carga completa de expedientes** | **AUD-0063**, AUD-0065 | Agregados en SQL (PROPUESTA) |
| `GET /api/encargada/dashboard` | agregados + `whereHas(plazos vigentes)` con selects acotados | — | `fecha_ingreso`, `fecha_limite` | eager selectivo (`EncargadaDashboardService:59-112`) | FK/idx ✓ | N/A | **NO** | múltiples agregados por request | `EncargadaDashboardService:21-198`; medición23 queries/30 ms | **Patrón correcto** (referencia para el dashboard admin) | — | Reusar patrón (PROPUESTA para AUD-0063) |
| `GET /api/admin/usuarios` | `Usuario` + filtros | rol/activo/búsqueda | — | rol eager | UNIQUE ci/username ✓ | **N** (`AdminUsuariosController:61`) | NO | no | `AdminUsuariosController:61` | Volumen acotado (usuarios) | AUD-0018 (menor) | Evaluar `paginate()` si crece (PROPUESTA) |
| `GET /api/usuarios` (operativos) | `Usuario` | rol activo | — | — | ✓ | **S** (`UsuarioController:40`) | NO | — | `UsuarioController:40` | OK (endpoint hoy sin uso en vistas → O-15) | — | — |
| `GET /api/admin/feriados` | `Feriado` | año/fechas | fecha | — | UNIQUE `fecha` ✓ | N\* (`AdminFeriadosController:51`) | NO | — | `:51` | Dataset acotado (feriados/año) | — | no requiere |
| Catálogos: `estados`, `reglamentos`, `actuados`, `requisitos` | `get()` puro | rol/reglamento | — | — | UNIQUE ✓ | N\* (`CatalogoEstadoController:32`, `ReglamentoController:31`, `CatalogoActuadoController:60`, `EvaluacionAdmisibilidadController:33`) | NO | — | grep §8 | Catálogos pequeños por diseño | — | no requiere |
| Cron `plazos:verificar-vencidos` | UPDATE/SELECT sobre `plazos` por `fecha_limite` | `tipo_plazo`, `estado`, `fuera_de_plazo`, `fecha_limite` | — | — | **solo FK** → `type=ALL` | N/A (lote) | NO | 2 queries conocidas por corrida | `MarcarPlazosVencidosService:26-31`; `ArchivoPorAbandonoService:49-54`; EXPLAIN §6 | Full scan diario | **AUD-0066** (+ AUD-0042 preexistente) | Índice `(estado, fecha_limite)` (PROPUESTA) |
| Búsqueda por NUREJ | `WHERE nurej_code LIKE '%x%' OR resumen_hechos LIKE '%x%'` | `buscar` | — | — | UNIQUE `nurej_code` **no usable** con comodín inicial | ver monitoreo | NO | — | `AdminMonitoreoController:52-57`; EXPLAIN §6 | `type=ALL` full scan | **AUD-0067** (+ AUD-0018) | Definir patrón de búsqueda (PROPUESTA) |
| Reportes / exportaciones | **no existen** (Excel 0/9, PDF 0/9) | — | — | — | — | — | — | — | `MATRIZ_REPORTES.md:54-55` | Nada que medir | — | — |

---

## §6 `EXPLAIN` ejecutados (MySQL 9.7.0, BD dev, 24 expedientes)

| # | Consulta (texto real evaluado) | type | key | rows | Extra | Lectura |
|---|---|---|---|---|---|---|
| 1 | `SELECT * FROM expedientes WHERE EXISTS (…asignaciones activa usuario_id=3) ORDER BY fecha_ingreso DESC LIMIT 15` (bandeja operador) | subq `ALL` + expedientes `eq_ref` (PK) + asignaciones `ref` (`asignaciones_usuario_id_foreign`) | PK / FK usuario | 1 / 1 / 3 | **Using temporary; Using filesort** | Eager subquery materializada ✓; **el orden global no tiene índice** → sort completo por página |
| 2 | `SELECT * FROM expedientes WHERE estado_actual_id=2 ORDER BY fecha_ingreso DESC LIMIT 15` (bandeja sorteo) | `ref` | `idx_expedientes_estado` | 4 | **Using filesort** | Índice de filtro ✓; orden ✗ |
| 3 | `SELECT * FROM expedientes WHERE estado_actual_id=2 ORDER BY fecha_ingreso DESC` (monitoreo con filtro) | `ref` | `idx_expedientes_estado` | 4 | Using filesort | ídem |
| 4 | `… WHERE nurej_code LIKE '%A-%' OR resumen_hechos LIKE '%A-%'` | **ALL** | NULL | 24 | Using where | Búsqueda no indexable con patrón actual (**AUD-0067**) |
| 5 | `… WHERE nurej_code = 'A-0001'` | const (UNIQUE) | `expedientes_nurej_code_unique` | — | no matching row in const table | El índice **sí** funciona con igualdad |
| 6 | `… WHERE resumen_hechos LIKE '%test%'` | **ALL** | NULL | 24 | Using where | ídem #4 |
| 7 | `SELECT * FROM actuados WHERE expediente_id=1 ORDER BY fecha_hora ASC` | `ref` | `idx_actuados_exp_fecha` | 1 | (ninguno) | Índice compuesto en uso correcto |
| 8 | `SELECT * FROM plazos WHERE expediente_id=1 AND estado='VIGENTE'` | `ref` | `plazos_expediente_id_foreign` | 1 | Using where | OK |
| 9 | `SELECT * FROM plazos WHERE estado='VIGENTE' AND fecha_limite < CURDATE()` (cron) | **ALL** | NULL | 8 | Using where | Full scan diario (**AUD-0066**) |
| 10 | `SELECT * FROM asignaciones WHERE usuario_id=3 AND activa=1` | `ref` | `asignaciones_usuario_id_foreign` | 3 | Using where | OK |
| 11 | `SELECT via, COUNT(*)… GROUP BY via` | `index` | `idx_expedientes_via` | 24 | Using index; temporary; filesort | Aceptable (agregado global) |
| 12 | `SELECT * FROM partes WHERE expediente_id=1 AND es_version_actual=1` | `ref` | `idx_partes_expediente` | 2 | (ninguno) | OK |
| 13 | `SELECT * FROM sesiones_acceso ORDER BY login_at DESC LIMIT 6` | **ALL** | NULL | 2 | **Using filesort** | Sin índice de orden (**AUD-0065**) |
| 14 | `SELECT count(*) FROM sesiones_acceso WHERE exitoso=0 AND login_at >= NOW()-1d` | **ALL** | NULL | 2 | Using where | Sin índice de filtro (**AUD-0065**) |

---

## §7 Medición dinámica de queries (`DB::listen`, solo lectura, BD dev)

| Escenario (reproducible con `tinker --execute`) | Queries | Tiempo | Composición relevante |
|---|---|---|---|
| `GET /api/bandeja` completo (serialize de la respuesta, 3 expedientes asignados al usuario) | **43** | 127 ms | **13× `select fecha from feriados` + 13× `select fecha_inicio, fecha_fin from suspensiones_plazo` = 26/43 (60%)**; resto =1 principal + 9 eager + policy/auth + `count(*)` de paginación |
| Serialización directa de **15** expedientes con `relacionesDetalle` | **32** | — | 22 repetidas (11+11) = 3 semáforos de expediente + 8 semáforos de `PlazoResource` |
| Detalle (`show`) con 12 relaciones cargadas | **12** | 13 ms | 2 repetidas por los plazos del expediente |
| `GET /api/admin/dashboard` | **23-24** | 65 ms | 2 repetidas (feriados/suspensiones del servicio) + **`select * from expedientes` completo** + 4 agregados adicionales sobre la misma tabla |
| `GET /api/encargada/dashboard` | **23** | 30 ms | agregados + `whereHas(plazos)` con select acotado |
| `GET /api/admin/monitoreo` | **11** | 24 ms | 1 principal + 7 eager + auth + relación de estados — **sin N+1** |

**Raíz de las consultas repetidas (AUD-0062):** cada
`app(SemaforoPlazoService::class)` (`ExpedienteResource.php:82`,
`PlazoResource.php:24`) construye una instancia nueva — **no hay `singleton`/
`bind` en `app/Providers` (grep = 0)** — y su constructor inyecta
`PlazoCalculatorService` (`SemaforoPlazoService.php:11-14`) cuyo constructor
ejecuta `loadFeriados()` (`PlazoCalculatorService.php:25,141-143`:
`Feriado::pluck('fecha')`) y `loadSuspensiones()` (`:149`) **una vez por
instancia**. Verificado: `app(SemaforoPlazoService::class)` dos veces → dos
instancias distintas y 4 queries extra (test de identidad `$a === $b` → `false`).

---

## §8 Inventario de paginación server-side (§6 del alcance)

**CON `paginate()`:** `ExpedienteController@bandejaSorteo:63`,
`ExpedienteController@bandejaOperador:76` (ambas 15/página, con `count(*)` y
`?page=` desde la vista), `UsuarioController@indexOperativos:40`.

**SIN paginación y NO acotado (volumen = tabla completa):**
`AdminMonitoreoController:113` (todos los expedientes),
`AdminDashboardController:95` (todos los expedientes + plazos),
`AdminUsuariosController:61` (todos los usuarios).

**SIN paginación pero acotado por diseño (N\*):** catálogos
(`CatalogoEstadoController:32`, `ReglamentoController:31`,
`CatalogoActuadoController:60`, `EvaluacionAdmisibilidadController:33`),
`AdminFeriadosController:51`, `EncargadaDashboardService` (`limit(5)/limit(3)`
en `:71,166`; `whereHas(plazos)` en `:88-113`), dashboards (`take(3/5/6)`),
`SorteoAlgorithmService:91` (candidatos activos), `ExpedienteService:159`
(solo `PENDIENTE_SORTEO`, por diseño del sorteo en lote),
`ArchivoPorAbandonoService:54` (vencidos del día), `NurejHijoService:82`
(partes de un padre), `EvaluacionAdmisibilidadService:50,182` (catálogo/poço).

**Criterio aplicado (no todo `get()` es defecto):** volumen potencial ×
propósito × uso desde frontend × límite existente × naturaleza del dataset.

---

## §9 Reauditoría de hallazgos existentes (sin modificar sus fichas)

### AUD-0018 (P2, Fase 2) — permanece **OPEN**; alcance **ampliado** en F15
- La evidencia histórica "**0 `paginate()`**" (`BACKLOG_AUDITORIA.md:275`) está
  **desactualizada**: hoy existen 3 usos (`ExpedienteController:63,76`,
  `UsuarioController:40`) y las bandejas **sí** usan paginación server-side con
  `count(*)` (medido: query `select count(*) … exists(asignaciones…)`).
- **Se mantiene sin cerrar** porque persisten sin paginar: monitoreo
  (`AdminMonitoreoController:113`), dashboard admin (`:95`) y usuarios admin
  (`AdminUsuariosController:61`), y **no existe ninguna ruta de búsqueda** en
  `routes/` (grep `buscar|search|q=` = 0); la única búsqueda es el parámetro
  `buscar` del monitoreo (AUD-0067).
- **Ampliación documentada:** para el monitoreo, agregar `paginate()` no es un
  cambio trivial: el filtro `estado` es **computado post-load**
  (`AdminMonitoreoController:295-312`, estados `POR_VENCER`/`FUERA_DE_PLAZO` no
  existen en BD) y el `resumen` se calcula sobre el conjunto completo
  (`:317-335`) → la paginación server-side exige un diseño previo (O-20).
- **No se modificó la ficha ni se cerró el hallazgo.**

### AUD-0036 (P3, Fase 9) — **confirmado, sin cambios**
- `php artisan db:table plazos` → la tabla **solo** tiene índices FK
  (`plazos_expediente_id_foreign`, `plazos_parametro_plazo_id_foreign`,
  `plazos_actuado_disparador_id_foreign`, `plazos_actuado_cierre_id_foreign`) y
  PK: **no existe índice único `(expediente_id, tipo_plazo)`**.
- Correspondencia con la ficha: `create_plazos_table` sin `->unique(...)` ✓.
- No se creó ni modificó ningún índice.

### AUD-0055 (P3, Fase 14) — **evidencia vigente, sin cambios**
- El código no cambió: `ExpedienteController:63` sigue con `paginate(15)` y
  `bandeja-sorteo.blade.php:219` sigue recargando conservando `current_page`
  tras sortear; el paginador de Laravel (`LengthAwarePaginator`) no recorta
  páginas fuera de rango → el comportamiento descrito en F14 permanece idéntico.
- **No se modificó la ficha.**

---

## §10 Búsqueda por NUREJ (§8 del alcance)

| Punto | Evidencia |
|---|---|
| Dónde | Único punto del sistema: `AdminMonitoreoController:52-57` (`nurej_code LIKE '%buscar%' OR resumen_hechos LIKE '%buscar%'`), invocado desde `monitoreo.blade.php:73,421-426` con `buscar` |
| Índice disponible | `expedientes_nurej_code_unique` (btree) |
| ¿Lo usa? | Solo con **igualdad**: EXPLAIN `nurej_code = 'A-0001'` → `const`/unique ✓. Con **comodín inicial** (`LIKE '%…%'`) → `type=ALL`, key NULL (EXPLAIN #4) |
| Búsqueda parcial | Sí, parcial en ambos extremos (`%x%`), sobre `nurej_code` **y** `resumen_hechos` (TEXT, no indexable con btree) |
| Múltiples filtros | `buscar` + `responsable` + `orden` combinables; todos sobre el mismo `get()` sin límite |
| Rutas dedicadas de búsqueda | **0** (grep en `routes/`) |

**Conclusión (sin proponer índice por el nombre de la columna):** el índice
único de `nurej_code` ya cubre el caso exacto; para el patrón `%…%` actual
**ningún índice btree sirve** → la recomendación es de **diseño de búsqueda**
(prefijo/`LIKE 'x%'`, autocompletado exacto, o FULLTEXT para `resumen_hechos`),
**PROPUESTA / PENDIENTE DE DECISIÓN** (AUD-0067).

---

## §11 Bandej­as, monitoreo, dashboard y reportes (§9 del alcance)

- **Bandej­as:** paginación ✓, eager completo ✓ (12 relaciones), orden por
  `fecha_ingreso` sin índice (AUD-0064), **N+1 de semáforo** por resource
  (AUD-0062). EXPLAIN de la subquery de asignación usa índice ✓.
- **Monitoreo:** 11 queries, **sin N+1** (eager de 7 relaciones, medido); carga
  total por diseño (semáforo/resumen en PHP, O-20); búsqueda `LIKE '%…%'` full
  scan (AUD-0067); orden sin índice (AUD-0064); sin paginación (AUD-0018
  ampliado).
- **Dashboard admin:** 23-24 queries/65 ms con datos de 24 expedientes;
  **carga la tabla completa de expedientes con plazos** (`AdminDashboardController:95`)
  y hace 5 pasadas sobre `expedientes` en una petición (count `:64`, group `:77`,
  not-exists `:88`, get `:95`, take(5) `:133`) → escalabilidad lineal con el
  volumen (AUD-0063). **Dashboard Encargada = patrón correcto a emular**
  (selects acotados, `whereHas`, `withCount`).
- **Reportes/exportaciones:** no existen (Excel 0/9, PDF 0/9 según
  `MATRIZ_REPORTES.md:54-55`) → sin riesgo de memoria/tiempo por exportación
  **hoy**; si se implementan en el futuro, deberán usar cursores/lotes (nota
  para F18, no es hallazgo).

---

## §12 Hallazgos nuevos (fichas en `BACKLOG_AUDITORIA.md`)

| ID | Sev. | Título | Evidencia clave |
|---|---|---|---|
| **AUD-0062** | P2 | N+1 de consultas de referencia: `feriados` + `suspensiones_plazo` se recargan por cada expediente/plazo serializado (26 de 43 queries en la bandeja) | `ExpedienteResource:82`, `PlazoResource:24`, `SemaforoPlazoService:11-14`, `PlazoCalculatorService:25,141-149`; sin singleton (grep providers = 0); medición §7 |
| **AUD-0063** | P2 | Dashboard admin carga la tabla completa de expedientes (con plazos) y la consulta 5 veces por petición | `AdminDashboardController:64,77,88,95,133`; `select * from expedientes` trazado en medición §7; contraparte correcta: `EncargadaDashboardService:88-113` |
| **AUD-0064** | P3 | Sin índice en `expedientes.fecha_ingreso` → `Using temporary; Using filesort` en bandejas y monitoreo | EXPLAIN #1-#3 (§6); `ORDER BY fecha_ingreso` en `ExpedienteController:62,75`, `AdminMonitoreoController:98,102,110`, `AdminDashboardController:134`; migración `…191155:30-32` |
| **AUD-0065** | P3 | `sesiones_acceso` sin índice en `login_at` / `(exitoso, login_at)`: el dashboard admin ordena y filtra esas columnas → `type=ALL` + filesort; crece con cada login | `db:table sesiones_acceso`; `AdminDashboardController:159-174`; EXPLAIN #13-#14 |
| **AUD-0066** | P3 | El cron diario de plazos hace full scan de `plazos` (sin índice `(estado, fecha_limite)`) | `MarcarPlazosVencidosService:26-31`, `ArchivoPorAbandonoService:49-54`; `db:table plazos` (solo FK); EXPLAIN #9 |
| **AUD-0067** | P3 | Búsqueda por NUREJ/resumen con `LIKE '%…%'` → full scan imposible de indexar; única búsqueda del sistema y sin endpoint dedicado | `AdminMonitoreoController:52-57`; EXPLAIN #4-#6; grep rutas de búsqueda = 0 |

**Recomendaciones asociadas (PROPUESTA / PENDIENTE DE DECISIÓN, ninguna
implementada):** cachear/unificar `PlazoCalculatorService` (AUD-0062);
agregados en SQL en el dashboard admin (AUD-0063); índices `(estado_actual_id,
fecha_ingreso)` o `fecha_ingreso` (AUD-0064), `(exitoso, login_at)` +
`login_at` (AUD-0065), `(estado, fecha_limite)` en `plazos` (AUD-0066) — cada
uno justificado por el patrón de consulta citado, conforme a §55; rediseño de
la búsqueda (AUD-0067). **Ningún índice creado; ninguna migración tocada.**

---

## §13 Observaciones (sin ficha)

| ID | Observación | Por qué no es ficha |
|---|---|---|
| O-20 | En el monitoreo el filtro `estado` se aplica **después** de cargar todo (`AdminMonitoreoController:295-312`) porque `POR_VENCER`/`FUERA_DE_PLAZO` son estados computados, y el `resumen` se calcula sobre el conjunto completo (`:317-335`) | Es una decisión de diseño documentada en el propio código; se registra porque condiciona la solución de AUD-0018 (no es un defecto por sí mismo) |
| O-21 | La mayoría de `->get()` sin límite están acotados por catálogo/purpose (§8, N\*) | No todo `get()` es defecto: dataset pequeño y estable por naturaleza |

**Verificaciones positivas (no son hallazgo):** monitoreo y dashboards usan
eager loading correcto (`with()`/`withCount()`), sin acceso lazy en bucles;
`actuados` y `partes` tienen índices compuestos que coinciden con sus
consultas reales; `PlazoCalculatorService` no se instancia en bucles de
services (solo vía resources); no existen `->cursor()` ni `->chunk()` que
oculten cargas masivas.

---

## §14 Decisiones pendientes (para el usuario)

1. **AUD-0062:** ¿aprobar la unificación/caché del `PlazoCalculatorService`
   (cambio de arquitectura liviana en resources/services) o aceptar el costo
   actual de ~26 queries por bandeja?
2. **AUD-0063:** ¿aprobar la conversión de agregados del dashboard admin a SQL
   (usando el patrón del dashboard Encargada)?
3. **AUD-0064/0065/0066:** ¿crear los tres índices propuestos (cada uno con su
   patrón de consulta justificado)? Requiere migración nueva + confirmación.
4. **AUD-0018 (ampliado):** ¿diseñar la paginación/búsqueda del monitoreo
   (implica decidir cómo paginar estados computados y resumen)?
5. **AUD-0067:** ¿qué patrón de búsqueda de NUREJ/resumen se adopta?

---

## §15 Limitaciones de la medición

1. **BD de desarrollo con volúmenes mínimos** (24 expedientes, 30 actuados,
   10 plazos, 3 sesiones, 15 usuarios): los `EXPLAIN` y tiempos (13-127 ms) no
   son representativos de producción; **lo medible aquí es el plan de ejecución
   (type/key/Extra) y el conteo de queries, que sí escalan linealmente**.
2. **Sin datos de volumen real del SRS** no se puede cuantificar el ahorro
   absoluto; solo la complejidad O(fila/plazo) de cada hallazgo.
3. **Sin aplicación en ejecución en red**: las latencias son de CLI local
   (sin HTTP, sin sesión real de navegador).
4. Medición de queries con `DB::listen` en `tinker` reproduce el camino de
   código de los controllers pero **no** pasa por HTTP/middleware; el conteo
   incluye las queries de auth simuladas con `setUserResolver`.
5. No se ejecutó `EXPLAIN ANALYZE` (costo/beneficio con datos triviales);
   `EXPLAIN` estático es suficiente para el plan, no para filas reales.
6. No se modificó ninguna consulta para "mejorar" un `EXPLAIN` (§13).

---

## §16 Conclusión y gates de F15

**Conclusión factual:**

1. La paginación server-side **existe en las dos bandejas** (`paginate(15)` +
   `count(*)`); quedan sin paginar por diseño o por deuda: monitoreo,
   dashboard admin y usuarios admin (AUD-0018 ampliado, sin cerrar).
2. El único **N+1 real medido** es el del semáforo en resources: **26 de 43
   queries** de la bandeja son `feriados`/`suspensiones_plazo` repetidas
   (AUD-0062, P2). El resto del sistema usa eager loading correctamente.
3. Los **índices existentes coinciden con sus consultas** en `actuados`,
   `partes`, `asignaciones` y catálogos; **faltan tres** justificados por
   patrón real de consulta (AUD-0064/0065/0066) y **ninguno se creó**.
4. **Ningún índice sirve para la búsqueda actual** de NUREJ (`LIKE '%…%'`) →
   decisión de diseño, no de esquema (AUD-0067).
5. El dashboard admin es el punto de escalabilidad más frágil (carga completa +
   5 pasadas); el dashboard Encargada muestra el patrón correcto (AUD-0063).
6. **No existen exportaciones** (Excel/PDF 0/9) → sin riesgo de memoria/tiempo
   por exportación en el estado actual.

**Gates (ejecutados al cierre de F15):**

| Gate | Resultado |
|---|---|
| `vendor/bin/pint --dirty --format agent` | **OK** (sin PHP sucio: F15 no tocó código PHP) |
| `php artisan test --compact` | **296 tests · 289 OK · 1 fallo (AUD-0001, `SecurityCompartimentosTest`, deuda aceptada desde F12) · 0 errores · 6 omitidos** — idéntico al baseline, **sin regresiones** |

**Cambios de producto en F15: NINGUNO** (solo se creó este `.md` y se
actualizaron `BACKLOG_AUDITORIA.md`, `PROGRESO.md` y
`PLAN_EJECUCION_AUDITORIA.md`).
