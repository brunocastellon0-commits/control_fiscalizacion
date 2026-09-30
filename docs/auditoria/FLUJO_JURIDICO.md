# FLUJO_JURIDICO — Flujo completo del Auditor Jurídico (Fase 8)

> Auditoría según Plan §3 (F8: "happy path + error + no autorizado + estado
> inválido + concurrencia") contra SRS (RN-02/04/07/08/09, RF-03/RF-04,
> `SRS_EXTRAIDO.txt:96-100,161,166,393-402`) y código real. Verificado el
> 2026-09-30. Toda afirmación lleva `archivo:línea`. Cruzar con
> `MAQUINA_ESTADOS.md`, `MATRIZ_PLAZOS.md`, `MATRIZ_BANDEJAS.md`,
> `MATRIZ_SEGURIDAD.md` y `FLUJO_TECNICO.md`.

---

## §0 Alcance e identidad del rol

**No existe ruta, controlador ni vista que se llame "jurídico"** (grep
`juridic` en `app/` → 0 coincidencias). El rol entra por
`Rol::CODIGO_AUD_JURIDICO` (`app/Models/Rol.php:17`) y comparte el 100 % de la
superficie con TÉCNICO y AUD_FINANCIERO, diferenciándose por:

| Diferenciador | Evidencia |
|---|---|
| Sorteo por vía | `SorteoAlgorithmService:30-33` mapea `JURIDICO → Rol::CODIGO_AUD_JURIDICO` |
| Pivote `catalogo_actuado_roles` | `CatalogoActuadoSeeder:113-115`: `ACT_INFORME_FINAL` **solo** AUD_JURIDICO, `reglamento_id = null` |
| Política de descargos (RN-09 `:161`) | descargos **exclusivos** AUD_FINANCIERO: `ExpedientePolicy:352-376` |
| Plazo de evaluación RN-02 `:137` | "Motor Jurídico/Financiero: 3 o 5 días" → parametrado fijo (AUD-0019) |
| Sin producto propio de UI | grep de `impugnaci\|informe\|resolver` en `resources/views/**/*.blade.php` → **0** (ver AUD-0035) |

---

## §1 Recorrido completo del flujo Jurídico

