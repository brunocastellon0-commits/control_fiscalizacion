# Guía Técnica — Módulos de Flujo Procesal (US-2.3 a US-2.6 + Motor Disciplinario)

**Sistema de Control y Fiscalización — Consejo de la Magistratura (Cochabamba)**

> Documento de soporte para desarrollo: describe a nivel de código el **qué**,
> **dónde** y **por qué** de los módulos del flujo procesal entregados entre
> US-2.3 y US-2.6 más el Motor Disciplinario. Verificado contra el código real
> (servicios, controladores, requests, policies, rutas, seeders y tests).
>
> Fuentes complementarias: `doc_avances/SCHEMA_CONTEXTO.md` (esquema de BD),
> `doc_avances/README.md` (bitácora) y `docs_Avances/BACKLOG_JIRA.md` (backlog).

## 1. Alcance y mapeo

| Módulo | Historia | Épica (backlog) | Servicio principal | Tests |
|---|---|---|---|---|
| Impugnaciones | US-2.3 | Épica 7 (E7-S1/E7-S2) | `App\Services\ImpugnacionService` | `ImpugnacionRechazoTest` |
| Planificación y Visto Bueno | US-2.4 | Épica 4 (E4-S1/E4-S2/E4-S3) | `App\Services\PlanificacionService` | `PlanificacionTest`, `RelojProcesualTest` |
| Devolución de Planificación | US-2.5 | Épica 4 (E4-S3) | `PlanificacionService@devolverPlanificacion` | `PlanificacionTest` |
| Ampliación de Plazo | US-2.6 | Épica 5 (E5-S1) | `App\Services\AmpliacionService` | `AmpliacionTest` |
| Motor Disciplinario | QA 3 + RN-03 | Épica 1 (E1-S1-BE) | `VerificarVencimientoPlazosCommand`, `MarcarPlazosVencidosService`, `ArchivoPorAbandonoService`, `SemaforoPlazoService` | `ArchivoPorAbandonoTest`, `SemaforoPenalizacionTest` |

**Estado de la suite (referencia):** 202 tests / 201 passed / 1 skipped.

**Comandos de verificación usados durante el desarrollo:**

```bash
vendor/bin/pint --dirty --format agent     # formato PSR-12 (PHP modificado)
php artisan test --compact                 # suite completa (backend)
```

## 2. Módulo US-2.3 — Impugnaciones (RN-08)

### 2.1 Objetivo y Contexto Legal

Cuando la evaluación de admisibilidad emite `ACT_RECHAZO` (falta un requisito
**crítico** no subsanable), el interesado puede impugnar. La RN-08 establece dos
turnos:

- **1 día hábil** para que el operador que rechazó **remita** la impugnación a
  la Encargada (`IMPUGNACION_REMITIR`).
- **3 días hábiles** para que la Encargada **resuelva** (`IMPUGNACION_RESOLVER`).

La resolución es binaria y terminal en el flujo de admisibilidad:

- **Ratifica** el rechazo → expediente en `ARCHIVO_DEFINITIVO` (estado final,
  cierre de bandejas y plazos).
- **Revoca** el rechazo → el expediente vuelve a `ADMITIDO` y se reabre la
  sustanciación asignándolo al **operador original** (nueva asignación activa
  con plazo de `PLANIFICACION`).

### 2.2 Archivos Intervinientes

| Capa | Archivo | Rol |
|---|---|---|
| Servicio | `app/Services/ImpugnacionService.php` | `remitirImpugnacion()`, `resolverImpugnacion()` (toda la lógica transaccional) |
| Controller | `app/Http/Controllers/ImpugnacionController.php` | Endpoints delgados → servicio |
| Form Requests | `app/Http/Requests/ImpugnacionRemitirRequest.php` | `descripcion` required, string, **min:10**, max:5000 |
| Form Requests | `app/Http/Requests/ResolverImpugnacionRequest.php` | `ratifica` required boolean; `justificacion` required, **min:10**, max:5000 |
| Policy | `app/Policies/ExpedientePolicy.php` | `remitirImpugnacion()`, `resolverImpugnacion()` |
| Rutas | `routes/api.php:52-53` | `POST /expedientes/{expediente}/impugnacion/remitir` y `/resolver` |
| Catálogo | `database/seeders/CatalogoActuadoSeeder.php` | `ACT_REMITIR_IMPUGNACION`, `ACT_RESOLUCION_RATIFICA_RECHAZO`, `ACT_RESOLUCION_REVOCA_RECHAZO` (+ pivotes por perfil/reglamento) |
| Catálogo | `database/seeders/ParametroPlazoSeeder.php` | `IMPUGNACION_REMITIR`=1d, `IMPUGNACION_RESOLVER`=3d (para AC022/054/055) |
| Catálogo | `database/seeders/CatalogoEstadoSeeder.php` | estado `EN_IMPUGNACION` |
| Mapeo plazos | `App\Services\ActuadoService::MAPA_TIPO_PLAZO` | `ACT_RECHAZO`→`IMPUGNACION_REMITIR`, `ACT_REMITIR_IMPUGNACION`→`IMPUGNACION_RESOLVER`, revocación→`PLANIFICACION` |
| Modelo | `app/Models/Impugnacion.php` | registro `impugnaciones` (resultado PENDIENTE/RATIFICADO/REVOCADO) |
| Tests | `tests/Feature/ImpugnacionRechazoTest.php` | plazos 1d/3d, revocación al operador original, ratificación cierra, 403 |

