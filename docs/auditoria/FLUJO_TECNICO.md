# FLUJO_TECNICO — Sorteo y flujo completo del Técnico (Fase 7)

> Auditoría según Plan Maestro §11 (SORTEO) y §12 (TÉCNICO, líneas 861-904),
> contra SRS (RN-03/04/05, RF-04, RN-08, RN-10) y código real. Verificado el
> 2026-09-29. Toda afirmación lleva `archivo:línea`. Cruzar con
> `MAQUINA_ESTADOS.md`, `MATRIZ_PLAZOS.md` y `MATRIZ_BANDEJAS.md`.

---

## §1 Sorteo (Plan §11) — 9 puntos exigidos

| Punto | Veredicto | Evidencia |
|---|---|---|
| Aleatoriedad | ✅ ruleta ponderada con `random_int` (CSPRNG); RNG inyectable para tests deterministas | `SorteoAlgorithmService:159-191` |
| Elegibilidad | ✅ solo usuarios `activo` del rol de la vía (`MAPA_VIA_ROL` TECNICO/JURIDICO/FINANCIERO) | `:30-34`, `:83-92` |
| Exclusiones | ⚠️ **solo activo+rol**: no excluye al **creador de la propia causa** (apertura es TECNICO, `ExpedientePolicy:92`), ni ausencias/vacaciones. SRS no lo exige → **O-6** (§4) | `:87-91` |
| Operadores disponibles | ✅ error explícito 422 si la vía no tiene candidatos; lote todo-o-nada (una vía sin candidatos revierte todo) | `:54-58`, `ExpedienteService:153-184` |
| Especialidad | ✅ la vía determina el motor; balance por reglamento vía `sorteo_pesos` | `:101-125` |
| Carga laboral | ✅/⚠️ pesos históricos por (usuario, reglamento): `TICKETS = (peso_max − peso) + 1`, +1 atómico por sorteo ganado (`upsert ON DUPLICATE KEY`); **el peso nunca decrementa** → balancea totales históricos, no carga activa. SRS solo pide "distribuye la carga laboral" (`SRS:45`) — diseño documentado, no es brecha | `:114-151` |
| Persistencia del resultado | ✅ `SorteoPeso` + asignación activa del ganador + estado EN_EVALUACION | `ExpedienteService:125-133` |
| Trazabilidad | ✅ `ACT_SORTEO_INICIAL` append-only con hash de cadena de custodia (trigger) y metadatos `via`/ganador como `usuario_destino_id` | `:131`, `ActuadoService:100-114` |
| Alteración silenciosa imposible | ✅ sin endpoints de edición de sorteo; `lockForUpdate` + validación PENDIENTE_SORTEO serializa y evita doble sorteo (2º intento → 422) | `ExpedienteService:114-120` |

Distinción Técnico / Auditor Jurídico / Auditor Financiero según la vía ✅ (`:30-34`; sorteo solo por rol de vía).

---

## §2 Recorrido completo del flujo Técnico (Plan §12)

