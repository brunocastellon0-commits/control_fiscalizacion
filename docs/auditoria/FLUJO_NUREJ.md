# Fase 11 — NUREJ Padre/Hijo (independencia de actuados y filtrado de línea de tiempo)

Estado: **VALIDADA Y CERRADA (2026-09-30)** · Reproducción con tests contra
`control_fiscalizacion_test` (`RefreshDatabase`); esquema verificado en
migraciones reales; BD dev solo lectura. Único cambio de código de la fase:
fix autorizado AUD-0038 en `bootstrap/app.php` (manejo HTTP de
`CannotDeriveNurejException`) + test feature.

## §0 Alcance y método

Definición del Plan §3: *"F11 NUREJ Padre/Hijo: independencia de actuados y
filtrado de línea de tiempo"*.

- **Marco normativo:** SRS `:106` (RF-01: NUREJ Padre correlativo e
  irrepetible + derivados `YYYY-NNNNN-X`), `:170-174` (RN-10: trazabilidad
  no herencia `:171`, **independencia de actuados** `:173`, salidas
  asíncronas `:174`), `:127` (RF-R09 reporte trazabilidad → alcance F12).
- **Superficie auditada:**
  - `POST /api/expedientes/{expediente}/nurej-hijo` — `routes/api.php:75`
    (grupo `auth:sanctum, throttle:api` `:29`) →
    `ExpedienteController@derivarNurejHijo:149-163` →
    `NurejHijoService@crearHijo:32-73`.
  - Autorización: `DerivarNurejHijoRequest@authorize:13-18` →
    `ExpedientePolicy@derivarNurejHijo:266-270` (solo Encargada activa);
    validación `motivo` 10-5000 (`:23-28`).
  - Generación: `NurejGeneratorService@generarHijo:35-51` (lock `FOR UPDATE`
    sobre el padre `:38-41`, bloqueo RN-10 de sub-derivar `:43-45`,
    correlativo por conteo `:47`).
  - Línea de tiempo: `ExpedienteController@show:83-88` +
    `relacionesDetalle():171-187` (`actuados.*`); relación
    `Expediente@actuados:68-71` = `hasMany(Actuado, expediente_id)` (filtrado
    por FK, orden por `fecha_hora`).
  - Especialidad: `SorteoAlgorithmService:30-33` (`MAPA_VIA_ROL`),
    `:83-89` (candidatos por `via`), `:101-105` (pesos por `reglamento_id`).
- **Esquema verificado (`create_expedientes_table:16,26,31`):**
  `nurej_code` **UNIQUE** (RF-01), FK `nurej_padre_id` → `expedientes`,
  índice `idx_expedientes_padre`. Sin sub-hijos posibles a nivel de datos
  (solo la app puede poblar `nurej_padre_id`).

## §1 Recorrido del flujo (happy path)

1. Encargada activa → `POST /expedientes/{padre}/nurej-hijo` con `motivo`
   (≥10 car.) — `DerivarNurejHijoRequest:26`.
2. `NurejHijoService@crearHijo` (transacción, 3 reintentos `:38,:72`):
   - `generarHijo` bloquea el fila del padre (`lockForUpdate`) →
     `YYYY-NNNNN-X` (`NurejGeneratorService:47-49`); lanza
     `CannotDeriveNurejException` si el origen ya es derivado (`:43-45`).
   - Hijo: `nurej_padre_id = padre`, **hereda `via` y `reglamento_id` del
     padre** (`NurejHijoService:46-47`), nace en `PENDIENTE_SORTEO`
     (`:41,:48`), `creado_por = encargada` (`:51`).
   - Copia partes vigentes como versiones nuevas independientes (`:54`,
     `:80-97`).
   - Emite `ACT_CREACION_NUREJ_HIJO` **sobre el padre** con metadatos
     `expediente_hijo_id`/`nurej_hijo_code` (`:56-69`); el padre **no**
     cambia de estado (`estadoNuevoIdExplicito = estado actual :68`).
3. Hijo en `PENDIENTE_SORTEO` → sorteo posterior (alcance F7) hacia
   candidatos de **su `via`** (`SorteoAlgorithmService:83-89`).
4. Línea de tiempo: cada `GET /expedientes/{id}` lista solo los actuados de
   ese `expediente_id` (`Expediente@actuados:70`) — el filtrado es por FK,
   no hay endpoint global de actuados (grep rutas: solo `POST` store,
   `routes/api.php:55`).

## §2 Verificaciones contra SQL/esquema (BD dev y test)

