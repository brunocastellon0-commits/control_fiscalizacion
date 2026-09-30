# Fase 9 — Flujo Financiero (RN-09: descargos, informes y Bloqueo de Salida)

> Auditoría según Plan §3 (F9: happy path + error + no autorizado + estado
> inválido + concurrencia; descargos financieros de 5 días hábiles con
> pausa/reanudación del reloj). Fecha: 2026-09-30.
> Baseline de la fase: **277 tests — 265 OK · 1 fallo · 5 errores · 6 omitidos**
> (fallos/errores restantes del baseline: AUD-0001 y AUD-0003; AUD-0002 cerrado
> el 2026-09-30).

## §0 Alcance y método

- **Norma:** RN-09 (`SRS_EXTRAIDO.txt:159-168`), Art. 25 Ac. 55/2018
  (5 días hábiles, solo Auditor Financiero, `:161`).
- **Código auditado:**
  - `app/Services/DescargoFinancieroService.php` (344 líneas)
  - `app/Http/Controllers/DescargoFinancieroController.php`
  - `app/Http/Requests/ComunicarHallazgosRequest.php`,
    `app/Http/Requests/RecibirDescargosRequest.php`
  - `app/Http/Requests/StoreActuadoRequest.php` (Bloqueo de Salida)
  - `app/Policies/ExpedientePolicy.php:334-375`
  - Rutas: `routes/api.php:55` (actuados genérico), `:85-87` (descargos)
- **Fuera de alcance de F9:** Fase 3 del cierre (Filtro del Encargado) y
  Fase 4/5 (salidas y derivación penal) — cubiertas en `MATRIZ_BANDEJAS.md`,
  `DerivacionTransparenciaTest`; semáforo/vencimiento — `MATRIZ_PLAZOS.md` §4.

## §1 Recorrido completo del flujo (happy path)

| # | Paso | Implementación | Autorización | Plazo | Test | Veredicto |
|---|------|----------------|--------------|-------|------|-----------|
| 1 | **Comunicar hallazgos** (pausa reloj) | `POST /api/expedientes/{e}/descargos/comunicar` → `DescargoFinancieroController@comunicar:23-34` → `DescargoFinancieroService@comunicarHallazgos:72-101` (transacción, 3 reintentos `:100`) | `ComunicarHallazgosRequest:14-19` → `ExpedientePolicy@comunicarHallazgos:334-337` → `puedeTramitarDescargos:352-375`: activo + rol AUD_FINANCIERO + `EN_EJECUCION` + bandeja propia + pivote catálogo/AC055 | congela EJECUCION → `SUSPENDIDO` + `fecha_pausa` (`:243-252`); abre sub-reloj `DESCARGOS` 5 días hábiles del parámetro AC055 (`:258-275`) | `DescargoFinancieroTest:157` ✓ · `FlujoFinancieroTest:167` ✓ | ✓ |
| 2 | **Corre el sub-reloj** (5 d hábiles, omite sáb/dom/feriados) | `PlazoCalculatorService@calculateDueDate` (`DescargoFinancieroService:271`) | — | CA-1 verificado en `MATRIZ_PLAZOS.md` §1 | aserción `DescargoFinancieroTest:188` (2026-09-23 con reloj congelado) | ✓ |
| 3 | **Recibir descargos** (cierra sub-reloj, reanuda reloj) | `POST .../descargos/recibir` → `@recibir:40-51` → `recibirDescargos:118-149` | `RecibirDescargosRequest:14-19` → `ExpedientePolicy@recibirDescargos:343-346` (mismos requisitos) | sub-reloj → `CUMPLIDO` con `actuado_cierre_id` (`:140-144`); reloj → `VIGENTE` con `fecha_reanudacion` y límite recalculado = hoy + días hábiles restantes (`:282-297`) | `DescargoFinancieroTest:157` ✓ · `:348` (cancelación sin días restantes) ✓ · `FlujoFinancieroTest:185` ✓ | ✓ |
| 4 | **Fase 2: Informe Final CON o SIN responsabilidad** | `POST /api/expedientes/{e}/actuados` (genérico, `ActuadoController@store`) | `StoreActuadoRequest:14-24` → `ExpedientePolicy@crearActuado:17-33` (pivote rol+reglamento + bandeja) | **Bloqueo de Salida:** `StoreActuadoRequest@withValidator:53-65` → `DescargoFinancieroService@validarFaseDescargosPrevia:157-168` exige `ACT_RECEPCION_DESCARGOS` previo → **422** (diseño: no 403, `:46-51`); `CODIGOS_INFORMES_FINANCIEROS:32-35` cubre **ambos** códigos | CON: `DescargoFinancieroTest:218` ✓ · SIN: `FlujoFinancieroTest:220` ✓ | ✓ |
| 5 | **Estado destino del informe** | catálogo real `CatalogoActuadoSeeder:82-83` → `PENDIENTE_VISTO_BUENO_FINAL` | — | — | `DescargoFinancieroTest:249-256` ✓ · `FlujoFinancieroTest` (SIN) ✓ | ✓ |
| 6 | Fases 3-5 (Filtro Encargado, salida ordinaria, derivación penal) | `aprobarVistoBuenoFinal:276`, `ejecutarRepartoInstitucional:287` | fuera de F9 | — | `MATRIZ_BANDEJAS.md` | fuera de alcance |

