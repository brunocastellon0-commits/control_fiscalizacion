# Fase 10 — Flujo Encargada (bandeja con contexto completo, VB de cronograma y MPA)

Estado: **VALIDADA Y CERRADA (2026-09-30)** · Estrictamente solo lectura sobre
la BD de desarrollo; tests contra `control_fiscalizacion_test`
(`RefreshDatabase`). Único cambio de código de la fase: fix autorizado
AUD-0003 en `tests/Feature/EncargadaDashboardTest.php:149-150`.

## §0 Alcance y método

Definición del Plan §3 (línea 74): *"bandeja con contexto completo, VB de
cronograma y MPA; `EncargadaDashboardService` contra SQL real"*.

- **Marco normativo:** SRS `:77` (máxima autoridad del flujo: bandeja,
  supervisión, VB y devoluciones), `:91-92` (VB de control de plazos; Arts.
  26-27 AC_022/2018: inicio de investigaciones y cronogramas aprobados por el
  inmediato superior), `:142` (2 días hábiles para subir el Cronograma),
  `:145` (el VB activa el reloj de ejecución), `:346` y `:352` (carga del plan
  y Actuado de Visto Bueno a Planificación).
- **Superficie auditada:**
  - `GET /api/encargada/dashboard` — `routes/api.php:89-90`, dentro del grupo
    `auth:sanctum, throttle:api` (`routes/api.php:29`); controlador
    `EncargadaDashboardController:17-22` → `authorize('bandejaSorteo')`.
  - `ExpedientePolicy:48-51` (`bandejaSorteo`): usuario activo con rol
    `ENCARGADA` exacto.
  - `EncargadaDashboardService@obtenerResumen:19` (único método).
  - VB: `POST /planificacion` · `/planificacion/visto-bueno` ·
    `/planificacion/devolver` (`routes/api.php:66-68`);
    `ExpedientePolicy:206-211` (`aprobarPlanificacion`) y `:217-221`
    (`devolverPlanificacion`): solo Encargada activa con el expediente en
    `PENDIENTE_VISTO_BUENO`; `PlanificacionService@cargarPlanificacion:57`,
    `aprobarVistoBueno:102`, `devolverPlanificacion:137`.
- **Método de comparación servicio ↔ SQL:** semillas temporales de solo
  lectura en la BD dev vía scripts tinker
  (`C:\Users\Bruno\AppData\Local\Temp\opencode\f10_sql_real.php`,
  `f10_dashboard_svc.php`, `f10_dashboard_svc2.php`): se vertieron los arrays
  del servicio (`obtenerResumen()` → JSON) y se corrieron consultas SQL
  independientes con los mismos criterios, comparando campo por campo.

## §1 Bandeja del dashboard — servicio vs SQL real (BD dev)

Población de referencia: 24 expedientes, 8 plazos vigentes en 5 expedientes,
0 usuarios inactivos con asignación, sin plazos próximos a vencer.

| Campo | `EncargadaDashboardService` | SQL independiente | ¿Coincide? |
| --- | --- | --- | --- |
| `expedientes.total` | 24 | 24 | ✓ |
| `expedientes.pendientes` (PENDIENTE_SORTEO) | 19 | 19 | ✓ |
| `expedientes.sin_asignar` | 19 | 19 | ✓ |
| `asignaciones.activas` | 5 | 5 | ✓ |
| `expedientes.por_via` | TECNICO 10 · FINANCIERO 7 · JURIDICO 7 | idem | ✓ |
| `expedientes.por_estado` | PENDIENTE_SORTEO 19 · EN_EVALUACION 4 · EN_SUBSANACION 1 | idem | ✓ |
| `asignaciones.carga_operadores` | id 3→3 · id 11→1 · id 10→1 | id 3→3 · id 10→1 · id 11→1 | ✓ mismos totales (el orden de los empates no está definido en ninguna de las dos consultas: sin impacto funcional) |
| `expedientes.ultimos` (5) | 2026-00001 … 2026-00005 | 2026-00001 … 2026-00005 | ✓ |
| `vencimientos.fuera_de_plazo` | 5: 2026-00006, 00004, 00005, 00017, 00019 | 5 expedientes con plazo vigente vencido | ✓ |
| `vencimientos.proximos` | 0 | 0 (sin plazos próximos) | ✓ |
| `semaforo.total_fuera_de_plazo` | 5 | 5 | ✓ |
| `feriados_proximos` | 2026-12-25 Navidad | idem (próximo ≥ hoy) | ✓ |

