# Backlog de Auditoría — Sistema de Control y Fiscalización

> Registro único de hallazgos de las 18 fases (ver `PLAN_EJECUCION_AUDITORIA.md`).
> Regla: **todo hallazgo se registra aquí aunque no tenga solución todavía**.
> Nada se marca CERRADO sin `php artisan test --compact` en verde y evidencia
> de verificación (ruta → controller → policy → service → migración → test).

## Formato de entrada

```
### AUD-XXXX — Título corto
- **Severidad:** P1 (funcionalidad crítica / seguridad) | P2 (deuda / calidad) | P3 (informativo)
- **Fase:** fase que lo detectó / debe cerrarlo
- **Estado:** OPEN | BLOCKED | NEEDS_REVIEW | AMBIGÜEDAD | CERRADO
- **Evidencia:** `ruta/archivo.php:línea` o comando ejecutado
- **Impacto:**
- **Acción propuesta:**
```

## Resumen por severidad

| ID | Título | Sev. | Fase | Estado |
|---|---|---|---|---|
| AUD-0001 | `ExpedientePolicy::viewAny` devuelve `true` a ADMIN → test 403 falla | P1 | 0 | **CERRADO (2026-10-05)** — fix en `ExpedientePolicy::view()` (eliminación del bypass de ADMIN) en tarea B2.0; `SecurityCompartimentosTest` 9/9 verde sin modificar el test; suite 310 tests · 304 OK · 0 fallos · 6 omitidos |
| AUD-0002 | Falla `DescargoFinancieroTest` (fecha plazo: 2026-09-23 vs 2026-10-06) | P1 | 0 | **CERRADO (2026-09-30)** |
| AUD-0003 | 5 tests `EncargadaDashboardTest` con `Usuario::getJson()` inexistente | P2 | 0 | **CERRADO (2026-09-30)** |
| AUD-0004 | Ruta de inactivar usuario duplicada | P2 | 0 | OPEN |
| AUD-0005 | `doc_avances/BACKLOG_JIRA.md` desactualizado vs realidad | P3 | 0 | OPEN |
| AUD-0006 | CDNs públicos (no air-gapped) en layout | P2 | 0 | OPEN |
| AUD-0007 | Doble mecanismo de auditoría de sesiones | P3 | 0 | OPEN |
| AUD-0008 | Informes finales Técnico/Jurídico faltantes (E5-S3) | P1 | 0 | OPEN |
| AUD-0009 | Catálogo de actuados incompleto + sin Enmienda (RF-02) | P1 | 0 | OPEN |
| AUD-0010 | Transferencias de expediente sin endpoints (RF-05) | P1 | 0 | OPEN |
| AUD-0011 | Reportes RF-R01…R09 / Carátula PDF / exportaciones inexistentes | P1 | 0 | OPEN (Fase 12 confirma: 0/9 Excel, 0/9 PDF, ver `MATRIZ_REPORTES.md` §1) |
| AUD-0012 | Sin CRUD `suspensiones_plazo` ni recálculo retroactivo (RF-07) | P1 | 0 | OPEN |
| AUD-0013 | Administración de catálogos/parámetros incompleta en Admin | P2 | 0 | OPEN |
| AUD-0014 | Sin `GET /api/catalogo/requisitos?reglamento_id=` (E2-S2) | P2 | 0 | OPEN |
| AUD-0015 | Excusas/recusaciones (Épica 6) no existen | P1 | 0 | OPEN |
| AUD-0016 | `app/Models/Transferencia.Php` con mayúscula (rompe PSR-4 en Linux) | P2 | 0 | OPEN |
| AUD-0017 | Autorización inline (`abort(403)` por rol) en `AdminDashboardController` y `AdminMonitoreoController` en vez de policy | P2 | 1 | OPEN (Fase 12 reconfirma: `AdminDashboardController:28-29`, `AdminMonitoreoController:31`) |
| AUD-0018 | RNF-03 sin paginación server-side ni búsqueda (0 `paginate()`, 0 rutas de búsqueda) | P2 | 2 | OPEN (Fase 12 reconfirma: monitoreo `$query->get()` sin paginar, `AdminMonitoreoController:113`) |
| AUD-0019 | RN-02: rango "3 o 5 días según complejidad" no parametrizable (solo 5 fijo AC054/AC055) | P2 | 2 | **DECIDIDO (2026-09-30): P2 confirmado** — parametrizar en el rango 3-5; la complejidad determina 3/4/5 (no asumir 5). Fix NO aplicado: antes documentar cómo se determina la complejidad y su fuente |
| AUD-0020 | Endpoint genérico de actuados no valida `estado_origen_id` → saltos de estado, re-emisión y cierre sin informe | P1 | 4 | **DECIDIDO (2026-09-30): mantener P1/OPEN** — validar estado origen antes de transicionar (destino válido no lo sustituye). Fix NO autorizado hasta matriz `actuado → estado_origen → estado_destino` |
| AUD-0021 | Huecos del grafo: informe AC022/054 sin destino (cierre inalcanzable) + ADMITIDO sin salida tras revocación | P1 | 4 | **DECIDIDO (2026-09-30): mantener P1/OPEN** — grafo actual NO aceptado; antes de corregir, matriz `estado → actuados de salida → destinos → roles` respetando SRS (sin inventar transiciones) |
| AUD-0022 | Ningún test ejecuta los seeders de catálogo; fixtures propias divergentes del grafo real | P2 | 4 | OPEN |
| AUD-0023 | BD dev desactualizada: 7/25 actuados, 16/21 estados, 8/17 parámetros de plazo (falta `db:seed` con confirmación) | P2 | 4 | **DECIDIDO (2026-09-30): mantener P2/OPEN** — NO `db:seed` global; preparar sync/seed idempotente de catálogos (identificar seeders afectados, sin reprocesar demo). Ejecución en dev → autorización específica posterior. **Incluye sub-caso O-7** (`IMPUGNACION_RESOLVER` ausente en dev → fallback `now()`) |
| AUD-0024 | RN-05: plazo de 15 días "Administrativa" inalcanzable (`resolveSubtipoEjecucion` hardcodea JURISDICCIONAL) | P1 | 5 | **DECIDIDO (2026-09-30): P1 confirmado, NO fuera de alcance** — campo explícito `naturaleza` (JURISDICCIONAL/ADMINISTRATIVA) que determine el plazo; NO reutilizar `via`. Cambio de esquema NO aplicado: antes presentar impacto (migración, alta, edición/enmienda, plazos, tests) |
| AUD-0025 | RN-04: PLANIFICACION=2 para MPA de auditores vs "sin plazo estricto" en el SRS | P2 | 5 | **DECIDIDO (2026-09-30): P2 confirmado** — AC022 Técnico = 2 días; AC054/055 MPA sin reloj rígido; fecha de ejecución = la aprobada en el MPA. NO aplicado: antes presentar cambio mínimo + tests |
| AUD-0026 | Ampliación aprobada sobre ejecución ya vencida: límite ampliado calculado desde el límite vencido | P3 | 5 | OPEN |
| AUD-0027 | `usuario_destino_id` controlado por el cliente en el actuado genérico (enrutamiento de bandejas, sin validar activo ni destino) | P2 | 6 | **DECIDIDO (2026-09-30): mantener P2/OPEN** — validar destino (activo + rol compatible + destino institucional) en servidor; no confiar en la UI. Fix NO aplicado: antes matriz `actuado → roles/destinos permitidos` |
| AUD-0028 | Sin bandeja de supervisión/acción para la Encargada: solo agregados; monitoreo es solo-ADMIN (Plan §7/§8.1) | P1 | 6 | **DECIDIDO (2026-09-30): mantener P1/OPEN** — el dashboard agregado NO sustituye la supervisión operativa; diseñar la superficie desde los casos de uso auditados. NO implementar hasta cerrar diseño de permisos y bandeja |
| AUD-0029 | Inactivar usuario no redistribuye sus asignaciones activas → expedientes huérfanos | P2 | 6 | **DECIDIDO (2026-09-30): mantener P2/OPEN** — estrategia explícita de reasignación/retorno a bandeja institucional con actuado y trazabilidad; sin reasignación silenciosa en BD. NO aplicar hasta definir el flujo institucional |
| AUD-0030 | Plazos no se cierran al concluir su fase (EVALUACION/EJECUCION) → falsos `fuera_de_plazo` permanentes | P1 | 7 | **CERRADO (2026-10-06, tarea B1.3)** — fix AUTORIZADO (opción i: cerrar al salir de fase) y aplicado: `ActuadoService::MAPA_CIERRA_PLAZO` + `cerrarPlazosDeFase()` dentro de la transacción/lock de `registerActuado` (`estado=CERRADO` + `actuado_cierre_id`, sin columna `fecha_cierre` — decisión opción A); test nuevo `CierrePlazosFasesTest` (7 tests); `FlujoIntegralTest:333` actualizado (codificaba el bug); pint OK; suite 354 tests · 347 OK · 0 fallos · 7 omitidos |
| AUD-0031 | Archivo por abandono sin validar estado actual: CRON archiva casos vivos que salieron de EN_SUBSANACION | P1 | 7 | **CERRADO (2026-10-06, tarea B1.4)** — fix AUTORIZADO y aplicado en `ArchivoPorAbandonoService::archivarVencidos()`: (1) filtro estricto `whereHas('expediente', estado_actual = EN_SUBSANACION)`; (2) `try/catch (\Throwable)` por expediente con `Log::error` (plazo_id/expediente_id/clase/mensaje) → una falla no aborta la corrida (reproduce en rojo que, post-D-6g, la ValidationException de origen abortaba el CRON completo); gate con `SubsanacionExitoTest` (negativo, corrida mixta, aislamiento con mock); pint OK; suite 363 tests · 356 OK · 0 fallos · 7 omitidos. Huérfanos SUBSANACION VIGENTE fuera de fase: fuera de alcance de B1.4 (decisión del usuario; hallazgo residual documentado) |
| AUD-0032 | EN_SUBSANACION sin salida de éxito (no existe actuado "subsanación aceptada", RN-03 incompleto) | P1 | 7 | **CERRADO (2026-10-06, tarea B1.4)** — fix AUTORIZADO y aplicado: actuado `ACT_SUBSANACION_ACEPTADA` (`EN_SUBSANACION → EN_EVALUACION`) en `CatalogoActuadoSeeder` + pivote `perfilesEvaluacion` (aprobación del usuario: seeder, sin migración de catálogo); cierre del reloj SUBSANACION vía `MAPA_CIERRA_PLAZO` en `ActuadoService` (mecanismo B1.3, con `actuado_cierre_id`); decisiones: sin reloj EVALUACION nuevo, `requiere_adjunto=false`, 422 si el plazo ya venció (`fecha_limite` es columna `date` → comparación estricta contra `today()` en `app.timezone`=America/La_Paz, o plazo en estado VENCIDO — vencimiento independiente del CRON); sin plazo parametrizado → permite. Gates: `SubsanacionExitoTest` 9/9, pint OK, suite 363 tests · 356 OK · 0 fallos · 7 omitidos |
| AUD-0033 | `ACT_INFORME_FINAL` (informe jurídico) no emisible: `estado_nuevo_id` NOT NULL vs destino `null` → 500 | P1 | 8 | **DECIDIDO (2026-09-30): mantener P1/OPEN** — NO hacer `estado_nuevo_id` nullable; resolver dentro de la máquina de estados (¿destino del informe? ¿estado de espera de VB? → verificar grafo → cambio mínimo). Reproducido con test |
| AUD-0034 | Catálogo de actuados sin `expediente_id` en la vista de detalle (+ `expediente_id` sin validar en el FormRequest) | P2 | 8 → 9 | **DECIDIDO (2026-09-30, reiterado): P2 — problema funcional, no de seguridad.** Fix autorizado conceptualmente (catálogo contextualizado al expediente/reglamento + validación en servidor); NO aplicar mientras se cruce con AUD-0033/0020/0021 (resolver antes estados/catálogo) |
| AUD-0035 | RN-08 (impugnación) e informe jurídico sin interfaz: 0 vistas, solo alcanzables por HTTP directo | P1 | 8 | **DECIDIDO (2026-09-30): P1 confirmado** — endpoints funcionales NO sustituyen la interfaz; verificar el conjunto de operaciones jurídicas requeridas (RN-08/RN-09) y diseñar la UI contra el flujo normativo (no botones aislados). NO implementar aún |
| AUD-0036 | Concurrencia paralela de `comunicar` sin índice único en `plazos` ni bloqueo de fila (observación, no reproducida) | P3 | 9 | OPEN (observación; no elevar sin evidencia experimental) |
| AUD-0037 | `firstOrFail`/`findOrFail` → 404 en flujos de negocio cuando falta catálogo/parámetro de configuración | P3 | 9 | OPEN (observación; no clasificar como vulnerabilidad sin evidencia) |
| AUD-0038 | Derivar NUREJ Hijo desde un ya derivado → **HTTP 500** (`CannotDeriveNurejException` sin handler) | P2 | 11 | **CERRADO (2026-09-30)** (fix autorizado y aplicado: 422 vía handler en `bootstrap/app.php` + test) |
| AUD-0039 | El NUREJ Hijo hereda `via`/`reglamento_id` del padre: RN-10 `:171,:173` exige intervención de otra especialidad (Ac. 054/055) | P2 | 11 | **DECIDIDO (2026-09-30): Opción B adoptada** — `via_destino` explícito validado en servidor; mapeo vía→reglamento (JURIDICO→AC054, FINANCIERO→AC055); sin herencia automática; combinaciones salen de matriz normativa explícita. NO aplicado: pendiente matriz de derivaciones permitidas |
| AUD-0040 | `AmpliacionTest:175` falló 1 vez en suite completa (288 tests) y no reprodujo después (aislado 10/10 y suite limpia); candidato raíz: `ampliacionAsignar:108` usa `CatalogoActuado::first()` sin `ORDER BY` | P3 | 11 | **DECIDIDO (2026-09-30, cierre F12): MONITORIZAR — flake/no reproducido, causa raíz abierta.** Sin cambio en helper ni producto sin reproducción fiable o evidencia de causa raíz |
| AUD-0041 | `AdminDashboardController` no valida `activo` → ADMIN inactivo recibe 200 en el dashboard (el monitoreo sí devuelve 403) | P2 | 12 | **CERRADO (2026-09-30, con la validación de F12)** — fix AUTORIZADO y aplicado: check `activo`+rol en dashboard (patrón Monitoreo) + test `ReportesAdminTest`; pint OK; suite de cierre F12: 296: 289 OK · 1 fallo (AUD-0001, deuda aceptada) · 0 errores · 6 omitidos |
| AUD-0042 | Job `plazos:verificar-vencidos` falla con excepción no capturada (exit 1) si falta `ACT_ARCHIVO_POR_ABANDONO` o un ADMIN activo → RN-03 y marca `fuera_de_plazo` no corren (en BD dev **ya falla hoy**) | P2 | 13 | OPEN — fix propuesto (degradación controlada + log) NO aplicado (toca producto) |
| AUD-0043 | Corte UTC prematuro: comando corre 00:00 UTC = 21:00 ART y compara `fecha_limite` contra fecha UTC → archiva/marca hasta 3 h antes de la medianoche local que pide el SRS | P2 | 13 | **CERRADO (2026-10-05, tarea B3.3)** — `config/app.timezone` ahora `env('APP_TIMEZONE', 'America/La_Paz')` (default La Paz): `php artisan config:show app.timezone` → **America/La_Paz** (UTC-4, coincide con el sistema); verificado con `tests/Feature/TimezoneBoliviaTest.php` (5 tests: cierre 23:59:59 La Paz, transición de medianoche UTC↔La Paz, persistencia sin corrimiento) |
| AUD-0044 | Sidebar "Bandeja de entrada" visible para ENCARGADA/ADMIN → 403 en `/expedientes` | P2 | 14 | OPEN — requiere decisión: gatear enlace por rol vs habilitar ruta (esta última toca autorización) |
| AUD-0045 | Monitoreo: fallo de carga sin mensaje renderizado → "No se encontraron expedientes" (sin indicador de carga) | P2 | 14 | OPEN |
| AUD-0046 | Monitoreo: rótulo "Actualización automática" sin mecanismo de auto-refresco | P3 | 14 | OPEN |
| AUD-0047 | Vista `administrador\parametros` inalcanzable + "Guardar" que informa éxito sin persistir | P3 | 14 | OPEN — requiere decisión (eliminar / habilitar con API / congelar) |
| AUD-0048 | Login: "¿Olvidaste tu contraseña?" con `href="#"` sin ruta ni endpoint | P3 | 14 | OPEN |
| AUD-0049 | Login: errores sin adaptar al usuario (429 en inglés sin `Retry-After`; respuesta no-JSON → mensaje técnico crudo) | P3 | 14 | OPEN |
| AUD-0050 | Bandeja operador: `meta.total` nunca asignado → contador siempre vacío | P3 | 14 | OPEN |
| AUD-0051 | Apertura: plantilla `errorGral` jamás activada (UI inerte) | P3 | 14 | OPEN |
| AUD-0052 | Apertura: límite de 10 partes solo en cliente (sin `max` en servidor; valor sin respaldo en SRS) | P3 | 14 | OPEN — requiere decisión sobre si es regla de negocio |
| AUD-0053 | Dashboards admin y Encargada: refresco fallido conserva datos previos junto al banner de error | P3 | 14 | OPEN |
| AUD-0054 | Sorteo individual: el error queda invisible si el usuario cierra el modal durante la petición | P3 | 14 | OPEN |
| AUD-0055 | Sorteo: recarga a página fuera de rango tras sortear el último ítem → estado vacío + contador > 0 | P3 | 14 | OPEN |
| AUD-0056 | Usuarios: "Inactivar" visible sobre la propia cuenta → 422 siempre | P3 | 14 | OPEN |
| AUD-0057 | Layout: `cargarUsuario()` y `cerrarSesion()` fallan en silencio | P3 | 14 | OPEN |
| AUD-0058 | Salidas de consola con datos en vistas entregadas (`console.log` de la respuesta del API) | P3 | 14 | OPEN — candidato a F17 (hardening) |
| AUD-0059 | `welcome.blade.php` inalcanzable con enlace a `/dashboard` inexistente (código muerto) | P3 | 14 | OPEN |
| AUD-0060 | RF-04 sin interfaz: la evaluación de admisibilidad no es ejecutable desde la UI | P1 | 14 | OPEN — decisión de alcance de UI (mismo criterio que AUD-0035) |
| AUD-0061 | Operaciones con endpoints dedicados sin interfaz: planificación/VB/devolución, ampliación, cierre/reparto, transparencia, descargos | P1 | 14 | OPEN — decisión de alcance de UI |
| AUD-0062 | N+1 medido: `feriados` + `suspensiones_plazo` se recargan por cada expediente/plazo serializado (26 de 43 queries de la bandeja) | P2 | 15 | OPEN — fix PROPUESTO (unificar/cachear `PlazoCalculatorService`), pendiente de decisión; NO implementado |
| AUD-0063 | Dashboard admin carga la tabla completa de expedientes (con plazos) y la consulta 5 veces por petición | P2 | 15 | OPEN — fix PROPUESTO (agregados en SQL, patrón del dashboard Encargada), pendiente de decisión; NO implementado |
| AUD-0064 | Sin índice en `expedientes.fecha_ingreso` → `Using temporary; Using filesort` en bandejas y monitoreo | P3 | 15 | OPEN — índice PROPUESTO (justificado por patrón real), pendiente de decisión; NO creado |
| AUD-0065 | `sesiones_acceso` sin índice en `login_at` / `(exitoso, login_at)` → `type=ALL` + filesort en el dashboard admin; crece con cada login | P3 | 15 | OPEN — índice PROPUESTO, pendiente de decisión; NO creado |
| AUD-0066 | Cron diario de plazos hace full scan de `plazos` (sin índice `(estado, fecha_limite)`) | P3 | 15 | OPEN — índice PROPUESTO, pendiente de decisión; NO creado |
| AUD-0067 | Búsqueda por NUREJ/resumen con `LIKE '%…%'` → full scan no indexable; única búsqueda del sistema y sin endpoint dedicado | P3 | 15 | OPEN — rediseño de búsqueda PROPUESTO, pendiente de decisión |

