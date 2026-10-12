# DIVERGENCIAS — Plan de corrección (B2.2, divergencias 1–3)

**Estado:** PLAN — pendiente de aprobación de las 3 decisiones del §7 antes de ejecutar.
**Contexto:** B2.2 cerrada y validada (402 tests · 395 OK · 0 fallos · 7 omitidos · 2193
aserciones). CTR-10 reemplazó el flujo B1.3. Sin Git, sin tocar BD de desarrollo, sin
`db:seed`/`migrate`, sin implementar B2.3/B2.6/B4.2.

## Contexto verificado en código

- `TransparenciaService::solicitarIncompetencia()` (app/Services/TransparenciaService.php:45)
  hoy **no** congela relojes; metadatos solo `{tipo, unidad_destino_id}`.
- `vistoBuenoIncompetencia()` aprobado → `remitirPorIncompetencia()` que **congela al momento
  del VB** (TransferenciaUnidadService.php:204) y guarda IDs en el actuado del VB; rechazado →
  restaura X sin tocar relojes (líneas 166-179).
- Reutilizables ya existentes en `TransferenciaUnidadService`: **`congelarPlazos()`** (:319,
  devuelve IDs y pone SUSPENDIDO + `fecha_pausa`) y **`reanudarPlazos()`** (:364, conserva
  `fecha_pausa`, recalcula `fecha_limite` con `businessDaysBetween`/`calculateDueDate`
  preservando días hábiles). `registrarRecepcion()` (CTR-05) lee los IDs desde
  `contenido.plazos_suspendidos` del actuado de la remisión vía `plazosSuspendidosDe()` (:345)
  — **no necesita cambios** si el actuado del VB hereda los IDs de la solicitud.
- Endpoint genérico: `StoreActuadoRequest` identifica el tipo por **`catalogo_actuado_id`**
  (input) y ya tiene patrón de 422 de negocio en `withValidator()` (:53, estilo Bloqueo de
  Salida); `authorize()` usa `ExpedientePolicy::crearActuado` (:18) = activo + pivote/`rol_id`
  legacy + (ENCARGADA o asignación propia). Los 5 tipos son alcanzables hoy (403 solo para
  actores sin pivote) — el bloqueo correcto va en **validación → 422**, no en la policy.
- `perteneceAlRolConReglamento` (CatalogoActuado.php:56) cae a `rol_id` legacy sin pivotes →
  los tests pueden crear los 5 catálogos manualmente.
- Ningún test existente emite los 5 tipos por el genérico (grep limpio) → bloquearlos no rompe
  la regresión actual.

---

## 1. Archivos de producción a modificar

| # | Archivo | Cambio |
|---|---|---|
| 1 | `app/Services/TransferenciaUnidadService.php` | (a) `congelarPlazos()` y `reanudarPlazos()`: `protected` → **`public`** (misma firma, sin lógica nueva; reutilizar sin duplicar). (b) `remitirPorIncompetencia()`: **param aditivo** `array $plazosSuspendidosPrevios = []`; dentro de la transacción: `$plazosSuspendidos = array_values(array_unique(array_merge($plazosSuspendidosPrevios, $this->congelarPlazos($expediente))))` — conserva los IDs congelados en la solicitud y suma cualquier plazo VIGENTE nuevo; se guarda igual en `contenido.plazos_suspendidos` del actuado del VB → **CTR-05 queda intacto**. `remitir()`/`registrarRecepcion()` **no se tocan**. |
| 2 | `app/Services/TransparenciaService.php` | (a) `solicitarIncompetencia()`: tras todas las validaciones y antes de `registerActuado`: `$plazos = $this->transferenciaUnidadService->congelarPlazos($expediente);` y añadir `'plazos_suspendidos' => $plazos` a los metadatos del actuado de solicitud. (b) rama aprobada de `vistoBuenoIncompetencia()`: leer `$plazos = array_map('intval', (array)($solicitud->contenido['plazos_suspendidos'] ?? []))` y pasarlos como `plazosSuspendidosPrevios:`. (c) rama rechazada: después del `registerActuado` (restaura X), `$this->transferenciaUnidadService->reanudarPlazos($expediente, $plazos)` — reanuda **exactamente** esos IDs. (d) actualizar docblocks (:32-44, :91-106) que hoy dicen "no congela relojes". `?? []` defensivo para solicitudes antiguas sin la clave (no hay datos prod). |
| 3 | `app/Http/Requests/StoreActuadoRequest.php` | const `TIPOS_FLUJO_ESPECIALIZADO` con las 5 constantes existentes (sin duplicar strings): `EnmiendaService::CODIGO_ACTUADO`, `TransferenciaUnidadService::CODIGO_ACT_REMISION_UNIDAD`/`CODIGO_ACT_RECEPCION_UNIDAD`, `TransparenciaService::CODIGO_ACT_SOLICITUD`/`CODIGO_ACT_VB`. En el `after()` de `withValidator()`: si `$catalogo->codigo` ∈ lista → `$validator->errors()->add('catalogo_actuado_id', 'Este tipo de actuado debe emitirse mediante su endpoint especializado.')`. **No** se toca `authorize()` (403 → 422 controlado) ni `rules()` base; el hook del Bloqueo de Salida existente sigue funcionando (códigos disjuntos). |
| 4 | *(Decisión 2, opcional)* `app/Http/Controllers/CatalogoActuadoController.php` | Excluir los 5 códigos del `GET /api/catalogo/actuados` (`whereNotIn('codigo', ...)` coherente con el bloqueo). Verificado: `CatalogoActuadoControllerTest` y CTR-03 usan `toContain`/`not->toContain` (sin conteos exactos) → **no se romperían**, pero es cambio de contrato de CTR-03 → requiere aprobación. |

