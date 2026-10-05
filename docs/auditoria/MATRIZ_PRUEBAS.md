# MATRIZ DE PRUEBAS — FASE 16 (PRUEBAS INTEGRALES)

**Archivo:** `docs/auditoria/MATRIZ_PRUEBAS.md`
**Fase:** F16 — Pruebas (`PLAN_EJECUCION_AUDITORIA.md:80`)
**Estado:** **VALIDADA Y CERRADA (2026-10-02)** — validación de cierre ejecutada; correcciones
documentales aplicadas en §1, §6 y §9 (recuentos, cobertura HTTP previa, `EnsureAdmin`,
aserciones por etapa y bucle de seeders). Ver §5-§12.
**Modalidad:** validación integrada de pruebas sobre la arquitectura real. Cero cambios de
producción, cero nuevas funcionalidades, sin repetir F13/F14/F15.

---

## §0 Alcance (plan existente, no redefinido)

> "escenario integral mínimo (Registro→…→Salida) + suites negativas; cobertura de
> prioridades (seguridad, plazos, actuados)" — `PLAN_EJECUCION_AUDITORIA.md:80`.

Objetivo: comprobar mediante tests que el ciclo de vida completo del expediente funciona
de extremo a extremo y que las condiciones negativas y prioridades críticas están cubiertas.

---

## §1 Inventario de pruebas existentes (previo a escribir cualquier test)

- **Estructura:** `tests/Feature/` (47 archivos), `tests/Unit/` (6), `tests/Pest.php`,
  `tests/TestCase.php`. `Pest.php:17-19` aplica `RefreshDatabase` a todo `Feature/`.
  **No hay helpers compartidos** (`Pest.php:47` solo declara un placeholder `something()`):
  cada archivo de flujo define sus propias funciones globales `*Semilla()`/`*CrearExpediente()`
  (139 funciones helper globales al cierre de F16 = 122 previas + 17 de F16, todas prefijadas
  por archivo para evitar colisiones). Conteo: declaraciones `function` de nivel superior en
  `tests/Feature` (134) + `tests/Unit` (5).
- **Baseline previo F16:** 296 tests · 289 OK · 1 fallo (AUD-0001,
  `SecurityCompartimentosTest`) · 0 errores · 6 omitidos.
- **Cobertura por etapa (evidencia `archivo:línea`):**