### 2.3 Funcionamiento Arquitectónico y Transaccional

Ambos métodos corren dentro de `DB::transaction(..., 3)` (reintentos ante
deadlock). El registro físico del actuado y el encadenamiento SHA-256
(`hash_anterior` → `hash_actuado`) los resuelve el **trigger de MySQL**, no
PHP: `App\Services\ActuadoService::registerActuado()` solo inserta el evento,
transiciona el estado, reasigna la bandeja y abre plazos según el mapa.

**`remitirImpugnacion()`** (estado esperado: `RECHAZADO`):

1. `validarEstado(RECHAZADO)`.
2. `resolverEncargada()` → primer usuario activo con rol `ENCARGADA`.
3. Cierra el plazo `IMPUGNACION_REMITIR` vigente (el operador ya entregó).
4. `registerActuado(ACT_REMITIR_IMPUGNACION, destino=Encargada, metadatos
   {tipo: IMPUGNACION, fase: REMITIR})` → transiciona a `EN_IMPUGNACION`,
   reasigna la bandeja a la Encargada y **abre automáticamente** el plazo
   `IMPUGNACION_RESOLVER`.
5. Localiza ese plazo por `actuado_disparador_id` y crea el registro
   `Impugnacion` con `fecha_limite_resolucion = plazo->fecha_limite` y
   `resultado = PENDIENTE`, referenciando el actuado de rechazo más reciente
   (`ACT_RECHAZO`).

**`resolverImpugnacion()`** (estado esperado: `EN_IMPUGNACION`), carga la
impugnación `PENDIENTE` más reciente:

- **RATIFICA = true**: `registerActuado(ACT_RESOLUCION_RATIFICA_RECHAZO)` →
  `cerrarPlazosActivos()` (todos los `VIGENTE`→`CERRADO`) →
  `cerrarBandejaActiva()` (asignación activa→inactiva) →
  `Impugnacion->update(resultado: RATIFICADO)`.
- **RATIFICA = false**: `operadorOriginalDelExpediente()` (última asignación
  **inactiva**) → `cerrarPlazoImpugnacionResolver()` (cierra **solo**
  `IMPUGNACION_RESOLVER`) → `registerActuado(ACT_RESOLUCION_REVOCA_RECHAZO,
  destino=operador original)` → `Impugnacion->update(resultado: REVOCADO)`.
  El actuado de revocación abre por mapa un plazo de `PLANIFICACION`.

### 2.4 Flujo Procesal (State Machine)

```text
                            ┌─────────────┐
          ACT_RECHAZO      │             │
EN_EVALUACION ────────────►│  RECHAZADO  │──►  (interesado impugna)
                            │             │
                            └──────┬──────┘
                                   │ ACT_REMITIR_IMPUGNACION (1 día, operador→Encargada)
                                   ▼
                            ┌─────────────┐
                            │EN_IMPUGNACION│ (3 días, bandeja Encargada)
                            └──────┬──────┘
              ┌───────────────────┼───────────────────────┐
   ACT_RESOLUCION_                │        ACT_RESOLUCION_
   RATIFICA_RECHAZO               │        REVOCA_RECHAZO
   (Encargada)                    │        (Encargada)
              ▼                   │                       ▼
      ARCHIVO_DEFINITIVO          │                  ADMITIDO
      (estado final)              │                  + reasignación al
                                  │                  operador original
                                  │                  + plazo PLANIFICACION
                                  └── cierra plazos y bandeja activa
```

### 2.5 Decisiones de Diseño y Edge Cases

- **Cierre selectivo vs genérico.** En la revocación solo se cierra
  `IMPUGNACION_RESOLVER` para no matar el plazo `PLANIFICACION` que reabre la
  sustanciación; en la ratificación se cierran todos los plazos vigentes porque
  el caso terminó.
- **`operadorOriginalDelExpediente()`** = última asignación `activa=false` por
  `fecha_asignacion` desc. No se depende de IDs ni de roles hardcodeados.
  `firstOrFail` guarda contra bandejas huérfanas (422 si no existe el operador).
- **Bandejas vía `registerActuado`.** La remisión desactiva la asignación del
  operador y crea una activa para la Encargada (definido en
  `ActuadoService`), lo que mantiene la invariante **RF-03** (una sola
  asignación activa) garantizada dentro de la transacción.