---

## Fichas

### AUD-0001 — `ExpedientePolicy` da acceso total a rol ADMIN
- **Severidad:** P1 (seguridad / RF-03 compartimentos)
- **Fase:** 0 (detectado) · 1 (evidencia recopilada) · **fix aplicado en tarea B2.0 (2026-10-05)**
- **Estado:** **CERRADO (2026-10-05)** — fix en `app/Policies/ExpedientePolicy.php`: eliminada la rama `CODIGO_ADMIN` con `return true` en `view()` (antes `:80-82`), sin tocar `esRolConAccesoCatalogos()` ni `crearActuado()` y **sin modificar el test**. Validación: `SecurityCompartimentosTest` **9/9 verde** (incluye `:165`, que espera `assertForbidden()`); `php artisan test --compact` → **310 tests · 304 OK · 0 fallos · 6 omitidos · 1453 aserciones**; `vendor/bin/pint --dirty --format agent` → aplicado (solo estilo) y revalidado en verde. *(Historial: decisión 2026-09-30 de "deuda conocida aceptada" en F12 — resuelta por este fix.)*
- **Código:** `app/Policies/ExpedientePolicy.php:80-82` — rama `CODIGO_ADMIN` con `return true` en `view()`, sin comprobar asignación activa.
- **Afecta:** `ExpedienteController@show` (`:85`), `WorkstationController@detalle` (`:35`), `EvaluacionAdmisibilidadController@requisitos` (`:26`), `AdjuntoController@descargar` (`:22`, vía `authorize('view', $expediente)`), por tanto la descarga de archivos.
- **Test en conflicto:** `tests/Feature/SecurityCompartimentosTest.php:165` — `it('bloquea a ADMIN sin asignacion para ver un expediente ajeno (sin bypass indebido)')` → espera `assertForbidden()` (línea 179); ejecución real: 200. **No ajustar el test para que pase.**

**Evidencia de requisitos recopilada en Fase 1 (solo lectura):**

| Dimensión | Evidencia | Lectura |
| --- | --- | --- |
| SRS — perfil ADMIN | `SRS_EXTRAIDO.txt:84-86`: "Administrador (Sistemas)… Gestiona usuarios, feriados y catálogos. **Capacidad de Monitoreo: Puede visualizar el estado y ubicación del expediente para soporte. Restricción: No puede registrar causas, ver el contenido de los archivos ni mover trámites o interferir en el flujo.**" | Existe una capacidad de visualización limitada ("estado y ubicación") **y** una restricción explícita de no ver contenido de archivos. El bypass actual otorga `view` completo, que además habilita descarga de adjuntos — tensión con la restricción. |
| SRS — RF-03 | `SRS_EXTRAIDO.txt:108`: "Los usuarios visualizarán e interactuarán exclusivamente con las solicitudes asignadas formalmente a su perfil… Intentar acceder a otros casos retornará un error de 'Acceso Denegado'." | RF-03 no menciona excepción para ADMIN; el SRS de roles sí da una capacidad de monitoreo. **Ambigüedad a dirimir** (¿`view` de detalle = "estado y ubicación"?, ¿los adjuntos quedan excluidos?). |
| Matriz de roles y permisos | `Rol` constants (`ENCARGADA`, `TECNICO`, `AUD_JURIDICO`, `AUD_FINANCIERO`, `ADMIN`) + `database/seeders/RolSeeder.php:16-20` (`ADMIN` = "Administración del sistema"). SRS: ADMIN es "Nivel 3 (Configuración)", no participa del flujo. | ADMIN no tiene asignaciones posibles (no opera bandejas) → con regla estricta de asignación, nunca vería nada; por eso el `return true` es coherente con "monitoreo" pero colisiona con RF-03 y con la restricción de archivos. |
| Policies relacionadas | `UsuarioPolicy` (todo ADMIN), `FeriadoPolicy::gestionar` (ADMIN), `ExpedientePolicy::esRolConAccesoCatalogos` incluye ADMIN (catálogos de solo lectura). | El patrón del sistema da a ADMIN solo **catálogos y gestión administrativa**; el bypass de `view` es la única concesión sobre expedientes individuales. |
| Middleware/guards | `EnsureAdmin` solo aplicado a rutas **web** `/administrador/*` (`routes/web.php:45`): valida login + activo + rol. Rutas `api/admin/*` dependen de policy por controlador. | Sin bypass adicional; el bypass vive únicamente en la policy. |
| Menús visibles para ADMIN | `resources/views/layouts/app.blade.php:167-203` (`template x-if="usuario?.rol === 'ADMIN'"`: dashboard, usuarios, monitoreo, feriados). No hay enlace de ADMIN a detalle de expedientes. | El frontend **no** presenta a ADMIN bandejas ni detalle de expediente: la visualización "para soporte" del SRS no tiene UI propia (se llegaría por URL directa). |
| ADMIN técnico vs operativo | Solo existe un rol ADMIN (no hay distinción ADMIN-sistema/ADMIN-operativo en constants, seeders ni SRS). | No existe tal distinción en el código: la decisión debe ser sobre un único rol. |
| Efecto colateral | `UsuarioPolicy::viewOperativos` fue modificado (versión anterior comentada en `:14-22`) para incluir ADMIN además de ENCARGADA. | Indica que la ampliación de privilegios de ADMIN ya se tocó antes por la misma lógica; conviene revisarla en Fase 3/6 junto con este hallazgo. |

**Hipótesis abiertas (sin confirmar):** H1) ADMIN debe poder ver estado/ubicación de cualquier expediente pero **no** el contenido (los adjuntos deberían quedar fuera del bypass); H2) RF-03 aplica sin excepción y el bypass es un defecto; H3) se necesita un endpoint/visión "de soporte" propio (nurej + estado + ubicación) en lugar de `view` completo. **Resolución: Fase 3 (auditoría de seguridad) + confirmación explícita del usuario.** No se modifica policy ni test hasta entonces.

**Impacto:** el test de compartimentos RF-03 no puede pasar; el bypass alcanza también descarga de adjuntos.

### AUD-0002 — Falla de fecha en `DescargoFinancieroTest` (plazos)
- **Severidad:** P1 (RF-07 cálculo de plazos)
- **Fase:** 0 (detectado) · 1 (contexto recopilado) · **5 (análisis de raíz COMPLETADO — `MATRIZ_PLAZOS.md` §7)**
- **Estado:** **CERRADO (2026-09-30)** — fix del test autorizado por el usuario
  y aplicado: se congela el tiempo en el test (`tests/Feature/DescargoFinancieroTest.php:157-161`
  `Carbon\Carbon::setTestNow('2026-09-15 10:00:00')` + reset `:214-215`),
  reproduciendo exactamente las expectativas `:188` (2026-09-23) y `:208`
  (2026-09-25). **El servicio NO se modificó** (`DescargoFinancieroService`
  intacto; su cálculo era correcto — H2 descartada). Evidencia: el archivo
  pasa 5/5 y la suite completa pierde 1 fallo (2 → 1); quedan solo AUD-0001 y
  AUD-0003.
- **Hechos verificados (Fase 1/5, solo lectura):**
  - El test **no** congela el tiempo (grep: 0 `travelTo`/`setTestNow` en `tests/Feature/DescargoFinancieroTest.php`); el servicio usa `now()` → `DescargoFinancieroService@abrirSubRelojDescargos:270-271` → `calculateDueDate(now(), 5)`.
  - Ejecución 2026-09-29: calcula **2026-10-06** (mié30,jue1,vie2,–,–,lun5,mar6). Aserción fija espera **2026-09-23** (`:188`).
  - Con `now = 2026-09-15` (fecha autoral): feriados 15/16 creados por el propio test (`:153-154`) → 17,18,21,22,23 = **2026-09-23** ✓. La aserción `:208` (`2026-09-25` = `now()->addDays(10)` del helper `:145`) solo cuadra con `now = 2026-09-15` también.
- **Conclusión Fase 5:** **H1 confirmada** (fechas de calendario fijas dependientes de la fecha de ejecución, autoral ≈ 2026-09-15). **H2 descartada**: el motor cumple CA-1 (`SRS :181`) y RN-09 (5 días hábiles AC055) — la aserción es exactamente lo que el motor produce con `now = 2026-09-15`.
- **Corrección aplicada (2026-09-30, en los términos autorizados por el usuario):** congelar el tiempo en el test. **El servicio no se tocó: su cálculo es correcto.**

### AUD-0003 — `EncargadaDashboardTest`: `Usuario::getJson()` inexistente
- **Severidad:** P2 (tests rotos)
- **Fase:** 0
- **Estado:** **CERRADO (2026-09-30)** — fix del test autorizado por el usuario y aplicado en Fase 10.
- **Evidencia:** 5 tests fallaban con `Call to undefined method App\Models\Usuario::getJson()`.
- **Raíz (Fase 10, verificada):** `tests/Feature/EncargadaDashboardTest.php:149-150`
  encadenaba `Sanctum::actingAs($usuario, ['*'])->getJson('/api/encargada/dashboard')`;
  `Sanctum::actingAs()` devuelve el `Usuario`, no el TestCase → error. Solo ese
  test usaba la cadena; los demás (`:79-81`, `:137-139`) separan sentencias y pasaban.
- **Impacto (antes del fix):** 5 errores del baseline; no era bug de producto —
  la ruta está protegida (variante correcta del mismo escenario pasa en `:65`;
  la data set solo varía rol/activo).
- **Corrección aplicada (2026-09-30, en los términos autorizados por el usuario):**
  separar en dos sentencias (`Sanctum::actingAs(...);` y luego `$this->getJson(...)`)
  **solo en `EncargadaDashboardTest:149-150`**. Ni producción, ni servicios,
  policies, controllers, seeders ni BD.
- **Verificación:** `vendor/bin/pint --dirty` → passed; `php artisan test --compact`
  → **284: 277 OK · 1 fallo (AUD-0001) · 0 errores · 6 omitidos** — los 5 errores
  desaparecen, sin regresiones (único rojo restante: fallo AUD-0001 del baseline).

### AUD-0004 — Ruta de inactivar usuario duplicada
- **Severidad:** P2 (superficie de API)
- **Fase:** 0
- **Estado:** OPEN
- **Evidencia:** `POST /api/usuarios/{usuario}/inactivar` y `POST /api/admin/usuarios/{usuario}/inactivar`.
- **Impacto:** Doble mantenimiento y doble política de autorización que puede divergir.
- **Acción propuesta:** consolidar en una sola y actualizar llamadas del frontend (`resources/views`/`app.js`); verificar antes qué usa el JS real.

### AUD-0005 — Backlog JIRA previo desactualizado
- **Severidad:** P3 (documentación)
- **Fase:** 0
- **Estado:** OPEN
- **Evidencia:** `doc_avances/BACKLOG_JIRA.md` afirma que dashboard y CRUD de feriados no existen (sí existen) y menciona 234 tests (hay 252).
- **Impacto:** Riesgo de planificar trabajo ya hecho si se usa como insumo.
- **Acción propuesta:** tratarlo como histórico; usar `MAPA_FUNCIONAL.md` como fuente de verdad.

### AUD-0006 — CDNs públicos en el layout
- **Severidad:** P2 (seguridad de red, sistema gubernamental)
- **Fase:** 0
- **Estado:** OPEN
- **Evidencia:** `resources/views/layouts/app.blade.php:8-10` (Tailwind/FontAwesome/Alpine por CDN público).
- **Impacto:** La app depende de Internet externo; en red restringida la UI degrada
  y se expone a terceros (supply chain).
- **Acción propuesta:** evaluar en Fase 17 localizar assets (Vite/npm). Requiere aprobación (cambia dependencias/build).

### AUD-0007 — Doble mecanismo de sesiones
- **Severidad:** P3 (informativo)
- **Fase:** 0
- **Estado:** OPEN
- **Evidencia:** tablas `sessions` (driver de Laravel) y `sesiones_acceso` (dominio propio).
- **Impacto:** Confusión posible al auditar autenticación; dos fuentes de verdad.
- **Acción propuesta:** documentar en Fase 3 cuál cubre qué requisito; no tocar sin confirmación.