| Etapa / frente | Tests existentes (evidencia) |
| --- | --- |
| Registro/apertura | `ExpedienteControllerTest.php:109` (201+estructura), `:135` (422 sin adjunto), `:148` (403 rol); `FormRequestsTest` StoreExpedienteRequest (3 tests) |
| Sorteo | `ExpedienteControllerTest.php:250` (asigna+EN_EVALUACION), `:274` (422 fuera de estado), `:209` (bandeja solo Encargada); `SorteoAlgorithmTest` (7: mapeo vía→rol, pesos, sin candidatos), `SorteoTodosTest` (4: lote, 403, 422); `SeguridadIdorTest.php:160,171` |
| Evaluación / técnico | `EvaluacionAdmisibilidadTest` (12: 401/403 RF-03/422 estado/checklist, ACT_ADMISION→EN_PLANIFICACION+plazo, OBSERVACION→SUBSANACION con días hábiles reales, ACT_RECHAZO desactiva relojes, RF-04 catálogo); `PlanificacionTest` (15: Cronograma AC022, MPA AC054 fecha dinámica, 403 rol/bandeja/estado, 422 fecha/adjunto, VB→EN_EJECUCION, devolución); `RelojProcesualTest` (3: admisión abre PLANIFICACION no EJECUCION; VB arranca EJECUCION); `AmpliacionTest` (10: US-2.6 completo + 7 negativas); `SorteoAlgorithmTest` |
| Jurídico / impugnación | `FlujoJuridicoTest` (9: rechazo+remitir RN-08, informe jurídico destino null, 403 rol/ajeno/sin asignación, 422 adjunto, doble remisión, fuera de estado); `ImpugnacionRechazoTest` (7: remitir+ratificar→ARCHIVO_DEFINITIVO, revocar→ADMITIDO, 403 rol, 422 fuera de estado) |
| Financiero / descargos | `FlujoFinancieroTest` (4: estado inválido comunicar/recibir, 422 sin reloj/recepción sin pausa, Bloqueo de Salida RN-09); `DescargoFinancieroTest` (5: **ciclo comunicar→recibir con cálculo de días hábiles reales**, informe exige descargos previos, 403 rol/bandeja/AC055, 422 adjunto/duplicar fase, reanudación sin días restantes) |
| Cierre | `CierreExpedienteTest` (10: VB final→LISTO_PARA_REPARTO, reparto→CONCLUIDO_REMITIDO+bandeja vacía, **`:174` "cierra el ciclo completo" encadena VB final+Reparto (2 etapas)**, 422 destino/nota, 403 rol/estado) |
| Salida / archivo | `ArchivoPorAbandonoTest` (3: abandono RN-03 idempotencia de corte); `DerivacionTransparenciaTest` (10: **`:249` "cierra el ciclo completo" encadena derivación+remisión→DERIVADO_TRANSPARENCIA (2 etapas)**, plazos congelados, 403/422); `CierreExpedienteTest:137` |
| Seguridad/autorización | `SecurityCompartimentosTest` (9: RF-03 estanco, inactivo, sin token — 1 fallo conocido AUD-0001); `SeguridadIdorTest` (16: IDOR sobre 16 endpoints); `SeguridadSesionesTest` (5: sesiones/purga/auditoría); `AuthFeatureTest` (13: login stateful, rate limiting, sesiones_acceso); `WebWorkstationRoutesTest` (7: rutas web + sesión) |
| Plazos/días hábiles/feriados | `PlazoCalculatorServiceTest` (6: `calculateDueDate`/feriados/suspensiones); `PlazoResourceTest` (3); `SemaforoPlazosFeatureTest` (6: VERDE/AMARILLO/ROJO/FUERA_DE_PLAZO/feriado); `SemaforoPenalizacionTest` (4); `SemaforoPlazoServiceTest` (8 unit); `VerificarVencimientoPlazosTest` (3: idempotencia, corte UTC AUD-0043, catálogo faltante AUD-0042); `RelojProcesualTest` (3) |
| Actuados/cadena de custodia | `CadenaCustodiaTest` (6: encadenado de 1-3 actuados, reproducción sha256 del trigger, **UPDATE y DELETE bloqueados por inmutabilidad**); `CadenaHashIntegrityTest` (1: cadena bajo `lockForUpdate`); `CadenaHashConcurrenciaTest` (1: cadena no se bifurca con N subprocesos); `ActuadoControllerTest` (3); `ActuadoResourceTest` (3); `ActuadoServiceTest` (3 unit) |
| Concurrencia | `StressConcurrenciaTest` (5: T1 NUREJ únicos, T2 cadena con N×M inserts, T3 sorteo concurrente → 1 ACT_SORTEO_INICIAL y 1 asignación, T4 apertura masiva, T5 sortearTodas vs aperturas sin deadlock) + `CadenaHashConcurrenciaTest` |
| Entradas inválidas | `FormRequestsTest` (10: apertura/sorteo/actuados — 403 rol y 422 payload) + decenas de 422 inline en todos los `*Test` de flujo |

**Existencia de E2E previo (verificado): NO existe.** Ningún test atraviesa más de dos
etapas encadenadas sobre el mismo expediente. Las únicas pruebas "ciclo" son
`CierreExpedienteTest:174` (VB final + Reparto) y `DerivacionTransparenciaTest:249`
(derivación + remisión); el resto **siembra el estado directamente** con
`Expediente::create` en el estado deseado en lugar de recorrer el flujo. Este es el
hueco que F16 debe cubrir.

---

## §2 Secuencia integral verificada contra controllers/servicios (no inventada)

Antes de escribir `FlujoIntegralTest.php` se leyeron y verificaron cada transición:

| # | Etapa | Endpoint (real) | Requisito verificado (archivo:línea) |
| --- | --- | --- | --- |
| 1 | Registro/apertura | `POST /api/expedientes` | `StoreExpedienteRequest` (rol TECNICO, via ∈ TECNICO/JURIDICO/FINANCIERO, partes ≥1, adjunto PDF) → `ExpedienteService::aperturaCausa` crea expediente en `PENDIENTE_SORTEO` + `ACT_REGISTRO_DIGITALIZACION` + partes (`ExpedienteService.php:37-88`) |
| 2 | Sorteo | `POST /api/expedientes/{id}/sortear` | `SortearExpedienteRequest` (rol ENCARGADA) → valida `PENDIENTE_SORTEO`, candidatos = activos del rol de la vía (`FINANCIERO→AUD_FINANCIERO`, `SorteoAlgorithmService.php:31-33`), emite `ACT_SORTEO_INICIAL` → `EN_EVALUACION` + plazo `EVALUACION` (`ExpedienteService.php:102-137`, `ActuadoService.php:34`) |
| 3 | Evaluación (admisión) | `POST /api/expedientes/{id}/evaluacion` | `StoreEvaluacionAdmisibilidadRequest` (checklist = **exactamente** los requisitos activos del reglamento) → sin faltantes ⇒ `ACT_ADMISION` → `EN_PLANIFICACION` + plazo `PLANIFICACION` (`EvaluacionAdmisibilidadService.php:125-136`, `ActuadoService.php:36`); policy `evaluarAdmisibilidad` exige asignación activa (`ExpedientePolicy.php`) |
| 4 | Planificación (MPA) | `POST /api/expedientes/{id}/planificacion` | `StorePlanificacionRequest` (asignación + estado `EN_PLANIFICACION` + pivot rol/reglamento; MPA exige `fecha_limite_propuesta > hoy` + adjunto) → cierra plazo `PLANIFICACION`, emite `ACT_MPA`, bandeja → Encargada, estado `PENDIENTE_VISTO_BUENO` (`PlanificacionService.php:57-89`) |
| 5 | Visto Bueno planif. | `POST /api/expedientes/{id}/planificacion/visto-bueno` | `VistoBuenoPlanificacionRequest` (solo Encargada + `PENDIENTE_VISTO_BUENO`) → `ACT_VISTO_BUENO_PLANIFICACION` → `EN_EJECUCION`, abre plazo `EJECUCION` con la fecha límite explícita del MPA (RN-05), bandeja vuelve al operador original (`PlanificacionService.php:102-123`, `ActuadoService.php:197-203`) |
| 6a | Descargos: comunicar | `POST /api/expedientes/{id}/descargos/comunicar` | `ComunicarHallazgosRequest` + policy `comunicarHallazgos` (AUD_FINANCIERO + asignación + `EN_EJECUCION` + pivot AC055) → no-op de estado, **pausa** plazo `EJECUCION` (`SUSPENDIDO`) y abre sub-reloj `DESCARGOS` (5 días hábiles, parámetro AC055) (`DescargoFinancieroService.php:72-101`) |
| 6b | Descargos: recibir | `POST /api/expedientes/{id}/descargos/recibir` | cierra `DESCARGOS` → `CUMPLIDO` y **reanuda** `EJECUCION` → `VIGENTE` (`DescargoFinancieroService.php:118-149`) |
| 7 | Informe final | `POST /api/expedientes/{id}/actuados` | `StoreActuadoRequest` (pivot rol/reglamento + asignación; **Bloqueo de Salida RN-09**: informe financiero exige `ACT_RECEPCION_DESCARGOS` previo → 422) → `ACT_INFORME_..._SIN_RESPONSABILIDAD` → `PENDIENTE_VISTO_BUENO_FINAL` (`StoreActuadoRequest.php:53-65`, `DescargoFinancieroService.php:157-168`) |
| 8 | Cierre: VB final | `POST /api/expedientes/{id}/cierre/visto-bueno` | `AprobarVistoBuenoRequest` (solo Encargada + `PENDIENTE_VISTO_BUENO_FINAL`) → `LISTO_PARA_REPARTO` (`CierreExpedienteService.php:49-68`) |
| 9 | Salida: reparto | `POST /api/expedientes/{id}/cierre/reparto` | `RepartoInstitucionalRequest` (destino ∈ {Juzgado Disciplinario, Sumariante, Asesoría Legal, Asesoría Jurídica} + nota ≥10) → `CONCLUIDO_REMITIDO` + cierra bandeja activa (`CierreExpedienteService.php:81-106`) |

