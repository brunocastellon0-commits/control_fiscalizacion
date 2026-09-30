# MATRIZ_PLAZOS — Motor de plazos, días hábiles y relojes (Fase 5)

> Auditoría del motor de cálculo de plazos contra el SRS (`SRS_EXTRAIDO.txt`),
> el código real y la BD de desarrollo. Base para cerrar AUD-0002 (análisis de
> raíz, §7) y para los hallazgos AUD-0024/0025/0026.
> Fecha de verificación: 2026-09-29. Toda afirmación lleva `archivo:línea`.

---

## §0 Alcance y método

| Fuente | Qué se verificó |
|---|---|
| SRS | RN-03 (`:138-140`), RN-04 (`:141-143`), RN-05 (`:144-149`), RN-08 (`:156-158`), RN-09 (`:159` ss.), Tabla Comparativa (`:265-289`), CA "Auditoría de Plazos" (`:181`), CA "Control de Vencimiento" (`:185`) |
| Código | `PlazoCalculatorService`, `ActuadoService@abrirPlazoSiAplica` (`:165-215`), `DescargoFinancieroService` (`:243-297`), `AmpliacionService` (`:85-141`), `TransparenciaService@congelarPlazos` (`:127-133`), `MarcarPlazosVencidosService`, `ArchivoPorAbandonoService`, `SemaforoPlazoService`, `VerificarVencimientoPlazosCommand` |
| Parámetros | `ParametroPlazoSeeder` (17 filas) vs BD dev (**8 filas** — ver §2.2) |
| Tests | `PlazoCalculatorServiceTest` (6 unit), `RelojProcesualTest` (3), `Semaforo*` (3), `ArchivoPorAbandonoTest`, `DerivacionTransparenciaTest`, `PlazoResourceTest`, `DescargoFinancieroTest` (1 en fallo = AUD-0002) |

---

## §1 Motor de cálculo (`PlazoCalculatorService`)

Reglas verificadas línea a línea:

| Regla | Implementación | Veredicto |
|---|---|---|
| El día de inicio **no** cuenta | `calculateDueDate` suma primero `addDay()` (`:44-45`) | ✅ CA-1/RN-09 |
| Sábados y domingos se omiten | `if ($fecha->isWeekend()) continue;` (`:47-49`) | ✅ SRS `:181` |
| Feriados se omiten | `in_array(..., $feriados)` (`:51-53`), catálogo cargado de BD (`:141-144`) | ✅ (ver O-1, §8) |
| Suspensiones (rango inclusivo) | `isSuspendedDate` inicio≤fecha≤fin (`:122-136`), cargadas de BD (`:149-152`) | ✅ test unitario |
| Fin de plazo = 23:59:59 | `return $fecha->endOfDay();` (`:62`) | ✅ |
| Recálculo al reanudar (RN-09) | `businessDaysBetween(pausa, límite)` + `calculateDueDate(hoy, restantes)` (`:85-117`, uso en `DescargoFinancieroService:284-290`) | ✅ no pierde ni suma días |
| Días restantes (semáforo) | `daysRemaining` = `businessDaysBetween(hoy, límite)` (`:69-76`) | ✅ |

**Cobertura unitaria:** 6 tests en `tests/Feature/PlazoCalculatorServiceTest.php`
(fines de semana `:8`, feriados `:18`, suspensión `:29`, día de inicio `:45`,
vencido→0 `:54`, restantes `:62`). ✅ CA-1 (`SRS :181`) testeado.

---

## §2 Parámetros normativos vs SRS vs BD

### §2.1 Seeder (`ParametroPlazoSeeder`) vs SRS

| Reglamento | Tipo plazo (subtipo) | Días | SRS | Veredicto |
|---|---|---|---|---|
| AC022 | EVALUACION | 2 | Tabla :265-269 "2 días estrictos" | ✅ |
| AC022 | SUBSANACION | 3 | RN-03 `:138` / tabla `:270-274` | ✅ solo rígidamente Técnico (auditores sin SUBSANACION ✅) |
| AC022 | PLANIFICACION | 2 | RN-04 `:141-143` | ✅ |
| AC022 | EJECUCION (JURISDICCIONAL) | 10 | RN-05 `:144-149` | ✅ |
| AC022 | EJECUCION (ADMINISTRATIVA) | 15 | RN-05 "15 días" | ⚠️ **inalcanzable — AUD-0024 (§2.3)** |
| AC022 | EJECUCION_AMPLIADA | 5 | RN-05 "+5 ampliación" | ✅ (`AmpliacionService`) |
| AC054 | EVALUACION | 5 | Tabla "3 a 5 según complejidad" | ⚠️ AUD-0019 (rango no parametrizable) |
| AC055 | EVALUACION | 5 | Tabla "hasta 5 (SAFCO)" | ✅ |
| AC054/055 | PLANIFICACION | 2 | RN-04 / tabla `:275-279`: "MPA **sin plazo estricto**" | ⚠️ **AUD-0025 (§2.4)** |
| AC055 | DESCARGOS | 5 | RN-09 / AC055 | ✅ |
| AC022/054/055 | IMPUGNACION_REMITIR / _RESOLVER | 1 / 3 | RN-08 `:156-158` | ✅ |
| AC054/055 | EJECUCION (por MPA) | fecha fija | RN-05 "límite = fecha MPA" | ✅ vía `fechaLimiteExplicita` (`ActuadoService:161-163,197-199`; caller `PlanificacionService:120`; `dias_habiles_otorgados=0` porque AC054/055 no tienen parámetro EJECUCION) |