**Invariantes verificados por test:** el expediente **no cambia de estado** en
paso 1 ni 3 (no-op declarado en `:63-65` y `:109-110`, aserciones en
`FlujoFinancieroTest:185`); la fase de descargos es **única por causa**
(`validarSinDescargosPrevios:227-238`, probado con doble envío).

## §2 Validación negativa (Plan F9: error / no autorizado / estado inválido / concurrencia)

| Caso | Escenario | Esperado y real | Test |
|------|-----------|-----------------|------|
| **No autorizado** | Técnico sin rol financiero; auditor sin bandeja; fuera de AC055 | **403** (policy antes que el servicio) | `DescargoFinancieroTest:259` ✓ |
| **Estado inválido** | Expediente fuera de `EN_EJECUCION` (comunicar **y** recibir) | **403** — `puedeTramitarDescargos:362-364` filtra en la policy; el 422 del servicio (`:195-204`) no llega a ejecutarse (mismo patrón que F8 jurídico) | **`FlujoFinancieroTest:147`** ✓ |
| **Error (servicio)** | Comunicar sin reloj EJECUCION vigente | **422** `errors.expediente.0 = 'No hay un reloj de EJECUCION vigente que pausar.'` (`:216-220`) | **`FlujoFinancieroTest:167`** ✓ |
| **Error (servicio)** | Recibir con sub-reloj vigente pero reloj principal **no** suspendido (carrera/manipulación) | **422** `'No hay un reloj de EJECUCION suspendido que reanudar.'` (`:328-332`); sin actuado nuevo, sub-reloj intacto, estado intacto | **`FlujoFinancieroTest:185`** ✓ |
| **Error (validación)** | Sin adjunto, descripción corta, recibir sin comunicar, doble comunicación, doble recepción | **422** con clave correspondiente | `DescargoFinancieroTest:295` ✓ |
| **Concurrencia (secuencial)** | Doble envío de comunicar / de recibir | **422** en el segundo (fase única `:227-238`; sub-reloj ya `CUMPLIDO` `:302-316`) — un solo sub-reloj, un solo reloj congelado | `DescargoFinancieroTest:325-345` ✓ |
| **Concurrencia (paralela)** | Dos `comunicar` simultáneos sobre el mismo NUREJ | **No reproducido** (ver AUD-0036): sin índice único en `plazos`, `validarSinDescargosPrevios` lee sin bloqueo dentro de la transacción | — (análisis, §4) |

## §3 Checklist RN-09 (SRS `:159-168`)