Cadenas verificadas además contra la BD real (`catalogo_actuados` vía tinker) y
`CatalogoActuadoSeeder` / `CatalogoEstadoSeeder` / `ParametroPlazoSeeder` /
`CatalogoRequisitoSeeder` / `FeriadoSeeder`.

**Por qué la rama AC055 (FINANCIERO):** es la única cadena completa del catálogo
que llega a una salida (`CONCLUIDO_REMITIDO`) atravesando además la fase de descargos:
`ACT_ADMISION → planificación (MPA) → VB → EN_EJECUCION → descargos → informe →
VB final → reparto`. Las ramas alternativas (jurídico/impugnación, observación/
subsanación, abandono, transparencia) son cobertura de tests dedicados (ver §8).

---

## §3 Infraestructura de pruebas reutilizada

- `RefreshDatabase` global (transacción por test, rollback automático).
- `Storage::fake('local')` (disco real de adjuntos: `AdjuntoService.php:25`).
- `Carbon::setTestNow(...)` — patrón ya estandarizado (92 usos en la suite).
- Seeders reales del proyecto (`$this->seed([...])`) para catálogos: `RolSeeder`,
  `CatalogoEstadoSeeder`, `ReglamentoSeeder`, `CatalogoRequisitoSeeder`,
  `ParametroPlazoSeeder`, `FeriadoSeeder`, `CatalogoActuadoSeeder` (nada de catálogos
  inventados a mano en el test integral: se usa el grafo real).
- `Sanctum::actingAs($usuario, ['*'])` para cambiar de rol en cada etapa.
- Funciones globales con prefijo `fi*` (flujo integral) para no colisionar con los
  `*Semilla()` existentes.

---

## §4 Limitaciones de diseño del recorrido (declaradas, no ocultas)

1. **Impugnación / rechazo:** es una rama alternativa (solo desde `RECHAZADO`), no una
   etapa del camino de admisión. Un único recorrido no puede pasar por ambas ramas sobre
   el mismo expediente sin forzar el flujo. Se cubre con su máxima secuencia integrada
   existente: `FlujoJuridicoTest:152` (rechazo→remisión→resolución) e
   `ImpugnacionRechazoTest:208,286` (ratifica→`ARCHIVO_DEFINITIVO`, revoca→`ADMITIDO`).
2. **Estado `ADMITIDO`:** verificado contra BD real y `CatalogoActuadoSeeder`: **ningún
   actuado del catálogo tiene `estado_origen_id = ADMITIDO`** (solo es destino de la
   revocación). Esto impide encadenar la rama de impugnación dentro del mismo recorrido
   integral. Se documenta aquí como limitación de cobertura; no se modifica producción
   ni se reabren fases cerradas (F4/F8). Se eleva al usuario en el informe de entrega.
3. **`EN_INVESTIGACION`, `EN_DESCARGOS`, `CONCLUIDO`, `OBSERVADO`** son estados
   heredados del catálogo sin transiciones entrantes nuevas en el seeder actual: no se
   declara cobertura sobre ellos.
4. Sin driver de cobertura PHP (ni `xdebug` ni `pcov` en el entorno): **no hay % de
   cobertura PHPUnit**; la cobertura real se documenta cualitativamente por frente y por
   etapa (§8), con citas de archivo:línea — nunca se declara lo no cubierto.

---

## §5 Resultados (gates ejecutados 2026-10-02)

| Métrica | Baseline previo F16 | Tras F16 | Delta |
| --- | --- | --- | --- |
| Tests totales | 296 | **303** | +7 |
| OK | 289 | **296** | +7 |
| Fallos | 1 (`SecurityCompartimentosTest` → AUD-0001) | **1 (el mismo)** | 0 |
| Errores | 0 | **0** | 0 |
| Omítidos | 6 | **6** | 0 |
| Aserciones | — | **1309** | +160 de F16 |

