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
| AUD-0001 | `ExpedientePolicy::viewAny` devuelve `true` a ADMIN → test 403 falla | P1 | 0 | **DECIDIDO (2026-09-30, cierre F12): DEUDA CONOCIDA aceptada; F12 no lo introdujo ni agravó; sin cambio de código ni de test en F12.** Semántica de ADMIN (opciones A/B/C de la ficha) → resolver en la fase de hardening/seguridad; no aplicar ninguna todavía; NO modificar el test para hacerlo pasar |
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
| AUD-0030 | Plazos no se cierran al concluir su fase (EVALUACION/EJECUCION) → falsos `fuera_de_plazo` permanentes | P1 | 7 | OPEN |
| AUD-0031 | Archivo por abandono sin validar estado actual: CRON archiva casos vivos que salieron de EN_SUBSANACION | P1 | 7 | OPEN |
| AUD-0032 | EN_SUBSANACION sin salida de éxito (no existe actuado "subsanación aceptada", RN-03 incompleto) | P1 | 7 | OPEN |
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
| AUD-0043 | Corte UTC prematuro: comando corre 00:00 UTC = 21:00 ART y compara `fecha_limite` contra fecha UTC → archiva/marca hasta 3 h antes de la medianoche local que pide el SRS | P2 | 13 | OPEN — fix requiere decisión de zona horaria institucional (config global); NO aplicado |

---

## Fichas

### AUD-0001 — `ExpedientePolicy` da acceso total a rol ADMIN
- **Severidad:** P1 (seguridad / RF-03 compartimentos)
- **Fase:** 0 (detectado) · 1 (evidencia recopilada) · pendiente de resolución en Fase 3 + decisión del usuario
- **Estado:** OPEN/P1 — **DECISIÓN (2026-09-30, cierre de validación F12): deuda conocida ACEPTADA.** F12 no introdujo ni agravó AUD-0001; el fallo existente queda conocido y documentado (único fallo de la suite). NO autorizado ningún cambio de código dentro de F12; NO modificar el test para hacerlo pasar artificialmente. La semántica de acceso de ADMIN (opciones A/B/C abajo) se traslada a la fase de hardening/seguridad correspondiente — mantenerlas abiertas, no aplicar ninguna todavía. *(Decisión previa "NEEDS_REVIEW" de 2026-09-30 queda resuelta por esta.)*
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
- **Estado:** OPEN — **INCORPORADO formalmente a la lista de decisiones pendientes del usuario (2026-09-30, cierre F12); NO aplicar.** Propuesta técnica mantenida: decidir entre **(i)** cerrar los plazos al salir de la fase o **(ii)** filtrar CRON/semáforo por fase actual; + tests. No bloquea F12 (hallazgo anterior; F12 no lo introdujo ni modificó).
- **Evidencia:** los únicos cierres en `app/` son: rechazo cierra todos (`EvaluacionAdmisibilidadService:156-158`), entrega de planificación cierra PLANIFICACION (`PlanificacionService:223-229`), ampliación cierra EJECUCION (`AmpliacionService:111`), impugnación cierra los suyos (`ImpugnacionService:246,257,269`), descargos→CUMPLIDO, archivo→VENCIDO. **No cierran:** EVALUACION tras ADMISION/OBSERVACION, EJECUCION tras VB final/reparto (`CierreExpedienteService` ni menciona `Plazo`), SUBSANACION (AUD-0031).
- **Impacto:** el CRON diario (`MarcarPlazosVencidosService:26-31`, `routes/console.php:11`) estampa `fuera_de_plazo=true` en relojes ya cumplidos; `SemaforoPlazoService@colorMasUrgente:151-163` toma el VIGENTE más antiguo → cada expediente admitido queda con semáforo FUERA_DE_PLAZO permanente y los concluidos siguen contando como urgentes en los tableros RF-R01/R05 → falsa sanción interna a operadores cumplidores.
- **Acción propuesta:** cerrar el plazo de la fase al transicionar fuera de ella (o filtrar CRON/semáforo por fase actual) + tests. No aplicar sin aprobación.

### AUD-0031 — Archivo por abandono sin validar el estado actual del expediente
- **Severidad:** P1 (acción automática destructiva; RN-03 mal aplicado)
- **Fase:** 7
- **Estado:** OPEN — **INCORPORADO formalmente a la lista de decisiones pendientes del usuario (2026-09-30, cierre F12); NO aplicar.** Propuesta técnica mantenida: condicionar el archivo por abandono al estado `EN_SUBSANACION` y/o cerrar correctamente el plazo al salir de esa fase + test negativo. No bloquea F12 (hallazgo anterior; F12 no lo introdujo ni modificó).
- **Evidencia:** `ArchivoPorAbandonoService@archivarVencidos:49-54` filtra solo `tipo=SUBSANACION AND estado=VIGENTE AND fecha_limite < hoy`, **sin consultar el estado del expediente**; el plazo SUBSANACION jamás se cierra en ninguna transición de salida (grep: ningún cierre `CERRADO` para SUBSANACION fuera de este servicio). Programado a diario (`routes/console.php:11`). Los tests solo cubren expedientes que siguen en EN_SUBSANACION (`ArchivoPorAbandonoTest:88-220`).
- **Impacto:** un expediente que salió de EN_SUBSANACION (p. ej. salto vía endpoint genérico — AUD-0020) y sigue vivo es archivado automáticamente a los 3 días: emite ACT_ARCHIVO_POR_ABANDONO, cierra su bandeja (`:105-110`) y lo sella en ARCHIVO_POR_ABANDONO.
- **Acción propuesta:** cerrar el plazo SUBSANACION en toda salida y/o condicionar el archivo al estado `EN_SUBSANACION` + test negativo. No aplicar sin aprobación.