**Coherencia de semáforo/vencimientos con los 8 plazos vigentes en 5
expedientes:** `SemaforoPlazoService@colorMasUrgente:151-163` evalúa
únicamente `plazos->where(VIGENTE)->sortBy('fecha_limite')->first()` — el
plazo vigente con fecha límite **más temprana**, que es exactamente el más
urgente (fuera de plazo depende solo de `fecha_limite` vs hoy). Los
`vencimientos` también toman el plazo de fecha límite más temprana. Los 3
plazos vigentes "extra" pertenecen a expedientes ya representados; no hay
subregistro ni doble conteo.

**Seguridad de la respuesta:** autenticación obligatoria (401 sin sesión:
`FlujoEncargadaTest:113`), autorización por policy (403 roles/inactiva:
`EncargadaDashboardTest:146`), y la respuesta expone solo datos operativos
del tablero, sin información administrativa/usuarios/seguridad/auditoría
(`EncargadaDashboardTest:65`).

## §2 Visto Bueno de Cronograma (AC022) y MPA (AC054/055)

Transiciones: `EN_PLANIFICACION` --(operador carga plan)-->
`PENDIENTE_VISTO_BUENO` --(VB Encargada)--> `EN_EJECUCION`; la devolución
regresa a `EN_PLANIFICACION` con reabertura del plazo (SRS `:142,:145,:346,:352`).

- **Cobertura preexistente (15 tests verdes, `PlanificacionTest.php`):**
  - Cronograma: carga cierra el plazo de planificación (`:174`), VB pasa a
    `EN_EJECUCION` con 10 días hábiles y devuelve la bandeja al Técnico
    (`:326-371`, asertando `parametro_plazo_id` y fecha límite
    **2026-09-24** con reloj congelado), sin adjunto → 422 (`:313`), fuera de
    `EN_PLANIFICACION` → 403 (`:429`).
  - MPA: fecha límite dinámica exacta (2026-10-15, `dias_habiles_otorgados=0`
    y `parametro_plazo_id=null`: `:373-408`).
  - Autorización: operador intenta VB → 403 (`:410`), operador intenta
    devolver → 403 (`:536`).
  - Devoluciones: con observaciones y reabertura de plazo (`:442`, `:497`).
- **Tests nuevos Fase 10 (`FlujoEncargadaTest.php`, 3/3 verdes):**
  - `:113` API sin sesión → **401**.
  - `:117` contexto completo del tablero sembrado: total/sin_asignar/carga,
    semáforo `total_fuera_de_plazo=1`, vencimiento con `nurej_code` y
    `asignado_a`, feriado próximo, `por_estado` y `ultimos`.
  - `:158` **concurrencia secuencial del VB:** segundo VB tras la aprobación →
    **403** (policy exige `PENDIENTE_VISTO_BUENO`), devolución posterior →
    **403**, y se verifica que solo existe **1** actuado de VB y el expediente
    queda en `EN_EJECUCION` (sin doble reloj ni estados aberrantes).

## §3 Validación negativa (Plan §10: error / no autorizado / estado inválido)