- `php artisan test --compact` → `{"tests":303,"passed":296,"assertions":1309,"failed":1,"skipped":6}`.
- El único fallo sigue siendo **AUD-0001** (`ExpedientePolicy::view`, `app/Policies/ExpedientePolicy.php:80-82`
  devuelve `true` para rol ADMIN sin asignación activa): deuda abierta conocida y aceptada,
  fuera del alcance de F16. No se tocó la política.
- **Cero tests existentes fueron modificados, reescritos ni omitidos**: solo se agregaron
  archivos nuevos (§11).

---

## §6 Tests agregados (7 tests, 160 aserciones)

### `tests/Feature/FlujoIntegralTest.php` — 3 tests · 140 aserciones

Prefijo de helpers `fi*` (15 funciones globales, `:35-269`; sin colisión con las 139
helpers existentes en total). Semilla con los **7 seeders reales** del proyecto en bucle
`$test->seed(Clase)` (`:37-47`), usuarios por factory, `Storage::fake('local')`,
`Carbon::setTestNow('2026-10-05 10:00:00')` + feriado `2026-10-07` insertado a mano
(`:66-74`, no existe en `FeriadoSeeder`).

| # | Test | Evidencia | Qué acredita |
| --- | --- | --- | --- |
| 1 | `it('recorre el ciclo completo AC055: sorteo, admisión, MPA, visto bueno, descargos, informe y reparto')` | `FlujoIntegralTest.php:271` | **E2E real de 10 etapas** sobre el mismo expediente, sin sembrar estado: apertura→sorteo→admisión→MPA→VB→comunicar→recibir→informe→VB final→reparto. **Aserciones por etapa (no todas combinan las 4 dimensiones):** etapas 1-5 y 8-10 asertan estado + bandeja activa (`Asignacion.activa`) + shape de respuesta; la **etapa 6** aserta estado + shape + plazos **pero no banda** (`:376-387`); la **etapa 7** aserta shape + plazos, **ni estado ni banda** (`:390-403`). Plazos: `:312` EVALUACION 2026-10-13, `:331` PLANIFICACION 2026-10-08, `:365` EJECUCION 2026-10-16 con `parametro_plazo_id=null` y `dias_habiles_otorgados=0`, `:387` DESCARGOS 2026-10-13. Cierra con **10 actuados en orden exacto** y **cadena de custodia completa** (`:440-467`): `hash_anterior` del primero `null`, cada `hash_anterior == hash_actuado` anterior y cada `hash_actuado` reproducido sha256 contra la fila cruda de BD. |
| 2 | `it('mantiene el compartimento estanco y los roles del recorrido (RF-03)')` | `FlujoIntegralTest.php:470` | Segundo `AUD_FINANCIERO` creado **después** del sorteo: 403 en `GET`, `evaluacion` y `comunicar` (`:489-496`); Técnico creador pierde acceso tras sortear (`:501`) y no puede emitir VB de planificación (`:505`); Encargada no participa de descargos AC055 (`:511`); **IDOR entre expedientes** con una segunda causa asignada al segundo auditor: 403 cruzado en ambos sentidos (`:518-522`); el asignatario legítimo sí opera su causa; VB final solo Encargada — 403 para Técnico (`:540`) y para el auditor ajeno (`:545`), 201 para Encargada. |
| 3 | `it('bloquea las acciones emitidas fuera de orden o por el rol equivocado')` | `FlujoIntegralTest.php:557` | Negativas de orden/rol dentro del flujo: informe **sin** descargos → 422 `catalogo_actuado_id` (Bloqueo de Salida RN-09, `:570`) sin crear actuado; segunda comunicación → 422 `expediente` (fase única, `:580`); Técnico emite informe → 403 (`:586`); auditor emite VB final → 403 (`:591`); reparto fuera de estado → 403 (`:597`); reparto **antes** del VB final → 403 (`:615`); cierre correcto en orden; y un NUREJ ya `CONCLUIDO_REMITIDO` rechaza nuevo reparto y nuevo VB final (`:629-632`). |

