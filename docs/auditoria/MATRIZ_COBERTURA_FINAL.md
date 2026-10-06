# MATRIZ FINAL DE COBERTURA — AUDITORÍA FINAL (Fase 18)

**Archivo:** `docs/auditoria/MATRIZ_COBERTURA_FINAL.md`
**Fase:** 18 · **Fecha:** 2026-10-02 · **Estado:** ENTREGADA — PENDIENTE DE VALIDACIÓN
**Fuente normativa:** Plan Maestro §77 (auditoría final), §78 (formato de matriz), §79 (criterios de producción), §116 (release checklist), §117 (definición del éxito).
**Método:** consolidación documental de las fases F0–F17 (no se repiten sus auditorías), más una **segunda ronda de re-verificación** de los 39 hallazgos P1/P2 abiertos, reutilizando la evidencia ya citada en `BACKLOG_AUDITORIA.md` y confirmando su vigencia con greps/consultas puntuales **el 2026-10-02**. Sin implementación de cambios: este documento no modifica código, configuración, esquema ni tests.
**Fuentes:** `MATRIZ_SRS_IMPLEMENTACION.md`, `BACKLOG_AUDITORIA.md`, `MATRIZ_PRUEBAS.md`, `MATRIZ_SEGURIDAD.md`, `MATRIZ_REPORTES.md`, `MATRIZ_RENDIMIENTO.md`, `MATRIZ_JOBS_CRON.md`, `MATRIZ_UX_FRONTEND.md`, `MATRIZ_HARDENING.md`, `MAPA_FUNCIONAL.md`, `FLUJO_FINANCIERO.md`, `MATRIZ_PLAZOS.md`, suite de Pest (corrida 2026-10-02).

**Leyenda de estado:** `CUMPLE` · `PARCIAL` · `NO CUMPLE` · `NO VERIFICABLE` (evidencia no disponible en este entorno: infraestructura/backups/secrets de producción) · en §1 se mantiene la leyenda del SRS (`IMPLEMENTADA`/`PARCIAL`/`FALTANTE`/`INCONSISTENTE`/`DIFERENCIA TECNOLÓGICA`).

---

## 1. Matriz por requisito (formato §78)

Requisitos SRS: 19 (RF-01…RF-06, RNF-01…RNF-04, RF-R01…RF-R09) + diferencias tecnológicas DT-01/DT-02.