| # | Paso | Implementación | Estado destino | Plazo: abre / cierra | Test | Veredicto |
|---|---|---|---|---|---|---|
| 1 | Registro → sorteo a la vía JURIDICO | `ExpedienteService@ejecutarSorteo:102-137` (vía → rol) | EN_EVALUACION | abre EVALUACION | `SorteoTodosTest` | ✅ (Fase 7) |
| 2 | Bandeja del Auditor Jurídico (web + API) | `WorkstationController@bandejaOperador:15` / `ExpedienteController@bandejaOperador:70`; policy `operadorBandeja` incluye AUD_JURIDICO (`ExpedientePolicy:59-63`) | — | — | `WebWorkstationRoutesTest:32`, `ExpedienteControllerTest:242` | ✅ (API sin `authorize` → O-4 de Fase 6) |
| 3 | Evaluación de admisibilidad (RF-04 `:109`) | `POST /evaluacion` → `EvaluacionAdmisibilidadService@evaluar:40-89`; policy solo exige asignación activa (`ExpedientePolicy:39-46`) | ADMISION / OBSERVACION / RECHAZO | cierra EVALUACION solo si RECHAZO (`:150-159`) | `EvaluacionAdmisibilidadTest` | ✅ / ❌ cierre parcial → **AUD-0030** |
| 4 | MPA (RN-04 `:143`, AC054) | `PlanificacionService` (catálogo AC054/AC055 → `ACT_MPA`, pivote jurídico+AC054 `CatalogoActuadoSeeder:60,106-109`) | PENDIENTE_VISTO_BUENO | cierra PLANIFICACION | `PlanificacionTest:215` (MPA jurídico AC054) | ✅ (2 d → AUD-0025) |
| 5 | Impugnación del rechazo (RN-08 `:330-339`) | `ImpugnacionService@remitirImpugnacion:54-91` / `@resolverImpugnacion:103-160`; policy `remitirImpugnacion` **sí incluye AUD_JURIDICO** (`ExpedientePolicy:135-150`); `resolver` solo ENCARGADA (`:156-161`) | EN_IMPUGNACION → ARCHIVO_DEFINITIVO / ADMITIDO | abre IMPUGNACION_RESOLVER 3 d, cierra IMPUGNACION_REMITIR (`:61,138,264-270`) | `ImpugnacionRechazoTest` + **`FlujoJuridicoTest`** | ✅ (Fase 8) |
| 6 | Fase de descargos (RN-09 Fase 1) | **exclusiva AUD_FINANCIERO** (`ExpedientePolicy:352-376`) — correcto: "Los Técnicos y Auditores Jurídicos omiten este paso" (`SRS:161`) | EN_EJECUCION (reloj pausado) | sub-reloj DESCARGOS 5 d | `DescargoFinancieroTest` | ✅ omisión correcta |
| 7 | **Informe Final Jurídico** (SRS `:396,:400` CON/SIN responsabilidad) | `POST /api/expedientes/{e}/actuados` con `ACT_INFORME_FINAL` (único en catálogo: `CatalogoActuadoSeeder:62`, **`estado_destino_id = null`**) | — (destino `null`) | — | **`FlujoJuridicoTest`** | ❌ **no emisible → AUD-0033**; solo 1 de 2 informes → **AUD-0008** |
| 8 | Control jerárquico Encargada (RN-07 `:153-155`) | exige `PENDIENTE_VISTO_BUENO_FINAL` (`CierreExpedienteService`); sin bandeja de informes pendientes | — | — | — | ❌ inalcanzable desde jurídico (**AUD-0033** + **AUD-0028**) |
| 9 | Salida del motor (RN-09 `:166`: "Auditor Jurídico → Encargada → Remisión a Asesoría Legal / Juez") | vía cierre: `ACT_VISTO_BUENO_FINAL` → `ACT_REPARTO_INSTITUCIONAL` | CONCLUIDO_REMITIDO | — | `CierreExpedienteTest` | ❌ **AUD-0021** (grafo) + **AUD-0033** (no llega informe) |
| 10 | NUREJ Hijo (RN-10 `:170-173`, desde informe final) | `NurejHijoService` | PENDIENTE_SORTEO | ninguno | `NurejHijoTest:193` | ❌ depende de 7/8 |

---

## §2 Validación negativa (Plan F8: error / no autorizado / estado inválido / concurrencia)

Todos los casos ejercitados en `tests/Feature/FlujoJuridicoTest.php` (9 tests,
semilla propia `fjSemilla()`, verificada el 2026-09-30).

| Dimensión | Caso | Comportamiento real | Test | Veredicto |
|---|---|---|---|---|
| No autorizado | Técnico emite `ACT_INFORME_FINAL` (pivote solo AUD_JURIDICO, `CatalogoActuadoSeeder:113-115`) | 403 (`ExpedientePolicy::crearActuado:17-33` → `perteneceAlRolConReglamento` `CatalogoActuado:56-72`) | `FlujoJuridicoTest` "bloquea con 403 al Técnico…" | ✅ |
| No autorizado | Jurídico emite informe en **expediente ajeno** (RF-03) | 403 (asignación propia exigida en `ExpedientePolicy:32`) | "…en un expediente ajeno (RF-03)" | ✅ |
| No autorizado | Jurídico sin asignación activa | 403 | "…sin asignación activa" | ✅ |
| Estado inválido | Remitir impugnación con estado ≠ RECHAZADO | 403 (policy `:149` filtra antes que el servicio; `ImpugnacionService:165-174` devolvería 422) | "…no está en RECHAZADO (estado inválido)" | ✅ |
| Doble envío | Segunda remisión de impugnación sobre el mismo expediente | 403 (ya no está RECHAZADO) + **1 sola fila en `impugnaciones`** | "doble remisión de impugnación" | ✅ |
| Error (validación) | Informe sin adjunto obligatorio | 422 `adjunto` (`StoreActuadoRequest:37-42`) | "sin el adjunto obligatorio" | ✅ |
| Error (validación) | Adjunto que no es PDF | 422 `adjunto` (`mimes:pdf`) | "adjunto … no es PDF" | ✅ |
| **Error (producto)** | Jurídico emite su informe con adjunto correcto y todo autorizado | **500 `QueryException`: `Column 'estado_nuevo_id' cannot be null`**; transacción revertida, 0 actuados nuevos, estado intacto | "no permite emitir el informe jurídico…" | ❌ **AUD-0033** |
| Concurrencia | Doble envío **secuencial** (mismo usuario) | bloqueado por estado (fila de arriba) | idem | ✅ parcial |
| Concurrencia | Requests **paralelos** reales sobre el mismo expediente | **NO VERIFICADO** (el runner de tests no ejecuta requests concurrentes). Mitigaciones en código: `DB::transaction(..., 3)` en `ImpugnacionService:56,105`, `ActuadoService:70,133`; `lockForUpdate` solo en sorteo (`ExpedienteService:114-120`) | — | ⚠️ NO VERIFICADA |