- **Una impugnación pendiente por vez.** `impugnacionPendiente()` consulta
  `resultado = PENDIENTE` con `latest('id')`; el estado `EN_IMPUGNACION` impide
  re-remitir, y un caso resuelto no puede volver a caer en este flujo.
- **Autorización por policy** (`remitirImpugnacion`/`resolverImpugnacion`):
  combina rol, estado actual y (para remitir) asignación activa del operador.
  La UI no es la barrera: el backend devuelve 403 ante cualquier otro perfil.
- **Fecha de resolución consistente.** `fecha_limite_resolucion` se toma del
  plazo abierto transaccionalmente, no se recalcula en el servicio (una sola
  fuente de verdad para días hábiles).

## 3. Módulo US-2.4 — Planificación y Visto Bueno (RN-04/RN-05)

### 3.1 Objetivo y Contexto Legal

Tras la admisión (`ACT_ADMISION`), el expediente queda `EN_PLANIFICACION` con
un plazo de **2 días hábiles** para que el operador cargue su plan de trabajo:

- **AC_022_2018 (Técnico)** → `ACT_CRONOGRAMA_TRABAJO` (adjunto PDF obligatorio).
- **AC_054_2018 / AC_055_2018 (Auditores)** → `ACT_MPA` con **fecha límite
  dinámica propuesta** por el auditor (`fecha_limite_propuesta`).

El **Visto Bueno de la Encargada** (`ACT_VISTO_BUENO_PLANIFICACION`) hace
arrancar oficialmente el reloj de investigación `EJECUCION` (RN-04/RN-05):
10 días hábiles base para el Técnico, o la fecha dinámica del MPA para los
auditores. Sin VB la investigación no corre y el plazo `PLANIFICACION` vencido
se penaliza por el Motor Disciplinario.

### 3.2 Archivos Intervinientes

| Capa | Archivo | Rol |
|---|---|---|
| Servicio | `app/Services/PlanificacionService.php` | `cargarPlanificacion()`, `aprobarVistoBueno()` |
| Controller | `app/Http/Controllers/PlanificacionController.php` | `store()`, `vistoBueno()` |
| Form Request | `app/Http/Requests/StorePlanificacionRequest.php` | `descripcion` min:10; `fecha_limite_propuesta` **solo si MPA** (`Rule::requiredIf`, `after:today`) — la regla se resuelve según reglamento del expediente; `adjunto` PDF ≤20MB obligatorio |
| Form Request | `app/Http/Requests/VistoBuenoPlanificacionRequest.php` | `descripcion` min:5 (autorización = policy `aprobarPlanificacion`) |
| Policy | `app/Policies/ExpedientePolicy.php` | `cargarPlanificacion()`, `aprobarPlanificacion()` |
| Rutas | `routes/api.php:56-58` | `POST /expedientes/{expediente}/planificacion`, `/planificacion/visto-bueno` |
| Catálogo | `CatalogoActuadoSeeder` | `ACT_CRONOGRAMA_TRABAJO` (pivote TECNICO+AC022), `ACT_MPA` (pivote AUD_JURIDICO+AC054, AUD_FINANCIERO+AC055), `ACT_VISTO_BUENO_PLANIFICACION` (pivote ENCARGADA) |
| Catálogo | `ParametroPlazoSeeder` | `PLANIFICACION`=2d (AC022/054/055); `EJECUCION/JURISDICCIONAL`=10d |
| Mapeo | `ActuadoService::MAPA_TIPO_PLAZO` | `ACT_ADMISION`→`PLANIFICACION`, `ACT_VISTO_BUENO_PLANIFICACION`→`EJECUCION` (con `subtipo` = `resolveSubtipoEjecucion()`) |
| Tests | `PlanificacionTest`, `RelojProcesualTest` | inicio del reloj, fechas por vía, 403 por perfil, devolución |

### 3.3 Funcionamiento Arquitectónico y Transaccional

**`cargarPlanificacion()`** (estado esperado: `EN_PLANIFICACION`):

1. `catalogoSegunReglamento()`: `match` sobre `expediente->reglamento->codigo` →
   AC022=Cronograma, AC054/AC055=MPA; acuerdo no soportado → 422 explícito.
2. Si es MPA: `validarFechaLimite()` (formato estricto `Y-m-d`, mayor a hoy) y
   se persiste en `contenido['fecha_limite']` del actuado.
3. `cerrarPlazoPlanificacion()`: `PLANIFICACION` VIGENTE → `CERRADO` (entregó a
   tiempo). El plazo anterior queda como evidencia.
4. `registerActuado(..., destino=Encargada)` → transiciona a
   `PENDIENTE_VISTO_BUENO` y mueve la bandeja a la Encargada.

**`aprobarVistoBueno()`** (estado esperado: `PENDIENTE_VISTO_BUENO`):