| Requisito | Implementado | Test | Seguridad | Evidencia | Estado |
| --------- | ------------ | ---- | --------- | --------- | ------ |
| **RF-01** NUREJ Padre/Hijo correlativo, no editable | Sí: `NurejGeneratorService` (secuencia por tipo), FK `nurej_padre_id`, `POST .../nurej-hijo`; sin endpoint de edición de NUREJ | `NurejGeneratorServiceTest` ✅, `NurejHijoTest` ✅ | Sin PUT/PATCH de `expedientes` (route list) | `MATRIZ_SRS:26`; suite 2026-10-02 | IMPLEMENTADA |
| **RF-02** Actuados append-only; corrección por Enmienda | Parcial: append-only con triggers + cadena de hash ✅; **tipo "Enmienda" inexistente** ❌ | `CadenaCustodiaTest` ✅; sin test de enmienda | Triggers bloquean UPDATE/DELETE (`create_actuados_triggers`, `add_hash_trigger_lock`) | `MATRIZ_SRS:27`; grep hoy: única mención "Enmienda" = mensaje del trigger (`...create_actuados_triggers.php:23`) | PARCIAL (AUD-0009) |
| **RF-03** Bandejas privadas; acceso ajeno denegado | Policy con ownership ✅ pero **bypass ADMIN** en `view` | `SecurityCompartimentosTest` 🔴 — **1 fallo** (espera 403, recibe 200) | Bypass contradice RF-03 y PERF-ADMIN | `ExpedientePolicy:80-82`; suite 2026-10-02 (único fallo) | INCONSISTENTE (AUD-0001) |
| **RF-04** Evaluación dinámica de requisitos | Sí: `GET .../requisitos` por reglamento + `POST .../evaluacion` + motor de reglas | `EvaluacionAdmisibilidadTest` ✅ + unit | Policy por recurso (F13/F16) | Route list; `MATRIZ_SRS:29` | IMPLEMENTADA (sin UI de checklist → AUD-0060) |
| **RF-05** Transferencia inter-unidad (Remisión/Recepción) | No: tabla `transferencias` + modelo existen, **0 endpoints** | Ninguna | — | grep `transferencia` en `routes/` = 0 (2026-10-02) | FALTANTE |
| **RF-06** Transiciones por actuados "firmados" | Sí: firma entendida como cadena de hash SHA-256 (`hash_anterior`/`hash_actuado`) en transacción | `CadenaHashIntegrityTest` ✅, `CadenaHashConcurrenciaTest` ✅ | 24 `DB::transaction`; hash encadenado | `MATRIZ_SRS:31` | IMPLEMENTADA (interpretación de "firma digital" → §6 D-8) |
| **RNF-01** Contraseñas encriptadas + RBAC | Sí: `Hash::make` (3), policies, middleware, throttle login | `AuthFeatureTest` ✅, `SeguridadSesionesTest` ✅ | Detalle en F13/F17 | `MATRIZ_HARDENING.md`; `MATRIZ_SRS:39` | IMPLEMENTADA |
| **RNF-02** Transacciones + hash de seguridad | Sí | `CadenaCustodiaTest` ✅, `CadenaHashConcurrenciaTest` ✅ | Triggers + hash | `MATRIZ_SRS:40` | IMPLEMENTADA |
| **RNF-03** Paginación server-side + búsquedas | **Parcial:** `paginate(15)` en ambas bandejas (`ExpedienteController:63,76`) y usuarios (`UsuarioController:40`); **0 rutas de búsqueda**; monitoreo sin paginar | F15 midió (43 queries/serialización en `GET /api/bandeja`); sin test unit de paginación | — | `MATRIZ_RENDIMIENTO:164-206`; grep `buscar|search` en `routes/` = 0 (hoy) | PARCIAL (AUD-0018 ampliado — ver §5 y §6 D-2) |
| **RNF-04** Laravel + PostgreSQL/JSONB | Laravel + **MySQL 9.7 InnoDB**, `JSON` nativo (sin `jsonb`) | — | — | Ver DT-01/DT-02 | DIFERENCIA TECNOLÓGICA |
| **RF-R01** Reporte carga laboral por usuario | No | Ninguno | — | `composer.json`: 0 libs Excel/PDF (grep hoy); sin endpoints de reporte | FALTANTE (AUD-0011) |
| **RF-R02** Reporte libro matriz de ingresos | No | Ninguno | — | ídem | FALTANTE (AUD-0011) |
| **RF-R03** Reporte recepción inter-unidad | No (además RF-05 inexistente) | Ninguno | — | ídem | FALTANTE (AUD-0011) |
| **RF-R04** Reporte estado de evaluación | No | Ninguno | — | ídem | FALTANTE (AUD-0011) |
| **RF-R05** Notificaciones pendientes | No: **no existe sistema de notificaciones** en `app/` | Ninguno | — | grep (F2/F12); ídem | FALTANTE (AUD-0011) |
| **RF-R06** Reporte resoluciones finales | No | Ninguno | — | ídem | FALTANTE (AUD-0011) |
| **RF-R07** Estadístico por vía (gráficas) | Parcial: resumen del dashboard de la Encargada; sin gráficas ni segmentación por vía | `EncargadaDashboardTest` ✅ hoy (🔴 en Fase 2 → AUD-0003 cerrado) | — | `EncargadaDashboardService`; `MATRIZ_SRS:58` | PARCIAL (AUD-0011) |
| **RF-R08** Carátula Oficial (PDF foja 0) | No | Ninguno | — | grep endpoints/PDF = 0 | FALTANTE (AUD-0011) |
| **RF-R09** Trazabilidad Padre-Hijo (reporte) | Parcial: FK + endpoint ✅; sin reporte | `NurejHijoTest` ✅ (dato); sin prueba de reporte | — | `MATRIZ_SRS:60` | PARCIAL (AUD-0011) |
| **DT-01** (SRS pide PostgreSQL + JSONB) | MySQL InnoDB + tipo `JSON` nativo de MySQL; requisito funcional (metadatos en JSON) cumplido | — | — | `SRS_EXTRAIDO:61,116,187`; `MATRIZ_SRS:17` | DIFERENCIA TECNOLÓGICA (no es brecha; documentada según instrucción SRS) |
| **DT-02** (SRS pide "SQL directas a PostgreSQL") | Mismas consultas sobre MySQL | — | — | `SRS_EXTRAIDO:187`; `MATRIZ_SRS:18` | DIFERENCIA TECNOLÓGICA |