### §2.2 BD de desarrollo: `parametros_plazo` = **8 de 17** (query directa 2026-09-29)

Presentes: AC022 (EVAL, SUBS, PLAN, EJEC-JUR, EJEC-ADMIN), AC054 (EVAL), AC055 (EVAL, DESC).
**Faltan 9:** AC022 `EJECUCION_AMPLIADA` + `IMPUGNACION_REMITIR/RESOLVER`; AC054/055 `PLANIFICACION` + `IMPUGNACION_*` (2×3).

Consecuencias verificadas en dev:
- `AmpliacionService@parametroAmpliacion` y `ImpugnacionService` usan `firstOrFail` → **404/500 en dev** al amplificar/impugnar.
- `ActuadoService@abrirPlazoSiAplica:193-195`: si falta el parámetro y no hay fecha explícita → **no abre el reloj en silencio** (AC054/055 no abren PLANIFICACION en dev).

→ Amplía **AUD-0023** (BD desactualizada; también catálogos: 7/25 actuados, 16/21 estados). Requiere confirmación del usuario para re-seed.

### §2.3 AUD-0024 — RN-05: 15 días "Administrativa" inalcanzable

- `ActuadoService@resolveSubtipoEjecucion:230-233` **devuelve `'JURISDICCIONAL'` siempre** (docblock: "ADMINISTRATIVA… es una historia futura").
- No existe atributo de naturaleza en `expedientes` (migración: solo `via ∈ {TECNICO, JURIDICO, FINANCIERO}` = vía de operador, `StoreExpedienteRequest:22`; grep `naturaleza|jurisdic|ADMINISTRATIVA` → 0 usos en `app/`).
- Resultado: **todo caso AC022 corre con 10 días**; el parámetro de 15 (Ac. 022 Art. 27 II) es configuración muerta. RN-05 (`SRS :147`) incumplida.

### §2.4 AUD-0025 — PLANIFICACION=2 para auditores vs "sin plazo estricto"

- RN-04/tabla (`:141-143`, `:275-279`): el Técnico tiene 2 días; **los Auditores elaboran el MPA sin plazo estricto predefinido**.
- El código abre `PLANIFICACION` (2 días) para AC054/055 también (`MAPA_TIPO_PLAZO['ACT_ADMISION']`, `ActuadoService:36`; parámetro `ParametroPlazoSeeder:28,30`).
- Efecto: semáforo/fuera_de_plazo sobre un plazo que el SRS no contempla para MPA (no bloquea — CA-3 — pero estampa penalización). En dev ni siquiera se abre (falta el parámetro, §2.2) → comportamiento distinto por entorno. **Requiere decisión del usuario.**

---

## §3 Qué abre cada reloj (quién, cuándo, parámetro)

| Fase | Disparador | Reloj abierto | Parámetro | Servicio |
|---|---|---|---|---|
| Evaluación | `ACT_SORTEO_INICIAL` (`ActuadoService:34`) | EVALUACION | 2/5/5 por reglamento | `ActuadoService@abrirPlazoSiAplica:165-215` |
| Subsanación | `ACT_OBSERVACION` (`:35`) | SUBSANACION (AC022) | 3 | idem; vencimiento → **Archivo por Abandono** (RN-03) |
| Admisión | `ACT_ADMISION` (`:36`) | PLANIFICACION | 2 (incl. auditores ⚠️ AUD-0025) | idem |
| Visto Bueno planificación | `ACT_VISTO_BUENO_PLANIFICACION` (`:37`) | EJECUCION (JURISDICCIONAL 10 o MPA fijo) | 10 / fecha MPA | idem; auditores con `fechaLimiteExplicita` (`PlanificacionService:120`) |
| Ampliación (AC022) | `ACT_APROBAR_AMPLIACION` | EJECUCION_AMPLIADA; cierra original → CERRADO | 5 desde el **límite original** (`AmpliacionService:106-111,128-137`) | `AmpliacionService@aprobarAmpliacion` |
| Descargos (AC055) | `ACT_COMUNICACION_HALLAZGOS` | DESCARGOS + congela EJECUCION (`DescargoFinancieroService:96-97,243-275`) | 5 | `DescargoFinancieroService`; cierre con `ACT_RECEPCION_DESCARGOS` y reanudación (`:140-145,282-297`) |
| Impugnación | `ACT_RECHAZO` / `ACT_REMITIR_IMPUGNACION` (`:39-40`) | IMPUGNACION_REMITIR (1) / _RESOLVER (3) | 1 / 3 | `ActuadoService` + `ImpugnacionService` |
| Derivación Transparencia | — | congela **todos** los relojes VIGENTES → SUSPENDIDO | — | `TransparenciaService@congelarPlazos:127-133` (punto final sellado, RN-06) |