### `tests/Feature/AdminHttpCoverageTest.php` — 4 tests · 20 aserciones

Prefijo `ahc*`. Cubre las **4 rutas web** del panel bajo `Route::middleware(['auth', EnsureAdmin::class])->prefix('administrador')` (`routes/web.php:45-62`).
**Cobertura previa a F16 (ya existía, no era cero):** solo `SeguridadIdorTest.php:230-236`
hacía un GET a `/administrador/dashboard` con un rol no autorizado (1 ruta × 1 rol, esperando
403); ninguna ruta tenía prueba de los 4 escenarios de autorización. **Cobertura añadida por
F16:** las 4 rutas × {sin sesión, rol no ADMIN, ADMIN inactivo, ADMIN activo}:

| # | Test | Evidencia |
| --- | --- | --- |
| 1 | sin sesión → 302 a `route('login')` en las 4 rutas | `AdminHttpCoverageTest.php:35` |
| 2 | rol TECNICO → 403 en las 4 rutas | `AdminHttpCoverageTest.php:41` |
| 3 | ADMIN **inactivo** → 403 en las 4 rutas (`EnsureAdmin.php:20-22`) | `AdminHttpCoverageTest.php:49` |
| 4 | ADMIN activo → 200 en las 4 rutas (vistas renderizan sin `@vite`) | `AdminHttpCoverageTest.php:57` |

(Autorización de rol no ADMIN resuelta en `EnsureAdmin.php:24-26`.)

---

## §7 Cobertura real por frente y etapa

Filas nuevas que se suman al inventario de §1 (las demás no cambian):

| Frente | Cobertura nueva (evidencia) |
| --- | --- |
| **E2E registro→salida** | `FlujoIntegralTest.php:271` — primera prueba que atraviesa **10 etapas encadenadas** sobre el mismo expediente con el grafo real de catálogos (§1 declaraba `NO existe E2E previo`; ese hueco queda **cubierto para la rama AC055/FINANCIERO**). |
| Cierre completo (3 etapas) | `FlujoIntegralTest.php:557` — informe → VB final → reparto encadenados con negativas intermedias. |
| Seguridad en flujo | `FlujoIntegralTest.php:470` — RF-03 y IDOR **durante** el recorrido (complementa a `SecurityCompartimentosTest`/`SeguridadIdorTest`, que siembran estado). |
| Rutas web admin | `AdminHttpCoverageTest.php:35-60` — 4 rutas × {sin sesión, rol no ADMIN, ADMIN inactivo, ADMIN activo}. Antes: solo `SeguridadIdorTest.php:230-236` (1 ruta × 1 rol). |
| Plazos en flujo | `FlujoIntegralTest.php:271` — los 4 relojes del ciclo verificados con feriado real: EVALUACION/DESCARGOS=2026-10-13, PLANIFICACION=2026-10-08, EJECUCION=fecha explícita del MPA. |
| Cadena de custodia en flujo | `FlujoIntegralTest.php:440-467` — 10 hashes encadenados y reproducidos (antes: `CadenaCustodiaTest` con 1-3 actuados sembrados). |

**Siguen sin cubrirse aquí** (declarado, no se declara cobertura falsa): ramas
alternativas (impugnación, observación/subsanación, abandono, transparencia) — ya
cubiertas por sus suites de fase (§4); UI/navegador (E2E de navegador); y el % de
cobertura PHPUnit (§4.4).

---

## §8 Suites negativas: brechas identificadas

1. **Conflicto de asignación admin (a informar).** `MATRIZ_SEGURIDAD.md:85` asigna las
   **6 mutations `/api/admin/*`** (POST/PUT/DELETE usuarios y feriados) a **F17**, mientras
   el alcance que F16 tomó de `MAPA_FUNCIONAL.md:196` dice "0 tests" para `/api/admin/*`.
   Verificado: los **GET** `/api/admin/*` **ya estaban cubiertos** antes de F16
   (`ReportesAdminTest.php:122-197`, `SeguridadIdorTest.php:212-240`), y F16 agregó la
   cobertura de las 4 rutas **web** `/administrador/*`. **Las 6 mutations quedan para F17**
   (no se tocaron: F16 no modifica endpoints ni políticas). Se eleva al usuario en §10/§12.