**Resumen §1:** 5 IMPLEMENTADA · 4 PARCIAL · 1 INCONSISTENTE · 8 FALTANTE · 1 DIFERENCIA TECNOLÓGICA (RNF-04/DT) = 19.
Las reglas de negocio RN-01…RN-10, criterios CA-1…CA-4, REST-* y PERF-ADMIN permanecen con su evidencia detallada en `MATRIZ_SRS_IMPLEMENTACION.md` §4-§6; sus brechas abiertas están mapeadas a hallazgos AUD en §5.

---

## 2. Consistencia global (§77: SRS ↔ Código ↔ BD ↔ UI ↔ API ↔ Tests)

| Par | Resultado | Evidencia clave |
| --- | --------- | --------------- |
| SRS ↔ Código | PARCIAL | 19 requisitos: 5 impl., 4 parciales, 1 inconsistente, 8 faltantes, 1 DIFERENCIA TECNOLÓGICA (RNF-04/DT-01) = 19 (§1); catálogo de actuados: 21/22 filas de `MATRIZ_SRS` §5 con equivalente en código (la única FALTANTE de esa tabla es informes Técnico); el actuado de Enmienda ausente está documentado en RF-02, no en esa tabla |
| SRS ↔ BD | PARCIAL | Esquema coherente (49 FKs reales verificadas hoy; triggers de inmutabilidad activos); **pero BD dev desactualizada vs seeders**: actuados 7/26, estados 16/23, parámetros 8/18 (AUD-0023, verificado hoy) |
| SRS ↔ UI | PARCIAL | Núcleo funcional con UI (F14/F16); **sin UI**: evaluación de requisitos (RF-04/AUD-0060), impugnación + informe jurídico (AUD-0035), operaciones de ampliación/descargos/transparencia/cierre vía endpoints (AUD-0061), transferencias (RF-05), reportes (RF-R01…R09); catálogo sin `expediente_id` (AUD-0034) |
| SRS ↔ API | PARCIAL | Endpoints de núcleo completos y policy-protegidos (F6/F13/F16); **sin endpoints**: transferencias (AUD-0010), catálogo global de requisitos (AUD-0014), reportes (AUD-0011), excusas/recusaciones (AUD-0015) |
| Código ↔ Tests | PARCIAL | Suite 2026-10-02: **310 tests, 303 pasan, 1 fallo (AUD-0001), 6 omitidos, 1453 aserciones**; cobertura F4–F17 (custodia, plazos, seguridad, admin, hardening, flujos); seeders reales solo en 2 suites (AUD-0022) |
| BD ↔ Tests | PARCIAL | Triggers y cadena de hash testados (F4/F17); transiciones de estados en tests usan catálogo en memoria según seeder parcial (AUD-0022); regresión de catálogo dev no testeada (AUD-0023) |

---

## 3. Criterios de listo para producción (§79)