### AUD-0008 — Informes finales Técnico/Jurídico faltantes
- **Severidad:** P1 (E5-S3 / funcionalidad crítica)
- **Fase:** 0 · evidencia refinada en Fase 1
- **Estado:** OPEN — **matiz de Fase 1/2:** el catálogo `catalogo_actuados` **sí** define `ACT_INFORME_FINAL` (rol AUD_JURIDICO, seeder:62) e informes financieros con/sin responsabilidad (seeder:82-83). Conteo contra SRS `:374-413`: **Técnico exige 4 informes → hay 0**; **Jurídico exige 2 (CON/SIN Responsabilidad) → hay 1 genérico**; **Financiero exige 2 → hay 2 ✅**. No hay endpoint/vista/flujo específico de informes finales (todo dependería del actuado genérico). **Fase 8:** además, el único informe jurídico existente **no es emisible** (`estado_nuevo_id` NOT NULL vs destino `null` → 500) — ver **AUD-0033**; y sin interfaz — ver **AUD-0035**.
- **Evidencia:** `database/seeders/CatalogoActuadoSeeder.php:62,82-83`; grep de "informe" en `app/**/*.php` → 12 coincidencias (constantes `CODIGO_INFORME_FINANCIERO_*` y `validarFaseDescargosPrevia` en `DescargoFinancieroService:22-34,152-171`, comentario `CierreExpedienteService:41`) — **sin controllers/vistas dedicados**; grep en `resources/views/**/*.blade.php` → **0 coincidencias**.
- **Impacto:** el ciclo de cierre E5-S3 no puede auditar/validar informes finales por rol.
- **Acción propuesta:** Fase 2 define el alcance exacto del SRS (qué exige E5-S3 por perfil); implementación en fase posterior. No cerrar con la evidencia actual.

### AUD-0009 — Catálogo de actuados incompleto + sin Enmienda (RF-02)
- **Severidad:** P1
- **Fase:** 0
- **Estado:** OPEN
- **Evidencia:** tabla `actuados` + seeder/enum de tipos verificados contra RF-02 del SRS: faltan tipos (incluido Enmienda).
- **Impacto:** No se pueden registrar actuaciones requeridas por el RF-02.
- **Acción propuesta:** comparar catálogo real vs lista RF-02 en Fase 2; migración de catálogo con confirmación previa.

### AUD-0010 — Transferencias de expediente sin endpoints (RF-05)
- **Severidad:** P1
- **Fase:** 0
- **Estado:** OPEN
- **Evidencia:** tabla `transferencias` existe; sin rutas/controller/lógica de transferencia (verificado en `routes/*.php`).
- **Impacto:** RF-05 no implementado; la tabla está huérfana de API.
- **Acción propuesta:** diseñar en Fase 6+ con transacción, audit log y autorización; no inventar flujo sin SRS.

### AUD-0011 — Reportes y exportaciones inexistentes
- **Severidad:** P1 (RF-R01…R09, carátula PDF)
- **Fase:** 0
- **Estado:** OPEN
- **Evidencia:** sin controllers/endpoints de reportes ni generación de PDF/carátula (grep sobre rutas y `app/`). **Fase 12 (2026-09-30) confirma con matriz completa:** `MATRIZ_REPORTES.md` §1 — pantalla parcial 5/9, **Excel 0/9, PDF 0/9**; 0 paquetes de export en `composer.json`, 0 vistas con export; filtros `:118` (fecha/usuario/estado): solo `estado` existe y es exclusivo de ADMIN (`AdminMonitoreoController:302`), sin filtro de fecha.
- **Impacto:** 9 reportes + carátula requeridos por el SRS no disponibles.
- **Acción propuesta:** Fase 9 (reportes); definir primero fuente de datos de cada RF-R por SRS.

### AUD-0012 — Sin CRUD de suspensiones ni recálculo retroactivo (RF-07)
- **Severidad:** P1
- **Fase:** 0
- **Estado:** OPEN
- **Evidencia:** sin rutas/modelos de `suspensiones_plazo`; no hay recálculo de plazos abiertos al suspender.
- **Impacto:** RF-07 incompleto; plazos pueden vencer indebidamente.
- **Acción propuesta:** Fase 4 (plazos); diseñar recálculo con tests de casos borde.

### AUD-0013 — Administración de catálogos/parámetros incompleta en Admin
- **Severidad:** P2
- **Fase:** 0
- **Estado:** OPEN
- **Evidencia:** panel Admin existe pero no cubre todos los catálogos/parámetros (ver mapa funcional).
- **Impacto:** Catálogos solo modificables por seed/manual.
- **Acción propuesta:** listar faltantes exactos en Fase 6 y priorizar.

### AUD-0014 — Falta `GET /api/catalogo/requisitos?reglamento_id=`
- **Severidad:** P2 (E2-S2)
- **Fase:** 0
- **Estado:** OPEN
- **Evidencia:** no existe endpoint de catálogo de requisitos filtrado por reglamento.
- **Impacto:** Frontend de checklist de requisitos no puede poblar por reglamento.
- **Acción propuesta:** endpoint simple con policy; Fase 6.

### AUD-0015 — Excusas/recusaciones (Épica 6) no existen
- **Severidad:** P1
- **Fase:** 0
- **Estado:** OPEN
- **Evidencia:** sin tablas, modelos ni rutas de excusa/recusación (grep sobre migraciones y rutas).
- **Impacto:** Épica 6 completa ausente.
- **Acción propuesta:** Fase 8; requerimiento exacto pendiente de Fase 2 (SRS).

### AUD-0016 — `app/Models/Transferencia.Php` con mayúscula
- **Severidad:** P2 (portabilidad)
- **Fase:** 0
- **Estado:** OPEN
- **Evidencia:** nombre de archivo `Transferencia.Php` (extensión con mayúscula).
- **Impacto:** en Linux (case-sensitive) el autoloader PSR-4 no encuentra la clase → error 500 al usar el modelo.
- **Acción propuesta:** renombrar a `Transferencia.php` + verificar usos; paso de 1 minuto, confirmar antes de tocar.

### AUD-0017 — Autorización inline por rol en endpoints del panel Admin (API)
- **Severidad:** P2 (arquitectura de autorización / regla del proyecto)
- **Fase:** 1
- **Estado:** OPEN
- **Evidencia:**
  - `app/Http/Controllers/Administrador/AdminDashboardController.php:26-31`: `if (($usuario->rol?->codigo ?? null) !== Rol::CODIGO_ADMIN) { abort(403, ...); }` dentro de `index()`.
  - `app/Http/Controllers/Administrador/AdminMonitoreoController.php:24-33`: chequeo equivalente con `abort(403)`.
  - Rutas `GET /api/admin/dashboard` y `GET /api/admin/monitoreo` solo llevan middleware `api + auth:sanctum + throttle:api` (verificado en `route:list --json`): **no** pasan por `EnsureAdmin` (ese middleware solo protege las rutas web `/administrador/*`, `routes/web.php:45`).
  - El resto de endpoints admin sí usan policies (`UsuarioPolicy::gestionar/activar`, `FeriadoPolicy::gestionar`).
  - **Fase 12 reconfirma** los chequeos inline: `AdminDashboardController:28-29`, `AdminMonitoreoController:31` (grep directo, 2026-09-30).
- **Impacto:** la lógica de autorización queda dispersa en controladores (regla del proyecto: "Autorización con Policies o Gates, nunca `if ($user->role == ...)` disperso en controladores" — `.opencode/rules/laravel-skills.md` §3). Funciona hoy, pero es propensa a olvidos al añadir métodos y no es testeable vía policy.
- **Acción propuesta:** migrar a policy (`gestionar` de Usuario/Feriado o policy nueva) en Fase 3/6, con test; **no tocar aún** (cambio de autorización → requiere confirmación explícita).

### AUD-0018 — RNF-03: sin paginación server-side ni búsqueda
- **Severidad:** P2 (RNF-03 del SRS)
- **Fase:** 2
- **Estado:** OPEN
- **Evidencia:** grep en `app/**/*.php`: `paginate(`/`simplePaginate`/`cursorPaginate` = **0**; solo `limit/take` en `EncargadaDashboardService:71,166,191,195`; grep en `routes/` de `buscar|search` = **0** rutas. `GET /api/bandeja` devuelve el conjunto completo de expedientes del usuario. **Fase 12 reconfirma:** `AdminMonitoreoController:113` `$query->get()` sin paginar (24 filas en dev, sin límite).
- **Impacto:** con volumen histórico masivo (contexto del SRS), las bandejas cargan todo de golpe y no hay búsqueda; RNF-03 lo exige explícitamente.
- **Acción propuesta:** paginación server-side + búsqueda (Fase 15); sin dependencias nuevas (el paginador es nativo de Laravel).

### AUD-0019 — RN-02: rango de días de evaluación no parametrizable
- **Severidad:** P2 (regla de negocio / plazos)
- **Fase:** 2
- **Estado:** OPEN
- **Evidencia:** SRS `:137` exige "Tres (3) o cinco (5) días hábiles **según la complejidad del memorial** y su reglamento"; `ParametroPlazoSeeder` fija AC054=5 y AC055=5 (líneas 23,25) y no existe campo "complejidad" en el flujo de evaluación.
- **Impacto:** el caso "3 días" es inalcanzable; no hay selector de complejidad. ¿Defecto, o el SRS se satisface con 5 fijo? **A determinar con el usuario** (no cambiar parámetros sin confirmación).
- **Acción propuesta:** preguntar en Fase 4/6; si exige complejidad, diseño de campo + efecto en plazos.
- **Decisión (2026-09-30): confirmado P2 — la decisión de diseño es parametrizar el plazo dentro del rango permitido (3-5 días): la complejidad determina si corresponde 3, 4 o 5 días; NO asumir automáticamente 5 como equivalente a "3-5 según complejidad". Fix NO aplicado: primero documentar cómo se determina la complejidad y qué componente debe ser la fuente de esa decisión.**

### AUD-0020 — Endpoint genérico de actuados no valida el estado de origen
- **Severidad:** P1 (integridad procesal / máquina de estados)
- **Fase:** 4
- **Estado:** OPEN — requiere decisión explícita del usuario antes de tocar código (cambio de autorización/reglas de negocio).
- **Evidencia:** `catalogo_actuados.estado_origen_id` **no se lee en ningún punto de `app/`** (grep verificado). `StoreActuadoRequest` solo valida rol+asignación (`crearActuado`) y el Bloqueo de Salida para informes financieros (`:53-65`); `ActuadoService@registerActuado:88-120` aplica `estado_destino_id` sin contrastar el estado actual. Los endpoints específicos sí validan su estado (evaluación, planificación, ampliación, impugnación, cierre, transparencia, descargos, sorteo — ver `MAQUINA_ESTADOS.md` §2/§4).
- **Impacto:** un usuario autenticado con rol del catálogo (+ asignación activa, o ENCARGADA exenta) puede vía `POST /api/expedientes/{e}/actuados`: (a) forzar saltos de estado (ej. ENCARGADA emite `ACT_REPARTO_INSTITUCIONAL` desde cualquier estado → `CONCLUIDO_REMITIDO`); (b) re-emitar actuados (duplicar transiciones); (c) cerrar expedientes **sin informe final** en AC022/AC054 (el Bloqueo de Salida solo protege los informes financieros). RF-02/RF-06 exigen transiciones exclusivamente por actuados válidos.
- **Acción propuesta:** validar en `ActuadoService@registerActuado` (o en `StoreActuadoRequest`) que `estado_origen_id` sea null o coincida con `estado_actual_id`, con test negativo; **solo tras confirmación del usuario**.
- **Decisión (2026-09-30): mantener P1 y OPEN — el endpoint genérico debe validar el estado origen esperado antes de permitir la transición; el hecho de que el estado destino sea válido NO sustituye la validación del estado actual. No autorizar implementación hasta completar la matriz explícita `actuado → estado_origen permitido → estado_destino`.**

### AUD-0021 — Huecos del grafo de estados (cierre AC022/054 y estado ADMITIDO)
- **Severidad:** P1 (flujo funcional completo)
- **Fase:** 4
- **Estado:** OPEN — AMBIGÜEDAD de diseño, resolver con el usuario (SRS no detalla el grafo interno).
- **Evidencia:**
  1. `ACT_INFORME_FINAL` (único informe de Técnico/Jurídico) tiene `estado_destino_id = null` (seeder `:62`) → no-op; solo los informes **financieros** llegan a `PENDIENTE_VISTO_BUENO_FINAL` (seeder `:82-83`). `CierreExpedienteService@aprobarVistoBueno` exige ese estado (`:56`) → **la vía Técnico/Jurídico no alcanza el cierre por arista válida** (los tests siembran el estado a mano: `CierreExpedienteTest:272`).
  2. Tras la revocación, el expediente queda en `ADMITIDO` (`ImpugnacionService:142-153`, destino del seeder `:70`) y **ningún actuado tiene `estado_origen = ADMITIDO`**; `cargarPlanificacion` exige `EN_PLANIFICACION` (policy `:175`) → 403. Ningún test encadena revocación → planificación.
  3. Estados huérfanos: `EN_INVESTIGACION`, `EN_DESCARGOS`, `CONCLUIDO` no referenciados por ningún código ni actuado.
- **Impacto:** RN-09/RF-06 (flujo de cierre por informes) y el ciclo post-impugnación no son completables por los endpoints formales; solo por el bypass AUD-0020.
- **Acción propuesta:** decidir con el usuario el grafo canónico (destino del informe AC022/054, arista de salida de ADMITIDO, destino de huérfanos); luego ajustar seeder + test de integración extremo a extremo. **No modificar seeders sin confirmación.**
- **Decisión (2026-09-30): mantener P1 y OPEN — la máquina de estados tiene caminos documentados hoy inalcanzables o con estados huérfanos; NO aceptar el grafo actual como definitivo. Antes de corregir, construir la matriz completa `estado → actuados de salida → destinos → roles`; la solución debe respetar el SRS y NO inventar transiciones nuevas.**

### AUD-0022 — Los tests no validan el catálogo real (fixtures divergentes)
- **Severidad:** P2 (cobertura/confiabilidad de la suite)
- **Fase:** 4
- **Estado:** OPEN
- **Evidencia:** grep en `tests/`: cero menciones de `CatalogoEstadoSeeder`/`CatalogoActuadoSeeder`; cada test crea sus filas con `factory()`/`create()`. Ejemplo divergente: `RelojProcesualTest:131` afirma `ACT_ADMISION → ADMITIDO` y `:160` `VB → EN_INVESTIGACION`, mientras el seeder real dice `→ EN_PLANIFICACION` y `→ EN_EJECUCION`.
- **Impacto:** la suite valida grafos propios, no el grafo de producción; los huecos de AUD-0021 no pueden detectarse con la suite actual. (Las fixtures aisladas son práctica válida, pero aquí sostienen afirmaciones que contradicen la configuración real.)
- **Acción propuesta:** Fase 16 — añadir 1-2 tests de integración que siembren con los seeders reales y recorran el flujo extremo a extremo (registro→cierre y revocación→planificación).

