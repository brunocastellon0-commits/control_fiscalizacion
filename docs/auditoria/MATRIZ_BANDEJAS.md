# MATRIZ_BANDEJAS — Bandejas y roles (Fase 6)

> Auditoría según Plan Maestro §16 (AUDITORÍA DE BANDEJAS), §7 (roles),
> §8/§8.1 (Encargada) y SRS RF-03 (`SRS_EXTRAIDO.txt:108`). Evidencia verificada
> el 2026-09-29 contra código, rutas y BD (query directa). Toda afirmación lleva
> `archivo:línea`.

---

## §1 Inventario de roles (BD verificada)

| id | código | nombre | usuarios |
|---|---|---|---|
| 1 | ENCARGADA | Encargada | 3 |
| 2 | TECNICO | Técnico | 3 |
| 3 | AUD_JURIDICO | Auditor Jurídico | 3 |
| 4 | AUD_FINANCIERO | Auditor Financiero | 3 |
| 5 | ADMIN | Administrador | 3 |

5 roles (coinciden con Plan §7), 15 usuarios, **5 asignaciones activas** al
2026-09-29. No hay roles extraños ni duplicados.

## §2 Mecánica de las bandejas

- **Bandeja = `asignaciones` con `activa = true`.** El re-enrutamiento lo hace
  `ActuadoService@reasignarBandeja` (`:140-156`): cierra la asignación activa
  previa y crea una para el usuario destino (tomando su rol), vinculada al
  actuado disparador. **Toda acción que mueve bandeja emite un actuado** (§16:
  "¿Qué actuado genera cada acción?" ✅).
- **Registro:** sin destino (`ExpedienteService:65` `usuarioDestinoId: null`) →
  sin asignación activa; el creador conserva lectura hasta el sorteo
  (`ExpedientePolicy::view:83-86`), después pierde acceso (RF-03) ✅.
- **Visibilidad (RF-03):** `ExpedientePolicy::view:66-87` — ENCARGADA ve todo;
  operador solo con asignación activa; ADMIN todo (⚠️ AUD-0001); creador
  pre-sorteo. Evaluación exige asignación **sin excepción jerárquica**
  (`evaluarAdmisibilidad:39-46`) ✅.
- **`crearActuado`:** rol del catálogo + reglamento del expediente; la ENCARGADA
  está exenta de asignación (control jerárquico, RN-07), los demás requieren
  asignación activa (`:17-33`) ✅.
- **Destinos hardcodeados por flujo** (grep `usuarioDestinoId:`): planificación→encargada
  (`PlanificacionService:84`), VB/devolución→operador original (`:118`, `:149`),
  ampliación→encargada/operador (`AmpliacionService:66`/`:118`), impugnación→encargada/operador
  (`ImpugnacionService:70`/`:147`), cierre→encargada / null al aprobar
  (`CierreExpedienteService:63`/`:97`), transparencia→encargada / null al derivar
  (`TransparenciaService:60`/`:95`), sorteo→ganador (`ExpedienteService:130`),
  descargos→sin cambio (bandeja permanece en el auditor). **Excepción: el
  endpoint genérico acepta `usuario_destino_id` del cliente (AUD-0027, §6).**

## §3 Matriz de bandejas (Plan §16)

| Bandeja | Entrada (por qué) | Rol | Acción (endpoint) | Actuado | Salida | Plazo |
|---|---|---|---|---|---|---|
| — (ventanilla) | Alta de causa (`POST /api/expedientes`) | TECNICO (creador, solo lectura pre-sorteo) | ver detalle | — | PENDIENTE_SORTEO | ninguno |
| **Sorteo** | Estado PENDIENTE_SORTEO | ENCARGADA | `POST /bandeja/sorteo`, `.../sortear`, `.../sortear/todos` | ACT_SORTEO_INICIAL | bandeja del ganador (asignación activa) | **abre EVALUACION** (2/5/5 d) |
| **Operador** (TECNICO / AUD_JUR / AUD_FIN) | sorteo ganador; VB planificación (`PlanificacionService:118`); devolución (`:149`); resolución que revoca (`ImpugnacionService:147`); ampliación aprobada (`AmpliacionService:118`); permanencia durante descargos AC055 | operativo con asignación activa | `GET /bandeja`, `evaluacion`, `planificacion`, `ampliacion`, `actuados` (genérico), `descargos/*` (AC055) — 403 verificados en Fase 3 | los de **su** rol + reglamento | ENCARGADA (planificación `:84`, solicitud ampliación `:66`, remisión impugnación `:70`, solicitud cierre `:63`, solicitud transparencia `:60`) | **abre** PLANIFICACION (2), EJECUCION (10/MPA), DESCARGOS (5, congela EJECUCION) |
| **Jerárquica** (ENCARGADA) | actuados con destino = encargada (5 servicios de §2) | ENCARGADA | VB/devolver planificación, aprobar ampliación, resolver impugnación, aprobar cierre, ejecutar reparto, derivar/transparencia, sortear (políticas por estado; exenta de asignación) | VB_PLANIFICACION, APROBAR_AMPLIACION, RESOLUCION_*, CIERRE_*, REPARTO, TRANSFERENCIA… | operador original, o **null** (cierre `:97`, derivación `:95` = sin bandeja) | VB **abre EJECUCION**; reabre PLANIFICACION en devolución/revocación |
| **Descargos** (AC055) | comunicar hallazgos (no cambia de bandeja) | AUD_FINANCIERO | `descargos/comunicar`, `descargos/recibir` | ACT_COMUNICACION_HALLAZGOS / ACT_RECEPCION_DESCARGOS | permanece en el auditor | congela EJECUCION → **abre DESCARGOS (5)**; recibido: cierra DESCARGOS y **reanuda EJECUCION** |
| **Final** | cierre aprobado / archivo por abandono / derivación | — | ninguno (solo lectura) | CIERRE/ARCHIVO/TRANSFERENCIA | sin asignación (cierra activa: `CierreExpedienteService:142`, `ArchivoPorAbandonoService:107`, `TransparenciaService:155`) | cierra o congela relojes |
| **Admin** | — | ADMIN | monitoreo, dashboards, usuarios, feriados | — | no participa en flujos | — |