| # | Criterio | Estado | Evidencia / motivo (2026-10-02) |
| - | -------- | ------ | ------------------------------- |
| 1 | No existen P0 | CUMPLE | BACKLOG: 0 hallazgos P0 abiertos |
| 2 | No existen P1 | **NO CUMPLE** | 18 P1 abiertos (§5) |
| 3 | Sin brechas críticas de seguridad | **NO CUMPLE** | AUD-0001 (bypass de aislamiento de roles) + `APP_DEBUG=true` (criterio 18) + AUD-0006 (CDN vs REST-LAN) |
| 4 | Flujos principales completos | PARCIAL | `FlujoIntegralTest` ✅ cubre núcleo ingreso→cierre; faltan RF-05, informes Técnico (AUD-0008), impugnación por UI (AUD-0035) |
| 5 | Roles correctamente aislados | **NO CUMPLE** | AUD-0001: `ExpedientePolicy:80-82`; `SecurityCompartimentosTest` falla en suite |
| 6 | Bandejas funcionando | CUMPLE | `bandejaSorteo`/`bandejaOperador` paginadas (`ExpedienteController:63,76`), tests + UI F16; salvedad: sin búsqueda (AUD-0018) |
| 7 | Actuados inmutables | CUMPLE | Triggers UPDATE/DELETE bloqueados + `CadenaCustodiaTest` ✅ (F4/F17) |
| 8 | NUREJ correcto | CUMPLE | `NurejGeneratorServiceTest` ✅; sin endpoint de edición |
| 9 | Padre/Hijo correcto | CUMPLE (con observación) | `NurejHijoTest` ✅; observación de diseño AUD-0039 (copia `via`/`reglamento_id`) |
| 10 | Plazos verificados | PARCIAL | F5 `MATRIZ_PLAZOS` + `RelojProcesualTest` ✅; abiertos AUD-0019, 0030, 0031, 0032 |
| 11 | Jobs verificados | CUMPLE | F13 `MATRIZ_JOBS_CRON`: comando, programación y salida verificados |
| 12 | Reportes consistentes | **NO CUMPLE** | F12: 0/9 Excel y 0/9 PDF; `composer.json` sin libs de export (AUD-0011) |
| 13 | Dashboard consistente | PARCIAL | F11/F12: coherencia de datos OK; sin gráficas (RF-R07) y N+1/sin límite (AUD-0062, AUD-0063) |
| 14 | Tests pasando | **NO CUMPLE** | Suite 2026-10-02: 303/310, 1 fallo = AUD-0001 |
| 15 | Migraciones verificadas | CUMPLE | F4/F5: `up()`/`down()` revisados (incl. reversión `LOTE-0012`), todas ejecutadas |
| 16 | MySQL verificado | CUMPLE | F0: MySQL 9.7 InnoDB `utf8mb4`; sin sintaxis exclusiva de otros motores (DT-01/02 documentados) |
| 17 | Backups/rollback considerados | **NO VERIFICABLE** | Infraestructura fuera del alcance: falta política de backup de BD, prueba de restore y plan de rollback de despliegue |
| 18 | Errores controlados | **NO CUMPLE** | **`APP_DEBUG=true`** verificado hoy (`config:show app.debug`) tanto en `.env` local como plantilla `.env.example:APP_DEBUG=true` → en producción debe ser `false`; manejo de errores de UI verificado en F14 |
| 19 | Auditoría final realizada | CUMPLE | F0–F17 validadas/cerradas + esta matriz (F18) |
| 20 | Matriz SRS cubierta | PARCIAL | 19 requisitos trazados con evidencia (§1); 8 FALTANTE + 1 INCONSISTENTE |

**Resultado §79:** 9 CUMPLE · 4 PARCIAL · 6 NO CUMPLE · 1 NO VERIFICABLE → **no se cumple READY FOR PRODUCTION** (ver §7).

---

## 4. Release checklist (§116)