### AUD-0023 — BD de desarrollo desactualizada respecto a los seeders
- **Severidad:** P2 (ambiente de desarrollo; no es código de producción)
- **Fase:** 4
- **Estado:** OPEN — **requiere confirmación del usuario para correr `php artisan db:seed`** (los seeders de demo `ExpedienteDemo*` no son idempotentes demostradamente; además `CatalogoEstadoSeeder` elimina la fila legacy `EN_VISTO_BUENO_FINAL`).
- **Evidencia (query directa 2026-09-29):** `catalogo_actuados` = **7** filas (faltan 18: planificación, impugnación, ampliación, cierre, transparencia, descargos, informes financieros, archivo automático, NUREJ Hijo); `catalogo_estados` = **16** filas (faltan 6: `PENDIENTE_VISTO_BUENO`, `PENDIENTE_APROBACION_AMPLIACION`, `PENDIENTE_VISTO_BUENO_FINAL`, `LISTO_PARA_REPARTO`, `PENDIENTE_REMISION_TRANSPARENCIA`, `EN_IMPUGNACION`; sobra `EN_VISTO_BUENO_FINAL` legacy); **`parametros_plazo` = 8 de 17** (faltan AC022 `EJECUCION_AMPLIADA`/`IMPUGNACION_*`, AC054/055 `PLANIFICACION`/`IMPUGNACION_*` — ampliado en Fase 5, `MATRIZ_PLAZOS.md` §2.2).
- **Impacto:** en el entorno dev, flujos como planificación/impugnación/cierre fallarían con `firstOrFail` de catálogo; `AmpliacionService`/`ImpugnacionService` caen con `firstOrFail` de parámetro; `ActuadoService:193-195` **no abre relojes en silencio** cuando falta el parámetro; cualquier prueba manual en dev es engañosa.
- **Acción propuesta:** preguntar al usuario si procede re-sincronizar catálogos (ideal: comando dedicado y idempotente solo de catálogos, no la carga demo completa — a diseñar con aprobación).
- **Decisión (2026-09-30): mantener P2 y OPEN — NO ejecutar `db:seed` completo ni ningún re-seed destructivo. Preparar un mecanismo de sincronización/seed idempotente de catálogos; antes de ejecutarlo, identificar exactamente qué seeders modifica y garantizar que no reprocesa datos demo de forma peligrosa; la ejecución sobre la BD dev requerirá autorización específica posterior. (BD dev: 8/17 parámetros de plazo vs 17 definidos en el seeder; faltantes ya producen comportamientos distintos/errores en desarrollo.)**
- **Sub-caso asociado (cierre F12, 2026-09-30):** **O-7** queda bajo esta ficha — en BD dev `IMPUGNACION_RESOLVER` está **ausente (0 filas, verificado 2026-09-30)** y el fallback `ImpugnacionService:87 → now()` produce un límite de resolución inmediato. Riesgo/candidato de corrección **asociado a la sincronización de parámetros**; sin ficha P2 independiente y sin modificar el comportamiento.

### AUD-0024 — RN-05: plazo de 15 días "Administrativa" inalcanzable
- **Severidad:** P1 (RN-05 / SRS `:144-149`: plazo mal impuesto a todo caso Administrativa)
- **Fase:** 5
- **Estado:** OPEN — requiere decisión del usuario (diseño de campo en `expedientes` + alta, o declarar fuera de alcance).
- **Evidencia:** `ActuadoService@resolveSubtipoEjecucion:230-233` devuelve `'JURISDICCIONAL'` siempre (docblock `:225-229`: "ADMINISTRATIVA… es una historia futura"). No existe atributo de naturaleza: migración de `expedientes` solo tiene `via` (que es `TECNICO|JURIDICO|FINANCIERO`, `StoreExpedienteRequest:22`); grep `naturaleza|jurisdic|ADMINISTRATIVA` en `app/` → 0 usos.
- **Impacto:** todo caso AC022 corre con 10 días; el parámetro AC022 EJECUCION ADMINISTRATIVA=15 (`ParametroPlazoSeeder:25`) es configuración muerta. Los casos Administrativa vencen 5 días hábiles antes de lo normativo (RN-05, Art. 27 II Ac. 022).
- **Acción propuesta:** añadir columna/enum de naturaleza en `expedientes` (alta + resource) y consumirla en `resolveSubtipoEjecucion`; o aceptar 10 días universales con visto bueno explícito del usuario.
- **Decisión (2026-09-30): confirmado P1 — NO declarar fuera de alcance. El SRS exige 10 días para jurisdiccional y 15 para administrativa; hoy no existe una naturaleza del expediente que permita la bifurcación y todo AC022 termina en JURISDICCIONAL. Decisión de diseño: incorporar una representación explícita de `naturaleza` (JURISDICCIONAL/ADMINISTRATIVA) que determine el plazo de ejecución; NO reutilizar `via` (via identifica motor/especialidad, no naturaleza). NO aplicar el cambio de esquema todavía: primero presentar impacto de migración, alta, edición/enmienda, plazos y tests.**

### AUD-0025 — RN-04: plazo de PLANIFICACION=2 para MPA de auditores (SRS: "sin plazo estricto")
- **Severidad:** P2 (regla de negocio / plazos; no bloquea, solo estampa fuera_de_plazo)
- **Fase:** 5
- **Estado:** OPEN — requiere decisión del usuario (conservar / eliminar / parametrizar por reglamento).
- **Evidencia:** SRS tabla comparativa `:275-279` y RN-04 `:141-143`: el Técnico tiene 2 días para el Cronograma, **los Auditores elaboran el MPA sin plazo estricto predefinido**. El código abre PLANIFICACION=2 para AC054/055 también (`MAPA_TIPO_PLAZO['ACT_ADMISION']`, `ActuadoService:36`; `ParametroPlazoSeeder:28,30`).
- **Impacto:** semáforo y `fuera_de_plazo` sobre un plazo que el SRS no contempla para el MPA (CA-3 no bloquea, pero penaliza); además en BD dev ni siquiera se abre (falta el parámetro, AUD-0023) → comportamiento distinto por entorno.
- **Acción propuesta:** decidir con el usuario; si el SRS manda, eliminar el parámetro PLANIFICACION de AC054/055 (con su test) o hacerlo informativo.
- **Decisión (2026-09-30): confirmado P2 — eliminar el plazo rígido de 2 días para el MPA de Jurídico/Financiero (el SRS distingue al Técnico con 2 días y los Auditores elaboran el MPA sin plazo estricto predefinido). Decisión de diseño: AC022 Técnico → PLANIFICACION = 2 días; AC054/AC055 → MPA sin reloj rígido de 2 días; la fecha de ejecución aprobada continúa siendo la del MPA. NO aplicar aún: primero presentar el cambio mínimo y sus tests.**

### AUD-0026 — Ampliación aprobada sobre ejecución ya vencida
- **Severidad:** P3 (caso límite; no bloquea)
- **Fase:** 5
- **Estado:** OPEN
- **Evidencia:** los plazos EJECUCION vencidos permanecen VIGENTE por diseño CA-3 (`MarcarPlazosVencidosService:15-17`); `AmpliacionService:106-109` calcula `calculateDueDate($plazoOriginal->fecha_limite, 5)` → si el límite original ya pasó, el límite ampliado también puede estar en el pasado o acortarse (la ventana +5 no se cuenta desde la aprobación).
- **Impacto:** una ampliación aprobada tarde otorga menos (o ningún) tiempo real.
- **Acción propuesta:** validar con el usuario si el cálculo debe ser `max(límite original, hoy) + 5`; bajo riesgo, solucionable con un guard.

### AUD-0027 — `usuario_destino_id` controlado por el cliente en el endpoint genérico de actuados
- **Severidad:** P2 (integridad del enrutamiento de bandejas / RF-03 asignación formal)
- **Fase:** 6
- **Estado:** OPEN — requiere decisión del usuario (cambio de reglas de negocio).
- **Evidencia:** `StoreActuadoRequest:36` valida `usuario_destino_id` solo como `exists:usuarios,id` (sin `activo`, sin rol ni destino institucional); `ActuadoController:30-31` lo pasa al servicio; `ActuadoService@reasignarBandeja:140-156` cierra la asignación activa vigente y crea la del destino.
- **Impacto:** un operador con asignación (o la ENCARGADA, exenta) puede mover el expediente vía `POST /api/expedientes/{e}/actuados` a **cualquier usuario** — incluido uno inactivo (bandeja huérfana, ver AUD-0029) o un rol que no puede tramitarlo (bloqueo del flujo) — rompiendo que "¿A dónde va después?" lo determine el actuado (Plan §16). Los flujos específicos sí hardcodean el destino; el hueco es del endpoint genérico.
- **Acción propuesta:** whitelist de destino por código de actuado (o eliminar el parámetro y dejar solo servicios específicos) + exigir `activo`. No aplicar sin aprobación.
- **Decisión (2026-09-30): mantener P2 y OPEN — el destino de una reasignación NO debe quedar bajo control arbitrario del cliente: `usuario_destino_id` debe validarse contra usuario activo + rol compatible + destino institucional permitido; la autorización final debe estar en servidor; no confiar en que la UI limite las opciones. NO aplicar todavía: primero producir la matriz `actuado → roles/destinos permitidos`.**

### AUD-0028 — Sin bandeja de supervisión/acción para la Encargada
- **Severidad:** P1 (módulo crítico Plan §7/§8.1; control jerárquico RN-07)
- **Fase:** 6
- **Estado:** OPEN — requiere definición de alcance con el usuario.
- **Evidencia:** `MATRIZ_BANDEJAS.md` §5. No existen endpoints/listados de: cronogramas pendientes de VB, MPA pendientes, informes pendientes, devoluciones, solicitudes de revisión ni derivaciones (solo contadores en `EncargadaDashboardService:174-199`); `GET /api/admin/monitoreo` (listado con filtros) es **solo-ADMIN** (`AdminMonitoreoController:26-32`); la web `/expedientes` devuelve 403 a la Encargada (`WorkstationController:15` + `ExpedientePolicy:53-64`); los expedientes aparqueados en su asignación solo son legibles vía `GET /api/bandeja` (ruta sin UI para ella).
- **Impacto:** la Encargada no puede identificar "¿Qué tengo que hacer ahora con este expediente?" (§8.1) sin conocer el NUREJ de antemano; el control jerárquico depende de conocimiento externo al sistema. Campos exigidos por §8.1 ausentes en los listados: denunciante/denunciado, último actuado, acción pendiente.
- **Acción propuesta:** diseñar con el usuario la bandeja de pendientes por categoría (o dar rol ENCARGADA acceso al monitoreo con filtros) + campos §8.1.
- **Decisión (2026-09-30): mantener P1 y OPEN — la Encargada debe disponer de la supervisión operativa prevista por el SRS (máxima autoridad del flujo, con supervisión de bandejas y VB/devolución). NO aceptar el dashboard agregado como sustituto de una bandeja/supervisión operativa cuando el flujo requiere actuar sobre expedientes; diseñar la superficie necesaria a partir de los casos de uso ya auditados. NO implementar todavía hasta cerrar el diseño de permisos y bandeja.**

### AUD-0029 — Inactivar usuario no redistribuye sus asignaciones activas
- **Severidad:** P2 (operación administrativa; flujo de expulsión)
- **Fase:** 6
- **Estado:** OPEN — requiere decisión del usuario (modifica `expulsar`).
- **Evidencia:** `SeguridadSesionesService@expulsar:50-54` pone `activo=false`, borra tokens y sesiones (correcto, transaccional y auditado) pero **no modifica `asignaciones`**; `AuthController:55-64` bloquea el login del inactivo; `EncargadaDashboardService:178` (`sin_asignar` = `whereDoesntHave('asignacionActiva')`) no los detecta porque la asignación "activa" sigue existiendo.
- **Impacto:** los expedientes del usuario expulsado quedan en una bandeja inaccesible: no salen en bandeja de nadie, no cuentan como "sin asignar" y no hay UI para detectarlos. Recuperable solo vía el endpoint genérico (AUD-0027) o SQL directo.
- **Acción propuesta:** al expulsar, reasignar/regresar a PENDIENTE_SORTEO (o desactivar la asignación) los expedientes activos del usuario, en la misma transacción.
- **Decisión (2026-09-30): mantener P2 y OPEN — la expulsión/inactivación NO debe dejar expedientes activos en una bandeja inaccesible. Definir una estrategia explícita de reasignación/retorno a bandeja institucional que genere actuado y conserve trazabilidad; NO realizar reasignación silenciosa en BD. NO aplicar hasta definir el flujo institucional correcto.**

### AUD-0030 — Los plazos no se cierran al concluir su fase
- **Severidad:** P1 (integridad de plazos / RF-07 / CA-3 `SRS:185`)
- **Fase:** 7
- **Estado:** **CERRADO (2026-10-06, tarea B1.3) — fix AUTORIZADO (decisión del usuario: opción i, cerrar al salir de fase; Opción A: sin `fecha_cierre`, la fecha se deriva de `actuados.fecha_hora`) y aplicado en `ActuadoService`**: const `MAPA_CIERRA_PLAZO` (ACT_ADMISION/ACT_OBSERVACION → EVALUACION; 9 informes finales + ACT_VISTO_BUENO_FINAL + ACT_REPARTO_INSTITUCIONAL → EJECUCION/EJECUCION_AMPLIADA) + `cerrarPlazosDeFase()` (`estado=CERRADO` + `actuado_cierre_id=$actuado->id`, idempotente, solo `VIGENTE` de los tipos del mapa) llamado dentro de la transacción/lock de `registerActuado` antes de `abrirPlazoSiAplica`. No toca `PlazoCalculatorService`, `VerificarVencimientoPlazosCommand` ni `SemaforoPlazoService`. Verificación: `CierrePlazosFasesTest` 7/7 (admisión, observación→SUBSANACION, informe cierra EJECUCION+AMPLIADA, VB final cierra residual y respeta SUSPENDIDO, ampliación NO cierra, ciclo AC055 pausa/reanudación, CRON no estampa cerrados); pint OK; `FlujoIntegralTest:333` actualizado (codificaba el bug "la admisión NO cierra EVALUACION"); suite 354 tests · 347 OK · 0 fallos · 7 omitidos.
- **Evidencia:** los únicos cierres en `app/` son: rechazo cierra todos (`EvaluacionAdmisibilidadService:156-158`), entrega de planificación cierra PLANIFICACION (`PlanificacionService:223-229`), ampliación cierra EJECUCION (`AmpliacionService:111`), impugnación cierra los suyos (`ImpugnacionService:246,257,269`), descargos→CUMPLIDO, archivo→VENCIDO. **No cierran:** EVALUACION tras ADMISION/OBSERVACION, EJECUCION tras VB final/reparto (`CierreExpedienteService` ni menciona `Plazo`), SUBSANACION (AUD-0031).
- **Impacto:** el CRON diario (`MarcarPlazosVencidosService:26-31`, `routes/console.php:11`) estampa `fuera_de_plazo=true` en relojes ya cumplidos; `SemaforoPlazoService@colorMasUrgente:151-163` toma el VIGENTE más antiguo → cada expediente admitido queda con semáforo FUERA_DE_PLAZO permanente y los concluidos siguen contando como urgentes en los tableros RF-R01/R05 → falsa sanción interna a operadores cumplidores.
- **Acción propuesta:** cerrar el plazo de la fase al transicionar fuera de ella (o filtrar CRON/semáforo por fase actual) + tests. No aplicar sin aprobación.

### AUD-0031 — Archivo por abandono sin validar el estado actual del expediente
- **Severidad:** P1 (acción automática destructiva; RN-03 mal aplicado)
- **Fase:** 7
- **Estado:** **CERRADO (2026-10-06, tarea B1.4) — fix AUTORIZADO (decisión del usuario: filtro estricto, sin higiene de huérfanos) y aplicado en `ArchivoPorAbandonoService::archivarVencidos()`:** (1) la consulta exige `expedientes.estado_actual_id = EN_SUBSANACION` (`whereHas` + const `ESTADO_EXPEDIENTE_SUBSANACION`); (2) cada expediente se procesa en su propio `try/catch (\Throwable)` con `Log::error` (plazo_id, expediente_id, clase, mensaje — sin datos sensibles) y la corrida continúa con el resto. **Evidencia roja previa al fix (reproducida):** post-D-6g el escenario abortaba el CRON completo con `ValidationException` "solo puede emitirse desde el estado EN_SUBSANACION" (tests `SubsanacionExitoTest` en rojo: negativo, corrida mixta y aislamiento). Regresión: `ArchivoPorAbandonoTest` 3/3, `VerificarVencimientoPlazosTest` 3/3, `SemaforoPenalizacionTest` OK. **Residual fuera de alcance (decisión del usuario):** plazos SUBSANACION VIGENTE de expedientes fuera de la fase (huérfanos por salidas con `estado_origen_id = null`) no se cierran en esta tarea — `marcarVencidos` excluye SUBSANACION; no confundir con AUD-0042.
- **Evidencia:** `ArchivoPorAbandonoService@archivarVencidos:49-54` filtra solo `tipo=SUBSANACION AND estado=VIGENTE AND fecha_limite < hoy`, **sin consultar el estado del expediente**; el plazo SUBSANACION jamás se cierra en ninguna transición de salida (grep: ningún cierre `CERRADO` para SUBSANACION fuera de este servicio). Programado a diario (`routes/console.php:11`). Los tests solo cubren expedientes que siguen en EN_SUBSANACION (`ArchivoPorAbandonoTest:88-220`).
- **Impacto:** un expediente que salió de EN_SUBSANACION (p. ej. salto vía endpoint genérico — AUD-0020) y sigue vivo es archivado automáticamente a los 3 días: emite ACT_ARCHIVO_POR_ABANDONO, cierra su bandeja (`:105-110`) y lo sella en ARCHIVO_POR_ABANDONO.
- **Acción propuesta:** cerrar el plazo SUBSANACION en toda salida y/o condicionar el archivo al estado `EN_SUBSANACION` + test negativo. No aplicar sin aprobación.