Todos los `Plazo::create` pasan por la calculadora (`calculateDueDate`) salvo el
MPA con fecha explícita (límite normativo del propio MPA, RN-05).

---

## §4 Vencimiento, "Fuera de Plazo" y semáforo (CA-3 / SRS `:185`)

| Requisito SRS `:185` | Implementación | Veredicto |
|---|---|---|
| No bloquea la continuidad si el plazo vence | `MarcarPlazosVencidosService:24-32` solo estampa `fuera_de_plazo=true`; el plazo sigue VIGENTE y ningún policy/endpoint bloquea por vencimiento (grep `VENCIDO` en `app/`: solo flags y tableros) | ✅ |
| Estampa "Fuera de Plazo" persistente | flag en `plazos` (`Plazo:25,36`), CRON diario `plazos:verificar-vencidos` (`VerificarVencimientoPlazosCommand`) | ✅ |
| Alerta en tableros de Jefatura | `EncargadaDashboardService:189`, `AdminMonitoreoController:211`, `AdminDashboardController:208`, `SemaforoPlazoService` | ✅ |
| RN-03 archivo automático por abandono | `ArchivoPorAbandonoService` (SUBSANACION vencida → VENCIDO + archiva) | ✅ testeado |
| Semáforo RF-R01/R05 | `SemaforoPlazoService:98-125`: FUERA_DE_PLAZO / ROJO ≤1 día / AMARILLO (2 en plazos ≤3, o ≤1/3 del total) / VERDE | ✅ |

**Cobertura de tests:** comando/flag testado en `ArchivoPorAbandonoTest:117,164,214`,
`SemaforoPenalizacionTest:131,170,211,250`, `DerivacionTransparenciaTest:311`;
semáforo en `SemaforoPlazoServiceTest`, `SemaforoPlazosFeatureTest`, `PlazoResourceTest`.

---

## §5 Cobertura por requisito

| Requisito | Estado | Evidencia |
|---|---|---|
| CA-1 motor multimotor (omite sáb/dom/feriados) | ✅ testeado | `PlazoCalculatorServiceTest` ×6 |
| RN-03 subsanación 3 días → archivo | ✅ | `ArchivoPorAbandonoTest` |
| RN-04 planificación Técnico 2 días | ✅ | `RelojProcesualTest:113` |
| RN-04 MPA auditores sin plazo estricto | ❌ AUD-0025 | §2.4 |
| RN-05 ejecución 10 días (Jurisdiccional) | ✅ | `RelojProcesualTest:145` |
| RN-05 ejecución 15 días (Administrativa) | ❌ AUD-0024 | §2.3 |
| RN-05 ejecución auditores = fecha MPA | ✅ código | `PlanificacionService:120` |
| RN-05 ampliación +5 | ✅ | `AmpliacionService:106-137` + `AmpliacionTest` |
| RN-08 impugnación 1/3 | ✅ | `ImpugnacionRechazoTest` |
| RN-09 descargos 5 + pausa/reanudación | ⚠️ motor ✅ / test en fallo | §7 (AUD-0002) |
| CA-3 fuera de plazo no bloquea + estampa | ✅ | §4 |
| Suspensiones (rango, recálculo) | ⚠️ motor ✅ / sin CRUD ni recálculo retroactivo | AUD-0012 |

---

## §6 Parámetros en BD vs decisión de re-seed (AUD-0023)

BD dev 2026-09-29 (query directa): `parametros_plazo`=8/17, `feriados`=5
(4 NACIONAL + 1 LOCAL `2026-07-16`), `suspensiones`=0. **No se ejecuta ningún
seed sin confirmación explícita del usuario** (operación que altera datos).

---

## §7 AUD-0002 — Análisis de raíz COMPLETADO (Fase 5)