| # | Ítem | Estado | Evidencia / motivo |
| - | ---- | ------ | ------------------ |
| 1 | Código limpio | CUMPLE | `vendor/bin/pint --dirty --test --format agent` → **passed** (2026-10-02) |
| 2 | Tests OK | **NO CUMPLE** | 303/310; 1 fallo AUD-0001 |
| 3 | Build OK | **NO VERIFICABLE** | Existe `public/build/manifest.json` de un build previo; **build no re-ejecutado en F18** (decisión del usuario: gates = pint + suite) |
| 4 | Migraciones OK | CUMPLE | F4/F5 |
| 5 | Migraciones compatibles con MySQL | CUMPLE | F4/F5 (InnoDB, sin tipos ajenos) |
| 6 | Variables documentadas | CUMPLE (con observación) | `MATRIZ_HARDENING:34-36` (`.env`, `.env.example`); observación O-B: plantilla con `APP_DEBUG=true`/`DB_PASSWORD=root` |
| 7 | Secrets fuera del repositorio | CUMPLE | `git ls-files .env` vacío; `.gitignore:3-6` cubre `.env*`; `.env.example` con `APP_KEY=` vacío |
| 8 | Jobs documentados | CUMPLE | `MATRIZ_JOBS_CRON.md` |
| 9 | Cron documentado | CUMPLE | `MATRIZ_JOBS_CRON.md` (schedule/cron); ejecución real del cron del SO → NO VERIFICABLE en este entorno |
| 10 | Backup considerado | **NO VERIFICABLE** | Sin infraestructura (§3.17) |
| 11 | Rollback definido | **NO VERIFICABLE** | `down()` de migraciones OK (F4/F5); rollback de despliegue no documentado |
| 12 | Logs funcionando | PARCIAL | `storage/logs/laravel.log` activo (3.1 MB, 2026-10-02); rotación/retención en producción NO VERIFICABLE |
| 13 | Errores controlados | **NO CUMPLE** | `APP_DEBUG=true` (§3.18) |
| 14 | Seguridad validada | PARCIAL | F13/F17 (matrices); AUD-0001 y AUD-0006 abiertos |
| 15 | Roles validados | **NO CUMPLE** | AUD-0001 |
| 16 | Bandejas validadas | CUMPLE | §3.6 |
| 17 | Plazos validados | PARCIAL | §3.10 |
| 18 | Actuados validados | CUMPLE | §3.7 |
| 19 | Reportes validados | **NO CUMPLE** | §3.12 |
| 20 | Dashboard validado | PARCIAL | §3.13 |
| 21 | NUREJ validado | CUMPLE | §3.8 |
| 22 | Padre/Hijo validado | CUMPLE (con observación) | §3.9 |
| 23 | MySQL validado | CUMPLE | §3.16 |
| 24 | Índices críticos revisados | PARCIAL | F15: PK/UNIQUE/índice de estado OK; AUD-0064 (P3, `fecha_ingreso` sin índice) abierto |
| 25 | Integridad referencial validada | CUMPLE | **49 foreign keys** reales contadas en BD (2026-10-02) |
| 26 | UAT preparado | **NO VERIFICABLE** | Sin plan ni entorno de UAT documentado en `docs/auditoria/` (1 mención de passim en PROGRESO) |
| 27 | Matriz SRS completa | PARCIAL | §1/§3.20 |

**Resultado §4:** 13 CUMPLE · 6 PARCIAL · 4 NO CUMPLE · 4 NO VERIFICABLE → **release NO habilitado**.

---

## 5. Segunda ronda de re-verificación de P1/P2 abiertos (2026-10-02)

Ámbito: **39 hallazgos abiertos** (18 P1 + 21 P2; 0 P0). Solo re-verificación de vigencia contra la evidencia citada; **ningún hallazgo se cierra ni se reclasifica aquí** (las propuestas van a §6). Sin hallazgos nuevos (no se crearon fichas; próximo ID libre AUD-0068 reservado por duplicado).