| Requisito (SRS) | Estado |
|---|---|
| `:161` Fase 1 solo Auditor Financiero; pausa reloj + **5 días hábiles**; Técnicos/Jurídicos omiten | ✓ — política de rol (`:358-360`, probada con 403 a Técnico); parámetro `DESCARGOS=5` (`ParametroPlazoSeeder`, semilla de test `:87-94`); cálculo probado (`DescargoFinancieroTest:188`) |
| `:161` Respaldo Art. 25 Ac. 55/2018 — aplica **solo AC055** | ✓ — `validarReglamento:183-190` + pivote AC055; 403 fuera de AC055 (`DescargoFinancieroTest:284-292`) |
| `:162` Informe Final **Con o Sin Responsabilidad** | ✓ — ambos códigos en `CODIGOS_INFORMES_FINANCIEROS:32-35`; Bloqueo de Salida probado para ambos (`DescargoFinancieroTest:218`, `FlujoFinancieroTest:220`) |
| `:163` Fase 3 Filtro del Encargado (Devolución / Visto Bueno) | fuera de F9 — `MATRIZ_BANDEJAS.md` |
| `:164-167` Fase 4 salidas ordinarias por perfil | fuera de F9 — `MATRIZ_BANDEJAS.md` |
| `:168` Fase 5 derivación por incompetencia → Transparencia | fuera de F9 — `DerivacionTransparenciaTest` |

## §4 Hallazgos, observaciones y análisis de esta fase

### AUD-0034 — Análisis de impacto COMPLETADO (decisión del usuario: solo analizar, no corregir)

- **Qué se analizó:** `detalle.blade.php:297` llama `GET /api/catalogo/actuados`
  **sin `expediente_id`** → no se aplica el filtro de reglamento
  (`CatalogoActuadoController:38-44`); `IndexCatalogoActuadosRequest:17-22` no
  valida ese parámetro aunque el controlador lo lee (`(int)` + `Expediente::find`).
- **Impacto de seguridad: NINGUNO verificable.** La respuesta siempre queda
  filtrada por el rol del usuario (`:32-54`) y exige el gate
  `verCatalogoActuados` (`:24`); no se expone datos de expedientes ni de otros
  roles, no hay inyección (cast a entero + bindings de Eloquent) y el peor caso
  de un `expediente_id` arbitrario es que el filtro de reglamento se amplíe o
  se omite (catálogo de referencia del propio rol).