1. `planificacionMasReciente()`: último actuado Cronograma/MPA.
2. `fechaLimiteDePlanificacion()`: si fue MPA, lee `contenido['fecha_limite']`.
3. `operadorOriginalDelExpediente()` (asignación inactiva más reciente).
4. `registerActuado(ACT_VISTO_BUENO_PLANIFICACION, destino=operador original,
   fechaLimiteExplicita=fecha del MPA o null)`. El parámetro
   `fechaLimiteExplicita` anula el cálculo por días hábiles dentro de
   `ActuadoService` (ver firma): **null** → cálculo normativo (10 días para
   Técnico); **fecha** → usa exactamente esa fecha para el plazo `EJECUCION`
   (MPA dinámico). El actuado cierra la bandeja de la Encargada, reasigna al
   operador original y transiciona a `EN_EJECUCION`.

### 3.4 Flujo Procesal (State Machine)

```text
  EN_EVALUACION
        │ ACT_ADMISION (abre plazo PLANIFICACION 2d)
        ▼
  EN_PLANIFICACION
        │ ACT_CRONOGRAMA_TRABAJO (Técnico, AC022)   ► adjunto PDF
        │ ACT_MPA (Auditor, AC054/055)              ► adjunto + fecha_limite_propuesta
        │   (cierra plazo PLANIFICACION)
        ▼
  PENDIENTE_VISTO_BUENO   (bandeja Encargada)
        │
        ├── ACT_VISTO_BUENO_PLANIFICACION ► EN_EJECUCION
        │       └── abre EJECUCION (10d Técnico / fecha MPA Auditor)
        └── ACT_DEVOLUCION_OBSERVACION   ► EN_PLANIFICACION (ver US-2.5)
```

### 3.5 Decisiones de Diseño y Edge Cases

- **El reloj no arranca en la admisión.** `ACT_ADMISION` solo abre
  `PLANIFICACION`; `EJECUCION` recién arranca con el VB. Documentado en el
  propio `MAPA_TIPO_PLAZO` (RN-04/RN-05).
- **MPA con fecha dinámica ⇒ `dias_habiles` no aplica.** El plazo creado por el
  actuado usa la fecha explícita; el cálculo por días hábiles del parámetro
  queda anulado. Si el auditor propone una fecha no hábil, se respeta tal cual
  (es su responsabilidad), mientras cumpla `after:today`.
- **Sub-tipo de EJECUCION siempre `JURISDICCIONAL`** hoy: `resolveSubtipoEjecucion()`
  está acoplado a la base de 10 días (el parámetro `ADMINISTRATIVA` de 15 días
  queda dormido y lo consume la ampliación, ver US-2.6).
- **Regla `requiredIf` dinámica en el Form Request.** La obligatoriedad de
  `fecha_limite_propuesta` depende del reglamento del expediente en ruta, no de
  una constante: la validación y el servicio comparten la misma fuente
  (`PlanificacionService::REGLAMENTO_AC022`).
- **`fechaLimiteExplicita` con `null` vs fecha.** El `null` significa "cálculo
  estándar"; una fecha significa "fecha fija". No hay "sin plazo": siempre se
  abre `EJECUCION` al aprobar.
- **403 por perfil y estado.** `cargarPlanificacion` exige rol operativo con
  pivote + estado `EN_PLANIFICACION`; `aprobarPlanificacion` exige rol
  `ENCARGADA` + estado `PENDIENTE_VISTO_BUENO`.
- **Entrega tardía de planificación.** El cierre `VIGENTE→CERRADO` de
  `cerrarPlazoPlanificacion` funciona aunque el Motor Disciplinario ya haya
  marcado `fuera_de_plazo=true`; la marca persiste como penalización (ver §6).

## 4. Módulo US-2.5 — Devolución de Planificación

### 4.1 Objetivo y Contexto Legal

El Visto Bueno no es automático: la Encargada puede **devolver** la
planificación con observaciones (`ACT_DEVOLUCION_OBSERVACION`) cuando el
Cronograma o el MPA no la satisfacen. El expediente vuelve a `EN_PLANIFICACION`
y se **reabre el plazo de planificación (2 días hábiles)** para que el operador
corrija y vuelva a cargar. Es un mecanismo de control de calidad jerárquico,
no una corrección silenciosa: cada ciclo queda registrado como actuado.

### 4.2 Archivos Intervinientes

| Capa | Archivo | Rol |
|---|---|---|
| Servicio | `PlanificacionService::devolverPlanificacion()` | lógica transaccional del retorno |
| Controller | `PlanificacionController::devolver()` | endpoint delgado |
| Form Request | `app/Http/Requests/DevolverPlanificacionRequest.php` | `justificacion` required, string, **min:10**, max:10000 (mensajes en español) |
| Policy | `ExpedientePolicy::devolverPlanificacion()` | rol Encargada + estado `PENDIENTE_VISTO_BUENO` |
| Rutas | `routes/api.php:58` | `POST /expedientes/{expediente}/planificacion/devolver` |
| Catálogo | `CatalogoActuadoSeeder` | `ACT_DEVOLUCION_OBSERVACION` (pivote ENCARGADA; transición `PENDIENTE_VISTO_BUENO`→`EN_PLANIFICACION`) |
| Mapeo | `ActuadoService::MAPA_TIPO_PLAZO` | `ACT_DEVOLUCION_OBSERVACION`→`PLANIFICACION` (reabre los 2 días) |
| Tests | `PlanificacionTest` | devolución en AC054: vuelve a planificar, plazo PLANIFICACION 2d VIGENTE, previo CERRADO |