| Hallazgo | Sev. | Vigencia | Evidencia re-verificada (2026-10-02) |
| -------- | ---- | -------- | ------------------------------------ |
| AUD-0001 | P1 | VIGENTE | `ExpedientePolicy:80-82`; suite: `SecurityCompartimentosTest` espera 403 y recibe 200 |
| AUD-0004 | P2 | VIGENTE | `routes/api.php:38` y `:102` → mismo método `inactivar` (2 URIs) |
| AUD-0006 | P2 | VIGENTE | `resources/views/layouts/app.blade.php:8-10` (Tailwind/Alpine/FontAwesome por CDN) |
| AUD-0008 | P1 | VIGENTE | `CatalogoActuadoSeeder`: informes Técnico = 0; Jurídico = 1 genérico (`:62`); Financiero = 2 (`:82-83`) |
| AUD-0009 | P1 | VIGENTE | "ENMIENDA": 1 coincidencia en todo el código = mensaje del trigger; sin fila en catálogo |
| AUD-0010 | P1 | VIGENTE | grep `transferencia` en `routes/` = 0 |
| AUD-0011 | P1 | VIGENTE | `composer.json` sin libs Excel/PDF; refuerza F12 (0/9 y 0/9 reportes) |
| AUD-0012 | P1 | VIGENTE (obs.) | 0 rutas de suspensiones; **el modelo `SuspensionPlazo` existe** (la evidencia original "sin modelos" es imprecisa); sin recálculo |
| AUD-0013 | P2 | VIGENTE | 11 rutas `/admin/` (dashboard, usuarios, feriados, monitoreo) |
| AUD-0014 | P2 | VIGENTE | grep `catalogo/requisitos` en `routes/api.php` = 0 |
| AUD-0015 | P1 | VIGENTE | grep `excusa|recusaci` en `app/routes/database` = 0 |
| AUD-0016 | P2 | VIGENTE | `app/Models/Transferencia.Php` existe sin endpoints asociados |
| AUD-0017 | P2 | VIGENTE (drift de líneas) | `abort(403)` inline en `AdminDashboardController:34` y `AdminMonitoreoController:31` (evidencia original `:28-29`) |
| AUD-0018 | P2 | **VIGENTE PARCIAL → propuesta §6 D-2** | `paginate(15)` en `ExpedienteController:63,76` + `UsuarioController:40` desde 2026-09-02; 0 rutas `buscar|search`; monitoreo sin paginar (F15 ya lo documentó en `MATRIZ_RENDIMIENTO:193-206`) |
| AUD-0019 | P2 | VIGENTE | `ParametroPlazoSeeder:27,29`: EVALUACION AC054=5, AC055=5 días fijos; sin campo complejidad |
| AUD-0020 | P1 | VIGENTE (evidencia actualizada) | `estado_origen_id` leído solo como filtro del catálogo (`CatalogoActuadoController:56-57`, `IndexCatalogoActuadosRequest:20`, `CatalogoActuadoResource:27`, `CatalogoActuado:22,81`); 0 uso en validación origen→destino de transiciones |
| AUD-0021 | P1 | VIGENTE | `CatalogoActuadoSeeder:62`: `ACT_INFORME_FINAL` con `estado_destino_id = null`; sin arista de cierre |
| AUD-0022 | P2 | **VIGENTE PARCIAL → propuesta §6 D-3** | `FlujoIntegralTest:16,17,39,44` y `FlujoJuridicoTest:63` **sí** usan seeders reales (F16); el resto de suites no |
| AUD-0023 | P2 | VIGENTE | BD dev vs seeders: actuados **7/26**, estados **16/23**, parámetros **8/18** (consultas hoy) |
| AUD-0024 | P1 | VIGENTE | `ActuadoService:226-232`: `return 'JURISDICCIONAL'` fijo |
| AUD-0025 | P2 | VIGENTE | `ParametroPlazoSeeder:28,30`: PLANIFICACION AC054/AC055 = 3 días (SRS pide 2) |
| AUD-0027 | P2 | VIGENTE | `StoreActuadoRequest:36`: `usuario_destino_id` solo `exists:usuarios,id`, sin derivación automática |
| AUD-0028 | P1 | VIGENTE | Único endpoint Encargada: `GET /api/encargada/dashboard` (`routes/api.php:90`) |
| AUD-0029 | P2 | VIGENTE | grep `asignacion` en `SeguridadSesionesService` = 0 |
| AUD-0030 | P1 | VIGENTE | `CierreExpedienteService`: grep `Plazo` = 0 |
| AUD-0031 | P1 | VIGENTE | `ArchivoPorAbandonoService:51,61` solo consulta estado de **plazos**, no `estado_actual` del expediente |
| AUD-0032 | P1 | VIGENTE | grep `SUBSANACION_ACEPTADA|SUBSANACION_SALIDA` en `app/database` = 0 |
| AUD-0033 | P1 | VIGENTE | `CatalogoActuadoSeeder:62` (destino null) + test negativo "no permite emitir" pasa en suite |
| AUD-0034 | P2 | VIGENTE | `detalle.blade.php:297` llama `GET /api/catalogo/actuados` sin `expediente_id`; `IndexCatalogoActuadosRequest` sin `expediente_id` |
| AUD-0035 | P1 | VIGENTE | grep `impugnacion/` ni `informe_final` en vistas = 0; endpoints existen (`routes/api.php:62-63`) |
| AUD-0039 | P2 | VIGENTE | `NurejHijoService:46-47` copia `via` y `reglamento_id` del padre |
| AUD-0042 | P2 | VIGENTE | `VerificarVencimientoPlazosTest` pasa en suite (comportamiento con catálogo ausente) |
| AUD-0043 | P2 | **CERRADO (2026-10-05, B3.3)** | `config:show app.timezone` = **America/La_Paz** (antes UTC); `TimezoneBoliviaTest` (5 tests) verifica corte a 23:59:59 La Paz |
| AUD-0044 | P2 | VIGENTE | `app.blade.php:135` enlace `/expedientes` sin `x-if` (sí lo tiene `/expedientes/nuevo` en `:160`) |
| AUD-0045 | P2 | VIGENTE | `administrador/monitoreo.blade.php`: 0 render de `error`/`cargando` |
| AUD-0060 | P1 | VIGENTE | grep `/evaluacion` y `/requisitos` en vistas = 0 |
| AUD-0061 | P1 | VIGENTE | grep endpoints `/ampliacion`, `/descargos`, `/transparencia`, `/cierre` en vistas = 0 |
| AUD-0062 | P2 | VIGENTE | `ExpedienteResource:6,82` y `PlazoResource:6,24` instancian servicios por ítem; 0 bindings singleton |
| AUD-0063 | P2 | VIGENTE | `AdminDashboardController:95` `Expediente::with([...])->get()` sin límite (además `:50,69,81,136,162,184`) |