**Sin tocar:** `ExpedientePolicy`, `ActuadoController`, `EnmiendaRequest`,
`RemitirUnidadRequest`/`RegistrarRecepcionRequest`, `registrarRecepcion()`, `remitir()`,
seeder, rutas, documentación de auditoría, BD.

## 2. Archivos de tests

**Modificar `tests/Feature/IncompetenciaInterUnidadTest.php`** (expectativas más fuertes, no
más débiles):

1. *Solicitar feliz*: plazo pasa a **SUSPENDIDO + `fecha_pausa`** (antes: VIGENTE sin pausa);
   actuado solicitud `contenido.plazos_suspendidos = [id]`; bandeja/asignación/sin-transferencia
   se mantienen.
2. *VB aprobado*: plazo ya SUSPENDIDO **antes** del VB (nueva aserción post-solicitar); tras
   aprobar, actuado del VB y transferencia conservan **los mismos IDs** (propagados, no
   re-congelados); resto igual.
3. *Round-trip CTR-05*: sin cambios de fondo (la matemática de `fecha_limite` ya lee
   `fecha_pausa` de la fila); se añade aserción de `plazos_reanudados` = [id] en el actuado de
   recepción si no existe ya.
4. *VB rechazado*: tras solicitar SUSPENDIDO; tras rechazo → **VIGENTE + `fecha_reanudacion` +
   `fecha_pausa` histórica conservada + `fecha_limite` recalculada exacta** (mismo patrón que
   CTR-05 con `PlazoCalculatorService`); 0 transferencias; re-solicitar → re-congela (nueva
   `fecha_pausa`).

**Modificar `tests/Feature/ActuadoControllerTest.php`** (divergencia 2):

- Helper nuevo `paso7SemillaTiposEspecializados()` que crea los 5 catálogos con `rol_id` legacy
  (ENCARGADA: REMISION/RECEPCION/VB; TECNICO con asignación: ENMIENDA/SOLICITUD).
- Test con **dataset de 5 filas** (código + actor): `POST /api/expedientes/{e}/actuados` →
  **422** `assertJsonValidationErrors('catalogo_actuado_id')` + que **no** se creó el actuado
  (`Actuado::count()`).
- Control positivo: el test existente `ACT_OBSERVACION → 201` (:47) demuestra que un tipo
  genérico válido sigue funcionando (sin test nuevo adicional).

**No crear archivos de test nuevos.**

## 3. Análisis divergencia 3 (sin cambio semántico)

Confirmado por lectura + tests verdes de B2.2:

1. **Estado original**: `estado_anterior_id` del actuado de solicitud = X antes de transicionar
   (test 1 ya lo aserta) ✓.
2. **Rechazo restaura X exacto**: `estadoNuevoIdExplicito: $estadoOriginalId` leído de la
   solicitud (TransparenciaService.php:127/178) ✓.