### 4.3 Funcionamiento Arquitectónico y Transaccional

`devolverPlanificacion()` (estado esperado: `PENDIENTE_VISTO_BUENO`), dentro de
`DB::transaction(..., 3)`:

1. Valida estado vía `validarEstado()`.
2. `operadorOriginalDelExpediente()`: el mismo mecanismo que US-2.4 (última
   asignación inactiva), que identifica **quien envió la planificación**.
3. `registerActuado(ACT_DEVOLUCION_OBSERVACION, destino=operador original,
   descripcion=justificacion)`. Por el mapa de plazos y la transición del
   catálogo, esto produce: desactiva la bandeja de la Encargada → reasigna al
   operador → `EN_PLANIFICACION` → abre **nuevo** plazo `PLANIFICACION` de 2
   días. El plazo anterior queda `CERRADO` como evidencia (trazabilidad).

### 4.4 Flujo Procesal (State Machine)

```text
  PENDIENTE_VISTO_BUENO
        │
        └── ACT_DEVOLUCION_OBSERVACION (Encargada, con justificación ≥10 chars)
                │   cierra bandeja Encargada / reasigna al operador original
                │   abre nuevo plazo PLANIFICACION 2d (previo queda CERRADO)
                ▼
           EN_PLANIFICACION ──► (operador corrige y vuelve a cargar, US-2.4)
```

### 4.5 Decisiones de Diseño y Edge Cases

- **Se reabre plazo, no se "revive" el anterior.** Un `Plazo::create` nuevo es
  coherente con el modelo append-only y con la penalización: si el plazo previo
  venció sin cargar, el `fuera_de_plazo=true` se mantiene aunque se devuelva la
  planificación.
- **Máximo de ciclos ilimitado hoy.** No hay contador de devoluciones
  (pendiente de pendientes si el SRS lo exige); cada ciclo queda auditado por
  actuado y por la cadena de hash.
- **Justificación obligatoria y mínima.** `min:10` evita devoluciones vacías;
  es el único requisito de negocio del acto (el comentario ES el contenido).
- **Reutilización del patrón operador-original.** El mismo método usado por
  US-2.4 y US-2.6; si se alterara la semántica de "última asignación inactiva",
  afectaría a los tres flujos (mantener en mente al refactorizar).

## 5. Módulo US-2.6 — Ampliación de Plazo (AC-022)

### 5.1 Objetivo y Contexto Legal

El Técnico que tramita un expediente bajo **AC_022_2018** puede solicitar la
**única ampliación** del plazo de investigación cuando se justifica. El diseño
aprobado cierra el plazo `EJECUCION` original (10 días base, queda `CERRADO`
como evidencia) y abre un plazo nuevo `EJECUCION_AMPLIADA` de **15 días hábiles**
cuyo vencimiento se calcula **sobre el vencimiento original** (no desde la
aprobación), preservando la ventana total de 10+15 días hábiles. La decisión la
toma la **Encargada**; mientras tanto el reloj base **sigue corriendo** (no se
pausa).

### 5.2 Archivos Intervinientes

| Capa | Archivo | Rol |
|---|---|---|
| Servicio | `app/Services/AmpliacionService.php` | `solicitarAmpliacion()`, `aprobarAmpliacion()` |
| Controller | `app/Http/Controllers/AmpliacionController.php` | `solicitar()` y `aprobar()` → 201 con `ActuadoResource` (eager-loads) |
| Form Requests | `app/Http/Requests/SolicitarAmpliacionRequest.php` | `justificacion` required, string, **min:10**, max:10000 |
| Form Requests | `app/Http/Requests/AprobarAmpliacionRequest.php` | solo `authorize()` (reglas `[]`); la lógica de negocio está en el servicio |
| Policy | `ExpedientePolicy` | `solicitarAmpliacion()` (TECNICO + `EN_EJECUCION` + pivote AC022 + asignación activa), `aprobarAmpliacion()` (ENCARGADA + `PENDIENTE_APROBACION_AMPLIACION`) |
| Rutas | `routes/api.php:61-62` | `POST /expedientes/{expediente}/ampliacion` y `/ampliacion/aprobar` |
| Catálogo | `CatalogoEstadoSeeder` | `PENDIENTE_APROBACION_AMPLIACION` (= gate de la policy, espejo de `PENDIENTE_VISTO_BUENO`) |
| Catálogo | `CatalogoActuadoSeeder` | `ACT_SOLICITAR_AMPLIACION` (pivote TECNICO+AC022, `EN_EJECUCION`→`PENDIENTE_APROBACION_AMPLIACION`), `ACT_APROBAR_AMPLIACION` (pivote ENCARGADA, `PENDIENTE_APROBACION_AMPLIACION`→`EN_EJECUCION`) |
| Catálogo | `ParametroPlazoSeeder` | `EJECUCION_AMPLIADA` = 15 días (AC022) |
| Tests | `tests/Feature/AmpliacionTest.php` | 10 casos: solicitud, aprobación, 403×5, 422 (justificación corta, sin plazo vigente, ampliación única) |