- **Impacto funcional: real y acotado.** El modal ofrece actuados de otros
  reglamentos del mismo rol (p. ej. AC054 y AC055); al emitirlos el usuario
  recibe **403** en `perteneceAlRolConReglamento` (`CatalogoActuado:56-72`,
  vía `crearActuado:23-32`) — incumpliendo la promesa del docblock
  (`CatalogoActuadoController:17-19`, "evitar ofrecer acciones que
  provocarían un 403").
- **Conclusión:** P2 (UX/consistencia) **sin efecto en confidencialidad ni
  integridad** → confirmado como problema **funcional, no de seguridad**.
  **Decisión del usuario (2026-09-30): fix aprobado como solución a
  implementar posteriormente, NO autorizado a modificar producto durante la
  auditoría** — corregir el origen del catálogo (actuados según el
  expediente/reglamento actual) y mantener la validación en servidor
  (`exists:expedientes,id` en `IndexCatalogoActuadosRequest`).

### O-8 → **AUD-0036** (ficha formal, convertida en observación por decisión del usuario 2026-09-30)

- `plazos` **no tiene índice único** sobre `(expediente_id, tipo_plazo)`
  (`database/migrations/2026_08_25_191200_create_plazos_table.php:17,22`).
- `validarSinDescargosPrevios` (`:227-238`) hace un `SELECT` sin bloqueo
  **dentro** de la transacción; dos `POST /descargos/comunicar` simultáneos
  podrían leer "no existe" ambos y crear **dos** sub-relojes `DESCARGOS`
  (el segundo `congelarRelojEjecucion:243-252` no falla: actualiza 0 filas).
- **No reproducido** (requiere peticiones verdaderamente paralelas; el stress
  de concurrencia real está gated con `RUN_STRESS_TESTS=1` y solo cubre
  NUREJ/custodia/sorteo — `StressConcurrenciaTest:22-27`). Ficha **AUD-0036**
  creada como **observación P3**: si se replica, elevar severidad (índice
  único + `lockForUpdate` como fix candidato).
- La defensa actual de **doble envío secuencial** sí está probada
  (`DescargoFinancieroTest:325-345`).

### O-9 → **AUD-0037** (ficha formal, convertida en observación por decisión del usuario 2026-09-30)

- `catalogoPorCodigo(...)->firstOrFail()` (`:87`, `:131`, `:342`) y
  `ParametroPlazo...firstOrFail()` (`:263`) devuelven **404** si la fila de
  catálogo/parámetro falta en la BD (reproducido durante la escritura de
  `FlujoFinancieroTest` al omitir `ACT_RECEPCION_DESCARGOS` en la semilla).
  En producción el seeder crea esas filas, pero una BD desalineada
  (relacionado con AUD-0023) se manifiesta como 404 confuso en vez de un
  error explícito de configuración. Riesgo P3.

## §5 Cobertura de tests del flujo

| Test | Cubre | Estado |
|---|---|---|
| `DescargoFinancieroTest:157` | Happy path completo: pausa + sub-reloj 5 d + cierre + reanudación con recálculo (reloj congelado 2026-09-15, fix AUD-0002) | ✓ |
| `DescargoFinancieroTest:218` | Bloqueo de Salida (informe CON) y emisión tras descargos → `PENDIENTE_VISTO_BUENO_FINAL` | ✓ |
| `DescargoFinancieroTest:259` | 403: sin rol financiero / sin bandeja / fuera de AC055 | ✓ |
| `DescargoFinancieroTest:295` | 422: adjunto, descripción, recibir sin comunicar, doble comunicación, doble recepción | ✓ |
| `DescargoFinancieroTest:348` | Reanudación sin días hábiles restantes (límite = hoy) | ✓ |
| **`FlujoFinancieroTest:147`** (nuevo) | **403 por estado inválido en comunicar y recibir** | ✓ |
| **`FlujoFinancieroTest:167`** (nuevo) | **422 del servicio: sin reloj EJECUCION vigente** | ✓ |
| **`FlujoFinancieroTest:185`** (nuevo) | **422: reloj no suspendido al recibir, sin efectos secundarios** | ✓ |
| **`FlujoFinancieroTest:220`** (nuevo) | **Bloqueo de Salida para el informe SIN responsabilidad** | ✓ |

**Gaps restantes:** concurrencia paralela (AUD-0036, no reproducida); comportamiento
del sub-reloj **ya vencido** al momento de recibir (hoy el servicio solo exige
`VIGENTE`, `:302-316`, sin comparar `fecha_limite` — coherente con CA-3, pero
sin test propio; verificado por lectura de código, no por ejecución).

## §6 Decisiones del usuario (Fase 9) — resueltas 2026-09-30

1. **AUD-0034:** **fix aprobado como solución a implementar posteriormente,
   NO autorizado a modificar código de producto durante la auditoría.**
   Hallazgo confirmado como problema **funcional, no de seguridad**. La
   solución corregirá el **origen del catálogo** (actuados ofrecidos según el
    expediente/reglamento actual) **y mantendrá la validación en servidor**.
    Ficha OPEN hasta la implementación posterior. **(Reiterado 2026-09-30:
    NO aplicar mientras se cruce con AUD-0033/AUD-0020/AUD-0021 — resolver
    antes estados/catálogo.)**
2. **O-8 → AUD-0036 (ficha formal):** convertida en observación P3; **no
   elevar severidad sin evidencia experimental** (aún no reproducida).
3. **O-9 → AUD-0037 (ficha formal):** observación P3; documentado el
   comportamiento `firstOrFail → 404` y analizados sus flujos (§4);
   **no clasificar como vulnerabilidad sin evidencia adicional**.
4. (Acumuladas → **RESUELTAS 2026-09-30** en BACKLOG/PROGRESO: AUD-0001 →
   deuda conocida aceptada (cierre F12); 0019/0020/0021/0023/0024/0025/
   0027/0028/0029/0033/0035 decididos; 0030/0031/0032 incorporados a la
   lista de decisiones pendientes (cierre F12, sin aplicar); O-4
   resuelta sin elevar; O-6 cerrada; O-7 sub-caso de AUD-0023.)