### AUD-0032 — EN_SUBSANACION sin salida de éxito (RN-03 incompleto)
- **Severidad:** P1 (flujo muerto en admisibilidad; Plan §12)
- **Fase:** 7
- **Estado:** **CERRADO (2026-10-06, tarea B1.4) — fix AUTORIZADO (decisiones del usuario) y aplicado:** actuado **`ACT_SUBSANACION_ACEPTADA`** (`EN_SUBSANACION → EN_EVALUACION`, fase ADMISIBILIDAD, `es_automatico=false`, `requiere_adjunto=false`) creado en **`CatalogoActuadoSeeder`** (updateOrCreate + pivote `perfilesEvaluacion` Técnico-AC022 / Jurídico-AC054 / Financiero-AC055; **aprobación: seeder, no migración**); cierre del reloj SUBSANACION con `'ACT_SUBSANACION_ACEPTADA' => ['SUBSANACION']` en `MAPA_CIERRA_PLAZO` (const `CODIGO_ACEPTACION_SUBSANACION`), reutilizando el mecanismo B1.3 (`estado=CERRADO` + `actuado_cierre_id`); **sin** entrada en `MAPA_TIPO_PLAZO` (decisión: al volver a evaluación no se reabre reloj EVALUACION); guard en `ActuadoService::verificarPlazoSubsanacionNoVencido()`: 422 si el plazo está `VENCIDO` o `fecha_limite` (columna `date`) ya superó `today()` en `app.timezone`=America/La_Paz (vencimiento independiente de la corrida del CRON; sin plazo parametrizado → permite). Autorización vía pivot + `ExpedientePolicy::crearActuado` (403 probado); D-6g da 422 de origen. Verificado por `SubsanacionExitoTest` (9/9) — flujo completo observación→aceptación→admisión incluido.
- **Evidencia:** `CatalogoActuadoSeeder`: EN_SUBSANACION es origen de **un único** actuado, `ACT_ARCHIVO_POR_ABANDONO` (`:66`, automático); solo entra por ACT_OBSERVACION (`:55`). No existe actuado "subsanación aceptada" ni transición de vuelta a EN_EVALUACION/EN_PLANIFICACION; `EvaluacionAdmisibilidadService:91-100` exige EN_EVALUACION.
- **Impacto:** Plan §12 prevé Subsanación → Planificación y RN-03 solo regula el caso negativo ("si caduca **y no se subsana**"), implícito un caso que sí subsana: el único camino registrado de un caso observado es el archivo automático; el éxito solo es posible con saltos irregulares (AUD-0020).
- **Acción propuesta:** crear actuado de salida (p. ej. `ACT_SUBSANACION_ACEPTADA`, EN_SUBSANACION → EN_EVALUACION/EN_PLANIFICACION con cierre del plazo) en migración de catálogo con aprobación (junto a AUD-0009).

### AUD-0033 — El informe jurídico (`ACT_INFORME_FINAL`) no se puede emitir
- **Severidad:** P1 (salida completa del motor jurídico bloqueada; RN-09 `SRS:166` inalcanzable)
- **Fase:** 8
- **Estado:** OPEN — **decisión del usuario (2026-09-30): NO corregir todavía.** No hacer `estado_nuevo_id` nullable, ni definir destino, ni omitir la columna, hasta completar el análisis del grafo (AUD-0021) y determinar cuál es la semántica normativa correcta de `ACT_INFORME_FINAL`. **El P1 queda confirmado y reproducible** (test `FlujoJuridicoTest` "no permite emitir el informe jurídico…").
- **Evidencia:** `database/migrations/2026_08_25_191156_create_actuados_table.php:21` (`estado_nuevo_id` NOT NULL; solo `create_actuados_triggers` lo toca después) vs `CatalogoActuadoSeeder:62` (`ACT_INFORME_FINAL` con `estado_destino_id = null`, pivote solo AUD_JURIDICO `:113-115`); `ActuadoService@registerActuado:89,105` escribe ese `null` en el INSERT y la rama `:116-120` solo protege el `update` del expediente. **Verificado con test** (`tests/Feature/FlujoJuridicoTest.php`, "no permite emitir el informe jurídico…"): `POST /api/expedientes/{e}/actuados` → 500 `QueryException` "Column 'estado_nuevo_id' cannot be null", transacción revertida (0 actuados nuevos, estado intacto).
- **Impacto:** el Auditor Jurídico no puede registrar ningún informe final. No le afecta al financiero: sus catálogos sí tienen destino `PENDIENTE_VISTO_BUENO_FINAL` (`CatalogoActuadoSeeder:82-83`, `DescargoFinancieroTest:225` → 201). Amplía AUD-0008 (faltan 2 informes jurídicos) y AUD-0021 (grafo): el único existente ni siquiera se registra.
- **Acción propuesta:** **Decisión (2026-09-30): mantener P1 y OPEN — NO hacer `estado_nuevo_id` nullable por ahora; el problema se resuelve respetando la máquina de estados y la semántica de `ACT_INFORME_FINAL` (no convertir una inconsistencia de catálogo/estado en una relajación de integridad de BD). Orden: (1) determinar si el informe final debe tener estado destino; (2) determinar qué estado representa la espera de VB; (3) verificar el grafo; (4) definir el cambio mínimo.** (Opciones previas (a)/(b)/(c) de la ficha quedan superadas por esta decisión.)

### AUD-0034 — Catálogo de actuados sin filtro de expediente en la UI (`expediente_id` sin validar)
- **Severidad:** P2 (UX / consistencia de autorización)
- **Fase:** 8
- **Estado:** **DECIDIDO (2026-09-30) — OPEN hasta implementación posterior.** Análisis de impacto completado en Fase 9 (`FLUJO_FINANCIERO.md` §4): **impacto de seguridad = ninguno verificable** (respuesta siempre filtrada por rol `CatalogoActuadoController:32-54` + gate `:24`; sin datos de expedientes ajenos, sin inyección — cast a entero + bindings); **impacto funcional = real y acotado** (el modal ofrece actuados de otros reglamentos del mismo rol → 403 al emitir, incumpliendo el docblock `:17-19`). **Decisión del usuario: el fix queda aprobado como solución a implementar después de la auditoría; NO autorizado a modificar código de producto durante la auditoría.** Confirmado como problema **funcional, no de seguridad**.
- **Evidencia:** `resources/views/expedientes/detalle.blade.php:297` llama `GET /api/catalogo/actuados` **sin `expediente_id`** → no se aplica el filtro de reglamento (`CatalogoActuadoController:38-44`); `IndexCatalogoActuadosRequest:17-22` valida solo `estado_origen_id`, no `expediente_id` (aunque el controlador lo lee). El docblock promete "evitar ofrecer acciones que provocarían un 403" (`:17-19`).
- **Impacto:** se listan actuados de otros reglamentos del mismo rol (p. ej. MPA de AC054 y AC055); al emitirlos → 403 en `perteneceAlRolConReglamento` (`CatalogoActuado.php:56-72`).
- **Solución aprobada (implementar posteriormente, fuera de la auditoría):** corregir el **origen del catálogo** para que los actuados ofrecidos correspondan al expediente/reglamento actual (pasar `expediente_id` desde la vista) **y mantener la validación en servidor** (regla `exists:expedientes,id` en `IndexCatalogoActuadosRequest` + filtro en `CatalogoActuadoController`). Prohibido aplicar durante la auditoría.
- **Decisión (2026-09-30, reiterada): P2 confirmado — problema funcional, no de seguridad; el fix queda autorizado conceptualmente en los términos anteriores (catálogo contextualizado al expediente/reglamento actual + servidor sigue validando el expediente; no confiar únicamente en el filtro de UI). NO aplicar todavía si el cambio se cruza con AUD-0033/AUD-0020/AUD-0021: resolver primero las dependencias de estados y catálogo.**

### AUD-0035 — RN-08 (impugnación) e informe jurídico sin interfaz: 0 vistas
- **Severidad:** P1 (fase normativa completa solo alcanzable por HTTP directo)
- **Fase:** 8
- **Estado:** OPEN (P1) — **decisión del usuario (2026-09-30): no crear UI todavía.** Debe documentarse/verificarse que la ausencia de interfaz es un **problema independiente de AUD-0033** (aunque ambos bloqueen la salida del motor jurídico) y contrastarse contra el flujo normativo completo (RN-08 + RN-09).
- **Evidencia:** grep sobre `resources/views/**/*.blade.php` de `impugnaci|informe_final|ACT_INFORME_FINAL|resolver` → **0 coincidencias**; la única acción del detalle es el modal genérico "Emitir Actuado" (`detalle.blade.php:133-135,207-244,340`). Existen `POST /api/expedientes/{e}/impugnacion/remitir|resolver` (`routes/api.php:62-63`) sin botones. Emitir `ACT_REMITIR_IMPUGNACION` vía actuado genérico no crearía la fila en `impugnaciones` (`ImpugnacionService:83-89`) → la resolución fallaría con `firstOrFail` (`:179-185`).
- **Impacto:** RN-08 (`SRS:330-339`, 1 d remitir / 3 d resolver) y el informe jurídico no son ejecutables por el usuario desde la UI; solo por llamada HTTP directa (los endpoints sí están policy-protegidos y testeados).
- **Acción propuesta:** UI por rol+estado (Fase 14) o enlace desde la vista de detalle; definir alcance con el usuario.
- **Decisión (2026-09-30): confirmado P1 y OPEN — la ausencia de interfaz para las operaciones jurídicas previstas por RN-08/RN-09 NO se considera solucionada por tener endpoints funcionales. Mantener el hallazgo; verificar el conjunto completo de operaciones jurídicas que deben estar disponibles en UI; diseñar la UI contra el flujo normativo, no crear botones aislados. NO implementar todavía.**

### AUD-0036 — Concurrencia paralela de `comunicar` sin índice único en `plazos` (observación)
- **Severidad:** P3 (observación — **no elevar sin evidencia experimental**, decisión del usuario 2026-09-30)
- **Fase:** 9 (origen: observación O-8; convertida en ficha formal a pedido del usuario)
- **Estado:** OPEN (observación, **no reproducida**)
- **Evidencia (análisis de código, sin ejecución):** `database/migrations/2026_08_25_191200_create_plazos_table.php:17,22` no define índice único sobre `(expediente_id, tipo_plazo)`; `DescargoFinancieroService@validarSinDescargosPrevios:227-238` hace un `SELECT` sin bloqueo **dentro** de la transacción; dos `POST /api/expedientes/{e}/descargos/comunicar` simultáneos podrían leer "no existe" ambos y crear **dos** sub-relojes `DESCARGOS` (el segundo `congelarRelojEjecucion:243-252` no fallaría: actualizaría 0 filas).
- **Impacto potencial:** fase de descargos duplicada → doble reloj concurrente, recálculo de reanudación ambiguo (integridad del RN-09). **No confirmado:** el stress de concurrencia real (`StressConcurrenciaTest`, gated con `RUN_STRESS_TESTS=1`) no cubre descargos (`StressConcurrenciaTest:22-27`). La defensa de **doble envío secuencial** sí está probada (`DescargoFinancieroTest:325-345`).
- **Acción propuesta:** reproducir con peticiones verdaderamente paralelas antes de clasificar; si se confirma → índice único `(expediente_id, tipo_plazo)` + `lockForUpdate` (requiere autorización: escrito de esquema). **Prohibido elevar severidad hasta tener evidencia experimental.**

### AUD-0037 — `firstOrFail`/`findOrFail` → 404 en flujos de negocio (observación)
- **Severidad:** P3 (observación — **no clasificar como vulnerabilidad sin evidencia adicional**, decisión del usuario 2026-09-30)
- **Fase:** 9 (origen: observación O-9; convertida en ficha formal a pedido del usuario)
- **Estado:** OPEN (observación)
- **Comportamiento documentado:** `firstOrFail`/`findOrFail` lanzan `ModelNotFoundException` → Laravel responde **HTTP 404** con `{"message":"No query results for model ..."}` en la API (el nombre del modelo se expone incluso con `APP_DEBUG=false`).
- **Flujos con respuesta funcionalmente incorrecta o no controlada (análisis de grep en `app/`):**
  1. **Configuración/BD ausente → 404 en endpoint existente** (el cliente interpreta "recurso no encontrado" cuando lo que falta es una fila de catálogo/parámetro): `DescargoFinancieroService:263` (parámetro DESCARGOS) y `:87,:131,:342` (catálogo por código), `AmpliacionService:257`, `ArchivoPorAbandonoService:47`, `CierreExpedienteService:152`, `EvaluacionAdmisibilidadService:57,93`, `ExpedienteService:46,47,109,122,154`, `ImpugnacionService:235`, `NurejHijoService:41,56`, `NurejGeneratorService:41,68`, `PlanificacionService:296`, `TransparenciaService:165`, `ExpedienteController:57`. **Reproducido en Fase 9:** al omitir `ACT_RECEPCION_DESCARGOS` en la semilla del test, `POST .../descargos/recibir` devolvió 404 (catálogo faltante, no recurso). Riesgo real con BD desalineada (**AUD-0023**: 8/17 parámetros).
  2. **Estado de negocio inexistente → 404 alcanzable desde la UI:** `ImpugnacionService:184` (impugnación PENDIENTE inexistente → `resolver` responde 404; escenario ya documentado en AUD-0035: emitir `ACT_REMITIR_IMPUGNACION` vía actuado genérico no crea la fila), `:196` (Encargada asignada), `:227` (actuado de rechazo).
  3. **Input de usuario — sin efecto incorrecto verificado:** `ActuadoController:27` está pre-validado por `exists:catalogo_actuados,id` (`StoreActuadoRequest:34` → 422 antes); `ImpugnacionRemitirRequest:19` / `ResolverImpugnacionRequest:19` hacen `findOrFail` del expediente de ruta (equivalente al route-model binding → 404 correcto); `Usuario::findOrFail` (`ActuadoService:146`, `AmpliacionService:249`, `ImpugnacionService:216`, `PlanificacionService:288`) con `usuario_destino_id` pre-validado en `StoreActuadoRequest:36` (los demás FormRequests **no verificados** en esta fase).
- **Impacto:** respuestas 404 engañosas y sin log de error de configuración; **no hay fuga de datos ni bypass de autorización demostrados** → no es vulnerabilidad con la evidencia actual.
- **Acción propuesta:** (a) mapear en Fase 13 (jobs/cron) o fase de endurecimiento los catálogos/parámetros requeridos vs seeders; (b) sustituir `firstOrFail` de configuración por un error explícito de configuración (log + 500/422 controlado). **Requiere autorización explícita para tocar producto.** — **(a) CUMPLIDO en Fase 13:** mapeo en `MATRIZ_JOBS_CRON.md` §5 (catálogo `ACT_ARCHIVO_POR_ABANDONO` + ADMIN activo para el job de plazos; ejecución real en dev → `ModelNotFoundException`, ver **AUD-0042**). (b) sigue pendiente de autorización.