| # | Paso | Implementación | Estado destino | Plazo: abre / cierra | Test | Veredicto |
|---|---|---|---|---|---|---|
| 1 | Registro → NUREJ Padre | `ExpedienteService@aperturaCausa:37-88` (transaccional, correlativo dentro de la transacción, partes versionadas) | PENDIENTE_SORTEO | abre: ninguno | `ExpedienteControllerTest` | ✅ |
| 2 | Sorteo | `ExpedienteService@ejecutarSorteo:102-137` | EN_EVALUACION | **abre EVALUACION (2/5/5 d)** | `:274`, `SorteoTodosTest` | ✅ (§1) |
| 3 | Evaluación (RF-04) | `POST /evaluacion` → `EvaluacionAdmisibilidadService@evaluar:40-89`: valida EN_EVALUACION, completitud exacta del checklist, persiste filas inmutables ligadas al actuado, decide ADMISION/OBSERVACION/RECHAZO según críticos | según resultado | cierra EVALUACION **solo si RECHAZO** (`:150-159`) | `EvaluacionAdmisibilidadTest` | ✅ RF-04 / ❌ cierre parcial → **AUD-0030** |
| 4 | Admisión | ídem servicio | EN_PLANIFICACION | abre PLANIFICACION (2 d); **EVALUACION queda VIGENTE** | `RelojProcesualTest:113` | ❌ **AUD-0030** |
| 5 | Observación | ídem servicio | EN_SUBSANACION | abre SUBSANACION (3 d) | `EvaluacionAdmisibilidadTest` | ❌ sin salida de éxito → **AUD-0032**; plazo sin cerrar → **AUD-0031** |
| 6 | Rechazo | ídem servicio; **cierra todos los relojes** (`:156-158`) | RECHAZADO | cierra todos ✅ | idem | ✅ |
| 6b | Impugnación del rechazo (RN-08) | `ImpugnacionService` (REMITIR 1 d → RESOLVER 3 d → RATIFICA / REVOCA→planificación, `ActuadoService:39-41`) | según resolución | abre/cierra IMPUGNACION_* ✅ | `ImpugnacionRechazoTest` | ✅ |
| 7 | Planificación / Cronograma | `PlanificacionService`: subida → PENDIENTE_VISTO_BUENO, **cierra PLANIFICACION al entregar** (`:223-229`), destino ENCARGADA | PENDIENTE_VISTO_BUENO | cierra PLANIFICACION ✅ | `PlanificacionTest:244-497` | ✅ |
| 8 | Visto Bueno | `PlanificacionService` VB → destino operador original | EN_INVESTIGACION (seeder; ver AUD-0022 fixtures) | **abre EJECUCION (10 d / MPA)** | `RelojProcesualTest:145` | ✅ (15 d ✗ → AUD-0024) |
| 9 | Ampliación | `AmpliacionService@aprobarAmpliacion:85-141` | EN_EJECUCION | cierra original→CERRADO, abre EJECUCION_AMPLIADA (5 d) | `AmpliacionTest` | ✅ (caso límite AUD-0026) |
| 10 | Informe Final Técnico (con/sin responsabilidad + Recomendación de Auditoría) | **no existe**: 0 de 4 informes Técnico en catálogo (SRS `:385-397` exige CON/SIN + recomendación) | — | — | — | ❌ **AUD-0008** |
| 11 | Visto Bueno final → Salida | `CierreExpedienteService` (VB → ENCARGADA; reparto → destino, cierra bandeja `:142-144`) | CONCLUIDO_REMITIDO / sin asignación | **EJECUCION jamás se cierra** (cero referencias a `Plazo` en el servicio) | `CierreExpedienteTest:102-174` | ❌ **AUD-0030** |
| 12 | NUREJ Hijo (RN-10) | `NurejHijoService` (hereda metadatos, nace PENDIENTE_SORTEO sin relojes) | PENDIENTE_SORTEO | ninguno | `NurejHijoTest:193` | ✅; sorteo del hijo emite ACT_SORTEO_INICIAL — falta **SORTEO_DERIVADO** (SRS `:311`) → AUD-0009 |

---

## §3 Checklist "validar específicamente" (Plan §12 :895-904)

| Requisito | Estado |
|---|---|
| 2 días de evaluación | ✅ parámetro AC022=2, plazo abierto en sorteo (rango 3-5 de auditores: AUD-0019) |
| 3 días de subsanación | ✅ plazo abierto / ❌ **sin ruta de éxito ni cierre** (AUD-0031/0032) |
| 2 días para cronograma | ✅ (`RelojProcesualTest:113`, `PlanificacionService:223-229`) |
| 10 días jurisdiccional | ✅ |
| 15 días administrativo | ❌ **AUD-0024** (hardcode JURISDICCIONAL) |
| Ampliación de 5 días | ✅ (`AmpliacionService`) |
| Fuera de plazo | ✅ sello CRON / ❌ **falsos positivos** (AUD-0030) |
| Informe con/sin responsabilidad | ❌ **AUD-0008** |
| Recomendación de auditoría | ❌ **AUD-0008** (SRS `:385`, `:389`: va junto al informe Técnico) |
| NUREJ Hijo | ✅ (falta sorteo derivado: AUD-0009) |