**Hecho objetivo:** `DescargoFinancieroTest:188` espera sub-reloj con
`fecha_limite = 2026-09-23`; la ejecución del 2026-09-29 calcula `2026-10-06`.

**Aritmética verificada (motor):** el test no congela el tiempo (grep: 0
`travelTo`/`setTestNow`/`Carbon::setTestNow` en `DescargoFinancieroTest`) y el
servicio usa `now()` (`DescargoFinancieroService:270-271`) con
`calculateDueDate(now(), 5)`:

- Ejecución **2026-09-29 (mar)** → mié 30(1), jue 01-10(2), vie 02(3), sáb/dom omitidos, lun 05(4), mar 06(5) = **2026-10-06 23:59:59** — coincide exactamente con el valor observado en el fallo.
- Si `now = 2026-09-15` (fecha para la que el test fue escrito): 15 y 16 son los feriados que **el propio test crea** (`:153-154`) → jue 17(1), vie 18(2), lun 21(3), mar 22(4), mié 23(5) = **2026-09-23** = la aserción `:188`.
- La segunda aserción `:208` espera `2026-09-25` = `now()->addDays(10)` del helper (`:145`, días **calendario**, semilla del test) con `now = 2026-09-15` → 25-09. También dependiente de la fecha.

**Veredicto:**
- **H1 CONFIRMADA** — el test fija fechas de calendario dependientes de la fecha
  de ejecución (autoral ≈ 2026-09-15) sin congelar el reloj.
- **H2 DESCARTADA** — el motor cumple CA-1 (`SRS :181`: omite sáb/dom/feriados
  con "precisión matemática") y RN-09 (5 días hábiles AC055); la aserción
  alternativa (2026-09-23) es exactamente lo que el motor produce con
  `now = 2026-09-15`.

**Corrección aplicada (2026-09-30, aprobada por el usuario — solo el test):**
congelar el tiempo en `tests/Feature/DescargoFinancieroTest.php:157-161`
(`Carbon\Carbon::setTestNow('2026-09-15 10:00:00')` + reset en `:214-215`).
El **servicio no se tocó**: su cálculo es correcto (H2 descartada).

**Estado de la ficha:** **CERRADO (2026-09-30).** Evidencia: archivo 5/5 verde;
suite completa pasa de 2 fallos a 1 (solo quedan AUD-0001 y AUD-0003 del
baseline).

---

## §8 Observaciones sin hallazgo formal

- **O-1 (feriados):** `PlazoCalculatorService@loadFeriados:141-144` ignora el
  campo `ambito` (NACIONAL/LOCAL) y aplica todos los feriados a todos los
  casos; el SRS (`:181`) no distingue ámbito y la tabla `expedientes` no tiene
  ámbito, así que hoy es consistente — pero el 2026-07-16 (Feria LOCAL) pausa
  relojes de todos los trámites. Si se necesita distinción futuro → diseño.
- **O-2:** aprobar una ampliación sobre una ejecución ya vencida (sigue
  VIGENTE por CA-3) calcula el límite ampliado **desde el límite vencido**
  (`AmpliacionService:106-109`), no desde hoy → la ventana +5 puede ser
  parcial o nula. Riesgo P3 (registrado como AUD-0026).
- **O-3:** helper del test crea `fecha_limite` con días **calendario**
  (`DescargoFinancieroTest:145`), no hábiles — solo afecta la semilla del test,
  no producción.

---

## §9 Decisiones del usuario derivadas de esta fase — RESUELTAS (2026-09-30)

1. ~~**AUD-0002:** ¿aprobar la corrección del test?~~ — **RESUELTO
   2026-09-30: aprobado y aplicado (§7), ficha CERRADO.**
2. **AUD-0024:** **RESUELTO — P1 confirmado, NO fuera de alcance.** Campo
   explícito `naturaleza` (JURISDICCIONAL/ADMINISTRATIVA) que determine el
   plazo; NO reutilizar `via`. Fix de esquema NO aplicado: antes presentar
   impacto de migración, alta, edición/enmienda, plazos y tests.
3. **AUD-0025:** **RESUELTO — P2 confirmado.** AC022 Técnico →
   PLANIFICACION = 2 días; AC054/AC055 → MPA sin reloj rígido de 2 días;
   la fecha de ejecución aprobada continúa siendo la del MPA. NO aplicado:
   antes cambio mínimo + tests.
4. **AUD-0023:** **RESUELTO — P2.** NO `db:seed` global; preparar sync
   idempotente de catálogos (identificar seeders, sin reprocesar demo);
   ejecución en dev con autorización específica posterior.
5. **AUD-0019:** **RESUELTO — P2.** Parametrizar 3-5 días según complejidad
   (3/4/5 según complejidad; no asumir 5). Fix NO aplicado: antes documentar
   la fuente de la "complejidad".