### AUD-0038 — Derivar desde un NUREJ ya derivado responde HTTP 500
- **Severidad:** P2 (respuesta de servidor no controlada en endpoint de negocio)
- **Fase:** 11
- **Estado:** **CERRADO (2026-09-30)** — fix autorizado por el usuario y aplicado.
- **Evidencia (reproducción):** Encargada deriva hijo del padre → 201; luego `POST /api/expedientes/{hijo}/nurej-hijo` con `motivo` válido → **500** con `{"message":"No se pueden generar NUREJ hijos de un expediente ya derivado (RN-10).","exception":"App\\Exceptions\\CannotDeriveNurejException","file":".../NurejGeneratorService.php","line":44}` (test temporal de reproducción, no persistido).
- **Causa raíz:** `NurejGeneratorService:43-45` lanza `CannotDeriveNurejException` (extends `DomainException`, `CannotDeriveNurejException:11`) sin handler HTTP (grep: 0 usos fuera de generador/test unitario; `bootstrap/app.php` sin `renderable`) → Laravel responde 500. `ExpedientePolicy@derivarNurejHijo:266-270` no excluye derivados, así que la petición siempre llega al servicio.
- **Impacto (antes del fix):** cualquier Encargada provoca un 500 en el endpoint (con traza si `APP_DEBUG=true`); la regla RN-10 sí se respeta (no se crea el sub-hijo) → no es bypass de autorización.
- **Corrección aplicada (2026-09-30, en los términos autorizados):** manejo HTTP explícito de `CannotDeriveNurejException` en `bootstrap/app.php` (`withExceptions`): `render()` → **HTTP 422** con `{"message": ...}` y `dontReport()` (es una regla de negocio, no un error a reportar). Se eligió **422** por coherencia con el contrato existente del proyecto (violaciones de regla de negocio → `ValidationException` → 422, ej. `ExpedienteService:117`, `SorteoAlgorithmService:56,73`; sin precedentes de 409 en `app/`). Solo cambia el manejo de esta excepción: servicio, política, generador y unit test intactos.
- **Verificación:** test feature nuevo `FlujoNurejTest:207` (422 + mensaje exacto + sin sub-hijo creado) 4/4 verdes; `vendor/bin/pint --dirty` → passed; `php artisan test --compact` → **288: 281 OK · 1 fallo (AUD-0001) · 0 errores · 6 omitidos** — sin regresiones.

### AUD-0039 — NUREJ Hijo hereda la especialidad del padre (RN-10 exige otra especialidad)
- **Severidad:** P2 (confirmada por el usuario, 2026-09-30)
- **Fase:** 11 → análisis en 12
- **Estado:** OPEN — **análisis de diseño entregado y DECISIÓN ADOPTADA (2026-09-30): Opción B.** Ver `docs/auditoria/AUD-0039_DISENO.md` (§1-§6 + §7 Decisión). Resumen: el NUREJ Hijo debe tener **especialidad explícita de destino** (no heredar); `via_destino` = input explícito controlado por servidor; debe existir un mapeo válido vía destino → reglamento destino (Técnico→AJ: `JURIDICO→AC054`; Técnico→Financiero: `FINANCIERO→AC055`); NO asumir que la vía destino puede ser igual a la del padre cuando el actuado que origina el hijo exige otra especialidad; cualquier combinación permitida saldrá de una **matriz normativa explícita**. **NO aplicado: pendiente terminar la matriz de derivaciones permitidas. Sin cambios de código.**
- **Evidencia:** SRS `:171` (derivación recomendando intervención de **otra especialidad**, ej. Técnico → Auditor Jurídico) y `:173` (el hijo aplica *"su reglamento… Acuerdos 54 o 55"*). Implementación: el hijo **copia `via` y `reglamento_id` del padre** (`NurejHijoService:46-47`); `grep` en `app/` → 0 endpoints/requests que actualicen `reglamento_id`/`via` de un expediente; `DerivarNurejHijoRequest:23-28` solo acepta `motivo` (sin especialidad destino); el sorteo elige candidatos por `via` (`SorteoAlgorithmService:30-33,:85-89`) con pesos por `reglamento_id` (`:101-105`).
- **Impacto:** el flujo canónico de RN-10 (Técnico AC022 → Auditor Jurídico AC054/Financiero AC055) es inalcanzable: la derivación solo produce causas de la misma especialidad y el hijo evaluaría con el reglamento heredado, no Ac. 054/055. 0 hijos en BD dev (sin datos reales).
- **Acción propuesta:** análisis de diseño (alcance definido por el usuario, ver Estado) → propuesta respaldada por evidencia antes de cualquier cambio. **No modificar código hasta entonces.**

### AUD-0040 — Flake no reproducida en `AmpliacionTest:175` (observación)
- **Severidad:** P3 (observación; no elevar sin reproducción)
- **Fase:** 11
- **Estado:** OPEN (observación)
- **Evidencia:** en la primera suite completa tras el fix de AUD-0038 (288 tests) `AmpliacionTest` falló 1 vez en `:175` (esperaba la justificación de la solicitud y obtuvo `'Asignación inicial de la semilla'`); corrida aislada posterior 10/10 ✓ y segunda suite completa 288:281 OK/1 fallo AUD-0001/0 errores ✓ — **no reproducida**.
- **Candidato raíz:** `ampliacionAsignar:108` usa `CatalogoActuado::first()->id` sin `ORDER BY` (catálogo arbitrario para el actuado semilla; si el primer catálogo fuera el de AMPLIACION, el `whereHas` por código de `:168-171` encontraría dos actuados candidatos). Hipótesis no confirmada.
- **Impacto:** sin impacto de producto verificado; solo una corrida de suite afectada.
- **Acción propuesta:** **Decisión (2026-09-30, cierre F12): MONITORIZAR (opción (a) aceptada).** Se registra como **flake/no reproducido, causa raíz abierta**. NO autorizado ningún cambio en el helper del test ni en el producto mientras no exista reproducción fiable o evidencia adicional de causa raíz; sin solución inventada. Si vuelve a fallar: reproducir → determinar causa raíz → presentar fix. *(Decisión previa "NEEDS_REVIEW" de 2026-09-30 queda resuelta por esta.)*

### AUD-0041 — `AdminDashboardController` no valida `activo` (ADMIN inactivo accede al dashboard)
- **Severidad:** P2 (autorización / defensa en profundidad; con mitigaciones verificadas)
- **Fase:** 12
- **Estado:** **CERRADO (2026-09-30, con la validación de Fase 12).** Fix AUTORIZADO y APLICADO en los términos de la decisión: se centralizó la condición de usuario activo replicando el chequeo de `AdminMonitoreoController` (patrón existente del módulo, sin ampliar alcance a AUD-0017 ni tocar policies): `if (! $usuario || ! $usuario->activo || rol !== ADMIN) abort(403)` en `AdminDashboardController@index`. Cierre con gates de F12: test en verde + pint OK + suite sin regresiones (único fallo = AUD-0001, deuda conocida aceptada en el mismo cierre). **La migración a policy (AUD-0017) sigue como mejora de arquitectura aparte, sin autorización.**
- **Evidencia:** `AdminDashboardController:28-29` chequea **solo** `rol === ADMIN` y no `activo`; en cambio `AdminMonitoreoController:26-31` sí exige `activo` + rol, `EnsureAdmin` (web) `:21` exige `activo`, y `UsuarioPolicy:45,55,65` exige `$admin->activo`. Reproducido en tinker con usuario sintético (`f12_admin.php`, solo lectura): `ADMIN INACTIVO: dashboard status=200 | monitoreo status=403`; `TECNICO ACTIVO: dashboard 403 | monitoreo 403`.
- **Mitigaciones verificadas:** el login bloquea inactivos (`AuthController:55-64`) y el único flujo de inactivación (`SeguridadSesionesService@expulsar:50-54`) hace `$objetivo->tokens()->delete()` (`:52`) → un ADMIN inactivo queda sin tokens válidos por la vía oficial. El vector práctico requiere un token vigente sobre un usuario inactivado fuera de ese flujo (estado que hoy solo se da por manipulación externa a la app).
- **Impacto:** con un token en esas condiciones, el endpoint devuelve agregados administrativos incluida la lista de sesiones recientes con `ip_origen` (`AdminDashboardController:154,162,210-211`); inconsistencia con la regla "inactivo = cero acceso" aplicada en el resto del módulo.
- **Acción propuesta:** **Verificación (2026-09-30):** fix aplicado como se describió; test feature en `ReportesAdminTest` ("prohíbe el dashboard y el monitoreo a un ADMIN inactivo…") → 5/5 verdes (34 aserciones); `vendor/bin/pint --dirty` → OK (formateo del controlador); `php artisan test --compact` → **293: 286 OK · 1 fallo (AUD-0001) · 0 errores · 6 omitidos** — sin regresiones. **La migración a policy (AUD-0017) queda como mejora de arquitectura aparte, sin autorización.**

### AUD-0042 — Job diario de plazos falla por completo si falta configuración de catálogo/usuario sistema
- **Severidad:** P2 (funcional / disponibilidad de la automatización; RN-03 no ejecuta)
- **Fase:** 13
- **Estado:** OPEN
- **Evidencia:** `ArchivoPorAbandonoService:47` hace `CatalogoActuado::...->firstOrFail()` **antes** de consultar plazos, y `:92-99` `firstOrFail` de ADMIN activo; `VerificarVencimientoPlazosCommand:26-27` llama los dos servicios sin catch → cualquier excepción aborta el comando y `MarcarPlazosVencidosService` jamás corre. **Reproducido en BD dev (2026-09-30):** estado previo verificado (`ACT_ARCHIVO_POR_ABANDONO` = 0 filas, 1 plazo SUBSANACION vencido, 7 internos sin marcar, 3 ADMIN activos) → `php artisan plazos:verificar-vencidos` → `ModelNotFoundException` en `ArchivoPorAbandonoService:47`, **exit=1**, sin escribir nada. Test nuevo `VerificarVencimientoPlazosTest` («falla sin ejecutar nada si falta el catálogo…») reproduce el mismo comportamiento en el entorno de tests.
- **Impacto:** en cualquier entorno sin el catálogo sembrado (hoy: BD dev alineada con AUD-0023) o sin ADMIN activo, el cron falla a diario en silencio (solo traza de consola): no se archiva por abandono (RN-03) ni se estampa `fuera_de_plazo` (QA 3). Relaciona con **AUD-0037** (acción (a) "mapear catálogos requeridos vs seeders" → cumplida en `MATRIZ_JOBS_CRON.md` §5) y **AUD-0023** (desalineación de catálogos en dev).
- **Acción propuesta:** **Fix propuesto (NO aplicado — requiere autorización, toca producto):** degradación controlada: validar dependencias al inicio, loguear el error de configuración y continuar con `marcarVencidos()` (o abortar con mensaje explícito), en vez de excepción no capturada. Mapeo de dependencias entregado en F13 (`MATRIZ_JOBS_CRON.md` §5): `ACT_ARCHIVO_POR_ABANDONO` (`CatalogoActuadoSeeder:66`, estado `:38`) + usuario ADMIN activo.

### AUD-0043 — Corte de plazos en UTC: el archivo por abandono ocurre 3 h antes de la medianoche local
- **Severidad:** P2 (funcional / precisión de plazos; cambio de estado irreversible fuera del horario esperado)
- **Fase:** 13
- **Estado:** **CERRADO (2026-10-05, tarea B3.3)**
- **Evidencia:** `php artisan config:show app.timezone` → **UTC**; `routes/console.php:11` `->daily()` → `0 0 * * *` (=`schedule:list`); grep `timezone|America/` en repo = 0; comparaciones con `now()->toDateString()` en `ArchivoPorAbandonoService:52` y `MarcarPlazosVencidosService:30`. SRS `SRS_EXTRAIDO.txt:443`: el archivo por abandono "**se dispara a la medianoche**". **Reproducido experimentalmente:** test `VerificarVencimientoPlazosTest` «archiva con la fecha UTC ya cambiada pero aún es el día de vencimiento en Argentina» — `Carbon::setTestNow('2026-09-08 00:05:00')` UTC = 2026-09-07 21:05 ART (UTC-3), `fecha_limite = 2026-09-07` → **archiva** (actuado inmutable emitido).
- **Impacto:** el plazo vence efectivamente a las **21:00** hora local del día de vencimiento, no a la medianoche: hasta 3 h de ventana en las que el interesado aún está dentro del día hábil local pero el sistema ya archiva el expediente (irreversible) y estampa `fuera_de_plazo`. Misma ventana afecta el semáforo/conservas de fechas comparadas en UTC.
- **Acción propuesta:** **Fix APLICADO (2026-10-05, tarea B3.3, decisión del usuario):** zona horaria institucional fijada en `config/app.php` como `env('APP_TIMEZONE', 'America/La_Paz')` (default La Paz sin tocar `.env`); `php artisan config:show app.timezone` → **America/La_Paz** y el schedule `->daily()` de `routes/console.php:11` hereda la TZ, por lo que el corte corre a **00:00 de Bolivia** (medianoche local del SRS). Verificación: `tests/Feature/TimezoneBoliviaTest.php` (5 tests: 23:59:59 La Paz, transición 04:00 UTC↔medianoche La Paz, persistencia de fechas sin corrimiento) + suite 325/319 OK/0 fallos. Evidencias de la sección **Evidencia** conservadas como registro del hallazgo en su momento de detección (2026-10-02).

### AUD-0044 — Sidebar "Bandeja de entrada" visible para la Encargada y el Administrador → 403
- **Severidad:** P2 (UX/autorización: enlace navegable que siempre falla)
- **Fase:** 14
- **Estado:** OPEN — requiere decisión: gatear el enlace por rol en el sidebar, o habilitar la ruta para ENCARGADA/ADMIN (esta última toca autorización → confirmación explícita obligatoria)
- **Evidencia:** `resources/views/layouts/app.blade.php:135-139` muestra el enlace a `/expedientes` **sin `x-if`** (los demás links sí: Encargada `:140`, Técnico `:159`, Admin `:167`); `routes/web.php:19` → `WorkstationController.php:15` `authorize('operadorBandeja', ...)`; `app/Policies/ExpedientePolicy.php:53-64` acepta solo TECNICO/AUD_JURIDICO/AUD_FINANCIERO activos; `tests/Feature/WebWorkstationRoutesTest.php:53-57` prueba el 403 de la Encargada. No hay `resources/views/errors/403.blade.php`.
- **Impacto:** 2 de los 5 roles (ENCARGADA, ADMIN) ven un enlace principal del sidebar que siempre termina en página de error genérica. El backend está protegido correctamente: es un defecto de UI, no de seguridad. Relacionado con **AUD-0028** (la Encargada sigue sin bandeja propia de supervisión).
- **Acción propuesta:** fix de UI (condicionar el enlace por rol) cuando se decida el alcance de la bandeja de la Encargada. NO aplicado en F14.

### AUD-0045 — Monitoreo: el fallo de carga no se renderiza y se muestra "No se encontraron expedientes"
- **Severidad:** P2 (feedback/estado: error silencioso + estado vacío engañoso)
- **Fase:** 14
- **Estado:** OPEN
- **Evidencia:** `resources/views/administrador/monitoreo.blade.php:506-509` asigna `this.error` en el `catch`, pero `grep error` sobre el markup de la vista no encuentra ningún `x-text="error"` ni banner (0 renders); `:369-375` renderiza "No se encontraron expedientes." cuando `expedientes.length === 0` (que el catch también fuerza en `:511`); `cargando` se pone en `true/false` (`:405-411`) pero tampoco tiene render → en la primera carga se ve el estado vacío antes de que lleguen los datos.
- **Impacto:** el Administrador no distingue entre "no hay expedientes" y "falló la API": ante un 422/500 ve datos vacíos sin causa. Inconsistente con los demás módulos, que sí renderizan errores (`encargada/dashboard:231`, `administrador/dashboard:308-314`).
- **Acción propuesta:** renderizar `error` (banner) y `cargando` (spinner) en la vista de monitoreo. NO aplicado en F14.

### AUD-0046 — Monitoreo: rótulo "Actualización automática" sin mecanismo de auto-refresco
- **Severidad:** P3 (UX: afirmación falsa en la interfaz)
- **Fase:** 14
- **Estado:** OPEN
- **Evidencia:** `resources/views/administrador/monitoreo.blade.php:21-23` muestra "Actualización automática" con ícono de rotación; `grep setInterval|setTimeout` en `resources/views/**` → 0 coincidencias; la única carga es `cargar()` en `:6` (on init) y tras aplicar filtros (`:73`, `:96-103`).
- **Impacto:** el operador cree que los datos se actualizan solos y toma decisiones sobre información potencialmente vieja; no hay forma de refrescar sin recargar la página (el dashboard admin sí tiene botón "Actualizar", O-16).
- **Acción propuesta:** o implementar un `setInterval` con `cargar()` (y quitarlo al salir de la vista), o eliminar el rótulo. NO aplicado en F14.