2. **`MAPA_FUNCIONAL.md:196` está desactualizado** respecto a `ReportesAdminTest` y
   `SeguridadIdorTest`. Se informa; no se edita (es artefacto de F5 y queda fuera del
   alcance de F16).
3. **Sin E2E de navegador** (paso de UI real): F16 valida HTTP/API + Blade, no el
   JavaScript del workstation. Queda para una eventual fase de navegador, no planificada.
4. **Sin driver de cobertura** (`xdebug`/`pcov` ausentes): se mantiene la cobertura
   cualitativa por frente de §4.4/§7.
5. **Ramas no encadenadas en un mismo expediente** (§4.1-§4.3 siguen vigentes): el
   expediente del E2E pasa por una sola rama (AC055). Las alternativas conservan sus
   suites dedicadas; no se duplican.

---

## §9 Gates

| Gate | Comando | Resultado |
| --- | --- | --- |
| Formato | `vendor/bin/pint --dirty --format agent` | `fixed` 1 archivo: `tests/Feature/AdminHttpCoverageTest.php` (fixer `blank_line_between_import_groups`). Re-ejecutado sin cambios pendientes. |
| Suite completa | `php artisan test --compact` | `tests:303 · passed:296 · assertions:1309 · failed:1 · skipped:6` — **sin errores**, el fallo es AUD-0001 (preexistente, §5). |
| Tests aislados (iteración) | `php artisan test --compact tests/Feature/FlujoIntegralTest.php` | `3/3 · 140 aserciones` |
| Tests aislados (iteración) | `php artisan test --compact tests/Feature/AdminHttpCoverageTest.php` | `4/4 · 20 aserciones` |
| Re-verificación de cierre (2026-10-02, tras correcciones documentales) | `vendor/bin/pint --dirty --test --format agent` | `passed` (dry-run, sin cambios) |
| Re-verificación de cierre (2026-10-02, tras correcciones documentales) | `php artisan test --compact` | `tests:303 · passed:296 · assertions:1309 · failed:1 · skipped:6` — idéntico a la entrega: **sin cambios en el baseline** (los 6 omitidos = 5 `RUN_STRESS_TESTS` + 1 `RUN_CONCURRENCY_TEST`, preexistentes). |

---

## §10 Fallos, hallazgos y decisiones

**Ajustes de test al comportamiento real (2, solo se tocó el test, nunca producción):**

1. **Sub-reloj `DESCARGOS` no tiene `fecha_pausa`.** Falló inicialmente asertando
   `fecha_pausa` en `FlujoIntegralTest.php:397`. Verificado en código:
   `DescargoFinancieroService@congelarRelojEjecucion:243-253` pausa **solo** el reloj
   `EJECUCION`; `abrirSubRelojDescargos:258-276` crea el sub-reloj con `fecha_inicio` y
   `estado=VIGENTE`. Se reemplazó por `parametro_plazo_id != null` (parámetro AC055).
   **No es bug: es el diseño.**
2. **Clave de la validación del Bloqueo de Salida.** El informe sin descargos previos
   lanza el error bajo **`catalogo_actuado_id`** (`DescargoFinancieroService:165-168`
   invocado desde `StoreActuadoRequest::withValidator`), no bajo `expediente`.
   Se corrigió el `assertJsonValidationErrors` en `FlujoIntegralTest.php:570`.

**Candidato descartado — AUD-0068 no se crea (duplicado):**

- Se detectó que `EvaluacionAdmisibilidadService::evaluar()` **no cierra el plazo
  EVALUACION al admitir**: `desactivarRelojesSiRechazo` (`:150-159`) solo actúa ante
  `ACT_RECHAZO`. Asertado en flujo: `FlujoIntegralTest.php:333` (`EVALUACION` sigue
  `VIGENTE` tras la admisión), dejando 2 plazos `VIGENTE` simultáneos.