**¿Qué acciones NO puede ejecutar?** → cubierto en `MATRIZ_SEGURIDAD.md`
(Fase 3): 44 endpoints con 403/401 testeado, 1 FALLA (AUD-0001), 1 FILTRADO,
6 solo-código, 2 N/A. **Plazos de la columna última** → `MATRIZ_PLAZOS.md` §3.

## §4 Superficies de bandeja (rutas reales)

| Superficie | Rol exigido | Evidencia |
|---|---|---|
| `GET /api/bandeja` | **sin `authorize`**: filtra por asignación propia, `paginate(15)` | `ExpedienteController:70-78` |
| `GET /api/bandeja/sorteo` | ENCARGADA (`bandejaSorteo`) + estado PENDIENTE_SORTEO | `:53-65` |
| `GET /expedientes` (web) | **TECNICO/AUD_*** vía `operadorBandeja` → ENCARGADA/ADMIN reciben 403 | `WorkstationController:15`, `ExpedientePolicy:53-64` |
| `GET /bandeja/sorteo` (web) | ENCARGADA | `WorkstationController:25` |
| `GET /api/encargada/dashboard` | ENCARGADA (reutiliza `bandejaSorteo`) | `EncargadaDashboardController:19` |
| `GET /api/admin/monitoreo` | **solo ADMIN** (lista con filtros/búsqueda) | `AdminMonitoreoController:26-32` |
| Detalle `show`/`detalle` | `view` (ENCARGADA/ADMIN/asignado/creador pre-sorteo) | `ExpedientePolicy:66-87`, `WorkstationController:35` |

- **O-4 (observación):** la misma "bandeja del operador" exige `operadorBandeja`
  en web pero no en API: un rol no operativo recibe 403 en web y lista vacía
  (o su propia asignación) en API. Sin brecha de lectura ajena (el filtro es por
  usuario), pero autorización divergente entre superficies.
- La inactivación **sí** revoca tokens y sesiones
  (`SeguridadSesionesService:50-54`, transaccional, con auditoría) → no hay
  acceso persistente de usuarios dados de baja ✅ (login también bloquea:
  `AuthController:55-64`).

## §5 Bandeja de la Encargada vs Plan §8/§8.1 (módulo crítico)

Plan §7: "Este es un módulo crítico". §8.1 exige **listados de acción**, no
agregados. Estado real:

| Requisito §8.1 | ¿Existe? | Evidencia |
|---|---|---|
| Solicitudes pendientes / recién registradas | ⚠️ solo `expedientes.ultimos` (5) y `por_estado` (contadores) | `EncargadaDashboardService:50-86,174-182` |
| Pendientes de sorteo | ✅ contador | `:22-26` |
| **Cronogramas pendientes de VB** | ❌ sin endpoint/listado | solo agregado `por_estado` |
| **MPA pendientes** | ❌ | idem |
| **Informes finales pendientes** | ❌ | idem |
| **Devoluciones / solicitudes de revisión / derivaciones** | ❌ | idem |
| Situaciones fuera de plazo | ✅ `vencimientos.fuera_de_plazo` (máx. 6) + semáforo | `:187-197` |
| Carga por operador / feriados | ✅ | `:143-172` |
| Por registro: denunciante/denunciado, fecha límite, días restantes, **último actuado, acción pendiente** | ❌ parcial (NUREJ/vía/estado/fecha/asignado en `ultimos`; fecha límite/días solo en `vencimientos`; partes existen en BD `partes` pero no se listan) | `:73-86`, `:125-137` |
| Supervisión listable (buscar/filtrar) | ❌ **`/api/admin/monitoreo` es solo-ADMIN** | `AdminMonitoreoController:29` |

Los expedientes "aparqueados" en la asignación de la Encargada (5 servicios de
§2 sí la reasignan a ella) **sí aparecen** en `GET /api/bandeja` (filtro por
usuario), pero esa ruta no está pensada/visible para ella en la UI y la web
`/expedientes` le devuelve 403 (`operadorBandeja`).

