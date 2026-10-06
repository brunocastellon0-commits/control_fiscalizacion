# Fase 13 — Jobs/cron: `plazos:verificar-vencidos`

Estado: **VALIDADA Y CERRADA (2026-09-30)** — decisiones del usuario sobre
AUD-0042/AUD-0043 registradas en §9 (cierre de validación).

Protocolo: toda referencia `archivo:línea` verificada antes de escribirse.
Evidencia de BD dev = solo lectura salvo la ejecución del comando (ver §5:
falló antes de escribir nada).

## §0 Alcance (Plan §3 F13)

`VerificarVencimientoPlazosCommand`, `MarcarPlazosVencidosService`,
`ArchivoPorAbandonoService` → **idempotencia, timezone, duplicados**.

## §1 Inventario verificado

| Pieza | Ubicación | Comportamiento |
|---|---|---|
| Comando | `app/Console/Commands/VerificarVencimientoPlazosCommand.php:22-33` | `plazos:verificar-vencidos`: llama `archivarVencidos()` y luego `marcarVencidos()`; imprime conteos; `SUCCESS` |
| Archivo por abandono (RN-03) | `app/Services/ArchivoPorAbandonoService.php:43-86` | plazos `SUBSANACION` `VIGENTE` con `fecha_limite < now()->toDateString()`; por cada uno, en transacción ×3 reintentos: plazo → `VENCIDO` + `fuera_de_plazo`, actuado `ACT_ARCHIVO_POR_ABANDONO` (`:67-77`), cierra asignación activa (`:105-110`) |
| Fuera de plazo (QA 3) | `app/Services/MarcarPlazosVencidosService.php:26-31` | plazos ≠ `SUBSANACION`, `VIGENTE`, `fuera_de_plazo = false`, `fecha_limite < hoy` → `update` flag; no transiciona expediente |
| Programación | `routes/console.php:11` | `Schedule::command('plazos:verificar-vencidos')->daily()` → `0 0 * * *` (verificado con `php artisan schedule:list`) |
| Requisito SRS | `SRS_EXTRAIDO.txt:442-446` | Archivo por Abandono: "**se dispara a la medianoche**"; marca fuera de plazo al llegar a cero |
| Zona horaria | `php artisan config:show app.timezone` → **UTC**; grep `timezone\|America/` en repo = **0** | no hay zona horaria institucional configurada |

## §2 Idempotencia — VERIFICADA

- **Análisis:** `MarcarPlazosVencidosService:29` filtra `fuera_de_plazo = false`
  → segunda corrida no vuelve a marcar. `ArchivoPorAbandonoService:50-52`
  filtra `estado = VIGENTE` y al archivar pasa a `VENCIDO` → sale del conjunto;
  el actuado solo se emite dentro de esa transacción (`:59-80`).
- **Tests preexistentes:** `SemaforoPenalizacionTest` («no vuelve a marcar un
  plazo que ya quedó fuera de plazo») y `ArchivoPorAbandonoTest` (3 tests del
  comando).
- **Test nuevo:** `tests/Feature/VerificarVencimientoPlazosTest.php`
  «es idempotente: la segunda corrida del comando no archiva ni marca nada de
  nuevo» → segunda corrida con `exit 0`, conteo de actuados sin cambio,
  exactamente 1 actuado de archivo.

## §3 Timezone — CORTE UTC PREMATURO REPRODUCIDO (AUD-0043)

- El comando corre a **00:00 UTC = 21:00 de Argentina (UTC-3)**; ambas
  comparaciones usan la fecha **UTC** (`now()->toDateString()` en
  `ArchivoPorAbandonoService:52` y `MarcarPlazosVencidosService:30`).
- Consecuencia: un plazo con `fecha_limite = hoy` se considera vencido a las
  **21:00** hora local del propio día de vencimiento — hasta **3 horas antes
  de la medianoche local** que pide el SRS (`SRS_EXTRAIDO.txt:443`) — y el
  expediente se **archiva de forma irreversible** (actuado inmutable) en esa
  ventana.