### AUD-0047 — Vista de parámetros inalcanzable con un "Guardar" que informa éxito sin persistir
- **Severidad:** P3 (funcional/consistencia: código muerto con feedback falso)
- **Fase:** 14
- **Estado:** OPEN — requiere decisión: eliminar la vista, habilitarla (requiere API nueva) o congelarla hasta remediación
- **Evidencia:** `routes/web.php:78-81` tiene la ruta `GET /administrador/parametros` **comentada** (bloque `web.php:64-86`); `php artisan route:list` no la incluye; `grep view('administrador.parametros')` en `app/` = 0 → **inalcanzable**; `resources/views/administrador/parametros.blade.php:287-305` el handler `guardar()` no hace `fetch` (0 `apiFetch` en la vista) y solo `this.exito = true` → toast "Parámetros guardados" sin escritura alguna.
- **Impacto:** si algún día se habilita la ruta, el botón informa éxito sin guardar nada (peor que ausencia de pantalla). Hoy es código muerto que engaña al auditor/lector.
- **Acción propuesta:** decisión de producto; no tocar en F14.

### AUD-0048 — Login: "¿Olvidaste tu contraseña?" es un enlace muerto
- **Severidad:** P3 (funcional: flujo inexistente)
- **Fase:** 14
- **Estado:** OPEN — sin endpoint de recuperación en el sistema
- **Evidencia:** `resources/views/auth/login.blade.php:274-278` `<a href="#">¿Olvidaste tu contraseña?</a>`; `php artisan route:list` no tiene rutas de recuperación/reset de contraseña; grep `reset|forgot` en `routes/` = 0.
- **Impacto:** el usuario que olvida la contraseña no tiene salida (la política de contraseñas obliga a cambio vía perfil, pero no existe recuperación por correo). Falta de alcance documentada por primera vez.
- **Acción propuesta:** definir alcance (módulo de recuperación + correo vs. reset por ADMIN). F14 solo lo registra.

### AUD-0049 — Login: los errores llegan sin adaptar al usuario
- **Severidad:** P3 (feedback/seguridad de mensajes)
- **Fase:** 14
- **Estado:** OPEN
- **Evidencia:** `resources/views/auth/login.blade.php:335` `const data = await response.json()` **sin `try/catch`** ni `response.ok` → una respuesta HTML (500/503) revienta con excepción no manejada; `:338` muestra `data.message` crudo → con `throttle:login` (`app/Providers/AppServiceProvider.php:25`, 5/min) el 429 se muestra como **"Too Many Attempts."** (mensaje de Laravel en inglés, sin `Retry-After`); `AuthorizationException` del backend (`messages/en` vs `es` no publicados) llega igual.
- **Impacto:** el usuario no entiende que está limitado ni cuánto esperar; posible fuga de texto técnico en 500. Relacionado con **AUD-0006** (mensajes genéricos de login).
- **Acción propuesta:** manejar `response.ok`, traducir/adaptar 422/429/500 a copy en español y ofrecer reintento. NO aplicado en F14.

### AUD-0050 — Bandeja operador: contador "Total de expedientes" siempre vacío
- **Severidad:** P3 (estado/datos en pantalla)
- **Fase:** 14
- **Estado:** OPEN
- **Evidencia:** `resources/views/expedientes/bandeja-operador.blade.php:16` usa `meta.total` en el rótulo; `:133-139` la respuesta del API se copia en `data` con `total: j.meta.total ?? data.length` pero **nunca** se asigna `this.meta` (grep `this.meta` = 0); comparar con `bandeja-sorteo.blade.php:184` que sí hace `this.meta = j.meta`.
- **Impacto:** el rótulo muestra un valor vacío; el usuario pierde el total que la API sí envía.
- **Acción propuesta:** asignar `this.meta` igual que en la bandeja de sorteo. NO aplicado en F14.

### AUD-0051 — Apertura: plantilla `errorGral` jamás activada
- **Severidad:** P3 (feedback: componente inerte)
- **Fase:** 14
- **Estado:** OPEN
- **Evidencia:** `resources/views/expedientes/apertura.blade.php:14-19` declara el banner `x-show="errorGral"`; `:170` y `:224` solo asignan `this.errorGral = null` en los `finally`; grep de asignaciones de texto a `errorGral` = 0 (los errores van a `error403`, `errorValidacion`, `errorServidor`, `catch` local).
- **Impacto:** sin efecto visible hoy (el resto de ramas cubre 403/422/500), pero el componente es código muerto que confunde al mantener la ilusión de un canal de error genérico.
- **Acción propuesta:** eliminar el banner o enrutar a él los errores no clasificados. NO aplicado en F14.

### AUD-0052 — Apertura: el límite de 10 partes existe solo en el cliente
- **Severidad:** P3 (validación consistente / posible cambio funcional)
- **Fase:** 14
- **Estado:** OPEN — requiere decisión: ¿el límite de 10 partes es regla de negocio real? No consta en el SRS (grep sin mención)
- **Evidencia:** `resources/views/expedientes/apertura.blade.php:71,198` oculta el botón "Agregar parte" a partir de 10 y bloquea con toast; `app/Http/Requests/StoreExpedienteRequest.php:25` valida `partes.*.nombre` con `required|string|max:255` **sin `max` en el array** → vía API se aceptan N partes.
- **Impacto:** validación débil en servidor (inconsistencia cliente/servidor, patrón contrario a las reglas del proyecto); si 10 no es regla real, el límite de la UI es arbitrario.
- **Acción propuesta:** decidir el alcance y, si procede, añadir `max` en el FormRequest + test. NO aplicado en F14 (toca validación → confirmación).

### AUD-0053 — Dashboards: un refresco fallido conserva los datos previos junto al banner de error
- **Severidad:** P3 (estado/consistencia de datos en pantalla)
- **Fase:** 14
- **Estado:** OPEN
- **Evidencia:** `resources/views/administrador/dashboard.blade.php:304-316` y `resources/views/encargada/dashboard.blade.php:226-235`: en el `catch` se setea `this.error` pero **no** se reinicia `datosListos` (solo se pone en `true` tras éxito) → `x-if="datosListos"` sigue mostrando los KPIs de la carga anterior, ahora con el banner de error encima.
- **Impacto:** el usuario ve números viejos junto a "Error al cargar" sin saber cuáles están desactualizados; decisión potencial sobre datos obsoletos.
- **Acción propuesta:** poner `datosListos = false` en el `catch` (o marcar los datos como "última actualización"). NO aplicado en F14.

### AUD-0054 — Sorteo individual: el error puede quedar invisible si se cierra el modal
- **Severidad:** P3 (feedback dependiente del timing del usuario)
- **Fase:** 14
- **Estado:** OPEN
- **Evidencia:** `resources/views/expedientes/bandeja-sorteo.blade.php:222-227` captura el error en `errorSorteo`, que solo se renderiza **dentro del modal** (`:135-136`); el cierre del modal (`seleccionado = null` en `:106,116,140`) queda habilitado durante la petición (solo el botón submit se deshabilita, `:145`) → si el usuario cierra mientras `fetch` está en vuelo, el `x-show` del modal se apaga y el mensaje deja de existir; no hay `apiToast` para este caso (contrasta con el resto de acciones del sistema).
- **Impacto:** falla silenciosa percibida: el expediente no se sorteó y la UI no lo dice.
- **Acción propuesta:** duplicar el error a un toast global y/o deshabilitar el cierre mientras `sorteandoId` esté activo. NO aplicado en F14.

### AUD-0055 — Sorteo: recarga a página fuera de rango tras sortear el último ítem
- **Severidad:** P3 (estado/paginación)
- **Fase:** 14
- **Estado:** OPEN
- **Evidencia:** `resources/views/expedientes/bandeja-sorteo.blade.php:219` hace `this.cargar()` conservando `current_page`; `sortear()` saca el ítem de la lista local (`:210-214`) pero no recalcula la página → con un ítem en la última página, el backend (`LengthAwarePaginator`, no recorta páginas fuera de rango) devuelve `data=[]` con `total > 0`.
- **Impacto:** pantalla vacía con contador "N de M" inconsistente y sin indicación de cómo volver; el usuario cree que desaparecieron expedientes.
- **Acción propuesta:** tras sortear, si la página quedó vacía y `current_page > last_page`, retroceder una página antes de recargar. NO aplicado en F14.

### AUD-0056 — Usuarios: "Inactivar" disponible sobre la propia cuenta → 422 del servidor
- **Severidad:** P3 (UX/estado inválido: la UI ofrece una acción que siempre falla)
- **Fase:** 14
- **Estado:** OPEN
- **Evidencia:** `resources/views/administrador/usuarios.blade.php:110-113` renderiza el botón para toda fila, sin comparar `id` con el usuario logueado; `app/Services/SeguridadSesionesService.php:44-48` lanza `ValidationException` "Un administrador no puede inactivarse a sí mismo" (mostrada en UI en `usuarios:337-345`).
- **Impacto:** feedback de error en vez de prevención: el admin descubre la regla solo al intentarlo. Relacionado con O-13 (auto-edición de rol).
- **Acción propuesta:** ocultar/deshabilitar el botón en la fila propia (comparación con `/api/me`). NO aplicado en F14.

### AUD-0057 — Layout: `cargarUsuario()` y `cerrarSesion()` fallan en silencio
- **Severidad:** P3 (feedback/errores no capturados)
- **Fase:** 14
- **Estado:** OPEN
- **Evidencia:** `resources/views/layouts/app.blade.php:261-272` `cargarUsuario()` no tiene `try/catch` (una sesión expirada ya redirige vía `api-helper:38-41`, pero un 500 deja la UI sin usuario sin aviso); `:274-281` `cerrarSesion()` tiene `catch` **vacío** → si `POST /api/logout` falla, el usuario cree que salió y el `localStorage`/estado quedan sin limpiar.
- **Impacto:** estados de sesión ambiguos en la barra superior; sin mensaje ni fallback.
- **Acción propuesta:** capturar, limpiar estado local y notificar (toast) en ambos casos. NO aplicado en F14.

### AUD-0058 — Salidas de consola con datos en vistas entregadas
- **Severidad:** P3 (higiene / candidato a F17 hardening)
- **Fase:** 14
- **Estado:** OPEN
- **Evidencia:** `resources/views/administrador/monitoreo.blade.php:469` `console.log` con la respuesta completa del monitoreo (datos de expedientes/personas), `:500` `console.error` del error; `resources/views/administrador/feriados.blade.php:645` similar en el save. Regla del proyecto: prohibido `Log::info()` de debug en entregables finales — el equivalente en consola del navegador aplica al mismo criterio.
- **Impacto:** cualquier persona con la consola abierta ve datos del expediente; inconsistente con la política de datos sensible del sistema gubernamental.
- **Acción propuesta:** eliminar los `console.*` de datos; mantener solo si se justifica. NO aplicado en F14 (candidato natural a F17 junto con AUD-0006/0027).

### AUD-0059 — `welcome.blade.php`: código muerto con enlace a `/dashboard` inexistente
- **Severidad:** P3 (código muerto / navegación)
- **Fase:** 14
- **Estado:** OPEN
- **Evidencia:** no hay `GET /` en `php artisan route:list`; `grep -r "welcome" routes/ app/ tests/` = 0 → la vista es inalcanzable; `resources/views/welcome.blade.php:26` enlaza a `/dashboard` (**ruta inexistente**) y `:30` a `route('login')`.
- **Impacto:** sin efecto de usuario hoy; si se habilita `/` aparecería un enlace roto. Código huérfano que engaña al inventory.
- **Acción propuesta:** eliminar la vista o corregirla si se decide habilitar la raíz. NO aplicado en F14.

### AUD-0060 — RF-04 sin interfaz: la evaluación de requisitos de admisibilidad no es ejecutable desde la UI
- **Severidad:** P1 (requisito funcional sin superficie de usuario; criterio ya adoptado por el usuario en AUD-0035: "los endpoints NO sustituyen la interfaz")
- **Fase:** 14
- **Estado:** OPEN — decisión de alcance de UI pendiente; NO implementar
- **Evidencia:** requisito citado sin interpretar: `docs/auditoria/SRS_EXTRAIDO.txt:109` (RF-04: el profesional asignado "**podrá seleccionar** desde un catálogo dinámico… qué requisitos se cumplen o faltan"). Backend **existe y está probado**: `routes/api.php:58-59` → `EvaluacionAdmisibilidadController@requisitos:23-33` y `@store:44-61` con `StoreEvaluacionAdmisibilidadRequest`, servicio `EvaluacionAdmisibilidadService`, tablas `catalogo_requisitos`/`evaluaciones_admisibilidad`, tests `EvaluacionAdmisibilidadTest`. UI: `grep -i "evaluacion|requisito" resources/views/**` → 0 llamadas a `/requisitos` ni `/evaluacion` (las coincidencias `detalle.blade.php:218,320` son `verificarRequisitoAdjunto` del modal de actuados). Estado previo en `MATRIZ_SRS_IMPLEMENTACION.md:29` = "IMPLEMENTADA (FE pendiente: sin UI de checklist)" con acción "UI en Fase 14".
- **Impacto:** RF-04 describe una capacidad **del usuario**; sin pantalla, solo es accesible por HTTP directo con sesión. Relacionado con AUD-0014 (endpoint global de catálogo de requisitos) y AUD-0061.
- **Acción propuesta:** diseñar la superficie (checklist por expediente + persistencia de la evaluación) como parte de la decisión de alcance de UI junto a AUD-0035/0061. NO implementar sin decisión explícita.

### AUD-0061 — 11 operaciones con endpoint dedicado no tienen interfaz
- **Severidad:** P1 (alcance de producto / flujo normativo incompleto en UI)
- **Fase:** 14
- **Estado:** OPEN — decisión de alcance de UI pendiente; NO implementar
- **Evidencia (todas verificadas en `routes/api.php` con grep de `resources/views/` = 0):**
  | Operación | Ruta | Fase backend |
  |---|---|---|
  | Evaluación de requisitos (RF-04) | `api.php:58-59` | 7 → **AUD-0060** |
  | Impugnación remitir/resolver | `api.php:62-63` | 7 → ya **AUD-0035** |
  | Planificación + VB + devolución | `api.php:66-68` | 8 |
  | Ampliación + aprobación | `api.php:71-72` | 8 |
  | Expediente hijo (NUREJ) | `api.php:75` | 8 |
  | Cierre: VB + reparto | `api.php:78-79` | 9 |
  | Derivación a Transparencia + remisión | `api.php:82-83` | 9 |
  | Descargos: comunicar/recibir | `api.php:86-87` | 9 |
- **Impacto:** vistos desde el usuario final, el sistema "tiene" planificación (RN-04/05), cierre con VB, Transparencia (US-2.6) y descargos (RN-09, 5 días hábiles) **solo como API**: el operador no puede ejecutarlos desde la interfaz. Relacionados: **AUD-0028** (VB/devolución Encargada), **AUD-0035** (impugnación), **AUD-0039**, **AUD-0060**.
- **Acción propuesta:** en conjunto con AUD-0035, definir la superficie de operaciones contra el flujo normativo (no botones aislados) y priorizarla. NO implementar en F14.