3. **Sin transferencia** en rechazo ✓ (test 4: count 0).
4. **Sin congelación en rechazo**: la rama no llama `congelarPlazos` — se mantiene ✓.
5. **Reanudación de los plazos suspendidos al solicitar**: **no existe hoy** porque B2.2 no
   congela al solicitar; queda cubierto por el cambio de la divergencia 1 (rechazo →
   `reanudarPlazos` con los IDs de la solicitud). El diseño actual **sí lo permite**: el rechazo
   es aditivo (`registerActuado` + `reanudarPlazos` en la misma transacción, patrón ya probado
   en CTR-05) — **no hay conflicto estructural**.
6. **Asignación intacta** ✓ (ningún camino toca `asignaciones`).

## 4. Riesgos / regresiones posibles

- **CTR-04 directo durante la espera de VB** (interacción **nueva** que introduce la
  congelación anticipada): la Encargada puede llamar `POST /remitir-unidad` con causa en
  EN_EVALUACION (policy lo permite) → `remitir()` congelaría `[]` (ya suspendidos) →
  transferencia sin IDs → tras CTR-05 los plazos quedarían **SUSPENDIDO para siempre**.
  → Decisión 1.
- Otras mutaciones del expediente durante la espera VB (p. ej. el operador ejecuta evaluación
  de admisibilidad y cambia el estado) son **preexistentes** a B2.2 y no se cubren aquí;
  documentarlas como riesgo conocido.
- Dashboards/semáforo verán relojes SUSPENDIDO con causa EN_EVALUACION (efecto deseado del
  requisito, pero cambio visible).
- `congelarPlazos`/`reanudarPlazos` a `public`: cambio de API interna sin comportamiento nuevo;
  CTR-04/05 verificados por `TransferenciaUnidadesTest`.
- Firma de `remitirPorIncompetencia` aditiva con default `[]` → no rompe llamadas existentes.
- Ciclo repetido solicitar→rechazar: `congelarPlazos` sobrescribe `fecha_pausa` y
  `reanudarPlazos` pone `fecha_reanudacion` — mismo ciclo ya probado CTR-04/05 ✓.
- `FormRequestsTest` (describe StoreActuadoRequest): hook aditivo con códigos disjuntos → sin
  impacto previsto (igual se incluye en regresión).

## 5. Orden exacto de implementación

1. `TransferenciaUnidadService`: visibilidad pública de `congelarPlazos`/`reanudarPlazos` +
   parámetro `plazosSuspendidosPrevios` en `remitirPorIncompetencia`.
2. `TransparenciaService`: congelar en `solicitar` (metadatos), propagar IDs en aprobación,
   `reanudarPlazos` en rechazo, docblocks.
3. Actualizar `IncompetenciaInterUnidadTest` (tests 1, 2, 3, 4) y correrlo → verde.
4. `StoreActuadoRequest`: lista + bloqueo 422.
5. `ActuadoControllerTest`: helper + dataset de 5 → correrlo → verde.
6. *(Solo si se aprueba)* excluir los 5 del listado en `CatalogoActuadoController`.
7. Gates completos.

## 6. Gates de validación

1. Tests nuevos/modificados: `IncompetenciaInterUnidadTest` + `ActuadoControllerTest` en verde.
2. Regresión dirigida: los anteriores + `TransferenciaUnidadesTest`, `CierrePlazosFasesTest`,
   `ContratosAdmisibilidadTest` (ejecuta seeder), `FormRequestsTest`, `ActuadoEnmiendaTest`
   (CTR-09 intacto), `CatalogoActuadoControllerTest`, `ExpedienteControllerTest`
   (quirk `paso7...`).
3. `vendor/bin/pint --dirty --format agent`.
4. Suite completa: baseline **402 · 395 OK · 0 fallos · 7 omitidos · 2193 aserciones**
   (esperado: +5 tests por el dataset, resto sin cambios; 0 fallos).

## 7. Decisiones pendientes de aprobación

1. **CTR-04 durante espera de VB**: ¿**bloquear** `POST /remitir-unidad` con 422 mientras
   exista solicitud de incompetencia abierta (recomendado — protege la integridad de los IDs
   congelados), o dejarlo **documentado** como divergencia conocida?
2. **Listado `GET /api/catalogo/actuados`**: ¿excluir también los 5 tipos bloqueados
   (coherencia oferta/demanda; CTR-03 no se rompe, verificado) o solo bloquear la emisión POST?
3. Confirmación menor: enfoque de reutilización = **hacer públicos** `congelarPlazos`/
   `reanudarPlazos` en `TransferenciaUnidadService` (alternativa: wrappers públicos con otro
   nombre — más código, misma función).