**Resultado §5:** 37 VIGENTE (3 con drift/observación menores de evidencia) · 2 VIGENTE PARCIAL con propuesta de reevaluación (AUD-0018, AUD-0022) = **39** · 0 dejaron de aplicar · 0 hallazgos nuevos · 0 P0 nuevos.

---

## 6. Decisiones bloqueantes (pendientes de aprobación del usuario)

| ID | Decisión | Por qué bloquea |
| -- | -------- | ---------------- |
| **D-1** | **AUD-0001**: eliminar el bypass `ADMIN` en `ExpedientePolicy::view` o aceptarlo como política explícita (y ajustar RF-03/PERF-ADMIN) | Único fallo de la suite; impide "Tests OK" (§4.2) y "Roles aislados" (§3.5) |
| **D-2** | **AUD-0018**: redefinir alcance/prioridad — la paginación server-side ya existe en bandejas; quedan búsqueda y monitoreo | Decide si se mantiene P2 con alcance nuevo o se cierra/reclasifica |
| **D-3** | **AUD-0022**: reevaluar alcance — 2 suites ya usan seeders reales | Decide si el hallazgo se reduce al resto de suites |
| **D-4** | **AUD-0030/0031/0032/0033/0021** (grafo de estados y ciclo de vida de plazos): orden y diseño de la solución antes de tocar código | Dependencias cruzadas de `catálogo_estados`/transiciones; fix posterior a la auditoría |
| **D-5** | **O-A (AUD-0004)**: consolidar la URI duplicada de inactivar | Cambio de API (rompe clientes existentes si se elimina una ruta) |
| **D-6** | **O-B**: valores de plantilla — `.env.example` con `APP_DEBUG=true` y `DB_PASSWORD=root` | Incumplimiento §3.18/§4.13; definir valores seguros de plantilla sin romper DX local |
| **D-7** | `MAPA_FUNCIONAL.md:196` desactualizado + estado `ADMITIDO` sin transición entrante | Saneamiento documental/funcional post-auditoría |
| **D-8** | **RF-06**: confirmar que "firma digital" = cadena de hash (no PKI) | Aceptación de la interpretación usada en §1 |
| **D-9** | **DT-01/DT-02**: aceptar la diferencia tecnológica MySQL vs PostgreSQL del SRS | §1; si el SRS exige PostgreSQL literal, hay brecha contractual |
| **D-10** | **RF-R05**: ausencia total de notificaciones | Faltante funcional del SRS sin diseño definido |