| Escenario | Esperado | Evidencia | Estado |
| --- | --- | --- | --- |
| Dashboard sin sesión (API) | 401 | `FlujoEncargadaTest:113` | ✓ verde |
| Dashboard con rol ≠ ENCARGADA o inactiva | 403 | `EncargadaDashboardTest:146` (data sets ×5) | ✓ verde (fix AUD-0003) |
| Operador emite VB o devolución | 403 | `PlanificacionTest:410`, `:536` | ✓ verde |
| Segundo VB tras aprobar | 403 | `FlujoEncargadaTest:158` | ✓ verde |
| Devolución tras aprobar | 403 | `FlujoEncargadaTest:158` | ✓ verde |
| Cargar plan fuera de `EN_PLANIFICACION` | 403 | `PlanificacionTest:429` | ✓ verde |
| Cronograma sin adjunto (requiere adjunto) | 422 | `PlanificacionTest:313` | ✓ verde |
| Concurrencia paralela del VB | — | no reproducida (mismo criterio que AUD-0036: sin índice único/`lockForUpdate`, no reclasificar sin evidencia) | no reproducida |

## §4 Hallazgos de la fase

### AUD-0003 — raíz determinada (bug del test, no del producto)
- **Evidencia:** `tests/Feature/EncargadaDashboardTest.php:149-150` encadena
  `Sanctum::actingAs($usuario, ['*'])->getJson('/api/encargada/dashboard')`.
  `Sanctum::actingAs()` devuelve el `Usuario` autenticado, no el TestCase →
  `Call to undefined method App\Models\Usuario::getJson()` (5 data sets del
  único test que usa esa cadena; los demás tests del archivo usan
  `Sanctum::actingAs(...);` en sentencia separada y pasan).
- **Impacto (antes del fix):** 5 errores del baseline; no era bug de producto —
  la ruta está protegida (la variante correcta del mismo test pasa en `:65` y
  la data set solo varía rol/activo).
- **Corrección aplicada (2026-09-30, autorizada por el usuario):** separar en
  dos sentencias (`Sanctum::actingAs(...);` y luego `$this->getJson(...)`)
  **exclusivamente en `EncargadaDashboardTest:149-150`**; ni producción ni
  otros tests. Verificación: `pint --dirty` → passed; suite →
  **284: 277 OK · 1 fallo (AUD-0001) · 0 errores · 6 omitidos** (los 5
  errores eliminados, sin regresiones). Ficha **AUD-0003 → CERRADO**.
- **Sin nuevos hallazgos de producto en Fase 10:** el dashboard coincide con
  SQL real en todos los campos comparables y los flujos VB están cubiertos.

## §5 Cobertura de tests del flujo

| Área | Tests | Estado |
| --- | --- | --- |
| Dashboard web/API: login, visibilidad, roles | `EncargadaDashboardTest:39-145` (5 tests) | ✓ verdes |
| Datos operativos sin fuga administrativa | `EncargadaDashboardTest:65` | ✓ verde |
| Carga de operadores | `EncargadaDashboardTest:91` | ✓ verde |
| Roles/inactiva (data set ×5) | `EncargadaDashboardTest:146` | ✓ verde (fix AUD-0003) |
| Auth 401 + contexto completo + doble VB/devolución 403 | `FlujoEncargadaTest:113,117,158` (3 tests nuevos) | ✓ verdes |
| VB Cronograma/MPA, devoluciones, 403/422 | `PlanificacionTest` (15 tests) | ✓ verdes |

Suite completa tras Fase 10 y el fix de AUD-0003: **284 tests: 277 OK ·
1 fallo (AUD-0001) · 0 errores · 6 omitidos** — sin regresiones (+3 verdes
nuevos; los 5 errores de AUD-0003 eliminados).

## §6 Decisiones del usuario (Fase 10)

1. **AUD-0003 — RESUELTA:** fix autorizado por el usuario y aplicado
   (2026-09-30) solo en `EncargadaDashboardTest:149-150`; ficha **CERRADO**;
   suite sin errores ni regresiones.
2. Orden de empates en `carga_operadores`: comportamiento no definido en
   servicio y SQL por igual, sin impacto funcional — documentado, no es
   hallazgo.
3. Concurrencia paralela del VB: no reproducida (mismo criterio que
   AUD-0036/Fase 9); no reclasificar sin evidencia experimental.