→ **AUD-0028 (§6):** no existe una bandeja de supervisión/acción para la
Encargada; solo agregados. Módulo crítico del §8.

## §6 Hallazgos de esta fase

### AUD-0027 — `usuario_destino_id` controlado por el cliente (enrutamiento)
- **P2** (integridad del enrutamiento de bandejas; RF-03 "asignación formal").
- `StoreActuadoRequest:36` valida solo `exists:usuarios,id` — **sin `activo`,
  sin rol/destino institucional**; `ActuadoController:30-31` lo pasa al servicio
  y `reasignarBandeja:140-156` cierra la asignación vigente y crea la nueva.
- Cualquier operador con asignación (o la ENCARGADA) puede, vía
  `POST /api/expedientes/{e}/actuados`, mover el expediente a **cualquier
  usuario** (incluido uno inactivo → bandeja huérfana, o un rol que no puede
  tramitarlo → bloqueo), rompiendo que "¿A dónde va después?" lo determine el
  actuado (Plan §16).
- Acción propuesta: whitelist de destino por código de actuado (o eliminar el
  parámetro del endpoint genérico y dejar solo los servicios específicos) +
  exigir `activo`. **Requiere decisión del usuario.**

### AUD-0028 — Sin bandeja de supervisión/acción para la Encargada
- **P1** (módulo crítico Plan §7/§8.1; RF-03/RN-07: control jerárquico).
- Evidencia en §5: no hay endpoints de listados de VB cronogramas, MPA,
  informes, devoluciones, revisiones ni derivaciones; monitoreo es solo-ADMIN
  (`AdminMonitoreoController:29`); `/api/encargada/dashboard` devuelve solo
  agregados (`EncargadaDashboardService:174-199`); la web de bandeja le da 403.
- Impacto: la Encargada no puede "identificar qué tiene que hacer ahora"
  (§8.1) sin conocer el NUREJ de antemano; el control jerárquico depende de
  fuera del sistema.
- Acción propuesta: definir con el usuario el alcance (bandeja de pendientes
  por categoría + campos §8.1, o reutilizar monitoreo con rol ENCARGADA).

### AUD-0029 — Inactivar usuario no redistribuye sus bandejas activas
- **P2** (operación administrativa; recuperable con esfuerzo).
- `SeguridadSesionesService@expulsar:50-54` desactiva, revoca tokens y sesiones
  (correcto) pero **no toca `asignaciones`**: los expedientes del usuario
  quedan con asignación activa a un inaccesible (login bloqueado,
  `AuthController:55`); no aparecen en `sin_asignar` (`EncargadaDashboardService:178`
  consulta `asignacionActiva` existente, no su actividad real) ni en bandeja de
  nadie.
- Acción propuesta: al inactivar, reasignar/regresar a pendiente los expedientes
  activos del usuario (transaccional) o marcarlos como "sin asignar". Requiere
  decisión del usuario (modifica flujo de expulsión).

## §7 Cobertura de tests relacionada

- RF-03/aislamiento: `SeguridadIdorTest` (16 tests, Fase 3), `ExpedienteControllerTest:209-242`,
  `WebWorkstationRoutesTest:23-53` (403 web por rol).
- Re-enrutamiento por actuado: `PlanificacionTest:244-497`, `AmpliacionTest:140-254`,
  `ImpugnacionRechazoTest:243`, `CierreExpedienteTest:102-174`,
  `DerivacionTransparenciaTest:128-217`, `DescargoFinancieroTest:252-273`,
  `SorteoTodosTest:74-132`, `EvaluacionAdmisibilidadTest` (múltiples).
- Sin tests para: AUD-0027 (destino arbitrario), AUD-0028 (listados
  pendientes), AUD-0029 (bandeja tras inactivación).

## §8 Decisiones del usuario — RESUELTAS (2026-09-30)

1. **AUD-0027:** decidido — validar destino (activo + rol compatible +
   destino institucional) en servidor; antes matriz `actuado → roles/destinos
   permitidos`. Fix no aplicado.
2. **AUD-0028:** decidido — el dashboard agregado NO sustituye la
   supervisión operativa; diseñar bandeja desde casos de uso; antes cerrar
   diseño de permisos/bandeja. Fix no aplicado.
3. **AUD-0029:** decidido — reasignación explícita con actuado y
   trazabilidad (sin reasignación silenciosa); antes definir flujo
   institucional. Fix no aplicado.
4. **O-4:** resuelta como **observación, NO elevada** — verificado contra
   RF-03: `ExpedienteController@bandejaOperador:72-76` filtra por
   `asignacionActiva.usuario_id = $request->user()->id` y `show:85` exige
   `authorize('view')` → sin lectura/operación de expedientes ajenos. La
   divergencia API (`GET /api/bandeja` sin chequeo de rol operativo) vs web
   (`operadorBandeja`) queda documentada; la unificación es mejora opcional
   futura, no hallazgo.