> Nota de transparencia: no se modificó código de producto ni se adaptó ninguna
> semilla para "hacer pasar" un test. La semilla de `FlujoJuridicoTest` **replica
> la configuración real del producto** (catálogo con `estado_destino_id = null`
> y pivote solo AUD_JURIDICO, igual que `CatalogoActuadoSeeder:62,113-115`) y el
> test **espera el fallo real** (500 + `QueryException`), dejando el
> comportamiento defectuoso a la vista en lugar de ocultarlo. Ver §4.

---

## §3 Checklist SRS específico del motor jurídico

| Requisito (SRS) | Estado |
|---|---|
| RN-02 `:137` evaluación 3-5 días (motor Jurídico/Financiero) | ❌ no parametrizable → **AUD-0019** |
| RN-04 `:143` MPA con plazos propios | ✅ (`PlanificacionTest:215`); plazo fijo 2 d → **AUD-0025** |
| RN-05 `:148` límite = fecha del MPA | ⚠️ fecha explícita soportada (`ActuadoService:197-199`) pero subtipo hardcodeado → **AUD-0024** |
| RN-07 `:153-155` Encargada revisa informes antes de salir | ❌ sin bandeja de revisión (**AUD-0028**) y el informe jurídico no puede emitirse (**AUD-0033**) |
| RN-08 `:156-158` 1 d remitir / 3 d resolver (Art. 25 Ac. 022/2018) | ✅ (`ImpugnacionRechazoTest:197-241` + `FlujoJuridicoTest`) |
| RN-09 `:161` jurídico omite descargos | ✅ correcto (`ExpedientePolicy:352-376`) |
| RN-09 `:166` salida "Encargada → Asesoría Legal / Juez" | ❌ **AUD-0021** + **AUD-0033** |
| RF-04 `:109` evaluación por profesional asignado | ✅ (`ExpedientePolicy:39-46`) |
| RF-03 `:108` aislamiento de bandejas | ✅ (403s de §2; `MATRIZ_SEGURIDAD.md`) |
| `SRS:96-100` informe jurídico (Art. 25/26) | ❌ 1 actuado genérico, no emisible → **AUD-0008 + AUD-0033** |
| `SRS:393-402` **dos** actuados exclusivos (CON/SIN Responsabilidad), ambos "Pasa a Visto Bueno" | ❌ catálogo solo tiene `ACT_INFORME_FINAL` y sin destino → **AUD-0008** |

---

## §4 Hallazgos nuevos de esta fase