| Verificación | Resultado | Evidencia |
| --- | --- | --- |
| Hijos existentes en BD dev | 0 (sin datos de derivación reales) | query `expedientes` con join `nurej_padre_id` |
| `nurej_code` irrepetible a nivel BD | UNIQUE confirmado | migración `:16` |
| Filtrado de línea de tiempo por expediente | por FK `expediente_id`, sin endpoint global de lectura | `Expediente@actuados:70`; `routes/api.php` (sin `GET /actuados`) |
| Hijo no hereda actuados/plazos/asignaciones | verificado con test (nace en cero) | `NurejHijoTest:171` |
| Padre conserva historial/plazos/bandeja | verificado con test | `NurejHijoTest:193` |
| **Independencia tras actividad del hijo** | **verificado con test nuevo**: tras derivar y emitir un actuado en el hijo, `GET` del padre lista exactamente sus 2 actuados (sin el del hijo) y `GET` del hijo lista exactamente el suyo (sin `ACT_CREACION_NUREJ_HIJO` ni historial del padre) | `FlujoNurejTest:114` |
| Partes copiadas independientes | test nuevo: modificar la parte del hijo no toca la del padre (IDs distintos) | `FlujoNurejTest:182` |
| Segundo hijo correlativo `-2` (RF-01) | test nuevo: `-1` y `-2` sobre el mismo padre | `FlujoNurejTest:158` |

## §3 Validación negativa

| Escenario | Esperado | Evidencia | Estado |
| --- | --- | --- | --- |
| Técnico deriva | 403 | `NurejHijoTest:213` | ✓ |
| Auditor Jurídico deriva | 403 | `NurejHijoTest:224` | ✓ |
| Encargada inactiva | 403 | `NurejHijoTest:235` | ✓ |
| `motivo` < 10 car. | 422 + `validation_errors.motivo` | `NurejHijoTest:248` | ✓ |
| Derivar **de un ya derivado** (sub-hijo) | respuesta de negocio controlada | **HTTP 422** con mensaje RN-10 (antes: 500; fix AUD-0038, `FlujoNurejTest:207`) | ✓ tras fix |
| Derivaciones concurrentes del mismo padre | serializadas por `lockForUpdate` | `NurejGeneratorService:38-41` | no reproducida (mismo criterio AUD-0036) |
| Código NUREJ duplicado | imposible (lock + UNIQUE) | `NurejGeneratorService:17-27`; migración `:16` | ✓ |

## §4 Hallazgos de la fase

### AUD-0038 — Derivar desde un expediente ya derivado respondía HTTP 500 — **CERRADO**
- **Severidad:** P2 (respuesta de servidor no controlada en endpoint de negocio).
- **Reproducción (antes del fix):** Encargada deriva hijo del padre (201) y
  luego intenta derivar desde ese hijo con `motivo` válido → **500** con
  `{"message": "No se pueden generar NUREJ hijos de un expediente ya
  derivado (RN-10).", "exception": "App\Exceptions\CannotDeriveNurejException",
  "file": ".../NurejGeneratorService.php", "line": 44}` (test temporal de
  reproducción, no persistido).
- **Causa raíz:** `NurejGeneratorService:43-45` lanza
  `CannotDeriveNurejException` (una `DomainException`,
  `CannotDeriveNurejException:11`) **sin ningún handler** que la traduzca a
  un código HTTP (grep: 0 referencias fuera de generador/test unitario;
  `bootstrap/app.php` sin `renderable` para ella) → Laravel la convierte en
  500. Además `ExpedientePolicy@derivarNurejHijo:266-270` no excluye
  expedientes con `nurej_padre_id !== null`, así que la petición llega
  siempre hasta el servicio.
- **Corrección aplicada (2026-09-30, autorizada por el usuario):** manejo
  HTTP explícito en `bootstrap/app.php` (`withExceptions`): `render()` de
  `CannotDeriveNurejException` → **422** con `{"message": ...}` + `dontReport`
  (regla de negocio, no error). **422 elegido por coherencia con el contrato
  existente** (violaciones de regla de negocio → `ValidationException` → 422,
  ej. `ExpedienteService:117`, `SorteoAlgorithmService:56,73`; sin
  precedentes de 409 en `app/`). Servicio, política, generador y unit test
  intactos — solo cambia el manejo HTTP de esta excepción.
- **Verificación:** `FlujoNurejTest:207` (422 + mensaje exacto + 0 sub-hijos
  creados) 4/4 verdes; pint → passed; suite → 288: 281 OK · 1 fallo
  (AUD-0001) · 0 errores · 6 omitidos, sin regresiones. Ficha **CERRADO**.

### AUD-0039 — El NUREJ Hijo hereda la especialidad del padre: RN-10 exige otra especialidad
- **Severidad:** P2 (**confirmada por el usuario 2026-09-30**, provisional
  hasta el análisis de diseño).