### 5.3 Funcionamiento Arquitectónico y Transaccional

**`solicitarAmpliacion()`** (4 validaciones, dentro de la transacción):

1. `validarReglamentoAmpliacion()` → solo `AC_022_2018`.
2. `validarEstado(EN_EJECUCION)`.
3. `validarSinAmpliacionPrevia()` → no puede existir **ningún** plazo
   `EJECUCION_AMPLIADA` (sin importar su estado): ampliación única por causa.
4. `validarRelojEjecucionVigente()` → existe plazo `EJECUCION` en `VIGENTE`.
5. `registerActuado(ACT_SOLICITAR_AMPLIACION, destino=Encargada, metadatos
   {tipo: AMPLIACION})` → `PENDIENTE_APROBACION_AMPLIACION`. **El reloj base
   NO se cierra ni pausa** (lo cierra la aprobación).

**`aprobarAmpliacion()`**:

1. `validarEstado(PENDIENTE_APROBACION_AMPLIACION)`.
2. `plazoEjecucionVigente()`: el plazo base que se va a ampliar (si no existe,
   422 "No hay un plazo de EJECUCION vigente que ampliar").
3. `parametroAmpliacion()`: `parametros_plazo` con `tipo_plazo =
   EJECUCION_AMPLIADA`, `subtipo = null` y reglamento del expediente (15 días).
4. `fechaLimiteAmpliada = PlazoCalculatorService::calculateDueDate(
   plazoOriginal->fecha_limite, 15)` — suma los 15 días hábiles sobre el
   vencimiento original (salta sábados/domingos y feriados/suspensiones).
5. Cierra el plazo original (`EJECUCION` → `CERRADO`).
6. `registerActuado(ACT_APROBAR_AMPLIACION, destino=operador original,
   metadatos: dias_habiles_otorgados, fecha_limite_anterior,
   fecha_limite_ampliada)` → `EN_EJECUCION`, bandeja al Técnico.
7. `Plazo::create` del `EJECUCION_AMPLIADA` con `actuado_disparador_id` del
   actuado de aprobación, `estado VIGENTE`, `dias_habiles_otorgados=15`.

Ambos métodos son `DB::transaction(..., 3)`. Los metadatos del actuado de
aprobación son la **traza de auditoría** del antes/después del plazo.

### 5.4 Flujo Procesal (State Machine)

```text
  EN_EJECUCION (plazo EJECUCION VIGENTE)
        │  ACT_SOLICITAR_AMPLIACION (Técnico AC022, con justificación)
        ▼
  PENDIENTE_APROBACION_AMPLIACION   (reloj EJECUCION sigue VIGENTE)
        │  ACT_APROBAR_AMPLIACION (Encargada)
        │    ├─ cierra EJECUCION → CERRADO (evidencia)
        │    ├─ abre EJECUCION_AMPLIADA 15d hábiles sobre el vencimiento original
        │    └─ registra metadatos fecha_limite_anterior / fecha_limite_ampliada
        ▼
  EN_EJECUCION  (bandeja del Técnico original, nuevo plazo VIGENTE)
```

### 5.5 Decisiones de Diseño y Edge Cases

- **Suma sobre el vencimiento original, no sobre `now()`.** Decisión de negocio
  confirmada: la ampliación extiende la ventana total 10+15 desde el inicio; si
  se sumara desde la aprobación se regalarían días de tramitación.
- **El reloj no se pausa en el período de aprobación.** A diferencia de los
  descargos (futuros), la solicitud mantiene `EJECUCION` vigente; el costo de
  una aprobación lenta es la penalización (`fuera_de_plazo`), que además motiva
  a la Encargada a resolver.
- **`PENDIENTE_APROBACION_AMPLIACION` como gate de policy.** Es un estado
  efímero que se consume al aprobar; su único propósito es autorizar
  (`aprobarAmpliacion`) y restringir (nadie más puede actuar). Espejo del patrón
  `PENDIENTE_VISTO_BUENO`.
- **Única por causa, incluso si está CERRADO.** La existencia de cualquier
  `EJECUCION_AMPLIADA` bloquea nuevas solicitudes: no hay dobles ampliaciones
  ni reemplazo de una aprobada.
- **Validaciones redundantes a propósito.** El service valida estado y plazo
  vigente aunque la request/policy ya lo hicieron: seguridad en profundidad y
  prueba directa sin HTTP (tests llamando al service).