### AUD-0033 — El informe jurídico (`ACT_INFORME_FINAL`) no se puede emitir
- **P1** (salida completa del motor jurídico bloqueada; RN-09 `:166` inalcanzable).
- **Evidencia (verificada con test el 2026-09-30):**
  - `database/migrations/2026_08_25_191156_create_actuados_table.php:21` →
    `estado_nuevo_id` es **NOT NULL** (FK `:31`); ninguna migración posterior lo
    altera (solo `create_actuados_triggers`).
  - `CatalogoActuadoSeeder:62` define `ACT_INFORME_FINAL` con
    `estado_destino_id = null` (pivote solo AUD_JURIDICO `:113-115`).
  - `ActuadoService@registerActuado:89,105` escribe `estado_nuevo_id` = destino
    del catálogo → `null`; la rama `if ($estadoNuevoId !== null)` (`:116-120`)
    solo protege la actualización del expediente, **no** el INSERT.
  - Resultado real: `POST /api/expedientes/{e}/actuados` → **500
    `QueryException` "Column 'estado_nuevo_id' cannot be null"**, la transacción
    (`:70,133`) revierte todo (0 actuados nuevos, estado EN_EJECUCION intacto).
    Test: `FlujoJuridicoTest` "no permite emitir el informe jurídico…".
- **Impacto:** el Auditor Jurídico **no puede emitir ningún informe final** (ni
  el único que existe en catálogo). La rama de cierre por arista válida para
  AC054 ya era inalcanzable (AUD-0021); ahora se confirma que ni siquiera se
  registra el actuado. Los informes financieros no se ven afectados: sus
  catálogos sí tienen destino `PENDIENTE_VISTO_BUENO_FINAL`
  (`CatalogoActuadoSeeder:82-83`, test `DescargoFinancieroTest:225` → 201).
- **Cruce:** amplía AUD-0008 (informes faltantes) y AUD-0021 (grafo): no es
  solo "faltan 2 de 2 informes jurídicos", sino que **el existente falla al
  registrarse**.
- **Acción propuesta:** decisión de diseño del usuario — (a) hacer nullable
  `estado_nuevo_id` (migración nueva), o (b) definir destino de estado para el
  informe jurídico (p. ej. `PENDIENTE_VISTO_BUENO_FINAL`), o (c) que
  `registerActuado` no escriba la columna cuando el destino es `null`.
  **Requiere aprobación explícita** (migración / regla de negocio).

### AUD-0035 — RN-08 y el informe jurídico no tienen interfaz: 0 vistas
- **P1** (funcionalidad crítica: una fase normativa completa solo alcanzable
  por llamada HTTP directa).
- **Evidencia:** grep sobre `resources/views/**/*.blade.php` de
  `impugnaci|informe_final|ACT_INFORME_FINAL|resolver` → **0 coincidencias**.
  Única acción de la vista de detalle: modal "Emitir Actuado" →
  `POST /api/expedientes/{id}/actuados`
  (`resources/views/expedientes/detalle.blade.php:133-135,207-244,340`).
- **Impacto:** no hay botón para remitir/resolver impugnaciones
  (`POST .../impugnacion/remitir|resolver`, `routes/api.php:62-63`) ni para
  emitir el informe jurídico. Emitir `ACT_REMITIR_IMPUGNACION` por el actuado
  genérico **no** crearía la fila en `impugnaciones`
  (`ImpugnacionService:83-89`), por lo que la resolución de la Encargada
  fallaría con `firstOrFail` (`:179-185`) → el flujo RN-08 completo no es
  completable desde la UI. Coincide con AUD-0008 (grep de "informe" en vistas →
  0) pero extiende el hallazgo a toda la fase de impugnación.
- **Acción propuesta:** UI dedicada (botones por rol+estado) o enlazar el
  flujo desde la vista de detalle. **Fase 14 (UX) + decisión del usuario.**

### AUD-0034 — Catálogo de actuados sin filtro de expediente en la UI (+ `expediente_id` sin validar)
- **P2** (UX/consistencia de autorización).
- **Evidencia:** `resources/views/expedientes/detalle.blade.php:297` llama
  `GET /api/catalogo/actuados` **sin `expediente_id`**, por lo que no se aplica
  el filtro de reglamento (`CatalogoActuadoController:38-44`); el
  `FormRequest` además **no valida `expediente_id`** (solo `estado_origen_id`,
  `IndexCatalogoActuadosRequest:17-22`) pese a leerse en el controlador. El
  docblock del controlador promete "evitar ofrecer acciones que provocarían un
  403" (`:17-19`), pero sin `expediente_id` se listan actuados de otros
  reglamentos del mismo rol (p. ej. MPA de AC054 y AC055) → selección → 403 en
  `perteneceAlRolConReglamento`.
