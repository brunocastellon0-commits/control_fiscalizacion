# AUD-0039 — Análisis de diseño: `via`/`reglamento` del NUREJ Hijo

Estado: **DECISIÓN DEL USUARIO ADOPTADA (2026-09-30): Opción B** — sin tocar
código; pendiente de implementación condicionada a la matriz de derivaciones
permitidas (§7). Obligación cumplida: análisis de diseño previo en la
siguiente fase disponible (Fase 12), con propuesta respaldada por evidencia.

## §1 Problema

SRS RN-10 (`:170-174`):

- `:171` — tras el Informe Final *recomendando la intervención de **otra
  especialidad** (ej. del Técnico al Auditor Jurídico)*, la Encargada crea
  el NUREJ Hijo.
- `:173` — el hijo aplica ***su** reglamento y requisitos específicos
  (**Acuerdos 54 o 55**)*.
- `:174` — el hijo continúa en ***otra bandeja*** en paralelo.

Realidad implementada:

- `NurejHijoService@crearHijo:46-47` — el hijo **hereda** `via` y
  `reglamento_id` del padre; no recibe ningún otro valor.
- `DerivarNurejHijoRequest:23-28` — el único input es `motivo`
  (string 10-5000). No existe `via`/`reglamento` destino.
- Sin UI: grep de `nurej-hijo` en `resources/views/**/*.blade.php` = **0**
  (flujo solo-API, coherente con AUD-0035).

Efecto: el hijo nace en `PENDIENTE_SORTEO` (`NurejHijoService:41,48`) y el
sorteo lo reparte dentro de la **misma** vía (`SorteoAlgorithmService:
MAPA_VIA_ROL:30-34`, candidatos por rol de la vía `:83`), con el **mismo**
reglamento que el padre → nunca llega a la otra especialidad. Contradice
`:171` (otra especialidad), `:173` (Ac. 54/55 como reglamento del hijo) y
`:174` (otra bandeja).

## §2 Cómo se determinan hoy `via`/`reglamento_id`

| Origen | Mecanismo | Evidencia |
|---|---|---|
| Apertura (padre) | **Input explícito del usuario**: `via` ∈ {TECNICO, JURIDICO, FINANCIERO} + `reglamento_id` (exists) | `StoreExpedienteRequest:22-23` → `ExpedienteService@aperturaCausa:51-52` |
| NUREJ Hijo | **Copia del padre**, sin input | `NurejHijoService:46-47` |
| Sorteo | `via` del expediente → rol (puede cambiar de responsable, nunca de vía) | `SorteoAlgorithmService:30-34,73,83` |

## §3 Dependencias del cambio

1. **Catálogo de actuados por (rol, reglamento):** la disponibilidad de
   actuados se resuelve por el pivote `catalogo_actuado_roles(rol_id,
   reglamento_id)` (`CatalogoActuado:44-65`; seeder `:163-173` con las tres
   combinaciones *Técnico-AC022 / Auditor Jurídico-AC054 / Auditor
   Financiero-AC055* — `CatalogoActuadoSeeder:18-20`). Si el hijo cambia de
   vía/reglamento, cambia el (rol, reglamento) del operador destino y con él
   los actuados habilitados (evaluación Admitir/Observar/Rechazar `:173`).
   **Con la herencia actual, un hijo en vía X con reglamento del padre puede
   caer en combinaciones que el pivote no contempla.**
2. **Reglamentos existentes:** `ReglamentoSeeder:17-35` — `AC_022_2018`,
   `AC_054_2018`, `AC_055_2018` → los Acuerdos 54/55 de `:173` existen en BD.
3. **Plazos:** `ParametroPlazo` se define por `reglamento_id` + tipo (ver
   `ParametroPlazoSeeder`) → el reglamento destino determina los plazos que
   corren en el hijo.
4. **Bandejas:** la visibilidad es por **asignación activa** del operador;
   el destino real lo decide el sorteo según la `via` → cambiar `via`
   destino alcanza para que el hijo entre en la bandeja de la otra
   especialidad (sin tocar `SorteoAlgorithmService`).
5. **Autorización:** `DerivarNurejHijoRequest@authorize:13-18` →
   `ExpedientePolicy@derivarNurejHijo` (solo Encargada activa). **No cambia**
   con el fix: quien deriva sigue siendo la Encargada.
6. **Estados:** el hijo nace `PENDIENTE_SORTEO` (`:41,48`) en ambas
   opciones; el grafo de estados no se toca.
7. **Trazabilidad (RF-R09 / RN-10 `:171`):** el actuado
   `ACT_CREACION_NUREJ_HIJO` sobre el padre ya guarda
   `expediente_hijo_id`/`nurej_hijo_code` (`NurejHijoService:63-66`) → es
   el lugar natural para registrar además la `via`/`reglamento` elegidos.
8. **Datos existentes:** Fase 11 verificó **0 hijos** en BD dev → no se
   requiere migración de datos históricos.

## §4 ¿Transformación automática o selección explícita?

- **Opción A — automática desde el Informe Final.** Requeriría que
  `ACT_INFORME_FINAL`/informes AC054-055 lleven `via_destino` en metadatos.
  **Hoy no existe** ningún campo de recomendación (el informe solo tiene
  `requiere_adjunto`, `CatalogoActuadoSeeder`), e `ACT_INFORME_FINAL` ni
  siquiera es emisible (**AUD-0033**, P1: `estado_nuevo_id` NOT NULL vs
  destino `null` → 500; ver también AUD-0021). Es decir: bloqueada por
  hallazgos P1 previos y por la ausencia de metadatos estructurados.