---

## 7. Veredicto documental (§79/§117)

> **NOT READY FOR PRODUCTION** (veredicto documental, no de despliegue).

Fundamento (solo hechos de esta matriz):
1. 18 P1 + 21 P2 abiertos; 0 P0 (§5).
2. 8 requisitos SRS FALTANTE (RF-05, RF-R01…R06, RF-R08) + RF-03 INCONSISTENTE (§1).
3. Tests: 1 fallo (AUD-0001) — §4.2.
4. `APP_DEBUG=true` en `.env` local y plantilla — §3.18.
5. Reportes: 0/9 Excel y 0/9 PDF — §3.12.
6. Backups/rollback/UAT: NO VERIFICABLE (infraestructura fuera de alcance) — §3.17, §4.10-11, §4.26.

**Condiciones mínimas para re-evaluar READY:** cerrar/justificar los 18 P1 (o aceptar por escrito cada uno), resolver D-1 (tests en verde), eliminar `APP_DEBUG=true` en producción (D-6), y obtener evidencia de backups/rollback/UAT en el entorno de despliegue (o una excepción escrita).

---

## 8. Gates, archivos y trazabilidad

**Gates ejecutados (2026-10-02):**

| Gate | Resultado |
| ---- | --------- |
| `vendor/bin/pint --dirty --test --format agent` | **passed** |
| `php artisan test --compact` | **310 tests · 303 passed · 1 failed (AUD-0001) · 0 errors · 6 skipped · 1453 assertions** (idéntico a la línea base de F17) |
| `npm run build` | **no ejecutado** (decisión del usuario en F18: gates = pint + suite) |

**Alcance de cambios de F18:** solo documentación `.md` (`docs/auditoria/MATRIZ_COBERTURA_FINAL.md`, `PLAN_EJECUCION_AUDITORIA.md`, `PROGRESO.md`). Sin cambios en `app/`, `routes/`, `config/`, `database/`, `resources/`, `public/`, `bootstrap/` ni `tests/`. `git status --porcelain` final comparado contra el baseline en memoria (12 líneas preexistentes de F13–F17); únicamente se agregan los `.md` de F18.

**Trazabilidad:** F0–F17 validadas y cerradas (ver `PLAN_EJECUCION_AUDITORIA.md:121-125` y cierres en `PROGRESO.md`). Esta matriz consolida sus resultados; no sustituye sus evidencias originales ni cierra hallazgos.