- **Impacto:** el usuario ve acciones que fallan con 403 al emitir.
- **Acción propuesta:** pasar `expediente_id` desde la vista y validarlo en
  `IndexCatalogoActuadosRequest`. **Requiere aprobación** (cambia
  comportamiento de UI/API).

### O-7 (observación, sin ficha)
- `ImpugnacionService:87` usa `fecha_limite_resolucion = $plazoResolucion?->fecha_limite ?? now()`:
  si el parámetro `IMPUGNACION_RESOLVER` no existe (BD dev 8/17, AUD-0023), la
  impugnación queda con límite "ahora" aunque el flujo siga vivo.
- **Resuelta (cierre F12, 2026-09-30): sub-caso de AUD-0023, sin ficha P2
  independiente.** Verificado: BD dev con 0 filas `IMPUGNACION_RESOLVER` →
  fallback `now()` activo en dev. NO modificar; candidato de corrección
  asociado a la sincronización de parámetros de AUD-0023.

---

## §5 Cobertura de tests del flujo

- **Nuevo en Fase 8:** `tests/Feature/FlujoJuridicoTest.php` — 9 tests (happy
  path RN-08 del jurídico, informe no emisible, 403 rol equivocado/ajeno/sin
  asignación, 422 sin adjunto y no-PDF, doble envío, estado inválido).
- Existentes que también cubren el rol: `ImpugnacionRechazoTest` (RN-08 con
  Técnico), `PlanificacionTest:215` (MPA AC054 jurídico),
  `EvaluacionAdmisibilidadTest`, `CatalogoActuadoControllerTest`,
  `SeguridadIdorTest:149-156`, `SecurityCompartimentosTest`,
  `DerivacionTransparenciaTest` (incluye jurídico), `WebWorkstationRoutesTest`,
  `DescargoFinancieroTest` (contraste: informe financiero sí se emite).
- **Sin tests:** emisión de un hipotético 2º informe jurídico (AUD-0008),
  concurrencia paralela real (§2), UI (AUD-0035).
- **Cobertura de rutas `administrador/*` y `api/admin/*`:** 0 tests (pendiente
  Fase 16, registrado en Fase 1).

## §6 Decisiones del usuario (Fase 8) — resueltas 2026-09-30

1. **AUD-0033**: **decidido: NO corregir todavía.** No hacer `estado_nuevo_id`
   nullable, ni asignar destino, ni omitir la columna, hasta analizar el grafo
   de estados (AUD-0021) y fijar la semántica normativa de `ACT_INFORME_FINAL`.
   El P1 queda confirmado y reproducible.
2. **AUD-0035**: **decidido: no crear UI todavía.** Documentar que es
   independiente de AUD-0033 y verificar el alcance contra el flujo normativo
   completo (RN-08 + RN-09) antes de proponer interfaz.
3. **AUD-0034**: **decidido: no tocar FormRequest ni vista todavía.** En la
   Fase 9 se determina primero el impacto funcional/seguridad real de la
   ausencia de `expediente_id`.
4. (Acumuladas → **RESUELTAS 2026-09-30** en BACKLOG/PROGRESO: AUD-0001 →
   deuda conocida aceptada; 0019/0020/0021/0023/0024/0025/0027/0028/0029
   decididos; 0030/0031/0032 **incorporados a la lista de decisiones**
   pendientes (cierre F12, sin aplicar); O-4 resuelta sin elevar; **O-6
   cerrada** (comportamiento aceptado provisionalmente); **O-7 cerrada como
   sub-caso de AUD-0023** (parámetro `IMPUGNACION_RESOLVER` ausente en BD
   dev → fallback `now()` documentado; sin modificar). **AUD-0002
   resuelto:** fix del test aprobado y aplicado → ficha CERRADO.)