---

## §4 Hallazgos nuevos de esta fase

### AUD-0030 — Los plazos no se cierran al concluir su fase (cierre de relojes incompleto)
- **P1** (integridad de plazos / RF-07 / CA-3 `SRS:185` sanciona plazos reales).
- **Evidencia:** los únicos cierres de plazos en `app/` son: rechazo cierra todos
  (`EvaluacionAdmisibilidadService:156-158`), entrega de planificación cierra
  PLANIFICACION (`PlanificacionService:223-229`), ampliación cierra EJECUCION
  (`AmpliacionService:111`), impugnación cierra los suyos
  (`ImpugnacionService:246,257,269`), descargos → CUMPLIDO, archivo → VENCIDO.
  **No cierran:** EVALUACION tras ADMISION/OBSERVACION, EJECUCION tras
  VB final/reparto/cierre (`CierreExpedienteService` ni menciona `Plazo`),
  SUBSANACION (ver AUD-0031).
- **Impacto:** el CRON diario (`MarcarPlazosVencidosService:26-31`) estampa
  `fuera_de_plazo=true` en relojes ya cumplidos; `SemaforoPlazoService@colorMasUrgente:151-163`
  toma el plazo VIGENTE más antiguo → **cada expediente admite queda con
  semáforo FUERA_DE_PLAZO permanente** y los expedientes concluidos siguen
  contando como urgentes/vencidos en los tableros (RF-R01/R05) → falsa sanción
  interna a operadores cumplidores.
- **Acción propuesta:** cerrar el plazo de la fase al transicionar fuera de ella
  (admisión/observación → EVALUACION CERRADO; cierre/reparto → EJECUCION
  CERRADO), o filtrar en CRON/semáforo por fase actual. **Requiere aprobación.**

### AUD-0031 — Archivo por abandono sin validar el estado actual del expediente
- **P1** (acción automática destructiva sobre casos vivos; RN-03 mal aplicado).
- **Evidencia:** `ArchivoPorAbandonoService@archivarVencidos:49-54` filtra solo
  `tipo=SUBSANACION AND estado=VIGENTE AND fecha_limite < hoy` — **no consulta
  el estado del expediente**; el plazo SUBSANACION **jamás se cierra** en ninguna
  transición (grep: ningún `->update(['estado' => 'CERRADO'...])` para SUBSANACION
  fuera de este servicio). Programado a diario (`routes/console.php:11`).
- **Impacto:** un expediente que salió de EN_SUBSANACION (p. ej. salto vía
  endpoint genérico — AUD-0020 — u otras rutas futuras) y sigue vivo es
  **archivado automáticamente** por el CRON a los 3 días: emite
  ACT_ARCHIVO_POR_ABANDONO, cierra su bandeja (`:105-110`) y lo sella en
  ARCHIVO_POR_ABANDONO. Los tests solo cubren expedientes que **siguen** en
  EN_SUBSANACION (`ArchivoPorAbandonoTest:88-144,144-174,175-220`).
- **Acción propuesta:** (1) cerrar el plazo SUBSANACION en toda transición de
  salida, y/o (2) condicionar el archivo al estado actual `EN_SUBSANACION`.
  **Requiere aprobación.**