- **Opción B — selección explícita de la Encargada al derivar.** Añadir
  `via_destino` (obligatoria) y opcionalmente `reglamento_id` al request,
  replicando el **precedente exacto de la apertura**
  (`StoreExpedienteRequest:22-23`). No depende de AUD-0033/0021. Cambios:
  `DerivarNurejHijoRequest::rules()`, `NurejHijoService@crearHijo` (usar
  destino en `:46-47` y registrarlo en los metadatos `:63-66`), tests.
  Sin UI hoy (solo API); cuando exista la pantalla de derivación, el campo
  se expone ahí.
- **Opción C — híbrida.** Recomendación estructurada en el informe (a futuro,
  tras AUD-0033/0021) **+** confirmación obligatoria de la Encargada. Es el
  destino natural del SRS (`:171` operador recomienda, `:171` Encargada
  genera), pero no se puede implementar aún.

## §5 Propuesta de diseño (para decisión del usuario, no implementada)

1. **Alcance mínimo (Opción B):**
   - `DerivarNurejHijoRequest`: `via_destino => required|in:TECNICO,JURIDICO,
     FINANCIERO` (mismo patrón que `StoreExpedienteRequest:22`).
   - `NurejHijoService@crearHijo`: `via` = `via_destino`; **reglamento
     destino**: `reglamento_id` opcional (`exists:reglamentos,id`) con
     default = mapeo vía→reglamento propuesto por el propio seeder
     (`CatalogoActuadoSeeder:18-20`): TECNICO→AC022, JURIDICO→AC054,
     FINANCIERO→AC055; si se prefiere, default = heredar del padre.
   - Metadatos del actuado en el padre: añadir `via_destino` y
     `reglamento_destino_id` junto a `:63-66` (evidencia RN-10 `:171`).
   - Registrar lo elegido también en `resumen_hechos`? **No** — solo
     metadatos; el SRS no lo pide.
2. **Decisión de diseño — RESUELTA por el usuario (2026-09-30), ver §7:**
   - `via_destino` = input explícito controlado por servidor (no herencia).
   - Mapeo vía destino → reglamento destino **obligatorio** (Técnico→AJ:
     `JURIDICO → AC054`; Técnico→Financiero: `FINANCIERO → AC055`).
   - Combinaciones permitidas → solo desde una **matriz normativa explícita**
     (pendiente de construir antes de implementar).
3. **Tests al implementar (borrador):** hijo creado con
   `via_destino=JURIDICO` → `expediente.via = JURIDICO`, sorteo asigna a
   `AUD_JURIDICO`; el pivote (rol, reglamento) habilita los actuados de
   evaluación del hijo; reglamento destino no heredado; trazabilidad en el
   padre; validación 422 sin `via_destino` o con valor inválido.
4. **Implicaciones:** ninguna migración de esquema (ya existen `via` y
   `reglamento_id`); sin cambios de autorización; sin cambios en sorteo,
   estados ni plazos (los plazos del hijo se calculan con su reglamento,
   comportamiento ya correcto).

## §6 Veredicto

- La herencia actual (`NurejHijoService:46-47`) **contradice RN-10
  `:171,:173,:174`** — P2 confirmado, no aceptable tal cual (criterio del
  usuario).
- La solución correcta a corto plazo es la **Opción B** (selección explícita
  con validación, precedente de apertura); la Opción A/C queda anclada a
  AUD-0033/0021 (P1, grafo de estados).

## §7 Decisión del usuario (2026-09-30) — Opción B adoptada

- **Confirmado P2; diseño adoptado: Opción B.** El NUREJ Hijo debe tener
  **especialidad explícita de destino**, no heredar automáticamente la del
  padre; el SRS exige independencia procesal y aplicación de su propio
  reglamento.
- **Decisiones concretas:**
  1. `via_destino` será un **input explícito controlado por servidor**
     (validado en el FormRequest, patrón `StoreExpedienteRequest:22-23`).
  2. **No** debe heredarse automáticamente del padre.
  3. Debe existir un **mapeo válido entre vía destino y reglamento destino**.
  4. Caso normativo Técnico → Auditoría Jurídica: **`JURIDICO → AC054`**.
  5. Técnico → Financiero: **`FINANCIERO → AC055`**.
  6. **No** asumir que la vía destino puede ser igual a la del padre cuando
     el actuado que origina el hijo exige una especialidad diferente.
  7. Cualquier combinación permitida deberá salir de una **matriz normativa
     explícita**.
- **Condición de implementación:** NO aplicar hasta terminar la **matriz de
  derivaciones permitidas** (`actuado/origen → especialidad destino →
  reglamento destino`); después: cambio mínimo (`DerivarNurejHijoRequest`,
  `NurejHijoService@crearHijo`, metadatos del actuado en el padre), tests
  específicos, suite completa y documentación de regresión.
- **Regla general del usuario:** esta decisión NO autoriza la ejecución
  masiva de fixes; AUD-0020/0021/0024/0025/0033/0039 se tratan como **conjunto
  de diseño** (estados, naturaleza, especialidad, reglamento, plazos).
- **Sin cambios de código en esta fase.**