- **Antes de fichar como AUD-0068 se verificó el backlog: es literalmente AUD-0030**
  ("Los plazos no se cierran al concluir su fase", `BACKLOG_AUDITORIA.md:386-392`), cuya
  evidencia ya dice textualmente "**No cierran: EVALUACION tras ADMISION/OBSERVACION,
  EJECUCION tras VB final/reparto**". Estado: OPEN, P1, incorporado a la lista de
  decisiones pendientes del usuario (2026-09-30) con acción **NO aplicar** sin aprobación.
- **Decisión: no se abre ficha nueva** (evita duplicar el mismo hallazgo) y **no se
  modifica `BACKLOG_AUDITORIA.md`** (no hay hallazgo nuevo que registrar). La confirmación
  queda registrada aquí y el test `FlujoIntegralTest.php:333` queda como evidencia viva de
  AUD-0030.

**Observaciones sin ficha (informativas, no requieren acción):**

3. **AC055 no tiene `ParametroPlazo` de `EJECUCION`** (verificado en
   `database/seeders/ParametroPlazoSeeder.php:29-31`: solo EVALUACION/PLANIFICACION/DESCARGOS
   + impugnaciones). Consecuencia asertada en `FlujoIntegralTest.php:365-367`: el reloj de
   ejecución queda con `parametro_plazo_id = null` y `dias_habiles_otorgados = 0`, usando la
   fecha explícita del MPA (`ActuadoService.php:197-203`). Es el comportamiento previsto por
   RN-05, documentado; no es defecto.

**Decisiones previas del usuario invocadas en esta fase:** "comienza" (autoriza ejecutar
F16). No se pidieron nuevas.

---

## §11 Archivos modificados

| Archivo | Acción |
| --- | --- |
| `tests/Feature/FlujoIntegralTest.php` | **NUEVO** (3 tests, helpers `fi*`) |
| `tests/Feature/AdminHttpCoverageTest.php` | **NUEVO** (4 tests, helpers `ahc*`) |
| `docs/auditoria/MATRIZ_PRUEBAS.md` | Creado en F16 paso 1 (§0-§4); completado con estado + §5-§12 en este cierre; corregido en la validación de cierre (§1, §6, §9) |
| `docs/auditoria/PROGRESO.md` | Modificado: entrada de cierre F16 + cierre formal (correcciones de validación) |
| `docs/auditoria/PLAN_EJECUCION_AUDITORIA.md` | Modificado: §5 fila F16 → **VALIDADA Y CERRADA (2026-10-02)** |

**No se modificó ningún archivo de producción** (`app/`, `routes/`, `database/`,
`resources/`) ni ningún test preexistente. `BACKLOG_AUDITORIA.md` no requiere cambios
(§10.2).

---

## §12 Conclusiones

1. F16 **cierra el hueco declarado en §1**: existe ahora una prueba E2E real de 10 etapas
   (registro→salida) sobre el grafo de catálogos del proyecto, con aserciones de estado y
   bandejas en las etapas que las requieren (§6.1), los 4 relojes del ciclo con feriado real,
   hashes de custodia y los 10 actuados en orden.
2. Las suites negativas de orden y de compartimento (RF-03/IDOR) quedan cubiertas **dentro
   del flujo**, no solo con estado sembrado.
3. La suite pasa sin errores; el único fallo es la deuda conocida **AUD-0001** y no fue
   introducido ni modificado por F16.
4. **A informar al usuario:** (a) conflicto de asignación de las mutations `/api/admin/*`
   entre F16/F17 (`MATRIZ_SEGURIDAD.md:85` vs `MAPA_FUNCIONAL.md:196`, §8.1); (b)
   `MAPA_FUNCIONAL.md:196` desactualizado (§8.2); (c) AUD-0068 no creado por duplicado con
   AUD-0030, cuya corrección sigue pendiente de decisión del usuario (§10.2).
5. **F16 queda VALIDADA Y CERRADA (2026-10-02).** No iniciar F17 sin instrucción.