### AUD-0032 — EN_SUBSANACION sin salida de éxito (RN-03 incompleto)
- **Severidad:** P1 (flujo muerto en admisibilidad; Plan §12)
- **Fase:** 7
- **Estado:** OPEN — **INCORPORADO formalmente a la lista de decisiones pendientes del usuario (2026-09-30, cierre F12); NO aplicar.** Propuesta técnica mantenida: creación del actuado de salida de subsanación (p. ej. `ACT_SUBSANACION_ACEPTADA`) y transición correspondiente; migración de catálogo **sujeta a aprobación** (junto a AUD-0009). No bloquea F12 (hallazgo anterior; F12 no lo introdujo ni modificó).
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
- **Estado:** OPEN
- **Evidencia:** `php artisan config:show app.timezone` → **UTC**; `routes/console.php:11` `->daily()` → `0 0 * * *` (=`schedule:list`); grep `timezone|America/` en repo = 0; comparaciones con `now()->toDateString()` en `ArchivoPorAbandonoService:52` y `MarcarPlazosVencidosService:30`. SRS `SRS_EXTRAIDO.txt:443`: el archivo por abandono "**se dispara a la medianoche**". **Reproducido experimentalmente:** test `VerificarVencimientoPlazosTest` «archiva con la fecha UTC ya cambiada pero aún es el día de vencimiento en Argentina» — `Carbon::setTestNow('2026-09-08 00:05:00')` UTC = 2026-09-07 21:05 ART (UTC-3), `fecha_limite = 2026-09-07` → **archiva** (actuado inmutable emitido).
- **Impacto:** el plazo vence efectivamente a las **21:00** hora local del día de vencimiento, no a la medianoche: hasta 3 h de ventana en las que el interesado aún está dentro del día hábil local pero el sistema ya archiva el expediente (irreversible) y estampa `fuera_de_plazo`. Misma ventana afecta el semáforo/conservas de fechas comparadas en UTC.
- **Acción propuesta:** **Fix NO aplicado — requiere decisión del usuario:** fijar la zona horaria institucional (p. ej. `app.timezone` + zona del schedule) con impacto global en todo el sistema de fechas (plazos, semáforos, feriados, tests). Presentar antes el análisis de impacto; cualquier cambio de configuración global requiere confirmación explícita.

---

## Observaciones O-x (sin ficha AUD) — decisión 2026-09-30

| ID | Evidencia exacta (origen) | Decisión |
|---|---|---|
| O-4 | `MATRIZ_BANDEJAS.md:77-80`: la misma "bandeja del operador" exige `operadorBandeja` en web pero no en API; un rol no operativo recibe 403 en web y lista vacía (o su propia asignación) en API. **Verificado contra RF-03 (2026-09-30):** `ExpedienteController@bandejaOperador:72-76` filtra por `asignacionActiva.usuario_id = $request->user()->id` y `show:85` exige `authorize('view')` → **sin lectura/operación de expedientes ajenos**. | **Observación, NO elevada.** Divergencia API/web documentada; sin brecha RF-03 no se convierte en hallazgo formal. Unificación de autorización = mejora opcional futura. |
| O-6 | `FLUJO_TECNICO.md:123-126`: el Técnico que creó la causa puede ganar su propio sorteo (candidatos = todos los TECNICO activos; el SRS no lo prohíbe). Verificado: `SorteoAlgorithmService:83-89` filtra solo `activo` + rol, sin `creado_por`. | **CERRADA (2026-09-30, cierre F12): "Comportamiento aceptado provisionalmente; el SRS no establece prohibición explícita de autoasignación."** No se autoriza excluir al creador del pool en esta fase. Si luego se decide excluir → abrir como **cambio funcional separado con sus respectivos tests**. |
| O-7 | `FLUJO_JURIDICO.md:158-161`: `ImpugnacionService:87` usa `fecha_limite_resolucion = $plazoResolucion?->fecha_limite ?? now()`; si falta el parámetro `IMPUGNACION_RESOLVER` (BD dev 8/17, AUD-0023) la impugnación queda con límite "ahora" aunque el flujo siga vivo. **Verificado 2026-09-30:** BD dev con **0 filas** `tipo_plazo = IMPUGNACION_RESOLVER` → fallback `now()` activo en dev. | **CERRADA (2026-09-30, cierre F12) como SUB-CASO de AUD-0023 — sin ficha P2 independiente.** Documentado: en BD dev el parámetro está ausente y el fallback `now()` produce un límite de resolución inmediato. NO modificar el comportamiento. Riesgo/candidato de corrección **asociado a la sincronización de parámetros de AUD-0023**. |
| O-10 | `routes/console.php:11`: `Schedule::command('plazos:verificar-vencidos')->daily()` sin `withoutOverlapping()`/`onOneServer()`/lock; sin índice único `(expediente_id, tipo_plazo)` en `create_plazos_table` → corridas solapadas podrían emitir dos actuados de archivo. Detectada en Fase 13 (`MATRIZ_JOBS_CRON.md` §4). | **Observación, NO elevada** (regla 2026-09-30): no reproducida experimentalmente (stress tests no cubren este comando) → sin evidencia no se clasifica; monitorizar/ reproducir antes de decidir fix (`withoutOverlapping()` o lock). |

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