- **Reproducción experimental** (test verde):
  `VerificarVencimientoPlazosTest` «archiva con la fecha UTC ya cambiada pero
  aún es el día de vencimiento en Argentina»: `Carbon::setTestNow('2026-09-08
  00:05:00')` UTC = 2026-09-07 21:05 ART, `fecha_limite = 2026-09-07` → el
  job archiva igual.
- Los tests preexistentes codifican esta semántica UTC (p. ej.
  `ArchivoPorAbandonoTest:115` congela `00:05` "día siguiente" en UTC).
- **Corrección posible (NO aplicada):** fijar zona horaria institucional
  (`app.timezone` + `Schedule`), decisión del usuario - cambio de configuración
  global con efecto en todo el sistema (plazos, semáforos, feriados) →
  requiere confirmación explícita antes de tocar.
- **Decisión del usuario (2026-09-30):** se **confirma el SRS**: el
  vencimiento/corte debe producirse a las **23:59 de la fecha
  correspondiente, según la zona horaria institucional aplicable**, y no
  anticiparse por ejecutar el scheduler a las 00:00 UTC. La corrección
  queda como **pendiente de desarrollo (AUD-0043)**; **no implementar el
  fix en esta etapa de auditoría**.

## §4 Duplicados / concurrencia — OBSERVACIÓN (O-10)

- `routes/console.php:11` no define `withoutOverlapping()`, `onOneServer()` ni
  lock; dos corridas solapadas leerían el mismo plazo antes del `update` y
  podrían emitir **dos actuados** de archivo (no hay índice único
  `(expediente_id, tipo_plazo)` — verificado en
  `create_plazos_table` migración, contexto AUD-0036).
- **No reproducido experimentalmente** (los stress tests con
  `RUN_STRESS_TESTS=1` no cubren este comando) → se registra como
  **observación O-10, sin elevar severidad** (regla del usuario 2026-09-30).

## §5 Dependencias de configuración vs seeders — ACCIÓN AUD-0037(a) CUMPLIDA

| Dependencia | Requerida por | Definida en | Estado en BD dev |
|---|---|---|---|
| Catálogo `ACT_ARCHIVO_POR_ABANDONO` | `ArchivoPorAbandonoService:47` (`firstOrFail`) | `CatalogoActuadoSeeder:66` (+ estado `ARCHIVO_POR_ABANDONO`, `:38`) | **AUSENTE (0 filas)** |
| Usuario sistema: ADMIN activo (`orderBy('id')`) | `ArchivoPorAbandonoService:92-99` (`firstOrFail`) | usuarios reales | 3 activos ✓ |

- **Ejecución real en BD dev** (estado verificado por tinker solo-lectura
  previo: `catalogo_existe=0`, `subs_vigente_vencidas=1`,
  `internas_sin_marcar=7`):
  `php artisan plazos:verificar-vencidos` → **`ModelNotFoundException`
  (exit=1)** en `ArchivoPorAbandonoService:47`, antes de procesar nada.
  **RN-03 y la marca `fuera_de_plazo` NO se ejecutan en dev**: 1 plazo de
  subsanación vencido queda sin archivar y 7 plazos internos sin marcar
  (el fallo ocurre antes de `MarcarPlazosVencidosService`, línea 26-27 del
  comando). La única salida es la traza de consola del cron — no hay log ni
  degradación controlada.
- Este es el escenario del `firstOrFail` de configuración documentado en
  **AUD-0037** (acción propuesta "(a) mapear en Fase 13"): ejecutada aquí.
- **Corrección posible (NO aplicada, requiere autorización):** validar
  dependencias al inicio y degradar controladamente (log de error +
  continuar con `marcarVencidos()` / abortar con mensaje explícito), en vez
  de excepción no capturada.