### AUD-0032 — EN_SUBSANACION sin salida de éxito (RN-03 incompleto)
- **P1** (flujo muerto en el módulo de admisibilidad).
- **Evidencia:** en `CatalogoActuadoSeeder`, `EN_SUBSANACION` es origen de
  **un único** actuado: `ACT_ARCHIVO_POR_ABANDONO` (`:66`, automático) —
  `grep '$subsanacion'` confirma que solo ACT_OBSERVACION entra (`:55`) y solo
  el archivo sale. No existe actuado "subsanación aceptada/acepta subsanación"
  ni transición de vuelta a EN_EVALUACION/EN_PLANIFICACION. El servicio de
  evaluación exige estado EN_EVALUACION (`EvaluacionAdmisibilidadService:91-100`)
  y `MAPA_TIPO_PLAZO` no tiene salida desde subsanación.
- **Impacto:** conforme al Plan §12 (Admisión/Obs → Subsanación → Planificación)
  y a RN-03 ("*Si caduca **y no se subsana**…" implica un caso en que sí se
  subsana), el único camino registrado para un caso observado es el **archivo
  automático**; el éxito solo es posible con saltos de estado irregulares
  (AUD-0020).
- **Acción propuesta:** crear el actuado de salida (p. ej.
  `ACT_SUBSANACION_ACEPTADA`, EN_SUBSANACION → EN_EVALUACION o EN_PLANIFICACION,
  con cierre del plazo) — catálogo nuevo → requiere migración de catálogo con
  aprobación (línea con AUD-0009).

### O-6 (observación, sin ficha)
- El Técnico que creó la causa puede ganar su propio sorteo (candidatos = todos
  los TECNICO activos; SRS no lo prohíbe). ¿Excluir al creador? → decisión
  usuario.
- **Resuelta (cierre F12, 2026-09-30): "Comportamiento aceptado
  provisionalmente; el SRS no establece prohibición explícita de
  autoasignación."** Si se decide excluir al creador → abrir como cambio
  funcional separado con sus respectivos tests.

---

## §5 Cobertura de tests del flujo

`ExpedienteControllerTest` (registro/sorteo), `SorteoTodosTest`, `EvaluacionAdmisibilidadTest` (RF-04 completo), `RelojProcesualTest` (relojes), `PlanificacionTest`, `AmpliacionTest`, `ImpugnacionRechazoTest`, `ArchivoPorAbandonoTest`, `CierreExpedienteTest`, `NurejHijoTest`, `DescargoFinancieroTest`, `DerivacionTransparenciaTest`.
**Sin tests para:** AUD-0030 (cierre de fase), AUD-0031 (archivo de caso fuera de EN_SUBSANACION), AUD-0032 (salida de subsanación), O-6.

## §6 Decisiones del usuario — RESUELTAS (2026-09-30)

1. **AUD-0030:** **incorporado a la lista de decisiones pendientes del
   usuario (cierre F12, 2026-09-30); NO aplicar.** Propuesta mantenida:
   cerrar plazo de fase al transicionar o filtrar CRON/semáforo por fase
   (a decidir).
2. **AUD-0031:** **incorporado a la lista de decisiones pendientes (cierre
   F12); NO aplicar.** Propuesta mantenida: condicionar archivo al estado
   EN_SUBSANACION + cerrar plazo SUBSANACION en toda salida.
3. **AUD-0032:** **incorporado a la lista de decisiones pendientes (cierre
   F12); NO aplicar.** Propuesta mantenida: actuado de salida de
   subsanación; migración de catálogo con autorización específica al
   momento.
4. **O-6:** **CERRADA (cierre F12): "Comportamiento aceptado
   provisionalmente; el SRS no establece prohibición explícita de
   autoasignación."** Sin exclusión del creador en esta fase; si se decide
   luego → cambio funcional separado con sus respectivos tests.
5. (Acumuladas → **RESUELTAS 2026-09-30** en BACKLOG/PROGRESO:
   AUD-0001 → deuda conocida aceptada (semántica ADMIN → hardening),
   0019/0020/0021/0023/0024/0025/0027/0028/0029 decididos;
   **AUD-0002 resuelto** → ficha CERRADO, `MATRIZ_PLAZOS.md` §7.)
