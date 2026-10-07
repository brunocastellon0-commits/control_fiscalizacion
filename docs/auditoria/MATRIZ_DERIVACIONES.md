# Matriz de derivaciones permitidas (NUREJ Padre → Hijo)

**Estado:** **APROBADA (2026-10-06) e IMPLEMENTADA (cierre técnico de
B1.5).** Cumple el condicionante de AUD-0039 §7 (*"NO aplicar hasta terminar
la matriz de derivaciones permitidas"*). **Esta matriz es la autoridad:**
**no existe una regla global de "destino diferente al padre"**; toda
combinación origen/actuado + vía destino que no esté explícitamente
permitida en §2 — incluidas las marcadas PROHIBIDAS o NO DETERMINADA —
responde **422** en el servidor (D-P2/D-P3, ver §5).

**Alcance:** únicamente la derivación interna Padre → Hijo
(`POST /api/expedientes/{expediente}/nurej-hijo` →
`ExpedienteController@derivarNurejHijo` → `NurejHijoService@crearHijo`).
**No cubre** (mecanismos distintos, no confundir):

- Derivación por Incompetencia / vía Penal → Transparencia (SRS
  `:417-421`; `TransparenciaService`).
- Reparto Institucional / salidas externas (SRS `:426-436`; destino exacto
  Juzgado Disciplinario, Sumariante, Asesoría Legal).
- Impugnación (RN-08).

---

## §1 Reglas generales (ya decididas)

| # | Regla | Fuente |
| --- | --- | --- |
| R1 | `via_destino ∈ {TECNICO, JURIDICO, FINANCIERO}` — vocabulario de `expedientes.via`, no códigos de rol | Decisión del usuario (2026-10-06); valores reales en `StoreExpedienteRequest:22`; `SorteoAlgorithmService:30-34` (`MAPA_VIA_ROL` soporta solo esas 3 vías) y `:71-76` (`rolParaVia` rechaza cualquier otra) |
| R2 | `reglamento_destino` se resuelve **server-side** con mapeo fijo: `TECNICO → AC_022_2018`, `JURIDICO → AC_054_2018`, `FINANCIERO → AC_055_2018` | Decisión del usuario (2026-10-06) + AUD-0039_DISENO §7.3-§7.5 (JURIDICO→AC054, FINANCIERO→AC055); códigos reales verificados en `ReglamentoSeeder:17,25,33`; consistente con el pivote `perfilesEvaluacion` del seeder (`CatalogoActuadoSeeder:121,124-125` y helper `:227-229`) |
| R3 | La especialidad destino debe ser **otra** respecto a la del padre (`via_destino ≠ expediente.via` del padre) | RN-10 SRS `:171` (*"recomendando la intervención de otra especialidad"*) y `:174` (*"otra bandeja"*); decisión del usuario (2026-10-06) |
| R4 | Esta matriz **prevalece** sobre las reglas generales cuando establezca restricciones específicas | Decisión del usuario (2026-10-06) |

> **D-P3 (decisión definitiva):** R3 se conserva aquí como **fundamento
> normativo de la fila M5**, pero **no se implementa como regla global
> independiente**. La autoridad es esta matriz: M5 prohíbe `TECNICO` destino
> porque no está en la whitelist, no por comparación con la vía del padre.

---

## §2 Matriz principal: actuado origen → especialidad destino → reglamento destino

| # | Actuado origen (vía del padre) | Vía destino | Reglamento destino | Fundamento / fuente | Restricciones y condiciones |
| --- | --- | --- | --- | --- | --- |
| M1 | `ACT_INFORME_TECNICO_CON_RESPONSABILIDAD_RECOMENDACION` (TECNICO) | `JURIDICO` | `AC_054_2018` | SRS `:171` (ejemplo canónico *"del Técnico al Auditor Jurídico"*), `:385-387` (gatilla el NUREJ Hijo al recibir VB); `CatalogoActuadoSeeder:95` | R3 ✓ (destino ≠ TECNICO). El hijo aplica **su** reglamento y requisitos: Ac. 54 (SRS `:173`) |
| M2 | `ACT_INFORME_TECNICO_CON_RESPONSABILIDAD_RECOMENDACION` (TECNICO) | `FINANCIERO` | `AC_055_2018` | SRS `:173` (*"Acuerdos 54 **o** 55"*), `:385-387`; `CatalogoActuadoSeeder:95` | R3 ✓. Descargos AC055 aplican dentro del hijo (SRS `:404-405`) |
| M3 | `ACT_INFORME_TECNICO_SIN_RESPONSABILIDAD_RECOMENDACION` (TECNICO) | `JURIDICO` | `AC_054_2018` | SRS `:389-391` (gatilla el NUREJ Hijo al recibir VB); `CatalogoActuadoSeeder:96` | R3 ✓ |
| M4 | `ACT_INFORME_TECNICO_SIN_RESPONSABILIDAD_RECOMENDACION` (TECNICO) | `FINANCIERO` | `AC_055_2018` | SRS `:173`, `:389-391`; `CatalogoActuadoSeeder:96` | R3 ✓ |
| M5 | Mismos orígenes TECNICO (M1-M4) | `TECNICO` | — | — | **PROHIBIDO** por R3: misma especialidad que el padre (SRS `:171`) |
| M6 | `ACT_INFORME_AUDITORIA_JURIDICA_CON_RESPONSABILIDAD` / `..._SIN_RESPONSABILIDAD` (JURIDICO) | **NO DETERMINADA** | **NO DETERMINADA** | SRS `:393-402`: los informes AJ solo pasan a Visto Bueno para **remisión externa** (`:398`) o archivo definitivo (`:402`); ningún texto menciona gatillo de NUREJ Hijo | Sin evidencia de derivación interna AJ → otra especialidad. **No inventar combinación** |
| M7 | `ACT_INFORME_AUDITORIA_FINANCIERA_CON_RESPONSABILIDAD` / `..._SIN_RESPONSABILIDAD` (FINANCIERO) | **NO DETERMINADA** | **NO DETERMINADA** | SRS `:404-413`: informe AF CON Responsabilidad pasa a VB para *"remisión a Asesoría Jurídica"* (`:409`), AF SIN Responsabilidad a archivo (`:413`); SRS `:430-432` clasifica las remisiones a Asesoría Legal/Juzgado/Sumariante como destinos del **Reparto Institucional** (salida externa), no como hijo | La remisión `:409` es ambigua; **no hay evidencia** de que sea una derivación Padre→Hijo → NO DETERMINADA |
| M8 | Cualquier expediente **sin** `ACT_INFORME_TECNICO_*_RECOMENDACION` previo emitido | **NO DETERMINADA** | **NO DETERMINADA** | RN-10 SRS `:171` condiciona la creación del hijo a que un operador haya emitido *"un Actuado de Informe Final recomendando la intervención de otra especialidad"*; SRS `:311-313` (Sorteo Derivado: *"Si es derivado desde un informe previo…"*) refuerza la condición de informe previo | **Resuelto en B1.5 (D-P1):** `NurejHijoService@verificarDerivable()` lo verifica y responde **422** si falta el informe habilitante |

**Estado de implementación (B1.5, 2026-10-06):** las filas **M1-M4 están
implementadas** (whitelist `DESTINOS_PERMITIDOS = [JURIDICO, FINANCIERO]` +
`MAPA_VIA_DESTINO_REGLAMENTO` server-side en `NurejHijoService`); **M5, M6,
M7 y M8 responden 422** en el servidor, igual que toda combinación no
contemplada. Cobertura: `NurejHijoEspecialidadTest` (7 tests, uno por fila).

---

## §3 Condiciones de la derivación (restricciones del endpoint)

| # | Condición | Fuente y estado actual |
| --- | --- | --- |
| C1 | Emisor: solo la Encargada **activa** (`activo` y `rol = ENCARGADA`) | `ExpedientePolicy:263-267`; `DerivarNurejHijoRequest:13-18`; `MATRIZ_SEGURIDAD.md` fila 26 |
| C2 | `motivo` obligatorio, 10-5000 caracteres | `DerivarNurejHijoRequest:26` |
| C3 | No sub-derivar: un expediente ya derivado no puede originar hijo → 422 | RN-10; `NurejGeneratorService:43-45`; fix AUD-0038 (handler 422 en `bootstrap/app.php`) |
| C4 | Habilitación: debe existir informe Técnico con recomendación previo en el padre (M1-M4) | SRS `:171`, `:311-313` — **implementado en B1.5 (D-P1):** `NurejHijoService@verificarDerivable()` exige el actuado habilitante (vía origen TECNICO) o responde 422 |
| C5 | El hijo nace en `PENDIENTE_SORTEO` y el sorteo posterior lo asigna con `MAPA_VIA_ROL[vía destino]` → bandeja de la especialidad destino; arranca el reloj de Evaluación con el reglamento destino | `NurejHijoService:41,48`; `SorteoAlgorithmService:30-34,:83-89`; SRS `:174` (*otra bandeja*), `:313` (*arranca el reloj de Evaluación*) |
| C6 | Independencia: el hijo no hereda actuados, plazos ni asignaciones del padre (línea de tiempo en cero) | SRS `:173`; verificado en `FlujoNurejTest:114,158` y `NurejHijoTest:171` |
| C7 | El hijo usa el reglamento destino (R2), no el heredado: requisitos y plazos propios | AUD-0039_DISENO §7; requisitos por reglamento en `CatalogoRequisitoSeeder:28-36`; plazos en `ParametroPlazoSeeder:21-31` |
| C8 | Trazabilidad: los metadatos de `ACT_CREACION_NUREJ_HIJO` en el padre deben incluir `via_destino` y `reglamento_destino_id` | RN-10 `:171`; AUD-0039_DISENO §5.1 — **implementado en B1.5 con el mecanismo existente:** dos claves añadidas al array `metadatos` de `registerActuado()` (se fusionan en la columna `contenido` del actuado); **sin columnas ni estructuras nuevas** |
| C9 | Combinación **PROHIBIDA** (M5) o **NO DETERMINADA** (M6-M8) → rechazo del servidor con 422 | **Aprobada e implementada (D-P2):** whitelist estricta en `NurejHijoService@verificarDerivable()` → 422 |

---

## §4 Observaciones NO DETERMINADAS (fuera del alcance de esta matriz)

1. **Plazos de ejecución del hijo en AC054/AC055:** `ParametroPlazoSeeder:27-31`
   parametriza EVALUACION y PLANIFICACION (más DESCARGOS en AC055), pero **no**
   EJECUCION / EJECUCION_AMPLIADA (solo AC022 los tiene, `:24-26`). Efecto
   sobre un hijo en vía JURIDICO/FINANCIERO que llegue a ejecución:
   **NO DETERMINADO** aquí (motor de plazos, fuera de alcance de B1.5).
2. **¿Quién "gatilla" el hijo?** SRS `:387,:391` dice *"gatilla la creación
   del NUREJ Hijo"* (evento del VB) y `:313` la asocia al Sorteo Derivado;
   RN-10 `:171` dice *"la Encargada generará"*. Implementación actual: manual
   por Encargada (`FLUJO_NUREJ.md` §1). No se determina ningún cambio aquí.
3. **SORTEO_DERIVADO (SRS `:311`):** `FLUJO_TECNICO.md:44` lo reporta como
   actuado faltante (AUD-0009) — pendiente en backlog, fuera de esta matriz.

---

## §5 Decisiones del usuario (2026-10-06) — RESUELTAS y aplicadas en B1.5

| # | Decisión | Resolución |
| --- | --- | --- |
| D-P1 | Validar en código el informe técnico habilitante de M1-M4 (no cualquier informe) | **SÍ — implementada:** `NurejHijoService@verificarDerivable()` exige un actuado `ACT_INFORME_TECNICO_CON_RESPONSABILIDAD_RECOMENDACION` o `ACT_INFORME_TECNICO_SIN_RESPONSABILIDAD_RECOMENDACION` previo en el padre (y `padre.via = TECNICO`, origen de las filas M1-M4); si no existe → **422**, 0 hijos creados |
| D-P2 | Whitelist estricta: toda combinación no contemplada explícitamente por la matriz → 422 (incluidas las PROHIBIDAS) | **SÍ — implementada:** `DESTINOS_PERMITIDOS = [JURIDICO, FINANCIERO]`; M5 (`TECNICO`), M6/M7 (orígenes AJ/AF), M8 (sin habilitante) y cualquier valor fuera del vocabulario → **422**. Sin regla genérica "si destino ≠ padre entonces permitir" |
| D-P3 | ¿Implementar R3 (`via_destino ≠ via del padre`) como regla global independiente? | **NO:** no se implementa. **La matriz es la autoridad**; M5 queda prohibida por no estar en la whitelist, no por comparación con el padre |
| C8 | Dónde almacenar `via_destino` y `reglamento_destino_id` (sin asumir estructura nueva) | **Mecanismo existente:** claves añadidas al array `metadatos` de `registerActuado()`, que se fusionan en la columna `contenido` del actuado `ACT_CREACION_NUREJ_HIJO` (patrón ya usado por `PlanificacionService`); **sin columnas, tablas ni campos nuevos en `expedientes`** |

---

## §6 Fuentes verificadas

| Fuente | Qué aporta |
| --- | --- |
| `docs/auditoria/SRS_EXTRAIDO.txt:170-174` | RN-10 completa: trazabilidad no herencia (`:171`), independencia de actuados (`:173`), salidas asíncronas (`:174`) |
| `docs/auditoria/SRS_EXTRAIDO.txt:385-391` | Informes Técnico con Recomendación → gatillan el NUREJ Hijo al recibir VB |
| `docs/auditoria/SRS_EXTRAIDO.txt:393-413` | Informes AJ (remisión externa / archivo) y AF (remisión a Asesoría Jurídica / archivo) — sin gatillo de hijo |
| `docs/auditoria/SRS_EXTRAIDO.txt:415-436` | Derivación por Incompetencia y Reparto Institucional (mecanismos distintos, fuera de alcance) |
| `docs/auditoria/SRS_EXTRAIDO.txt:307-313` | Registro/Sorteo Inicial/Sorteo Derivado; *"si es derivado desde un informe previo"* |
| `docs/auditoria/AUD-0039_DISENO.md §5-§7` | Decisiones adoptadas 2026-09-30: Opción B, `via_destino` explícito, mapeo obligatorio vía→reglamento, matriz como condición |
| `database/seeders/ReglamentoSeeder:17,25,33` | Códigos reales de reglamentos: `AC_022_2018`, `AC_054_2018`, `AC_055_2018` |
| `database/seeders/CatalogoActuadoSeeder:18-20,95-96,121,124-125,227-229` | Correspondencia vía-rol-reglamento; informes técnicos con recomendación; pivote perfiles de evaluación |
| `database/seeders/CatalogoRequisitoSeeder:28-36` | Requisitos de admisibilidad por reglamento (hijo usa los del destino) |
| `database/seeders/ParametroPlazoSeeder:16-31` | Parámetros de plazo por reglamento (ver §4.1) |
| `app/Services/SorteoAlgorithmService.php:30-34,71-76,83-89` | Vocabulario de vías soportado y sorteo por `via` del expediente |
| `app/Services/NurejHijoService.php:32-73` | Comportamiento actual: herencia de `via`/`reglamento_id` (`:46-47`), nacimiento en PENDIENTE_SORTEO (`:41,48`), metadatos actuales (`:63-66`) |
| `app/Services/NurejGeneratorService.php:35-51` | NUREJ hijo correlativo y bloqueo de sub-derivar (`:43-45`) |
| `app/Http/Requests/DerivarNurejHijoRequest.php:13-28` | authorize (policy) y validación actual solo de `motivo` |
| `app/Policies/ExpedientePolicy.php:263-267` | Solo Encargada activa |
| `docs/auditoria/FLUJO_NUREJ.md §0-§1` | Superficie del flujo y estado de auditoría |
| `docs/auditoria/MATRIZ_ACTUADOS.md:26` (A17) / `MAQUINA_ESTADOS.md:60` | Ficha de `ACT_CREACION_NUREJ_HIJO` |
| Decisiones del usuario (2026-10-06, sesión B1.5) | R1 (vocabulario), R2 (mapeo server-side), R3 (otra especialidad), R4 (prevalencia); **D-P1 SÍ, D-P2 SÍ, D-P3 NO → resueltas y aplicadas en B1.5 (§5)** |