- **Decisión del usuario (2026-09-30):** **AUD-0042 queda P2 OPEN como
  pendiente de desarrollo.** Esta etapa es de auditoría: **no se implementa
  ahora** la degradación controlada/log; la auditoría ya identificó y
  documentó correctamente el problema. Registrar como backlog de
  desarrollo posterior.

## §6 Comportamiento en fallo (nota, sin ficha)

`archivarVencidos()` no envuelve el `foreach` en catch: una excepción en un
expediente interrumpe la corrida y los plazos restantes **no** se procesan
hasta la siguiente ejecución diaria (auto-sanante: siguen `VIGENTE`, vuelven a
salir en la consulta). Transacciones individuales por expediente
(`:59-80`) → sin efectos parciales sobre un mismo expediente.

## §7 Hallazgos nuevos (registrados en `BACKLOG_AUDITORIA.md`)

| ID | Sev. | Resumen |
|---|---|---|
| **AUD-0042** | P2 | El job completo falla (exit 1, excepción no capturada) si falta el catálogo o un ADMIN activo; en BD dev **ya falla hoy** (evidencia §5) y ni RN-03 ni la marca de fuera de plazo corren. Fix propuesto: degradación controlada + log. **No aplicado (toca producto).** |
| **AUD-0043** | P2 | Ejecución/corte a 00:00 UTC = 21:00 ART: archiva y marca hasta 3 h antes de la medianoche local (SRS pide medianoche). Fix = decisión de zona horaria institucional. **CERRADO (2026-10-05, tarea B3.3): `app.timezone` = `America/La_Paz` vía `env()`, verificado con `TimezoneBoliviaTest`.** |
| **O-10** | obs. | Sin `withoutOverlapping()`/lock: corridas solapadas podrían duplicar el actuado de archivo. No reproducido → no elevado. |

Sin cambios de producto en la fase (solo tests + documentación).

**Decisiones del usuario (2026-09-30):**
- **AUD-0042 → P2 OPEN, pendiente de desarrollo.** No se implementa la
  degradación controlada/log en auditoría; problema identificado y
  documentado (§5) → backlog de desarrollo posterior.
- **AUD-0043 → SRS confirmado:** corte a las **23:59 de la fecha
  correspondiente según la zona horaria institucional aplicable**;
  corrección pendiente de desarrollo. **No implementar el fix ahora.**

## §8 Gates

- `vendor/bin/pint --dirty --format agent` → **OK**.
- `php artisan test --compact` → **296 tests: 289 OK · 1 fallo (AUD-0001,
  deuda conocida aceptada en el cierre de F12) · 0 errores · 6 omitidos**
  (+3 tests nuevos: idempotencia, corte UTC, catálogo ausente — 17
  aserciones en el archivo nuevo).

## §9 Cierre de validación (2026-09-30)

- **Entregables:** `MATRIZ_JOBS_CRON.md` (§0-§8) +
  `tests/Feature/VerificarVencimientoPlazosTest.php` (3 tests / 17
  aserciones).
- **Tests/gates:** pint OK; suite **296: 289 OK · 1 fallo (AUD-0001,
  deuda aceptada en F12) · 0 errores · 6 omitidos** (re-ejecutados como
  gate de este cierre; sin cambios de código en la fase).
- **Decisiones del usuario:** **AUD-0042** → P2 OPEN, pendiente de
  desarrollo (no implementar degradación/log en auditoría); **AUD-0043** →
  SRS confirmado: corte a las **23:59 de la fecha correspondiente según la
  zona horaria institucional aplicable**, fix pendiente de desarrollo, no
  implementar ahora.
- **Pasan a backlog de desarrollo:** **AUD-0042, AUD-0043** (+ relacionados
  ya existentes: AUD-0023, AUD-0036, AUD-0037(b); O-10 ya decidida como
  monitorización no elevada).
- **Sin pendientes de auditoría que bloqueen F13** (O-10 y AUD-0037(b)
  permanecen decididos/no bloqueantes; el resto son desarrollos futuros).

**Fase 13 VALIDADA Y CERRADA (2026-09-30).**