- **`AprobarAmpliacionRequest` sin reglas.** La decisión no carga datos del
  frontend (todo deriva del estado del expediente); `authorize()` es la única
  barrera. Si en el futuro se piden comentarios, se agregan al `metadatos`.

## 6. Motor Disciplinario (QA 3 + RN-03) — Épica 1

### 6.1 Objetivo y Contexto Legal

Garantiza que los plazos del sistema se controlen solos, sin depender de la
acción humana, y que el rendimiento quede auditado:

- **RN-03 (Archivo por abandono):** si el interesado no subsana en el plazo de
  `SUBSANACION`, el sistema emite `ACT_ARCHIVO_POR_ABANDONO` (estado final
  `ARCHIVO_POR_ABANDONO`).
- **QA 3 (Penalización de plazos internos):** un plazo interno vencido (p. ej.
  `EJECUCION`) se estampa `fuera_de_plazo=true` **sin bloquear el flujo** ni
  transicionar el expediente; queda una marca persistente de auditoría que
  sobrevive al cierre del caso.
- **Semáforo en tiempo real:** `SemaforoPlazoService` calcula
  VERDE/AMARILLO/ROJO/FUERA_DE_PLAZO contra `now()` para bandejas y detalle.

### 6.2 Archivos Intervinientes

| Capa | Archivo | Rol |
|---|---|---|
| Comando | `app/Console/Commands/VerificarVencimientoPlazosCommand.php` | `plazos:verificar-vencidos` (`#[Signature]`/`#[Description]`) |
| Schedule | `routes/console.php:11` | `Schedule::command('plazos:verificar-vencidos')->daily();` |
| Servicio | `app/Services/ArchivoPorAbandonoService.php` | `archivarVencidos()` (RN-03) |
| Servicio | `app/Services/MarcarPlazosVencidosService.php` | `marcarVencidos()` (QA 3) |
| Servicio | `app/Services/SemaforoPlazoService.php` | `evaluarPlazo()`, `resumenBandeja()`, `colorMasUrgente()` |
| Servicio | `app/Services/PlazoCalculatorService.php` | `daysRemaining()`, `calculateDueDate()` (hábiles con feriados/suspensiones) |
| Catálogo | `CatalogoActuadoSeeder` | `ACT_ARCHIVO_POR_ABANDONO` (`es_automatico=true`, rol ADMIN) |
| Catálogo | `CatalogoEstadoSeeder` | `ARCHIVO_POR_ABANDONO` (`es_final=true`) |
| Tests | `tests/Feature/ArchivoPorAbandonoTest.php`, `tests/Feature/SemaforoPenalizacionTest.php` | comando, idempotencia, semáforos, penalización |

### 6.3 Funcionamiento Arquitectónico y Transaccional

**`VerificarVencimientoPlazosCommand::handle()`** inyecta los dos servicios y
reporta conteos:

1. `archivoPorAbandonoService->archivarVencidos()`
2. `marcarPlazosVencidosService->marcarVencidos()`

**`MarcarPlazosVencidosService::marcarVencidos()`** (idempotencia total):

```text
UPDATE plazos SET fuera_de_plazo = true
WHERE tipo_plazo != 'SUBSANACION'
  AND estado = 'VIGENTE'
  AND fuera_de_plazo = false
  AND fecha_limite < CURDATE()
```

- El plazo **se mantiene `VIGENTE`**: los servicios de flujo lo cierran
  (`VIGENTE→CERRADO`) al entregar, preservando la marca. No transiciona el
  expediente ni cierra bandejas. El filtro `fuera_de_plazo=false` lo hace
  idempotente.
- Excluye `SUBSANACION` porque ese rango lo gestiona el archivo por abandono
  (tienen su propia transición a `VENCIDO`).

**`ArchivoPorAbandonoService::archivarVencidos()`** — por cada `SUBSANACION`
`VIGENTE` vencida estrictamente (`fecha_limite < today`), en una transacción
propia por expediente:

1. Marca el plazo `estado=VENCIDO` y `fuera_de_plazo=true`.
2. `registerActuado(ACT_ARCHIVO_POR_ABANDONO)` con **emisor = usuario sistema**
   (primer `ADMIN` activo; sin IDs hardcodeados), descripción estándar y
   metadatos `{tipo: AUTOMATICO, motivo: FALTA_SUBSANACION, plazo_id}`. El
   actuado es inmutable y encadena el hash por trigger; transiciona a
   `ARCHIVO_POR_ABANDONO` (final).
3. `cerrarBandejaOperador()` (asignación activa → inactiva).

La consulta inicial es por `fecha_limite < today` (vencimiento estricto del día
completo), entregando un `archivados` por expediente.

**`SemaforoPlazoService::evaluarPlazo()`**:

- Estados **no `VIGENTE`** (CERRADO/SUSPENDIDO/VENCIDO): se devuelven sin
  cálculo con `codigo_color = estado` y `es_fuera_de_plazo = (bool) fuera_de_plazo`
  → la penalización **sobrevive al cierre** del caso.
- Estados `VIGENTE`: `es_fuera_de_plazo = now() > fecha_limite->endOfDay()`;
  color =
  - `ROJO` si restan ≤1 día hábil (`daysRemaining`),
  - `AMARILLO` si plazos cortos (≤3d) con 2 restantes, o plazos largos si
    restantes ≤ `ceil(dias_habiles_otorgados / 3)` (último tercio),
  - `VERDE` en el resto; agrega `porcentaje_consumido`.
- `resumenBandeja()`: agrega {VERDE→en_plazo, AMARILLO→precaucion,
  ROJO→urgente, FUERA_DE_PLAZO→fuera_de_plazo} tomando el plazo vigente más
  urgente por expediente (`colorMasUrgente`).

### 6.4 Flujo Procesal (State Machine)

```text
  ── CRON diario: plazos:verificar-vencidos ───────────────
        │
        ├─ SUBSANACION vencida (VIGENTE) ──► VENCIDO
        │        + ACT_ARCHIVO_POR_ABANDONO (automático, ADMIN)
        │        + cierre bandeja operador
        │        ▼
        │   ARCHIVO_POR_ABANDONO   (estado final, actuado con hash)
        │
        └─ plazos internos vencidos (VIGENTE) ──► fuera_de_plazo = true
                 (sigue VIGENTE; el flujo lo cierra al entregar)
```

El semáforo es de solo lectura: colorea según `now()` y nunca muta el plazo.

### 6.5 Decisiones de Diseño y Edge Cases

- **`VENCIDO` solo para SUBSANACION.** Los plazos internos nunca transicionan a
  `VENCIDO` (decision de QA 3): `VIGENTE + fuera_de_plazo` mantiene compatibles
  los cierres por entrega tardía y la penalización histórica.
- **Idempotencia por diseño.** `marcarVencidos` filtrado por `fuera_de_plazo =
  false`; `archivarVencidos` filtrado por `estado = VIGENTE`. El cron puede
  correr N veces sin efectos duplicados.
- **Transacciones por expediente en el archivo.** Si falla un expediente, los
  anteriores quedan archivados y el resto pendiente para el próximo corrida;
  un `archivados++` solo tras éxito del `update`+actuado+bandeja.
- **Emisor sistema = primer ADMIN activo.** Independiente de IDs; si no hay
  ADMIN activo, `firstOrFail` rompe el cron (falla **visible**, no silenciosa —
  intencional en un sistema gubernamental).
- **Vencimiento estricto de día completo:** `fecha_limite < CURDATE()` y
  `now() > endOfDay()`: hoy NO vence hasta que el día termine; evita archivar
  a las 00:01.
- **El comando no orquesta planificaciones.** Solo archiva y estampa. Las
  transiciones de flujo (plazos que se cierran al entregar) son responsabilidad
  de los servicios US-2.3–2.6 vía `ActuadoService`.
- **`resumenBandeja` sin endpoint (gap).** El agregado está implementado y
  testeado pero no expuesto en `routes/api.php`; corresponde a Épica 10 del
  backlog cuando se construya el dashboard.

## 7. Anexo — Pendientes relacionados (para continuidad)

| Pendiente | Épica | Notas |
|---|---|---|
| FE: alerta visual "FUERA DE PLAZO" en bandeja/detalle | E1-S1-FE | Datos ya disponibles vía `evaluarPlazo()['es_fuera_de_plazo']` y `externo` |
| CRUD suspensiones/feriados + recálculo retroactivo | E1-S2/E1-S3 | `suspensiones_plazo` existe; falta controller/service |
| Registrar subsanación recibida (pausa/reanudación) | E3-S3 | Columnas `fecha_pausa/fecha_reanudacion` ya existen en `plazos` |
| Comunicación de hallazgos + descargos (pausa EJECUCION) | E5-S2 | Requiere `ACT_COMUNICACION_HALLAZGOS`/`ACT_RECEPCION_DESCARGOS` |
| Informes finales por perfil y VB Final | E5-S3/S4 | `ACT_INFORME_FINAL` ya seedeado (pivote AUD_JURIDICO hoy) |
| Derivación por incompetencia → Transparencia | E5-S5 | Requiere `ACT_DERIVACION_INCOMPETENCIA`/`ACT_REMISION_TRANSPARENCIA` |
| Transferencias inter-unidad | E8 | Tabla y modelo `Transferencia` existen; falta flujo |
| NUREJ Hijo (endpoint RN-10) | E9 | `NurejGeneratorService::generarHijo()` ya existe como método |
| Dashboard/métricas y export Excel/PDF | E10 | `resumenBandeja()` listo; requiere dependencias nuevas (aprobación) |
| Actuado de enmienda (RF-02) | E11 | `partes.vigente_hasta/es_version_actual` ya diseñado |