- **Evidencia:**
  - SRS `:171`: *"…recomendando la intervención de **otra especialidad** (ej.
    del Técnico al Auditor Jurídico), la Encargada generará el Actuado de
    Creación de NUREJ Hijo"*; SRS `:173`: el operador del hijo emite sus
    actuados de evaluación *"aplicando **su reglamento** y requisitos
    específicos (**Acuerdos 54 o 55**)"*.
  - El hijo **copia `via` y `reglamento_id` del padre**
    (`NurejHijoService:46-47`) y no existe cambio posterior: `grep` en
    `app/` → 0 endpoints/requests que actualicen `reglamento_id`/`via` de un
    expediente; `DerivarNurejHijoRequest:23-28` solo acepta `motivo` (sin
    especialidad destino).
  - El sorteo elige candidatos por la `via` del expediente
    (`SorteoAlgorithmService:30-33,:85-89`) y balancea pesos por su
    `reglamento_id` (`:101-105`) → un hijo de causa Técnica/AC022 se sortea
    siempre hacia **otro Técnico con AC022**, nunca hacia Auditor
    Jurídico/AC054 ni Financiero/AC055.
- **Impacto funcional:** el flujo canónico de RN-10 (Técnico → Auditor
  Jurídico) es inalcanzable: la derivación solo produce causas de la misma
  especialidad. Los requisitos/actuados de evaluación del hijo serían los
  del reglamento heredado, no los de Ac. 054/055 que exige `:173`. Sin
  evidencia de datos reales (0 hijos en BD dev).
- **Estado (decisión del usuario 2026-09-30):** **P2 confirmada**; sin fix
  y sin aceptar el comportamiento actual. Antes de decidir la solución se
  realizará el análisis de diseño (próxima fase disponible): cómo se
  determinan hoy `via`/`reglamento_id`; qué estados/catálogos/roles dependen
  de esos valores; si existe mecanismo previsto para seleccionar la
  especialidad del hijo; implicaciones para partes, bandejas, actuados,
  plazos y autorización; y si RN-10 exige transformación automática o
  selección explícita. **No tocar código hasta presentar la propuesta.**

### AUD-0040 — Flake no reproducida en `AmpliacionTest:175` (observación)
- En la primera suite completa tras el fix de AUD-0038 (288 tests) ese test
  falló una vez (esperaba la justificación de la solicitud y obtuvo el texto
  de la asignación de la semilla); corrida aislada 10/10 ✓ y segunda suite
  completa ✓. **No reproducida** → observación P3, candidato raíz
  `ampliacionAsignar:108` (`CatalogoActuado::first()` sin `ORDER BY`);
  monitorizar sin elevar sin reproducción (ficha en BACKLOG).

## §5 Cobertura de tests del flujo

| Área | Tests | Estado |
| --- | --- | --- |
| Derivación happy path (código, estados, partes, actuado de creación) | `NurejHijoTest:125` | ✓ |
| Hijo nace en cero (actuados/plazos/asignaciones) | `NurejHijoTest:171` | ✓ |
| Padre opera en paralelo | `NurejHijoTest:193` | ✓ |
| 403 roles/inactiva + 422 motivo | `NurejHijoTest:213,224,235,248` | ✓ |
| Secuencia NUREJ padre, año nuevo, hijo correlativo, RN-10 (unit) | `NurejGeneratorServiceTest:15,29,44,77` | ✓ |
| **Independencia de línea de tiempo tras actividad del hijo** | `FlujoNurejTest:114` (nuevo) | ✓ |
| **Hijo sin plazos/asignaciones + segundo hijo `-2`** | `FlujoNurejTest:158` (nuevo) | ✓ |
| **Independencia de partes copiadas** | `FlujoNurejTest:182` (nuevo) | ✓ |
| **Sub-derivar → 422 (RN-10, fix AUD-0038)** | `FlujoNurejTest:207` (nuevo) | ✓ |

Suite completa tras Fase 11 y el fix de AUD-0038: **288 tests: 281 OK ·
1 fallo (AUD-0001) · 0 errores · 6 omitidos** — sin regresiones (+4 verdes
nuevos; 0 errores).

## §6 Decisiones del usuario (Fase 11) — resueltas 2026-09-30

1. **AUD-0038 — RESUELTA:** fix autorizado y aplicado (manejo HTTP de
   `CannotDeriveNurejException` → **422** coherente con el contrato 422 del
   proyecto, `dontReport`, test `FlujoNurejTest:207`). Ficha **CERRADO**.
2. **AUD-0039 — RESUELTA (parcialmente):** **P2 confirmada**; análisis de
   diseño obligatorio antes de cualquier fix; **código intacto**. Ficha OPEN
   con el alcance del análisis definido por el usuario.
3. Salidas asíncronas RN-10 `:174` (padre cierra, hijo continúa): fuera del
   alcance de esta fase (flujo de cierre, F16).
4. RF-R09 (reporte trazabilidad padre-hijo `:127`): alcance **F12**.
5. **Nueva observación registrada:** AUD-0040 (flake no reproducida).