### AUD-0062 — N+1 medido: feriados y suspensiones se recargan por cada expediente/plazo serializado
- **Severidad:** P2 (rendimiento: crece linealmente con filas/plazos por página; 60% de las queries de la bandeja son redundantes)
- **Fase:** 15
- **Estado:** OPEN — fix PROPUESTO (unificar/cachear `PlazoCalculatorService`), pendiente de decisión; NO implementado
- **Evidencia (código):** `app/Http/Resources/ExpedienteResource.php:82` y `app/Http/Resources/PlazoResource.php:24` llaman `app(SemaforoPlazoService::class)` **por cada ítem serializado**; no hay `singleton`/`bind` en `app/Providers` (grep = 0) → cada llamada construye instancia nueva; `app/Services/SemaforoPlazoService.php:11-14` inyecta `PlazoCalculatorService`, cuyo constructor (`app/Services/PlazoCalculatorService.php:23-25`) ejecuta `loadFeriados()` (`:141-143`, `Feriado::pluck('fecha')`) y `loadSuspensiones()` (`:149`, `suspensiones_plazo`) **una vez por instancia**. Verificado con test de identidad en tinker: dos `app()` → instancias distintas (`$a === $b` → `false`) y +4 queries.
- **Evidencia (medición, `DB::listen` en BD dev 2026-10-01):** `GET /api/bandeja` completo = **43 queries / 127 ms**, de las cuales **13× `select fecha from feriados` + 13× `select fecha_inicio, fecha_fin from suspensiones_plazo` = 26/43 (60%)**; serialización directa de 15 expedientes con `relacionesDetalle` = 32 queries (22 repetidas); detalle de 1 expediente = 12 queries (2 repetidas). Composición: 3 semáforos de `ExpedienteResource` (plazos VIGENTES) + 10 semáforos de `PlazoResource` (10 plazos en dev).
- **Impacto:** cada página de bandeja/disparo de detalle paga ~2 queries extra por plazo y por expediente con plazo vigente; con volumen histórico del SRS esto domina el conteo de queries por request. Relacionado con **AUD-0018** (costo por página) y **RNF-03**.
- **Acción propuesta:** PROPUESTA — cachear feriados/suspensiones (singleton por request o `memoize` en `PlazoCalculatorService`) o inyectar una única instancia del servicio calculador en los resources. Requiere aprobación (toca services/resources). NO implementado en F15.

### AUD-0063 — Dashboard admin: carga completa de expedientes y 5 consultas sobre la misma tabla por petición
- **Severidad:** P2 (rendimiento/escalabilidad: costo lineal con el total de expedientes)
- **Fase:** 15
- **Estado:** OPEN — fix PROPUESTO (agregados en SQL, patrón del dashboard Encargada), pendiente de decisión; NO implementado
- **Evidencia (código):** `app/Http/Controllers/Administrador/AdminDashboardController.php:95` `Expediente::with(['plazos','asignacionActiva.usuario'])->get()` (**tabla completa** + plazos, agregación del semáforo en PHP `:97-126`); pasadas sobre `expedientes` en la misma petición: `:64` count, `:77-81` GROUP BY via, `:88` whereDoesntHave, `:95` get completo, `:133-136` take(5).
- **Evidencia (medición):** traza de queries de `GET /api/admin/dashboard` con `DB::listen` = 23-24 queries / 65 ms (BD dev), incluyendo `select * from expedientes` completo (query #13 de la traza). Dashboard Encargada equivalente = 23 queries / 30 ms **con select acotado** (`EncargadaDashboardService:88-113`: `whereHas('plazos')` + `select` mínimo) → patrón correcto ya existente en el proyecto.
- **Impacto:** con miles de expedientes (contexto del SRS), el dashboard admin carga y agrega en PHP toda la tabla en cada carga de pantalla (CPU PHP + memoria + red BD), aunque la respuesta solo devuelve resúmenes. Contrasta con Maestro §55 "dashboard" y "no cargar miles de expedientes".
- **Acción propuesta:** PROPUESTA — convertir los agregados del semáforo/`fuera_de_plazo` a consultas SQL (o reusar el enfoque del dashboard Encargada). Requiere aprobación. NO implementado en F15.

### AUD-0064 — Sin índice en `expedientes.fecha_ingreso`: filesort en cada página de bandeja y en monitoreo
- **Severidad:** P3 (rendimiento: plan de ejecución subóptimo; sin impacto medible con datos actuales)
- **Fase:** 15
- **Estado:** OPEN — índice PROPUESTO (justificado por patrón real de consulta), pendiente de decisión; NO creado
- **Evidencia (EXPLAIN, MySQL 9.7, BD dev):** bandeja operador (`EXISTS` + `ORDER BY fecha_ingreso DESC LIMIT 15`) → subquery materializada usa `asignaciones_usuario_id_foreign`, expedientes `eq_ref` PK, **`Extra: Using temporary; Using filesort`**; bandeja sorteo y monitoreo con filtro de estado → `ref idx_expedientes_estado` + **`Using filesort`** (§6 de `MATRIZ_RENDIMIENTO.md`, EXPLAIN #1-#3).
- **Evidencia (esquema):** `php artisan db:table expedientes` → solo `idx_expedientes_estado`, `idx_expedientes_padre`, `idx_expedientes_via`, UNIQUE `nurej_code`, PK y FKs (`mig 2026_08_25_191155_create_expedientes_table.php:30-32`); **ningún índice cubre `fecha_ingreso`**.
- **Patrón real que lo justifica:** `ORDER BY fecha_ingreso` en `ExpedienteController:62,75`, `AdminMonitoreoController:98,102,110`, `AdminDashboardController:134`.
- **Impacto:** ordenamiento completo (temp+filesort) en cada página; con volumen alto el sort domina el costo. **Limitación:** con 24 filas en dev no hay impacto observable; `EXPLAIN` con datos triviales puede subestimar el plan.
- **Acción propuesta:** PROPUESTA — evaluar `index('fecha_ingreso')` o compuesto `(estado_actual_id, fecha_ingreso)` (bandeja por estado). Cada opción debe justificarse contra las consultas citadas antes de crearla. NO creado en F15 (§55: sin índices indiscriminados).

### AUD-0065 — `sesiones_acceso` sin índice en `login_at` / `(exitoso, login_at)`; el dashboard admin ordena y filtra esas columnas
- **Severidad:** P3 (rendimiento: full scan + filesort; tabla que crece con cada login)
- **Fase:** 15
- **Estado:** OPEN — índice PROPUESTO, pendiente de decisión; NO creado
- **Evidencia (esquema):** `php artisan db:table sesiones_acceso` → solo PK y `sesiones_acceso_usuario_id_foreign`; **sin índices en `login_at` ni `exitoso`**.
- **Evidencia (código):** `AdminDashboardController:159-162` `SesionAcceso::with('usuario')->orderByDesc('login_at')->take(6)`; `:172-174` `where('exitoso', false)->where('login_at', '>=', now()->subDay())->count()`.
- **Evidencia (EXPLAIN):** `ORDER BY login_at DESC LIMIT 6` → `type=ALL`, key NULL, `Using filesort`; `WHERE exitoso=0 AND login_at>=…` → `type=ALL`, key NULL (§6, EXPLAIN #13-#14).
- **Impacto:** cada carga del dashboard admin recorre la tabla completa de sesiones (una fila por intento de login del sistema, crecimiento continuo por la auditoría de sesiones). **Limitación:** 3 filas en dev → costo actual nulo; el riesgo es el crecimiento.
- **Acción propuesta:** PROPUESTA — índices `login_at` y `(exitoso, login_at)` justificados por las dos consultas citadas. NO creado en F15.

### AUD-0066 — El cron diario de plazos hace full scan de `plazos` (sin índice `(estado, fecha_limite)`)
- **Severidad:** P3 (rendimiento de la automatización diaria; sin impacto actual por volumen)
- **Fase:** 15
- **Estado:** OPEN — índice PROPUESTO, pendiente de decisión; NO creado
- **Evidencia (código):** `app/Services/MarcarPlazosVencidosService.php:26-31` (`tipo_plazo != … AND estado='VIGENTE' AND fuera_de_plazo=false AND fecha_limite < hoy` → UPDATE) y `app/Services/ArchivoPorAbandonoService.php:49-54` (mismo filtro de `fecha_limite` → `get()`), ambos ejecutados por `plazos:verificar-vencidos` diario (`routes/console.php:11`).
- **Evidencia (esquema):** `php artisan db:table plazos` → solo PK + 4 índices FK; **sin índice que combine `estado`/`fecha_limite`**.
- **Evidencia (EXPLAIN):** `SELECT * FROM plazos WHERE estado='VIGENTE' AND fecha_limite < CURDATE()` → `type=ALL`, key NULL, `Using where` (§6, EXPLAIN #9).
- **Impacto:** recorrido completo de `plazos` una vez al día; crece con el histórico de plazos (los plazos no se borran). Relacionado con **AUD-0042** (robustez del mismo comando) y **AUD-0036** (falta de unicidad) — este hallazgo es solo de índice.
- **Acción propuesta:** PROPUESTA — índice `(estado, fecha_limite)` o `(fecha_limite, estado)` justificado por las dos consultas del cron. NO creado en F15.

### AUD-0067 — Búsqueda por NUREJ/resumen con `LIKE '%…%'`: full scan no indexable y sin endpoint de búsqueda
- **Severidad:** P3 (rendimiento + deuda de RNF-03: la única búsqueda del sistema no puede usar índice)
- **Fase:** 15
- **Estado:** OPEN — rediseño de búsqueda PROPUESTO, pendiente de decisión; NO implementado
- **Evidencia (código):** `app/Http/Controllers/Administrador/AdminMonitoreoController.php:52-57` `nurej_code LIKE '%buscar%' OR resumen_hechos LIKE '%buscar%'`; invocado desde `resources/views/administrador/monitoreo.blade.php:73,421-426` (input con debounce); **grep de rutas de búsqueda en `routes/` = 0** (no hay endpoint dedicado de búsqueda por NUREJ).
- **Evidencia (EXPLAIN):** `… nurej_code LIKE '%A-%' OR resumen_hechos LIKE '%A-%'` → `type=ALL`, key NULL (§6 #4); `… resumen_hechos LIKE '%test%'` → `type=ALL` (#6); contra-partida: `nurej_code = 'A-0001'` → **const vía `expedientes_nurej_code_unique`** (#5) → el índice existe y funciona solo con igualdad/prefijo.
- **Impacto:** la búsqueda monitoreo siempre escanea la tabla completa de expedientes (+`resumen_hechos` TEXT); imposible de acelerar con índices btree en el patrón `%…%`. Relacionado con **AUD-0018** (búsqueda exigida por RNF-03).
- **Acción propuesta:** PROPUESTA — definir el patrón de búsqueda (NUREJ por prefijo/exacto aprovechando el índice único; FULLTEXT o alternativa para `resumen_hechos`; o endpoint de búsqueda dedicado). Es decisión de diseño, NO un índice que agregar. NO implementado en F15.

---

## Observaciones O-x (sin ficha AUD) — O-4…O-10 decisión 2026-09-30; O-11…O-19 añadidas en F14 (2026-10-01); O-20…O-21 añadidas en F15 (2026-10-01)

| ID | Evidencia exacta (origen) | Decisión |
|---|---|---|
| O-4 | `MATRIZ_BANDEJAS.md:77-80`: la misma "bandeja del operador" exige `operadorBandeja` en web pero no en API; un rol no operativo recibe 403 en web y lista vacía (o su propia asignación) en API. **Verificado contra RF-03 (2026-09-30):** `ExpedienteController@bandejaOperador:72-76` filtra por `asignacionActiva.usuario_id = $request->user()->id` y `show:85` exige `authorize('view')` → **sin lectura/operación de expedientes ajenos**. | **Observación, NO elevada.** Divergencia API/web documentada; sin brecha RF-03 no se convierte en hallazgo formal. Unificación de autorización = mejora opcional futura. |
| O-6 | `FLUJO_TECNICO.md:123-126`: el Técnico que creó la causa puede ganar su propio sorteo (candidatos = todos los TECNICO activos; el SRS no lo prohíbe). Verificado: `SorteoAlgorithmService:83-89` filtra solo `activo` + rol, sin `creado_por`. | **CERRADA (2026-09-30, cierre F12): "Comportamiento aceptado provisionalmente; el SRS no establece prohibición explícita de autoasignación."** No se autoriza excluir al creador del pool en esta fase. Si luego se decide excluir → abrir como **cambio funcional separado con sus respectivos tests**. |
| O-7 | `FLUJO_JURIDICO.md:158-161`: `ImpugnacionService:87` usa `fecha_limite_resolucion = $plazoResolucion?->fecha_limite ?? now()`; si falta el parámetro `IMPUGNACION_RESOLVER` (BD dev 8/17, AUD-0023) la impugnación queda con límite "ahora" aunque el flujo siga vivo. **Verificado 2026-09-30:** BD dev con **0 filas** `tipo_plazo = IMPUGNACION_RESOLVER` → fallback `now()` activo en dev. | **CERRADA (2026-09-30, cierre F12) como SUB-CASO de AUD-0023 — sin ficha P2 independiente.** Documentado: en BD dev el parámetro está ausente y el fallback `now()` produce un límite de resolución inmediato. NO modificar el comportamiento. Riesgo/candidato de corrección **asociado a la sincronización de parámetros de AUD-0023**. |
| O-10 | `routes/console.php:11`: `Schedule::command('plazos:verificar-vencidos')->daily()` sin `withoutOverlapping()`/`onOneServer()`/lock; sin índice único `(expediente_id, tipo_plazo)` en `create_plazos_table` → corridas solapadas podrían emitir dos actuados de archivo. Detectada en Fase 13 (`MATRIZ_JOBS_CRON.md` §4). | **Observación, NO elevada** (regla 2026-09-30): no reproducida experimentalmente (stress tests no cubren este comando) → sin evidencia no se clasifica; monitorizar/ reproducir antes de decidir fix (`withoutOverlapping()` o lock). |
| O-11 | F14: `bandeja-operador.blade.php:128-131` muestra un único mensaje genérico con "Reintentar" sin distinguir `status` ni mostrar `data.message` (contrasta con el resto de vistas). | **Observación, NO elevada (F14):** hay mensaje visible; es calidad de copy, no ausencia de feedback. |
| O-12 | F14: `feriados.blade.php:293-317` permite editar/eliminar feriados con fecha pasada (backend sin restricción temporal). | **Observación, NO elevada (F14):** grep en SRS sin prohibición → no se inventa requisito. Si se decide bloquear, es cambio funcional con tests. |
| O-13 | F14: un ADMIN puede autoeditarse (incluido su rol) vía `PUT /api/admin/usuarios/{id}`. | **Observación, NO elevada (F14):** sin requisito que lo prohíba; puede ser intencional → **decisión pendiente** (¿prohibir auto-cambio de rol?). |
| O-14 | F14: `apertura.blade.php:223-241` sin guarda anti-doble envío en el mismo frame (`enviando` solo se setea tras la validación de cliente). | **Observación, NO elevada (F14):** no ejecutado en navegador; probabilidad baja. |
| O-15 | F14: `GET /api/estados` (`routes/api.php:47`) y `GET /api/usuarios` (`:37`) no son llamados por ninguna vista. | **Observación, NO elevada (F14):** funcionalidad sin consumidor; sin impacto de usuario. |
| O-16 | F14: botón "Actualizar" de `administrador/dashboard.blade.php:13` sin `:disabled` durante la carga (cargas concurrentes posibles). | **Observación, NO elevada (F14):** sin efecto sobre datos. |
| O-17 | F14: `monitoreo.blade.php:544` define `claseSemaforo` sin invocarlo (código muerto). | **Observación, NO elevada (F14):** sin impacto funcional. |
| O-18 | F14: `parametros.blade.php:81` `min="1"` vs valor por defecto `dias_fuera_plazo: 0`. | **Observación, NO elevada (F14):** vista inalcanzable (cubierto por AUD-0047). |
| O-19 | F14: descarga de adjunto en pestaña nueva (`detalle.blade.php:185`) sin manejo visible de 403. | **Observación, NO elevada (F14):** no verificable estáticamente (sin navegador). |
| O-20 | F15: el monitoreo filtra `estado` **después** de cargar todo (`AdminMonitoreoController:295-312`) porque `POR_VENCER`/`FUERA_DE_PLAZO` son estados computados, y el `resumen` se calcula sobre el conjunto completo (`:317-335`). | **Observación, NO elevada (F15):** decisión de diseño documentada en el código; se registra porque condiciona la solución de AUD-0018 (paginar el monitoreo no es trivial). |
| O-21 | F15: la mayoría de `->get()` sin límite están acotados por catálogo/purpose (catálogos, feriados, candidatos de sorteo, vencidos del día, partes de un padre). | **Observación, NO elevada (F15):** no todo `get()` es defecto — dataset pequeño/estable por naturaleza (inventario completo en `MATRIZ_RENDIMIENTO.md` §8). |

---

## Plantilla en blanco

```
### AUD-XXXX — Título corto
- **Severidad:**
- **Fase:**
- **Estado:**
- **Evidencia:**
- **Impacto:**
- **Acción propuesta:**
```
