# PROGRESO — Auditoría Sistema de Control y Fiscalización

Log de ejecución. Cada fase cierra solo con `php artisan test --compact` en verde
y evidencia de verificación. Hallazgos → `BACKLOG_AUDITORIA.md`.

---

## Decisiones del usuario — RESUELTAS (2026-09-30, fases 3-12)

**Esta sección es la fuente de verdad vigente y sustituye a todas las
menciones históricas de "decisiones pendientes del usuario" de las fases
anteriores (que se conservan solo como registro de su momento).**

| Hallazgo | Decisión (2026-09-30) | Estado |
|---|---|---|
| AUD-0001 | **(cierre F12)** Deuda conocida **ACEPTADA**; F12 no lo introdujo ni agravó; sin cambio de código ni de test; semántica ADMIN (opciones A/B/C) → fase de hardening/seguridad; NO maquillar el test | OPEN/P1, deuda conocida |
| AUD-0019 | P2 confirmado; parametrizar 3-5 días según complejidad (no asumir 5); antes: fuente de "complejidad" | DECIDIDO, fix no aplicado |
| AUD-0020 | P1/OPEN; validar estado origen; antes: matriz `actuado→origen→destino` | **FIX APLICADO en B1.2 (2026-10-06)**: validación `estado_origen_id` en `registerActuado`/`evaluar` (422) + lock; matriz completa no requerida por decisión del usuario |
| AUD-0021 | P1/OPEN; grafo actual NO aceptado; antes: matriz `estado→actuados→destinos→roles` (SRS, sin inventar) | **FIX PARCIAL en B1.2 (2026-10-06)**: D-6a informe → `PENDIENTE_VISTO_BUENO_FINAL`; D-6b `ADMITIDO` → `EN_PLANIFICACION`; matriz completa pendiente |
| AUD-0023 | P2/OPEN; NO `db:seed` global; sync idempotente de catálogos; ejecución dev con autorización específica | DECIDIDO, fix no aplicado |
| AUD-0024 | P1 confirmado, NO fuera de alcance; campo `naturaleza` (no `via`); antes: impacto de migración+tests | DECIDIDO, fix no aplicado |
| AUD-0025 | P2 confirmado; AC022=2d, AC054/055 MPA sin reloj rígido; ejecución = fecha MPA; antes: cambio mínimo+tests | DECIDIDO, fix no aplicado |
| AUD-0027 | P2/OPEN; validar destino (activo+rol+institucional) en servidor; antes: matriz `actuado→roles/destinos` | DECIDIDO, fix no aplicado |
| AUD-0028 | P1/OPEN; dashboard agregado NO sustituye supervisión operativa; diseñar bandeja desde casos de uso | DECIDIDO, fix no aplicado |
| AUD-0029 | P2/OPEN; reasignación explícita con actuado+trazabilidad, sin reasignación silenciosa; antes: flujo institucional | DECIDIDO, fix no aplicado |
| AUD-0033 | P1/OPEN; NO `estado_nuevo_id` nullable; resolver en el grafo (destino informe → espera VB → verificar → cambio mínimo) | **FIX APLICADO en B1.2 (2026-10-06)**: D-6a destino del informe = `PENDIENTE_VISTO_BUENO_FINAL` en seeder (sin nullable); `FlujoJuridicoTest` sigue con fixture propia (comentario obsoleto sin tocar) |
| AUD-0034 | P2 (funcional, no seguridad); fix conceptual autorizado (catálogo contextual + validación servidor); NO aplicar hasta AUD-0033/0020/0021 | DECIDIDO, fix no aplicado |
| AUD-0035 | P1 confirmado; endpoints NO sustituyen UI; verificar conjunto de operaciones jurídicas y diseñar UI contra flujo normativo | DECIDIDO, fix no aplicado |
| AUD-0039 | **Opción B adoptada** (`AUD-0039_DISENO.md` §7): `via_destino` explícito en servidor, mapeo vía→reglamento (JURIDICO→AC054, FINANCIERO→AC055), matriz normativa de combinaciones. **(B1.5) Implementado (2026-10-06)** tras aprobar `MATRIZ_DERIVACIONES.md` (D-P1/D-P2 aplicadas, D-P3 descartada): whitelist estricta M1-M4 + informe técnico habilitante, hijo sin herencia de `via`/`reglamento_id`, C8 vía metadatos existentes | **CERRADO en B1.5 (2026-10-06)**: `NurejHijoEspecialidadTest` 7/7; suite 370 · 363 OK · 0 fallos · 7 omitidos · 1931 aserciones |
| AUD-0040 | **(cierre F12)** Opción (a): **MONITORIZAR** — flake/no reproducido, causa raíz abierta; sin cambio en helper ni producto sin reproducción fiable o evidencia de causa raíz | OPEN/P3, monitorización |
| AUD-0041 | P2; fix AUTORIZADO y **APLICADO**: check `activo`+rol en `AdminDashboardController` (patrón Monitoreo) + test; gates OK | **CERRADO (2026-09-30, con validación de F12)** |
| AUD-0038 | Autorización mantenida (ya aplicada en Fase 11): 422 controlado + test; sin cambios relacionados con AUD-0039 | CERRADO |
| O-4 | Observación, NO elevada: verificado contra RF-03 → `bandejaOperador:72-76` filtra por usuario y `show:85` exige policy → sin lectura de expedientes ajenos | Resuelta (observación) |
| O-6 | **(cierre F12) CERRADA: "Comportamiento aceptado provisionalmente; el SRS no establece prohibición explícita de autoasignación."** Sin exclusión del creador en esta fase; si se decide luego → cambio funcional separado con tests | Cerrada (aceptada provisionalmente) |
| O-7 | **(cierre F12) CERRADA como sub-caso de AUD-0023** (sin ficha P2): BD dev sin `IMPUGNACION_RESOLVER` (0 filas) → fallback `now()` con límite inmediato; sin modificar; candidato de corrección en la sync de parámetros | Cerrada (asociada a AUD-0023) |
| AUD-0030 | **(cierre F12)** decidir: cerrar plazos al salir de fase vs filtrar CRON/semáforo. **(B1.3) Opción (i) aprobada (2026-10-06): cerrar al salir de fase, Opción A sin `fecha_cierre`** (fecha derivada de `actuados.fecha_hora`) | **CERRADO en B1.3 (2026-10-06)**: `MAPA_CIERRA_PLAZO` + `cerrarPlazosDeFase()` en la transacción de `registerActuado`; `CierrePlazosFasesTest` 7/7; `FlujoIntegralTest:333` actualizado; suite 354 · 347 OK · 0 fallos · 7 omitidos |
| AUD-0031/0032 | **(cierre F12)** incorporados formalmente a la lista de decisiones pendientes; NO aplicar. **(B1.4) Resueltos (2026-10-06)** — 0031: filtro estricto `EN_SUBSANACION` + `try/catch` por expediente en `archivarVencidos()` (huérfanos fuera de alcance, decisión del usuario); 0032: actuado `ACT_SUBSANACION_ACEPTADA` en seeder aprobado (`EN_SUBSANACION → EN_EVALUACION`, sin reloj EVALUACION nuevo, sin adjunto, 422 si plazo vencido contra `today()` America/La_Paz o `VENCIDO`) | **CERRADO en B1.4 (2026-10-06)**: `SubsanacionExitoTest` 9/9; regresión 29/29; pint OK; suite 363 tests · 356 OK · 0 fallos · 7 omitidos |

**`NEEDS_REVIEW` al cierre de F12: NINGUNO** (los cuatro elementos
anteriores —AUD-0001, AUD-0040, O-6, O-7— quedaron resueltos con las
decisiones de cierre de Fase 12).

**Regla general de fixes (del usuario):** estas decisiones NO autorizan la
ejecución masiva. Por hallazgo: (1) dependencias → (2) cambio mínimo →
(3) implementar cuando corresponda → (4) tests específicos → (5) suite →
(6) documentar regresión. **AUD-0020, AUD-0021, AUD-0024, AUD-0025,
AUD-0033 y AUD-0039 = conjunto de diseño** (estados, naturaleza,
especialidad, reglamento, plazos). **Sin BD de desarrollo ni seeders
globales sin autorización específica.**

**Nota sobre AUD-0030/0031/0032 (Fase 7):** **(cierre F12) incorporados
formalmente a la lista de decisiones pendientes del usuario** (antes no
figuraban en su lista y quedaban como "fix propuesto bajo la regla
general"). Mantenidas sus propuestas técnicas en las fichas; **NO aplicados;
no bloquean F12** (hallazgos anteriores que F12 no introdujo ni modificó).
Cada ejecución requerirá su autorización específica en el momento.

---

## Fase 0 — Descubrimiento (CERRADA, 2026-09-29)

### Entorno verificado

- PHP 8.5.9 (Windows ZTS) · Laravel 13.27.0 · Sanctum 4.3.3 · Pest 5.1.2
- MySQL 9.7.0, InnoDB, `utf8mb4_unicode_ci`, **28 tablas** (esquema leído con
  `SHOW COLUMNS`/FKs reales, no asumido)
- `SESSION_DRIVER=database` · `APP_DEBUG=true` (⚠️ verificar antes de prod)
- Schedule: `plazos:verificar-vencidos` diario (`routes/console.php`)

### Baseline de tests (línea base de referencia)

`php artisan test --compact` → **252 tests: 239 OK · 2 fallos · 5 errores · 6 omitidos**

- Fallos/errores → AUD-0001 (policy 403→200), AUD-0002 (fecha plazo), AUD-0003 (×5 `getJson()`)

### Superficie verificada

- **Rutas:** 58 (`routes/api.php` + `routes/web.php`); 1 duplicada (AUD-0004)
- **Autorización:** `authorize()` presente en controllers y en 31 Form Requests;
  casos sin `authorize()` directo verificados individualmente en Form Requests
- **Modelos vs migraciones:** sin desalineaciones inventadas; relaciones OK
- **Transacciones:** 24 usos de `DB::transaction`; sin `dd()`; sin soft deletes; sin Enums
- **Frontend:** Blade + Tailwind/Alpine/FontAwesome por CDN (AUD-0006) + `fetch` a `/api/*`;
  `resources/js/app.js` = 3 bytes (sin build JS real)
- **Cruce docs previos:** `doc_avances/` parcialmente desactualizado (AUD-0005);
  se usó solo como pista, no como verdad
- **SRS:** `docs/auditoria/SRS_EXTRAIDO.txt` (447 líneas, UTF-8 OK)

### Entregables

- `docs/auditoria/PLAN_EJECUCION_AUDITORIA.md` (plan 18 fases + protocolo)
- `docs/auditoria/MAPA_FUNCIONAL.md` (23 módulos con evidencia)
- `docs/auditoria/BACKLOG_AUDITORIA.md` (16 hallazgos iniciales)
- `docs/auditoria/SRS_EXTRAIDO.txt`
- `docs/auditoria/PROGRESO.md` (este archivo)

### Salida de fase

16 hallazgos (AUD-0001…0016): 8 P1 · 6 P2 · 2 P3. Ninguno cerrado.
Los P1 de seguridad/plazos (AUD-0001, AUD-0002) requieren decisión del usuario
antes de tocar autorización o lógica de plazos.

---

## Fase 1 — Mapa funcional (CERRADA, 2026-09-29)

### Trabajo realizado

- `MAPA_FUNCIONAL.md` reescrito con **tabla maestra de 14 columnas** (formato Plan Maestro §5): ~60 funcionalidades en 8 módulos, cada fila verificada contra ruta → controlador → autorización (Form Request/policy/middleware/ownership) → servicio → modelo/tabla → tests (cruce por grep de URI en `tests/`).
- **Reconciliación 58 vs 54 (objetiva, ver `MAPA_FUNCIONAL.md §1.1`):**
  - 58 = declaraciones `Route::` en los archivos: `routes/api.php` 45 + `routes/web.php` 13 (45/13 coincide con el registro de Fase 0). De esas, **4 son declaraciones de grupo** (api:29; web:18,31,45) → 54 rutas reales.
  - 54 = `php artisan route:list --except-vendor` (44 api + 10 web; GET 25 + POST 26 + PUT 2 + DELETE 1; 10 con nombre / 44 sin nombre; 51 URIs distintas porque 3 URIs tienen 2 métodos).
  - 59 = `route:list` sin filtro = 54 + 5 vendor (`_boost/browser-logs`, `sanctum/csrf-cookie`, `storage/{path}` GET/PUT, `up`).
  - HEAD: Laravel añade HEAD a cada GET en la misma fila (`GET|HEAD`); no suma rutas.
  - Raíz/fallback: **no existen** (grep; `bootstrap/app.php` solo `web`, `api`, `health:'/up'`).
  - El comando literal de Fase 0 no quedó guardado; se documenta sin retro-corrregir su registro.
- **AUD-0001:** evidencia de requisitos recopilada (SRS `:84-86` perfil ADMIN con capacidad de monitoreo **y** restricción de no ver archivos; RF-03 `:108` sin excepción; matriz de roles; policies; `EnsureAdmin`; menús; no existe ADMIN técnico/operativo). Ficha enriquecida con 3 hipótesis; **sin cerrar, sin tocar policy ni test**.
- **AUD-0002:** contexto registrado (servicio `DescargoFinancieroService@abrirSubRelojDescargos:270` usa `now()`; test con fecha fija `2026-09-23` y feriados 15/16-09; calculado `2026-10-06`); hipótesis H1/H2 sin concluir; **sin tocar servicio ni test**; resolución en Fase 4.
- **AUD-0008:** evidencia matizada (catálogo **sí** tiene informes jurídico y financieros; falta informe Técnico AC022 y flujo/vista específico) — grep verificado: 12 referencias en `app/`, 0 en vistas.
- **Hallazgo nuevo AUD-0017 (P2):** `AdminDashboardController:26-31` y `AdminMonitoreoController:24-33` autorizan con `abort(403)` inline (las rutas API no llevan `EnsureAdmin`); el resto de endpoints admin sí usa policies.
- Cobertura de tests por ruta mapeada: **rutas `administrador/*` y `api/admin/*` con 0 tests que las referencien** (registrado como fila NO VERIFICADA, Fase 16).

### Gate de salida (reproducido)

- `php artisan test --compact` → **252 tests: 239 OK · 2 fallos · 5 errores · 6 omitidos** — **idéntico al baseline de Fase 0** (Fase 1 no modificó código; solo MDs).
- Cero filas `NO VERIFICADA` sin fase que las cierre (`MAPA_FUNCIONAL.md §4`).

### Entregables actualizados

- `MAPA_FUNCIONAL.md` (Fase 1 completa) · `BACKLOG_AUDITORIA.md` (AUD-0001/0002/0008 enriquecidos, AUD-0017 añadido) · `PROGRESO.md` · `PLAN_EJECUCION_AUDITORIA.md` (estado).

### Salida de fase

17 hallazgos abiertos: 8 P1 · 7 P2 · 2 P3. Ninguno cerrado.
(Nota: el desglose de severidad de Fase 0 "9 P1 · 5 P2" estaba mal contado: el correcto de esa fase era 8 P1 · 6 P2 · 2 P3; se corrige aquí, es solo aritmética de la tabla de resumen.)

---

## Fase 2 — Matriz SRS → código (CERRADA, 2026-09-29)

### Trabajo realizado

- Creado `docs/auditoria/MATRIZ_SRS_IMPLEMENTACION.md` con las columnas exigidas (ID, Requisito, Ubicación SRS, Implementación, Archivos, Estado, Evidencia, Brecha, Acción, Prueba) sobre `SRS_EXTRAIDO.txt` con números de línea.
- **Cobertura:** 2 diferencias tecnológicas (PostgreSQL/JSONB → MySQL/JSON, no brechas) · 6 RF · 4 RNF · 9 RF-R (+ preámbulo de reportes) · 10 RN · 23 actuados del catálogo SRS vs seeder (1 a 1) · perfil ADMIN · 2 restricciones · 4 criterios de aceptación · 1 fila fuera de alcance.
- **Verificaciones nuevas hechas en Fase 2 (grep, no supuestos):** `paginate()`=0 y rutas de búsqueda=0 (RNF-03); `Hash::make`=3 (RNF-01); "enmienda" en todo el código = 1 sola coincidencia (mensaje del trigger); `parametros_plazo` completos leídos del seeder (2/3/2/10/15/5/5/5/1/3); `reglamentos.version` existe (RN-06); sin librerías Excel/PDF en `composer.json`; informes: Técnico 0/4, Jurídico 1/2, Financiero 2/2 (contra SRS `:374-413`).
- **Ambigüedades confirmadas:** RF-03/CA-2/PERF-ADMIN → AUD-0001 (sin decidir); "firma digitalmente" de RF-06 interpretada como cadena de hash (confirmar con usuario si exige PKI).
- **Hallazgos nuevos:** AUD-0018 (P2, RNF-03 sin paginación/búsqueda), AUD-0019 (P2, RN-02 rango 3-5 días no parametrizable).

### Gate de salida (reproducido)

- `php artisan test --compact` → **252: 239 OK · 2 fallos · 5 errores · 6 omitidos** (idéntico al baseline; sin cambios de código).

### Entregables

- `MATRIZ_SRS_IMPLEMENTACION.md` (nuevo) · `BACKLOG_AUDITORIA.md` (+AUD-0018/0019, AUD-0008 refinado) · `PROGRESO.md` · `PLAN_EJECUCION_AUDITORIA.md`.

### Salida de fase

19 hallazgos abiertos: 8 P1 · 9 P2 · 2 P3. Ninguno cerrado.

---

## Fase 3 — Seguridad/autorización (CERRADA, 2026-09-29)

### Trabajo realizado

- Creado `docs/auditoria/MATRIZ_SEGURIDAD.md`: los 54 endpoints (44 API + 10 web) con su capa de autorización real, regla aplicada, resultado IDOR (recurso ajeno) y evidencia de test.
- **Tests nuevos:** `tests/Feature/SeguridadIdorTest.php` — 16 tests (IDOR por endpoint: requisitos, adjunto, evaluación, planificación, ampliación, descargos, impugnación/resolver, sorteo, dashboards, catálogo de operativos, inactivación, panel ADMIN api+web, apertura, 401 admin). Semilla propia `idorSemilla()` (sin depender de helpers de otros archivos).
- **Resultados:** 44 endpoints con 403/401 testeado · 6 solo-código (CRUD admin, misma policy ya testeada en GET) · 1 falla conocida (AUD-0001) · 1 filtrado (bandeja) · 2 N/A.
- **Sin tocar:** `ExpedientePolicy`, tests preexistentes, lógica de plazos.
- Nota: ~10 de los 16 tests nuevos duplican cobertura existente (declarado en la matriz); aportan cobertura nueva a: endpoints ADMIN api/web, inactivación, 401 admin y confirmación cruzada de 8 políticas.

### Gate de salida (reproducido)

- `php artisan test --compact` → **268: 255 OK · 2 fallos · 5 errores · 6 omitidos** = baseline (252/239/2/5/6) + 16 tests nuevos en verde.
- `vendor/bin/pint --dirty` aplicado al archivo nuevo.

### Salida de fase

19 hallazgos abiertos: 8 P1 · 9 P2 · 2 P3 (sin cambios en Fase 3). Ninguno cerrado. Pendiente decisión de usuario sobre AUD-0001.

---

## Fase 4 — Máquina de estados / actuados (CERRADA, 2026-09-29)

### Trabajo realizado

- Creado `docs/auditoria/MAQUINA_ESTADOS.md`: inventario de 21 estados normativos + verificación por query de la BD dev (16 estados / 7 actuados → AUD-0023); grafo completo de transiciones (25 actuados, origen→destino, emisor, validación); plazos por actuado; tabla de validación de origen por vía; huecos del grafo; inmutabilidad; cobertura de tests.
- Creado `docs/auditoria/MATRIZ_ACTUADOS.md`: matriz operativa de los 25 actuados (A01-A24) con rol/reglamento, automático, adjunto, transición, plazo, endpoint, policy, validación y tests.
- **Hallazgos nuevos:** AUD-0020 (P1: el endpoint genérico no valida `estado_origen_id` — grep confirma que esa columna no se lee en `app/`; saltos de estado, re-emisión, cierre sin informe en AC022/054) · AUD-0021 (P1: informe AC022/054 con destino null → cierre inalcanzable por arista válida; ADMITIDO sin salida tras revocación; estados huérfanos EN_INVESTIGACION/EN_DESCARGOS/CONCLUIDO) · AUD-0022 (P2: ningún test ejecuta los seeders de catálogo; fixtures divergentes) · AUD-0023 (P2: BD dev desactualizada — requiere confirmación para `db:seed`).
- **Inmutabilidad:** requisito del plan (test que espere el fallo de UPDATE/DELETE) **ya cubierto** por `CadenaCustodiaTest:137,153` → 0 tests nuevos creados.
- Sin tocar: `ExpedientePolicy`, servicios, seeders, tests existentes. AUD-0001 y AUD-0002 sin cambios (abiertos, por instrucción del usuario; el análisis de plazos de AUD-0002 se completa en Fase 5).

### Gate de salida (reproducido)

- `php artisan test --compact` → **268: 255 OK · 2 fallos · 5 errores · 6 omitidos** (idéntico al de Fase 3; sin cambios de código).

### Salida de fase

23 hallazgos abiertos: 10 P1 · 11 P2 · 2 P3. Ninguno cerrado. Decisiones pendientes del usuario: AUD-0001 (bypass ADMIN), AUD-0020/0021 (grafo), AUD-0023 (re-seed BD dev). *(`Decisiones pendientes` de este registro histórico → RESUELTAS 2026-09-30, ver sección al inicio; AUD-0001 → deuda conocida aceptada (cierre F12).)*

---

## Fase 5 — Motor de plazos (CERRADA, 2026-09-29)

### Trabajo realizado

- Creado `docs/auditoria/MATRIZ_PLAZOS.md`: §1 motor (`PlazoCalculatorService` regla por regla: sin día de inicio, sáb/dom, feriados, suspensiones inclusivas, 23:59:59, recálculo al reanudar); §2 parámetros (17 en seeder vs SRS vs **8/17 en BD dev**); §3 qué abre cada reloj (mapa actuado→relój→parámetro); §4 vencimiento/fuera de plazo/semáforo (CA-3 verificado: no bloquea, estampa, tableros); §5 cobertura por requisito; §7 **AUD-0002 análisis de raíz COMPLETADO**; §8 observaciones O-1/O-2/O-3; §9 decisiones (resueltas 2026-09-30, ver BACKLOG/PROGRESO).
- **AUD-0002:** H1 **CONFIRMADA** (test con fechas fijas escrito para `now ≈ 2026-09-15`, sin `travelTo`; motor calcula correctamente 2026-10-06 desde hoy 2026-09-29; con `now=2026-09-15` + feriados del propio test produce exactamente 2026-09-23). H2 **DESCARTADA** (motor cumple CA-1 `SRS :181` y RN-09). Fix propuesto (`travelTo` o expectativa relativa) **NO aplicado: requiere aprobación del usuario** (test intocable). Ficha actualizada en BACKLOG; sigue OPEN.
- **Hallazgos nuevos:** AUD-0024 (P1: `resolveSubtipoEjecucion:230-233` hardcodea JURISDICCIONAL → RN-05 15 días Administrativa inalcanzable, sin atributo de naturaleza en `expedientes`) · AUD-0025 (P2: PLANIFICACION=2 para MPA de auditores vs SRS "sin plazo estricto") · AUD-0026 (P3: ampliación sobre ejecución vencida calcula desde el límite vencido).
- **AUD-0023 ampliado:** `parametros_plazo` = 8/17 en BD dev (afecta a `AmpliacionService`/`ImpugnacionService` con `firstOrFail` y al silenciamiento de relojes en `ActuadoService:193-195`).
- Sin tocar: código, servicios, seeders, tests (0 modificaciones en esta fase; solo MDs del backlog).

### Gate de salida (reproducido)

- `php artisan test --compact` → **268: 255 OK · 2 fallos · 5 errores · 6 omitidos** (idéntico; sin cambios de código).

### Salida de fase

26 hallazgos abiertos: 11 P1 · 12 P2 · 3 P3. Ninguno cerrado. Decisiones pendientes del usuario: AUD-0001, AUD-0002 (fix del test), AUD-0019, AUD-0020/0021, AUD-0023 (re-seed), AUD-0024, AUD-0025. *(`Decisiones pendientes` de este registro histórico → RESUELTAS 2026-09-30, ver sección al inicio; AUD-0001 → deuda conocida aceptada (cierre F12).)*

---

## Fase 6 — Bandejas y roles (CERRADA, 2026-09-29)

### Trabajo realizado

- Creado `docs/auditoria/MATRIZ_BANDEJAS.md`: §1 roles en BD (5 roles, 15 usuarios, 5 asignaciones activas — query directa); §2 mecánica (bandeja = `asignaciones.activa`, re-enrutamiento solo por actuado vía `reasignarBandeja:140-156`, visibilidad `ExpedientePolicy::view:66-87`); §3 **matriz Plan §16** (7 bandejas: ventanilla, sorteo, operador, jerárquica, descargos, final, admin — con Entrada/Rol/Acción/Actuado/Salida/Plazo); §4 superficies API/web + O-4; §5 Encargada vs Plan §8/8.1 (checklist con brechas); §6 hallazgos; §7 cobertura de tests.
- Verificado ✅: RF-03 (aislamiento), registro sin asignación + lectura del creador pre-sorteo, exención jerárquica de `crearActuado`, inactivación revoca tokens/sesiones (`SeguridadSesionesService:50-54`), destinos hardcodeados por flujo en los 5 servicios específicos.
- **Hallazgos nuevos:** AUD-0027 (P2: `usuario_destino_id` controlado por el cliente en el actuado genérico → cualquier usuario como destino, sin `activo`) · AUD-0028 (P1: sin bandeja de supervisión/acción para la Encargada — solo agregados; monitoreo solo-ADMIN; web de bandeja le da 403) · AUD-0029 (P2: expulsar no redistribuye asignaciones activas → expedientes huérfanos).
- Observación O-4: autorización divergente de `GET /api/bandeja` (API sin `authorize` vs web con `operadorBandeja`).
- Sin tocar: código, tests, BD (solo MDs).

### Gate de salida (reproducido)

- `php artisan test --compact` → **268: 255 OK · 2 fallos · 5 errores · 6 omitidos** (idéntico; sin cambios de código).

### Salida de fase

29 hallazgos abiertos: 12 P1 · 14 P2 · 3 P3. Ninguno cerrado. Decisiones pendientes del usuario (acumuladas): AUD-0001, AUD-0002, AUD-0019, AUD-0020/0021, AUD-0023, AUD-0024, AUD-0025, AUD-0027, AUD-0028, AUD-0029, O-4. *(`Decisiones pendientes` de este registro histórico → RESUELTAS 2026-09-30, ver sección al inicio; AUD-0001 → deuda conocida aceptada, O-4 resuelta sin elevar (cierre F12).)*

---

## Fase 7 — Flujos Técnico / Sorteo (CERRADA, 2026-09-29)

### Trabajo realizado

- Creado `docs/auditoria/FLUJO_TECNICO.md`: §1 sorteo contra los 9 puntos del Plan §11 (CSPRNG + RNG inyectable, elegibilidad por rol de vía, pesos monótonos `sorteo_pesos`, transaccional + lock, trazabilidad con hash, sin edición); §2 recorrido completo registro→NUREJ Hijo (12 pasos con estado/plazo/test/veredicto); §3 checklist Plan §12 (:895-904); §4 hallazgos; §5 cobertura; §6 decisiones.
- Verificado ✅: RN-04 relojes de evaluación/cronograma/investigación/ampliación, RN-08 impugnación, rechazo cierra todos los plazos, RN-10 NUREJ Hijo hereda sin relojes, lote de sorteo todo-o-nada.
- **Hallazgos nuevos:** AUD-0030 (P1: los plazos no se cierran al concluir su fase — EVALUACION tras admisión/observación, EJECUCION tras cierre → CRON estampa `fuera_de_plazo` permanente y semáforo rojo eterno en RF-R01/R05) · AUD-0031 (P1: `ArchivoPorAbandonoService:49-54` sin filtro de estado actual del expediente + plazo SUBSANACION nunca cerrado → CRON archiva casos vivos) · AUD-0032 (P1: EN_SUBSANACION sin salida de éxito — único actuado de origen es el archivo automático, RN-03 incompleto vs Plan §12).
- Observación O-6: el creador de la causa puede ganar su propio sorteo (SRS no lo prohíbe).
- Sin tocar: código, tests, BD (solo MDs).

### Gate de salida (reproducido)

- `php artisan test --compact` → **268: 255 OK · 2 fallos · 5 errores · 6 omitidos** (idéntico; sin cambios de código).

### Salida de fase

32 hallazgos abiertos: 15 P1 · 14 P2 · 3 P3. Ninguno cerrado. Decisiones pendientes del usuario (acumuladas): AUD-0001, AUD-0002, AUD-0019, AUD-0020/0021, AUD-0023, AUD-0024, AUD-0025, AUD-0027, AUD-0028, AUD-0029, **AUD-0030, AUD-0031, AUD-0032**, O-4, O-6. *(`Decisiones pendientes` de este registro histórico → RESUELTAS 2026-09-30, ver sección al inicio; 0030/0031/0032 incorporados a la lista de decisiones (cierre F12); O-6 cerrada.)*

---

## Fase 8 — Flujo Jurídico (CERRADA, 2026-09-30)

### Trabajo realizado

- Creado `docs/auditoria/FLUJO_JURIDICO.md`: §0 identidad del rol (no hay
  ruta/vista "jurídico": grep `juridic` en `app/` → 0; entra por
  `Rol::CODIGO_AUD_JURIDICO` y comparte superficie con Técnico/Financiero);
  §1 recorrido completo (10 pasos: sorteo → bandeja → evaluación → MPA →
  impugnación RN-08 → descargos omitidos correctamente → informe → control
  jerárquico → salida RN-09 `:166` → NUREJ Hijo) con estado/plazo/test/veredicto;
  §2 matriz de validación negativa (error, no autorizado, estado inválido,
  doble envío, concurrencia) con evidencia de test; §3 checklist SRS
  (RN-02/04/05/07/08/09, RF-03/04, `:393-402`); §4 hallazgos; §5 cobertura;
  §6 decisiones.
- **Tests nuevos:** `tests/Feature/FlujoJuridicoTest.php` — 9 tests con semilla
  propia `fjSemilla()` (patrón `descargoSemilla`/`impugnacionSemilla`): happy
  path RN-08 del jurídico (remitir → EN_IMPUGNACION + plazo 3 d + impugnación
  PENDIENTE), **verificación del P1 del informe**, 403 (rol equivocado /
  expediente ajeno RF-03 / sin asignación), 422 (sin adjunto / adjunto no-PDF),
  doble envío (segundo 403 + 1 sola impugnación), estado inválido (403).
- **Hallazgos nuevos:** AUD-0033 (P1: `ACT_INFORME_FINAL` **no emisible** —
  `actuados.estado_nuevo_id` NOT NULL en la migración `:21` vs
  `estado_destino_id = null` en `CatalogoActuadoSeeder:62`; verificado con test:
  500 `QueryException`, transacción revertida, 0 actuados; informes financieros
  no afectados) · AUD-0034 (P2: catálogo de actuados sin `expediente_id` en
  `detalle.blade.php:297` + `expediente_id` sin validar en
  `IndexCatalogoActuadosRequest`) · AUD-0035 (P1: RN-08 e informe jurídico sin
  interfaz — grep `impugnaci|informe|resolver` en vistas → 0).
- Observación O-7 (`ImpugnacionService:87`: límite de resolución cae a `now()`
  si falta el parámetro, caso BD dev AUD-0023). AUD-0008 refinado con el cruce
  a AUD-0033/0035.
- **Sin tocar:** código de producto, policies, servicios, seeders, migraciones
  ni tests preexistentes. La semilla del test **replica** la configuración real
  (destino `null` + pivote AUD_JURIDICO) y **espera el fallo real**; no se
  adaptó nada para ocultar el comportamiento (documentado en
  `FLUJO_JURIDICO.md` §2, nota de transparencia).

### Gate de salida (reproducido)

- `php artisan test --compact` → **277: 264 OK · 2 fallos · 5 errores · 6 omitidos**
  = baseline de Fase 0/3-7 (268/255/2/5/6) + 9 tests nuevos en verde.
  Mismos fallos/errores conocidos (AUD-0001, AUD-0002, AUD-0003).
- `vendor/bin/pint --dirty --format agent` → passed.

### Entregables

- `FLUJO_JURIDICO.md` (nuevo) · `tests/Feature/FlujoJuridicoTest.php` (nuevo) ·
  `BACKLOG_AUDITORIA.md` (+AUD-0033/0034/0035, O-7, AUD-0008 refinado) ·
  `PROGRESO.md` · `PLAN_EJECUCION_AUDITORIA.md`.

### Salida de fase

35 hallazgos abiertos: 17 P1 · 15 P2 · 3 P3. Ninguno cerrado. Decisiones
pendientes del usuario (histórico, acumuladas): AUD-0001, 0002, 0019, 0020, 0021, 0023,
0024, 0025, 0027, 0028, 0029, 0030, 0031, 0032, **0033, 0034, 0035**, O-4, O-6, O-7.
→ **RESUELTAS 2026-09-30** (ver sección «Decisiones del usuario — RESUELTAS»
al inicio; todos resueltos al cierre de F12: AUD-0001 = deuda conocida,
O-6 aceptada, O-7 sub-caso AUD-0023 — `NEEDS_REVIEW` = ninguno).

---

## Nota — decisiones del usuario (2026-09-30, post Fase 8)

- **AUD-0002 → CERRADO.** Fix autorizado: solo del test
  (`DescargoFinancieroTest.php:157-161` congela `2026-09-15 10:00:00`, reset
  `:214-215`); **servicio intacto** (H2 había descartado el motor). Gate:
  archivo 5/5 · suite **277: 265 OK · 1 fallo · 5 errores · 6 omitidos**
  (fallo AUD-0001 y errores AUD-0003 del baseline; desaparece AUD-0002).
  Evidencia en `MATRIZ_PLAZOS.md` §7 y `BACKLOG_AUDITORIA.md`.
- **AUD-0033 (P1):** NO corregir aún — sin nullable ni destino hasta analizar
  el grafo (AUD-0021) y la semántica normativa de `ACT_INFORME_FINAL`.
- **AUD-0034 (P2):** no tocar FormRequest/vista; en Fase 9 determinar impacto
  funcional/seguridad real de la ausencia de `expediente_id`.
- **AUD-0035 (P1):** no crear UI; documentar independencia de AUD-0033 y
  verificar alcance contra el flujo normativo completo (RN-08/RN-09).
- **Decisiones que siguen vigentes/sin resolver (histórico post-Fase 8):** AUD-0001, 0019, 0020, 0021,
  0023, 0024, 0025, 0027, 0028, 0029, 0030, 0031, 0032, O-4, O-6, O-7
  (las salidas de fase anteriores son snapshots históricos; este bloque manda
  sobre los previos). → **SUPERADO 2026-09-30:** todas resueltas según la
  sección «Decisiones del usuario — RESUELTAS» al inicio de este documento
  (los `NEEDS_REVIEW` de entonces —AUD-0001, O-6, O-7— quedaron resueltos
  al cierre de F12).
- Backlog: **36 abiertos (16 P1 · 15 P2 · 5 P3) + 1 cerrado (AUD-0002)** — cifras vigentes al cierre de Fase 9 (arriba).

---

## Fase 9 — Flujo Financiero (CERRADA, 2026-09-30)

### Trabajo realizado

- Aplicado el **fix autorizado de AUD-0002** (solo test): reloj congelado en
  `DescargoFinancieroTest:157-161` / reset `:214-215`; servicio intacto. Ficha
  **CERRADO**; quitan decisiones resueltas de `MATRIZ_PLAZOS.md` §7/§9,
  `FLUJO_TECNICO.md` §6, `FLUJO_JURIDICO.md` §6, `PLAN` §5, `MAPA_FUNCIONAL`,
  `MATRIZ_ACTUADOS`.
- Creado `docs/auditoria/FLUJO_FINANCIERO.md`: §0 alcance (RN-09
  `SRS_EXTRAIDO.txt:159-168`, Art. 25 Ac. 55/2018); §1 recorrido de 6 pasos
  (comunicar → pausa + sub-reloj 5 d → recibir → cierre/reanudación con
  recálculo → informe CON/SIN con Bloqueo de Salida → `PENDIENTE_VISTO_BUENO_FINAL`,
  fases 3-5 fuera de alcance); §2 matriz de validación negativa (403 no
  autorizado / 403 estado inválido / 422 del servicio sin reloj vigente /
  422 reloj no suspendido sin efectos / concurrencia secuencial ✓ y paralela
  no reproducida); §3 checklist RN-09; §4 hallazgos; §5 cobertura; §6
  decisiones.
- **Tests nuevos:** `tests/Feature/FlujoFinancieroTest.php` — 4 tests con
  semilla propia `ffSemilla()`: (1) **403 por estado inválido** en comunicar y
  recibir (policy filtra antes que el servicio), (2) **422 del servicio** sin
  reloj EJECUCION vigente, (3) **422 al recibir con el reloj principal no
  suspendido** con verificación de ausencia de efectos secundarios, (4)
  **Bloqueo de Salida para el informe SIN responsabilidad** (ambos códigos
  `CODIGOS_INFORMES_FINANCIEROS:32-35` cubiertos). 4/4 verdes.
- **AUD-0034 análisis de impacto COMPLETADO** (decisión del usuario):
  **seguridad = ninguno** (respuesta filtrada por rol `CatalogoActuadoController:32-54`
  + gate `:24`, sin datos ajenos ni inyección), **funcional = 403 al emitir
  actuados de otro reglamento** mostrados en el modal; P2 confirmado.
  **Decisión: fix aprobado como solución a implementar posteriormente, NO
  autorizado a modificar producto durante la auditoría** (corregir el origen
  del catálogo + mantener validación en servidor).
- **Observaciones convertidas en fichas formales** (decisión del usuario):
  **AUD-0036** (ex O-8: concurrencia paralela de `comunicar` sin índice único
  en `plazos` ni `lockForUpdate` — análisis, no reproducido; **P3, no elevar
  sin evidencia experimental**) · **AUD-0037** (ex O-9: `firstOrFail`/`findOrFail`
  → 404; documentado el comportamiento y analizados los flujos con respuesta
  funcionalmente incorrecta/no controlada — config/BD ausente, impugnación
  inexistente —; **P3, no clasificar como vulnerabilidad sin evidencia**).
- **Sin tocar:** código de producto, policies, servicios, seeders, migraciones
  ni tests preexistentes (salvo el fix de AUD-0002 autorizado).

### Gate de salida (reproducido)

- `php artisan test --compact` → **281: 269 OK · 1 fallo · 5 errores · 6 omitidos**
  (4 tests nuevos en verde; únicos rojos del baseline: fallo AUD-0001 y
  errores AUD-0003 — AUD-0002 ya cerrado).
- `vendor/bin/pint --dirty --format agent` → passed.

### Entregables

- `FLUJO_FINANCIERO.md` (nuevo) · `tests/Feature/FlujoFinancieroTest.php` (nuevo) ·
  `BACKLOG_AUDITORIA.md` (AUD-0002 CERRADO; AUD-0034 con análisis) ·
  `PROGRESO.md` · `PLAN_EJECUCION_AUDITORIA.md`.

### Salida de fase

**36 hallazgos abiertos: 16 P1 · 15 P2 · 5 P3 — 1 cerrado (AUD-0002).**
Decisiones del usuario de Fase 9 **resueltas** (2026-09-30): AUD-0034 (fix
aprobado a futuro, sin autorización para tocar producto), O-8 → **AUD-0036**
(ficha observación P3), O-9 → **AUD-0037** (ficha observación P3).
Decisiones pendientes acumuladas (histórico): AUD-0001, 0019, 0020, 0021, 0023, 0024,
0025, 0027, 0028, 0029, 0030, 0031, 0032, 0033, 0035, O-4, O-6, O-7.
→ **RESUELTAS 2026-09-30** (ver sección «Decisiones del usuario — RESUELTAS»
al inicio; AUD-0001/O-6/O-7 resueltos al cierre de F12; 0030/0031/0032
incorporados a la lista de decisiones (cierre F12)).

---

## Fase 10 — Flujo Encargada (CERRADA, 2026-09-30)

### Trabajo realizado

- Creado `docs/auditoria/FLUJO_ENCARGADA.md`: §0 alcance (Plan §3:
  bandeja con contexto completo, VB de cronograma y MPA;
  `EncargadaDashboardService` contra SQL real; SRS `:77,:91-92,:142,:145,:346,:352`);
  §1 comparación campo por campo servicio ↔ SQL directa en BD dev (solo
  lectura, scripts tinker `f10_*`): **coincide en los 13 campos comparables**
  (24/19/19/5 totales, por_via, por_estado, carga_operadores con mismos
  totales, últimos 5, 5 fuera de plazo, 0 próximos, feriado 2026-12-25) y se
  demostró que el semáforo con 8 plazos en 5 expedientes no subregistra
  (`colorMasUrgente` toma el plazo vigente más temprano = el más urgente,
  `SemaforoPlazoService:151-163`); §2 VB Cronograma/MPA con cobertura
  preexistente de `PlanificacionTest` (15 tests) más los nuevos; §3 matriz de
  validación negativa; §4 hallazgos; §5 cobertura; §6 decisiones.
- **Tests nuevos:** `tests/Feature/FlujoEncargadaTest.php` — 3 tests con
  semilla propia `feSemilla()`: (1) **401 sin sesión** en la API del
  dashboard, (2) **contexto completo** del tablero sembrado (totales, carga,
  semáforo fuera de plazo, vencimiento con nurej/asignado, feriado próximo,
  por_estado, últimos), (3) **concurrencia secuencial del VB**: segundo VB
  tras aprobar → 403 y devolución posterior → 403, con exactamente 1 actuado
  de VB y el expediente firme en `EN_EJECUCION`. 3/3 verdes.
- **AUD-0003 raíz determinada y CERRADA:** cadena
  `Sanctum::actingAs(...)->getJson(...)` en `EncargadaDashboardTest:149-150`
  (`actingAs` devuelve el `Usuario`); **fix autorizado por el usuario y
  aplicado 2026-09-30** (separar en dos sentencias, exclusivamente en esas
  líneas del test; sin tocar producción). Ficha → **CERRADO**; `FLUJO_ENCARGADA.md` §4.
- **Sin hallazgos nuevos de producto** en Fase 10. **Sin tocar:** código de
  producto, policies, servicios, seeders, migraciones (fix de AUD-0003
  limitado a `tests/Feature/EncargadaDashboardTest.php:149-150`).

### Gate de salida (reproducido, tras el fix de AUD-0003)

- `vendor/bin/pint --dirty --format agent` → passed.
- `php artisan test --compact` → **284: 277 OK · 1 fallo · 0 errores · 6 omitidos**
  (3 tests nuevos de Fase 10 en verde; los 5 errores de AUD-0003 eliminados;
  único rojo restante = fallo AUD-0001 del baseline — sin regresiones).

### Entregables

- `FLUJO_ENCARGADA.md` (nuevo) · `tests/Feature/FlujoEncargadaTest.php` (nuevo) ·
  `tests/Feature/EncargadaDashboardTest.php` (fix autorizado AUD-0003) ·
  `BACKLOG_AUDITORIA.md` (AUD-0003 **CERRADO**) · `PROGRESO.md` ·
  `PLAN_EJECUCION_AUDITORIA.md`.

### Salida de fase

**Fase 10 VALIDADA Y CERRADA por el usuario (2026-09-30).**
**35 hallazgos abiertos: 16 P1 · 14 P2 · 5 P3 — 2 cerrados (AUD-0002, AUD-0003).**
Decisiones del usuario de Fase 10 **resueltas**: AUD-0003 (fix autorizado y
aplicado), Fase 10 validada con todos los resultados documentados.
Decisiones pendientes acumuladas (histórico): AUD-0001, 0019, 0020, 0021, 0023, 0024,
0025, 0027, 0028, 0029, 0030, 0031, 0032, 0033, 0035, O-4, O-6, O-7.
→ **RESUELTAS 2026-09-30** (ver sección «Decisiones del usuario — RESUELTAS»
al inicio).

---

## Fase 11 — NUREJ Padre/Hijo (VALIDADA Y CERRADA, 2026-09-30)

### Trabajo realizado

- Creado `docs/auditoria/FLUJO_NUREJ.md`: §0 alcance (Plan §3: independencia
  de actuados y filtrado de línea de tiempo; SRS `:106,:170-174,:127`) y
  superficie (`routes/api.php:75` → `ExpedienteController@derivarNurejHijo:149`
  → `NurejHijoService@crearHijo:32` → `NurejGeneratorService@generarHijo:35`);
  §1 recorrido happy path (hijo en `PENDIENTE_SORTEO`, partes copiadas,
  `ACT_CREACION_NUREJ_HIJO` sobre el padre sin cambio de estado); §2
  verificaciones (esquema: `nurej_code` UNIQUE + FK padre + índice —
  migración `:16,:26,:31`; filtrado de línea de tiempo por FK `expediente_id`
  sin endpoint global de actuados; 0 hijos en BD dev); §3 validación negativa;
  §4 hallazgos; §5 cobertura; §6 decisiones.
- **Tests nuevos:** `tests/Feature/FlujoNurejTest.php` — 3 tests con semilla
  propia `fnSemilla()`: (1) **independencia de línea de tiempo** vía API:
  tras derivar y emitir un actuado en el hijo, `GET` del padre lista exactamente
  sus 2 actuados y `GET` del hijo solo el suyo (ids disjuntos, descripciones
  sin contaminación cruzada) — RN-10 `:173`; (2) el hijo no hereda
  plazos/asignaciones y el segundo derivado usa correlativo `-2` (RF-01);
  (3) **partes copiadas independientes**: modificar la parte del hijo no toca
  la del padre. 3/3 verdes.
- **AUD-0038 (nuevo, P2) — CERRADO:** reproducción → derivar desde un ya
  derivado → **HTTP 500** (`CannotDeriveNurejException` es `DomainException`
  sin handler HTTP; grep 0 mapeos; policy no excluye derivados). **Fix
  autorizado y aplicado 2026-09-30:** manejo HTTP explícito en
  `bootstrap/app.php` (`render()` → **422** con mensaje + `dontReport`),
  elegido por coherencia con el contrato 422 existente (sin precedentes 409);
  servicio/policy/generador/unit test intactos. Test feature
  `FlujoNurejTest:207` (422 + mensaje + 0 sub-hijos).
- **AUD-0039 (nuevo, P2 — confirmada por el usuario):** el hijo hereda
  `via`/`reglamento_id` del padre (`NurejHijoService:46-47`), sin input ni
  endpoint de especialidad destino, y el sorteo elige por `via`
  (`SorteoAlgorithmService:30-33,:85-89`) → contradice RN-10 `:171,:173`.
  **Decisión: sin fix; análisis de diseño obligatorio en la siguiente fase
  disponible** (alcance definido por el usuario en BACKLOG) antes de proponer
  solución; **no se modificó código**.
- **AUD-0040 (nueva observación, P3):** `AmpliacionTest:175` falló 1 vez en
  la primera suite completa tras el fix (288 tests) y **no reprodujo**
  (aislado 10/10 + segunda suite limpia); candidato raíz
  `ampliacionAsignar:108` (`CatalogoActuado::first()` sin `ORDER BY`);
  monitorizar, no elevar sin reproducción.
- **Sin tocar:** código de producto salvo el fix autorizado de AUD-0038
  (`bootstrap/app.php`); policies, servicios, seeders, migraciones y tests
  preexistentes intactos (solo archivos nuevos de tests/docs).

### Gate de salida (reproducido, tras el fix de AUD-0038)

- `vendor/bin/pint --dirty --format agent` → passed.
- `php artisan test --compact` → **288: 281 OK · 1 fallo · 0 errores · 6 omitidos**
  (4 tests nuevos de Fase 11 en verde; único rojo = fallo AUD-0001 del
  baseline — sin regresiones; corrida intermedia con 1 fallo no reproducido
  en `AmpliacionTest` → AUD-0040).

### Entregables

- `FLUJO_NUREJ.md` (nuevo) · `tests/Feature/FlujoNurejTest.php` (nuevo) ·
  `bootstrap/app.php` (fix autorizado AUD-0038) · `BACKLOG_AUDITORIA.md`
  (AUD-0038 **CERRADO**; AUD-0039 confirmada P2; AUD-0040 nueva) ·
  `PROGRESO.md` · `PLAN_EJECUCION_AUDITORIA.md`.

### Salida de fase

**Fase 11 VALIDADA Y CERRADA por el usuario (2026-09-30); AUD-0038
autorizado y cerrado; AUD-0039 confirmada P2 con análisis de diseño pendiente.**
**37 hallazgos abiertos: 16 P1 · 15 P2 · 6 P3 — 3 cerrados (AUD-0002, AUD-0003, AUD-0038).**
Decisiones pendientes acumuladas (histórico): AUD-0001, 0019, 0020, 0021, 0023, 0024,
0025, 0027, 0028, 0029, 0030, 0031, 0032, 0033, 0035, **0039 (análisis de
diseño en fase siguiente)**, 0040, O-4, O-6, O-7.
→ **RESUELTAS 2026-09-30** (ver sección «Decisiones del usuario — RESUELTAS»
al inicio; AUD-0039: Opción B adoptada; AUD-0040/O-6/O-7/AUD-0001
resueltos al cierre de F12 — `NEEDS_REVIEW` = ninguno).

---

## Fase 12 — Reportes (VALIDADA Y CERRADA, 2026-09-30)

**Alcance ejecutado:** Plan §3 *"Reportes: dashboard vs API vs Excel/PDF vs
SQL → `MATRIZ_REPORTES.md`"* + análisis de diseño mandatado **AUD-0039**.

**Entregables:**

- `docs/auditoria/MATRIZ_REPORTES.md` — matriz RF-R01…R09 ×
  pantalla/Excel/PDF/filtros/fuente/tests (§1: pantalla parcial **5/9**,
  Excel **0/9**, PDF **0/9**; filtros `:118`: solo `estado` existe, sin
  fecha); consistencia **SQL ↔ dashboard ↔ monitoreo** en BD dev verificada
  campo a campo (§2: 24 expedientes, 19 sin asignar, por_estado/por_via/
  semáforo 5 fuera / feriados / intentos — todo coincide); AUD-0041
  reproducido (§3); confirmaciones de AUD-0011/0017/0018 (§4).
- `docs/auditoria/AUD-0039_DISENO.md` — análisis de diseño completo
  (obligación del usuario): §2 cómo se determinan hoy `via`/`reglamento`
  (herencia en `NurejHijoService:46-47`, sin input/UI), §3 dependencias
  (pivote (rol, reglamento), Ac. 022/054/055, plazos, sorteo/bandejas,
  autorización intacta, 0 hijos en dev), §4 opciones A/B/C → **veredicto:
  Opción B (selección explícita con precedente de apertura); automática
  bloqueada por AUD-0033/0021**, §5 decisiones de diseño → **Opción B
  adoptada 2026-09-30 (`AUD-0039_DISENO.md` §7)**.
- `tests/Feature/ReportesAdminTest.php` — 5 tests / 34 aserciones: 403
  roles no-ADMIN en ambos endpoints; agregados del dashboard vs conteos
  independientes (usuarios, expedientes, por_estado, por_via, semáforo,
  feriados); monitoreo: resumen + filtros `estado`/`responsable`/`buscar`;
  dashboard y monitoreo 403 con ADMIN inactivo (esta última desde el fix
  AUD-0041).

**Hallazgo nuevo:** **AUD-0041 (P2)** — `AdminDashboardController:28-29`
no valida `activo` (dashboard 200 para ADMIN inactivo vs monitoreo 403);
mitigaciones: login bloquea inactivos (`AuthController:55-64`) y
`expulsar` revoca tokens (`SeguridadSesionesService:52`). **Fix AUTORIZADO
y aplicado 2026-09-30** (check `! $usuario || ! $usuario->activo || rol !==
ADMIN → abort(403)` + test en `ReportesAdminTest`; pint OK). Ficha OPEN
hasta validación de cierre.

**Confirmaciones F12:** AUD-0011 (0/9 Excel, 0/9 PDF, 0 paquetes),
AUD-0017 (`abort(403)` inline `AdminDashboardController:28-29`,
`AdminMonitoreoController:31`), AUD-0018 (`get()` sin paginar
`AdminMonitoreoController:113`).

**Gates:** `vendor/bin/pint --dirty --format agent` → OK;
`php artisan test --compact` → **293 tests: 286 OK · 1 fallo (AUD-0001)
· 0 errores · 6 omitidos** (sin regresiones; +5 tests nuevos).

**Backlog al cierre de Fase 12: 38 hallazgos abiertos: 16 P1 · 16 P2 · 6 P3 · 3
cerrados (AUD-0002, AUD-0003, AUD-0038).** *(snapshot histórico; vigente:
ver «Backlog vigente» en Fase 13.)*

### Cierre de validación (decisiones del usuario, 2026-09-30)

Documentación explícita del cierre:

- **Aceptados como deuda/observación:**
  - **AUD-0001 → DEUDA CONOCIDA aceptada.** F12 no introdujo ni agravó el
    hallazgo; el fallo existente queda conocido y documentado (único fallo
    de la suite) y **no debe ocultarse ni maquillarse**: NO se modifica el
    test para hacerlo pasar. Sin cambios de código dentro de F12.
  - **AUD-0040 → MONITORIZACIÓN** (flake/no reproducido, causa raíz
    abierta; sin cambio en helper ni producto sin reproducción fiable).
  - **O-6 → ACEPTADA PROVISIONALMENTE:** "Comportamiento aceptado
    provisionalmente; el SRS no establece prohibición explícita de
    autoasignación." Si luego se decide excluir al creador → cambio
    funcional separado con sus respectivos tests.
  - **O-7 → ASOCIADA A AUD-0023** como sub-caso (sin ficha P2): documentado
    que en BD dev `IMPUGNACION_RESOLVER` está ausente y el fallback
    `now()` produce un límite de resolución inmediato; sin modificar;
    candidato de corrección en la sincronización de parámetros.
- **Quedan abiertos para fases posteriores:**
  - **AUD-0001 (P1):** decisión de producto sobre el acceso de ADMIN
    (opciones A/B/C) → **fase de hardening/seguridad**; ninguna aplicada.
  - **AUD-0040 (P3):** causa raíz abierta, en monitorización.
  - **AUD-0030, AUD-0031, AUD-0032 (P1, Fase 7):** incorporados
    formalmente a la lista de decisiones pendientes del backlog global,
    con sus propuestas técnicas mantenidas; **no bloquean F12** (F12 no los
    introdujo ni modificó).
  - **AUD-0042/AUD-0043 (P2, Fase 13):** resueltos en el **cierre de F13
    (2026-09-30)** — ambos P2 OPEN → backlog de desarrollo; AUD-0043 con la
    corrección conforme al SRS (corte 23:59, zona horaria institucional)
    confirmada como pendiente de desarrollo.
- **Sin cambios de producto como consecuencia de esta validación:** el
  cierre solo registró decisiones en documentación. El único cambio de
  producto de F12 fue el fix de **AUD-0041**, autorizado explícitamente por
  el usuario antes de esta validación y verificado con su test.
- **AUD-0041:** fix autorizado + test + gates verificados → **ficha
  CERRADA** con el cierre de F12 (objeción posible del usuario si prefería
  mantenerla abierta).
- **`NEEDS_REVIEW` resultante: NINGUNO.**
- **Gates del cierre:** `vendor/bin/pint --dirty --format agent` → OK;
  `php artisan test --compact` → **296 tests: 289 OK · 1 fallo conocido
  (AUD-0001, deuda aceptada) · 0 errores · 6 omitidos** — sin regresiones.

**Decisiones anteriores de la tanda (2026-09-30)** — ver sección
«Decisiones del usuario — RESUELTAS» al inicio: AUD-0019/0020/0021/0023/
0024/0025/0027/0028/0029/0033/0034/0035/0039 decididos; **AUD-0041 fix
autorizado y aplicado**; O-4 resuelta sin elevar.

---

## Fase 13 — Jobs/cron (VALIDADA Y CERRADA, 2026-09-30)

### Trabajo realizado

- Creado `docs/auditoria/MATRIZ_JOBS_CRON.md`: §1 inventario verificado
  (`VerificarVencimientoPlazosCommand:22-33`, `ArchivoPorAbandonoService:43-86`,
  `MarcarPlazosVencidosService:26-31`, `routes/console.php:11` `daily()` →
  `0 0 * * *`, SRS `SRS_EXTRAIDO.txt:442-446` «se dispara a la medianoche»,
  `app.timezone=UTC` y grep `timezone|America/` = 0 en repo);
  §2 idempotencia verificada (análisis de filtros + tests); §3 corte UTC
  prematuro reproducido; §4 duplicados/concurrencia (observación); §5
  mapeo de dependencias catálogos/seeders (acción AUD-0037(a) cumplida) con
  ejecución real del comando en BD dev; §6 comportamiento en fallo; §7
  hallazgos nuevos; §8 gates.
- Creado `tests/Feature/VerificarVencimientoPlazosTest.php` — 3 tests /
  17 aserciones: idempotencia (segunda corrida 0/0, 1 actuado de archivo);
  **corte UTC prematuro** (`Carbon::setTestNow('2026-09-08 00:05:00')` UTC =
  21:05 ART del día de vencimiento → archiva igual); **falta de catálogo** →
  `ModelNotFoundException` sin ejecutar nada (plazo sigue `VIGENTE`,
  interna sin marcar, expediente intacto).
- Evidencia BD dev (solo lectura + ejecución del comando): `catalogo_existe=0`,
  `admin_activo=3`, `subs_vigente_vencidas=1`, `internas_sin_marcar=7`;
  `php artisan plazos:verificar-vencidos` → **`ModelNotFoundException`
  exit=1** en `ArchivoPorAbandonoService:47` (sin escribir nada).

### Hallazgos nuevos (BACKLOG)

- **AUD-0042 (P2/OPEN):** el job completo falla (excepción no capturada) si
  falta `ACT_ARCHIVO_POR_ABANDONO` o un ADMIN activo → RN-03 y
  `fuera_de_plazo` no corren; **ya falla hoy en BD dev**. Fix propuesto
  (degradación controlada + log) NO aplicado — toca producto.
  **Decisión (2026-09-30): P2 OPEN, pendiente de desarrollo** — no se
  implementa en esta etapa de auditoría; registrado en backlog de
  desarrollo.
- **AUD-0043 (P2/OPEN):** corte UTC (00:00 UTC = 21:00 ART) archiva/marca
  hasta 3 h antes de la medianoche local que pide el SRS; reproducido con
  test. Fix = decisión de zona horaria institucional (config global) NO
  aplicado. **Decisión (2026-09-30): se confirma el SRS — el
  vencimiento/corte debe producirse a las 23:59 de la fecha correspondiente
  según la zona horaria institucional aplicable**; corrección = pendiente
  de desarrollo, no implementar ahora.
- **O-10 (observación, NO elevada):** `daily()` sin
  `withoutOverlapping()`/lock + sin índice único → corridas solapadas
  podrían duplicar actuados; no reproducido → no clasificado.
- **AUD-0037:** acción (a) «mapear catálogos requeridos vs seeders»
  cumplida en `MATRIZ_JOBS_CRON.md` §5; (b) sigue pendiente de autorización.
- Sin cambios de producto en la fase (solo tests + documentación).

### Salida de fase — Cierre de validación (decisiones del usuario, 2026-09-30)

**Fase 13 VALIDADA Y CERRADA (2026-09-30).**

- **Entregables:** `MATRIZ_JOBS_CRON.md` (§0-§9) +
  `VerificarVencimientoPlazosTest.php` (3 tests / 17 aserciones).
- **Tests/gates:** `vendor/bin/pint --dirty --format agent` → OK;
  `php artisan test --compact` → **296 tests: 289 OK · 1 fallo (AUD-0001,
  deuda aceptada en F12) · 0 errores · 6 omitidos** (gate de este cierre).
- **Decisiones sobre los hallazgos de F13:**
  - **AUD-0042 → P2 OPEN, pendiente de desarrollo.** Etapa de auditoría:
    no se implementa la degradación controlada/log; el problema está
    identificado y documentado.
  - **AUD-0043 → SRS confirmado:** corte a las **23:59 de la fecha
    correspondiente, según la zona horaria institucional aplicable** (no
    anticiparse por el scheduler 00:00 UTC); fix = pendiente de
    desarrollo, no implementar ahora.
- **Pasan a backlog de desarrollo:** **AUD-0042, AUD-0043** (+ relacionados
  ya existentes: AUD-0023, AUD-0036, AUD-0037(b); O-10 monitorización ya
  decidida, no elevada).
- **Sin pendientes de auditoría que bloqueen F13.**
- **Backlog vigente: 39 hallazgos abiertos: 16 P1 · 17 P2 · 6 P3 — 4
  cerrados (AUD-0002, AUD-0003, AUD-0038, AUD-0041).**
- **`NEEDS_REVIEW`: NINGUNO.**

---

## Fase 14 — Auditoría UX / Frontend (2026-10-01)

### Entrada

- Plan §36 (auditoría de frontend), §37 (UX operativa), §70 (frontend vs backend), alcance `PLAN_EJECUCION_AUDITORIA.md` §3 "F14 UX/frontend".
- Obligación: detectar y documentar, NO corregir. Único cambio permitido: documentos de auditoría.
- Hallazgos previos a verificar: AUD-0033, AUD-0008, AUD-0034, RF-04 sin UI, `/expedientes` para la Encargada.

### Metodología

- Superficie real verificada: `route:list` = 54 rutas; 13 vistas Blade + `layouts/app.blade.php` + `partials/api-helper.blade.php`; stack Blade + Alpine (CDN) + Tailwind, sin Livewire/Inertia y sin `pest-plugin-browser` (verificado en `composer.json`/`package.json`) → revisión **estática** + lectura de backend + tests HTTP; sin ejecución en navegador.
- Cada acción visible trazada por la cadena §70 completa (UI → handler → ruta → controller → policy → request → operación → respuesta → refresco → mensaje). Toda afirmación con `archivo:línea`.

### Entregable

- **`docs/auditoria/MATRIZ_UX_FRONTEND.md`** (§0–§17): §4 inventario vistas→rutas→backend→acciones; matriz §36 por vista; §37 flujo por flujo; §70 por acción visible; §8 verificación AUD-0033/0008; §9 AUD-0034; §10 RF-04; §11 `/expedientes`; §12 hallazgos nuevos; §13 observaciones; §14 descartados; §15 decisiones pendientes; §16 limitaciones; §17 conclusión + gates.

### Hallazgos nuevos (18) — fichas completas en `BACKLOG_AUDITORIA.md`

- **P1 (2):** **AUD-0060** RF-04 sin interfaz (evaluación de admisibilidad no ejecutable desde la UI; `SRS:109` vs 0 llamadas en vistas) · **AUD-0061** 11 operaciones con endpoint dedicado sin interfaz (planificación/VB/devolución, ampliación, cierre/reparto, transparencia, descargos — `routes/api.php:58-87`).
- **P2 (2):** **AUD-0044** sidebar "Bandeja de entrada" visible para ENCARGADA/ADMIN → 403 (`layouts/app.blade.php:135-139` vs `ExpedientePolicy:53-64`; test `WebWorkstationRoutesTest:53-57`) · **AUD-0045** monitoreo: `error`/`cargando` nunca renderizados → "No se encontraron expedientes" en todo fallo.
- **P3 (14):** AUD-0046 (rótulo "Actualización automática" sin auto-refresco), AUD-0047 (vista `parametros` inalcanzable + "Guardar" con éxito falso), AUD-0048 (link "¿Olvidaste tu contraseña?" `href="#"`), AUD-0049 (login: 429 en inglés sin Retry-After, respuesta no-JSON), AUD-0050 (`meta.total` sin asignar), AUD-0051 (`errorGral` inerte), AUD-0052 (límite de 10 partes solo en cliente), AUD-0053 (dashboards: datos previos tras refresco fallido), AUD-0054 (error de sorteo invisible si se cierra el modal), AUD-0055 (página fuera de rango tras sortear), AUD-0056 ("Inactivar" sobre la propia cuenta → 422), AUD-0057 (`cargarUsuario`/`cerrarSesion` en silencio), AUD-0058 (`console.log`/`console.error` con datos), AUD-0059 (`welcome` inalcanzable + enlace `/dashboard` inexistente).
- **Observaciones NO elevadas (9):** O-11 (copy genérico de error en bandeja operador), O-12 (feriados pasados editables), O-13 (ADMIN autoedita rol), O-14 (sin guarda anti-doble envío), O-15 (`GET /api/estados`, `/api/usuarios` sin uso), O-16 (botón "Actualizar" sin disabled), O-17 (`claseSemaforo` muerto), O-18 (`min=1` vs default 0), O-19 (descarga en pestaña nueva sin manejo de 403).

### Verificaciones cerradas en F14

- **AUD-0033 / AUD-0008 / AUD-0035:** confirmado que NO existe UI de impugnación ni de informe final (grep = 0 en vistas); backend parcial ya testeado; decisión previa "NO implementar" **se mantiene** (no se cambia estado). Nueva evidencia: el modal genérico de actuados no crea la fila de `impugnaciones` que `resolver` exige.
- **AUD-0034:** confirmada y ampliada — `detalle.blade.php:297` no envía `expediente_id` **ni** `estado_origen_id` (el endpoint soporta ambos; `CatalogoActuadoController:37-51`) → el modal ofrece acciones que serán 403 o inválidas. Sin cambio de estado; fix sigue pendiente de autorización.
- **RF-04:** backend completo y testeado, UI inexistente → **AUD-0060 (P1)** por el criterio ya adoptado por el usuario en AUD-0035.
- **`/expedientes` Encargada:** backend protegido correctamente (403 testeado); el defecto es de UI (enlace sin gate) → **AUD-0044 (P2)**, relacionada con AUD-0028.
- **Confirmados sin duplicar:** `GET /api/bandeja` sin authorize ya está en `MATRIZ_SEGURIDAD.md:15` (F3); CDNs = AUD-0006.
- **Descartados con evidencia:** actuados para roles sin permiso (filtrado por rol en servidor), sorteo en estado inválido (revalidación con lock), errores de validación sin mostrar (sí se muestran), endpoints inexistentes llamados por la UI (correspondencia 1:1 con `route:list`).

### Decisiones pendientes (usuario)

1. AUD-0060/AUD-0061: alcance de la superficie de operaciones sin interfaz (mismo criterio que AUD-0035).
2. AUD-0047: destino de la vista `parametros` (eliminar / habilitar con API / congelar).
3. AUD-0044: gatear el enlace por rol vs habilitar la ruta (esta opción toca autorización → confirmación explícita).
4. O-13: ¿prohibir al ADMIN autoeditarse el rol?
5. AUD-0052: ¿el límite de 10 partes es regla de negocio real?

### Gates de salida

| Gate | Resultado |
|---|---|
| `vendor/bin/pint --dirty --format agent` | **OK** (sin PHP sucio; F14 no tocó código PHP) |
| `php artisan test --compact` | **296 tests · 289 OK · 1 fallo (AUD-0001, `SecurityCompartimentosTest`, deuda aceptada desde F12) · 0 errores · 6 omitidos** — idéntico al baseline de F13, sin regresiones |

- **Cambios de producto: NINGUNO** (solo `.md`: `MATRIZ_UX_FRONTEND.md` nuevo + `BACKLOG_AUDITORIA.md`, `PROGRESO.md`, `PLAN_EJECUCION_AUDITORIA.md` actualizados).
- **Estado: ENTREGADA (2026-10-01) — pendiente de validación de fase.**

---

## Cierre de validación F14 (2026-10-01)

- **F14 — UX/Frontend: VALIDADA Y CERRADA (2026-10-01).**
- **Los 18 hallazgos AUD-0044…AUD-0061 permanecen intactos** (sin cambios de severidad, estado ni evidencia; ninguno cerrado ni eliminado).
- **Las 5 decisiones pendientes de F14 (§15 de `MATRIZ_UX_FRONTEND.md`) permanecen pendientes: ninguna fue aplicada.**
- **Cambios de producto: NINGUNO.** Solo documentación de auditoría.
- **Gates de cierre (2026-10-01):** `vendor/bin/pint --dirty --format agent` → **OK**; `php artisan test --compact` → **296 tests · 289 OK · 1 fallo (AUD-0001, `SecurityCompartimentosTest`, deuda aceptada desde F12) · 0 errores · 6 omitidos** — **idéntico al baseline, SIN REGRESIONES**.
- Documentos del cierre: `PLAN_EJECUCION_AUDITORIA.md` §5 (fila F14) y esta sección.

---

## Fase 15 — Rendimiento (entrada 2026-10-01)

### Alcance (NO redefinido — plan existente)

- `PLAN_EJECUCION_AUDITORIA.md` §3: "F15 Rendimiento: N+1, índices vs `EXPLAIN`, paginación server-side".
- Plan Maestro §55 (`PLAN_MAESTRO_AUDITORIA_Y_FINALIZACION_SISTEMA.md:2201-2233`): N+1 queries, consultas repetidas, paginación, índices, joins, reportes pesados, exportaciones, dashboard, carga de bandejas; en MySQL: índices utilizados/faltantes, `EXPLAIN`, joins costosos, ordenamientos, filtros, búsquedas por NUREJ; **sin índices indiscriminados — cada índice debe justificarse por un patrón real de consulta**.
- Alcance operativo acordado: (1) índices reales vs columnas filtradas/ordenadas; (2) N+1 y consultas repetidas; (3) `EXPLAIN` de consultas críticas; (4) inventario de endpoints con `->get()` sin límite; (5) reauditoría de AUD-0018, AUD-0036, AUD-0055; (6) documentación de recomendaciones **sin implementar fixes**.

### Método

- Evidencia obligatoria `archivo:línea` o `EXPLAIN` reproducible; esquema real vía `php artisan db:table` (nada asumido de memoria).
- Medición dinámica de queries con `DB::listen` en tests existentes o `tinker --execute` (solo lectura).
- `EXPLAIN` solo lectura sobre consultas críticas; prohibido índices, migraciones, `paginate()` nuevo o cambios de controllers/resources/frontend/tests.
- Toda recomendación se registra como **PROPUESTA / PENDIENTE DE DECISIÓN**.

### Áreas a auditar

- Índices: `expedientes`, `asignaciones`, `actuados`, `plazos`, `usuarios`, catálogos (migraciones + esquema real).
- Endpoints: bandejas (operador/sorteo), monitoreo, dashboards (admin/encargada), catálogos, usuarios, feriados, evaluación de admisibilidad, reportes R01…R09.
- Hallazgos a reverificar (sin modificarlos): **AUD-0018** (P2, paginación/búsqueda — evidencia histórica "0 paginate()" desactualizada), **AUD-0036** (P3, índice único `plazos`), **AUD-0055** (P3, paginador, de F14).

### Entregable

- `docs/auditoria/MATRIZ_RENDIMIENTO.md` (fila añadida en §1 del plan) + fichas nuevas desde **AUD-0062** en `BACKLOG_AUDITORIA.md`.

### Estado

- **EN CURSO** (ejecución iniciada 2026-10-01). Gates finales y cierre: ver sección final de esta fase.

---

### F15 — Salida: ejecución, gates y control de cambios (2026-10-01)

- **Entregable creado:** `docs/auditoria/MATRIZ_RENDIMIENTO.md` (§0-§16): índice §4, matriz §5, EXPLAIN §6, medición dinámica §7, inventario de paginación §8, reauditoría §9, NUREJ §10, áreas §11, hallazgos §12, decisiones §14, limitaciones §15, gates §16.
- **Hallazgos nuevos (6):** **AUD-0062 (P2)** N+1 medido en resources — `feriados`+`suspensiones_plazo` = 26 de 43 queries de `GET /api/bandeja` (`ExpedienteResource:82`, `PlazoResource:24`, sin singleton) · **AUD-0063 (P2)** dashboard admin carga la tabla completa de expedientes y la consulta 5 veces por petición (`AdminDashboardController:64,77,88,95,133`) · **AUD-0064 (P3)** sin índice `expedientes.fecha_ingreso` → `Using temporary; Using filesort` en bandejas/monitoreo · **AUD-0065 (P3)** `sesiones_acceso` sin índice `login_at`/`(exitoso,login_at)` → `type=ALL` en el dashboard · **AUD-0066 (P3)** cron de plazos full scan (sin `(estado, fecha_limite)`) · **AUD-0067 (P3)** búsqueda NUREJ/resumen `LIKE '%…%'` full scan no indexable (única búsqueda del sistema).
- **Observaciones nuevas:** O-20 (el monitoreo filtra `estado` post-load por diseño → condiciona AUD-0018), O-21 (inventario de `->get()` acotados por catálogo/purpose).
- **Reauditoría (sin modificar fichas):** **AUD-0018** — evidencia histórica "0 paginate()" queda desactualizada (hoy 3 usos: `ExpedienteController:63,76`, `UsuarioController:40`); se mantiene OPEN y se **amplía** (monitoreo/dashboard/usuarios sin paginar + 0 rutas de búsqueda; paginar el monitoreo exige diseño previo por estados computados). **AUD-0036** — confirmado: `db:table plazos` sigue sin índice único `(expediente_id, tipo_plazo)` (solo FKs). **AUD-0055** — evidencia vigente: código sin cambios (`paginate(15)` + recarga con `current_page`).
- **Índices creados: NINGUNO. Migraciones tocadas: NINGUNA. Consultas alteradas: NINGUNA.** Recomendaciones registradas como PROPUESTA/PENDIENTE DE DECISIÓN (§14 de la matriz).

#### Gates de salida F15

| Gate | Resultado |
|---|---|
| `vendor/bin/pint --dirty --format agent` | **OK** (sin PHP sucio) |
| `php artisan test --compact` (corrida final) | **296 tests · 289 OK · 1 fallo (AUD-0001, deuda aceptada) · 0 errores · 6 omitidos** — baseline, **sin regresiones** |

**ANOMALÍA DETESTS REGISTRADA (no ocultada):** en la primera corrida de gates
de F15 (mismo código, mismos tests) la suite devolvió **289→287 OK · 2 fallos ·
1 error**: además de AUD-0001 falló `AmpliacionTest:175` (resumen esperado vs
"Asignación inicial de la semilla") y erroró `DerivacionTransparenciaTest`
("Undefined array key tipo"). Investigación: ambos tests **pasan aisladamente**
(10/10 cada uno) y las **dos corridas completas siguientes reprodujeron el
baseline exacto** (289/1/0/6). Conclusión: **flake/interacción entre tests
preexistente**, no introducido por F15 (F15 no modificó ni código de producto
ni tests — ver control de diff). Frecuencia observada: 1 corrida desviada en 4
corridas completas del día. Acción propuesta: monitorear; si se repite, abrir
ficha dedicada de estabilidad de tests (no se modifica ningún test en F15).

- **Control de diff final:** solo `docs/auditoria/*.md` (creados/ modificados
  `MATRIZ_RENDIMIENTO.md`, `BACKLOG_AUDITORIA.md`, `PROGRESO.md`,
  `PLAN_EJECUCION_AUDITORIA.md`). **Cero cambios de producto, cero
  migraciones, cero cambios de comportamiento.**
- **Estado: F15 ENTREGADA — PENDIENTE DE VALIDACIÓN (2026-10-01).**
- **Backlog vigente: 63 hallazgos abiertos: 18 P1 · 21 P2 · 24 P3 — 4
  cerrados (AUD-0002, AUD-0003, AUD-0038, AUD-0041). `NEEDS_REVIEW`: ninguno.**

---

## Cierre de validación F15 (2026-10-01)

- **F15 — Rendimiento: VALIDADA Y CERRADA (2026-10-01).**
- **Los 6 hallazgos AUD-0062…AUD-0067 permanecen intactos** (sin cambios de severidad, estado ni evidencia; ninguno cerrado ni eliminado); igual las 18 filas de F14 (AUD-0044…AUD-0061) y las fichas de AUD-0018, AUD-0036 y AUD-0055 sin tocar.
- **Las recomendaciones de F15 (§14 de `MATRIZ_RENDIMIENTO.md`) permanecen PROPUESTA / PENDIENTE DE DECISIÓN: ninguna fue implementada** — cero índices creados, cero migraciones, cero cambios de consultas/controllers/resources.
- **Cambios de producto: NINGUNO.** Solo documentación de auditoría.
- **Gates de cierre (2026-10-01):** `vendor/bin/pint --dirty --format agent` → **OK**; `php artisan test --compact` → **296 tests · 289 OK · 1 fallo (AUD-0001, `SecurityCompartimentosTest`, deuda aceptada desde F12) · 0 errores · 6 omitidos** — **idéntico al baseline, SIN REGRESIONES**.
- Documentos del cierre: `PLAN_EJECUCION_AUDITORIA.md` §5 (fila F15) y esta sección.
- **Estado del backlog al cierre: 63 hallazgos abiertos: 18 P1 · 21 P2 · 24 P3 — 4 cerrados (AUD-0002, AUD-0003, AUD-0038, AUD-0041). `NEEDS_REVIEW`: ninguno.**


---

## Fase 16 - Pruebas integrales (entrada 2026-10-01)

### Alcance (NO redefinido - plan existente)

- `PLAN_EJECUCION_AUDITORIA.md` §3: "F16 Pruebas integrales: escenario integral mínimo
  (Registro→…→Salida) + suites negativas; cobertura de prioridades (seguridad, plazos,
  actuados)" (`PLAN_EJECUCION_AUDITORIA.md:80`).
- Artefacto: `docs/auditoria/MATRIZ_PRUEBAS.md` (fila añadida en §1 del plan).

### Método

- Inventario previo obligatorio antes de escribir cualquier test (§1 de la matriz).
- Tests nuevos **solo** en `tests/Feature/`: cero cambios de producción, cero
  funcionalidad nueva, sin repetir F13/F14/F15, sin implementar recomendaciones de F15.
- Un test que falle por requisitos mal leídos se ajusta el **test**, nunca el producto;
  defecto de producto → ficha nueva (próximo ID **AUD-0068**) sin tocar código.
- Cobertura documentada cualitativamente (`archivo:línea`): sin xdebug/pcov no hay % PHPUnit.
- Baseline de gates: 296 tests · 289 OK · 1 fallo (AUD-0001) · 0 errores · 6 omitidos;
  F16 puede aumentar el total → documentar cantidad anterior/nueva.

### Entregable

- `docs/auditoria/MATRIZ_PRUEBAS.md` (§0-§12) + tests nuevos de F16 en `tests/Feature/`.

### Estado

- **VALIDADA Y CERRADA (2026-10-02).** Ejecución: pasos 1-6 completados
  (`MATRIZ_PRUEBAS.md` §0-§12, `tests/Feature/FlujoIntegralTest.php` — 3 tests +
  `tests/Feature/AdminHttpCoverageTest.php` — 4 tests, gates de entrega). Validación de
  cierre ejecutada con correcciones documentales y cierre formal — ver las dos secciones
  finales de esta fase.

---

### Nota de continuidad F16 — CORTE HISTÓRICO / PRE-EJECUCIÓN (2026-10-01, paso 2 pendiente)

> **⚠ Corte histórico (pre-ejecución).** Esta nota registra el estado al comienzo de la
> fase, cuando los tests aún no existían. **No contradice el cierre de F16**: conserva la
> trazabilidad de la planificación. El estado definitivo está en "## Ejecución F16" y en
> "## Cierre formal de F16" más abajo.

**Estado (al corte, pre-ejecución):** paso 1 (inventario) COMPLETADO; paso 2 (tests) EN
CURSO — el/los archivos de test aún NO se han escrito. Cero cambios de producto hasta
ahora (solo `MATRIZ_PRUEBAS.md`, `PLAN_EJECUCION_AUDITORIA.md` §1/§5 y esta entrada).

**Hecho:**
- `MATRIZ_PRUEBAS.md` §0-§4 (§5-§12 PENDIENTE, se llenan en paso 6).
- PLAN §1 fila artefacto + §5 fila F16 = EN CURSO (F17-18 = PENDIENTE).
- Cadena integral verificada contra código (payloads/códigos/respuestas):
  1) `POST /api/expedientes` (TECNICO; via=FINANCIERO, AC055, partes≥1, adjunto pdf)
  → 201 + ACT_REGISTRO_DIGITALIZACION → PENDIENTE_SORTEO;
  2) `POST .../sortear` (ENCARGADA, descripcion nullable) → 201 ActuadoResource +
  ACT_SORTEO_INICIAL → EN_EVALUACION + plazo EVALUACION (AC055=5) + asignación;
  candidatos = usuarios activos del rol vía (`SorteoAlgorithmService:83-89`) →
  semilla con UN SOLO AUD_FINANCIERO; `otroAuditor` (RF-03) se crea DESPUÉS del sorteo;
  3) `POST .../evaluacion` → 201 JSON plano `{resumen.resultado: ACT_ADMISION,...}`
  (NO es Resource); requisitos = exactamente los activos del reglamento leídos de DB;
  → EN_PLANIFICACION + plazo PLANIFICACION (AC055=2);
  4) `POST .../planificacion` (auditor; descripcion min:10,
  fecha_limite_propuesta=2026-10-16, adjunto pdf) → 201; ACT_MPA → PENDIENTE_VISTO_BUENO;
  cierra plazo PLANIFICACION (`PlanificacionService:75,223-230`);
  5) `POST .../planificacion/visto-bueno` (ENCARGADA; descripcion min:5) → 201 →
  EN_EJECUCION; plazo EJECUCION con fecha límite explícita MPA, dias_otorgados=0
  (AC055 sin parámetro EJECUCION; `ActuadoService:197-210`); bandeja → operador original;
  6) `POST .../descargos/comunicar` (descripcion min:5 + adjunto) → 201; estado no-op;
  EJECUCION→SUSPENDIDO (fecha_pausa) + sub-reloj DESCARGOS 5 días; exige AC055 +
  reloj EJECUCION VIGENTE + sin descargos previos (422 `expediente` si no);
  7) `POST .../descargos/recibir` (descripcion min:5 + adjunto) → 201;
  DESCARGOS→CUMPLIDO con actuado_cierre_id; EJECUCION→VIGENTE con fecha_reanudacion;
  8) `POST .../actuados` (catalogo=ACT_INFORME_AUDITORIA_FINANCIERA_SIN_RESPONSABILIDAD,
  descripcion min:5, adjunto) → 201 → PENDIENTE_VISTO_BUENO_FINAL;
  sin descargos previos → 422 key `catalogo_actuado_id` (Bloqueo de Salida RN-09,
  `StoreActuadoRequest:62` → `DescargoFinancieroService:165-168`);
  9) `POST .../cierre/visto-bueno` (ENCARGADA; descripcion min:10) → 201 →
  LISTO_PARA_REPARTO;
  10) `POST .../cierre/reparto` (destino='Juzgado Disciplinario' ∈ DESTINOS_REPARTO,
  justificacion min:10) → 201 → CONCLUIDO_REMITIDO + cierra bandeja.
- Infraestructura a usar: semilla en LOOP de `$test->seed(Clase)` una por una (NO array:
  `db:seed --class` es de valor único) con Rol/CatalogoEstado/Reglamento/
  CatalogoRequisito/ParametroPlazo/Feriado/CatalogoActuadoSeeder; usuarios por factory;
  `Carbon::setTestNow('2026-10-05 10:00:00')` + `Feriado::create(['fecha'=>'2026-10-07',...])`
  (no existe en FeriadoSeeder) → límites EVALUACION/DESCARGOS=2026-10-13,
  PLANIFICACION=2026-10-08, EJECUCION=2026-10-16; `Storage::fake('local')`;
  helpers prefijo `fi*` (sin colisión); hash de custodia lo resuelve trigger BEFORE INSERT
  (`2026_08_28_182631`) → assert final de cadena sobre 10 actuados en orden
  REGISTRO→SORTEO→ADMISION→MPA→VB_PLANIF→COMUNICACION→RECEPCION→INFORME_SIN→VB_FINAL→REPARTO;
  bandeejas asertir vía DB (`Asignacion.activa`), no via shape de Resources.

**Decisiones/observaciones a informar (no auto-registradas):**
- Candidato a **AUD-0068**: `evaluar()` NO cierra el plazo EVALUACION al admitir
  (solo se cierra en RECHAZO, `EvaluacionAdmisibilidadService:156-159`) → quedan 2 plazos
  VIGENTE simultáneos; ANTES de fichar verificar si `MATRIZ_PLAZOS`/cron F13 ya lo cubre.
- Alcance admin: F16 solo GET web `/administrador/*` (MAPA_FUNCIONAL:196);
  los 6 mutations `/api/admin/*` quedan para F17 según `MATRIZ_SEGURIDAD.md:85` →
  documentar conflicto de asignación en §8 de `MATRIZ_PRUEBAS.md` e informar.
  MAPA dice "0 tests" pero GET `/api/admin/*` ya cubiertos
  (`ReportesAdminTest:122-197`, `SeguridadIdorTest:212-240`) → documentar.
- `ADMITIDO` sin actuado con estado_origen=ADMITIDO (dead-end impugnación) → §4
  limitaciones + reportar.

**Pendiente (pasos 2-6):** (2) crear `tests/Feature/FlujoIntegralTest.php` — 3 tests:
happy path completo (estado/plazos/actuados/hash/bandejas por etapa), seguridad en flujo
(otroAuditor 403 RF-03, tecnico 403 VB final, IDOR entre expedientes), negativas de orden
(informe sin descargos 422, reparto antes de VB final 403, doble comunicación 422) y
correrlo ajustando SOLO el test si falla; (2b) `AdminHttpCoverageTest` web GET acotado;
(3-4) solo brechas reales; (5) `MATRIZ_PRUEBAS.md` §5-§12 con `archivo:línea`;
(6) gates `vendor/bin/pint --dirty --format agent` + `php artisan test --compact`
(documentar 296 → nueva cantidad), PLAN §5 F16 → **ENTREGADA — PENDIENTE DE VALIDACIÓN**,
cierre en PROGRESO. Cero cambios de producto; próximo hallazgo desde **AUD-0068**;
NO iniciar F17.

---

## Ejecución F16 — Pruebas integrales (ENTREGADA, 2026-10-02, previa a validación)

**Estado (al momento de la entrega):** F16 **ENTREGADA — pendiente de validación del usuario**. Pasos 2-6
completados. Cero cambios de producto: solo se agregaron 2 archivos de test nuevos y se
documentó el cierre (`MATRIZ_PRUEBAS.md` §5-§12, `PLAN_EJECUCION_AUDITORIA.md` §5 y esta
entrada). **Ningún test preexistente fue modificado, reescrito ni omitido.** *La validación
posterior y el cierre formal figuran en la sección final.*

**Hecho:**

- **`tests/Feature/FlujoIntegralTest.php` (3 tests, 140 aserciones, helpers `fi*`).**
  - `:271` — **E2E real de 10 etapas sobre el mismo expediente**, sin sembrar estado, con
    el grafo real de catálogos (7 seeders del proyecto en bucle): apertura → sorteo →
    admisión → MPA → VB planificación → comunicar → recibir → informe → VB final →
    reparto. Aserciones por etapa (no todas combinan las 4 dimensiones): etapas 1-5 y 8-10
    asertan estado + banda activa vía `Asignacion.activa` + shape de respuesta; la
    **etapa 6** aserta estado + shape + plazos **pero no banda**; la **etapa 7** aserta
    shape + plazos, **ni estado ni banda**. Plazos: EVALUACION 2026-10-13 · PLANIFICACION 2026-10-08 ·
    EJECUCION 2026-10-16 con `parametro_plazo_id=null` y `dias_habiles_otorgados=0` ·
    DESCARGOS 2026-10-13, todos con feriado real 2026-10-07. Final: **10 actuados en orden
    exacto** + **cadena de custodia completa** (`hash_anterior` null en el primero,
    encadenado y `sha256` reproducido contra la fila cruda de BD para los 10).
  - `:470` — **RF-03 en flujo**: 2.º auditor (creado después del sorteo) 403 en
    GET/evaluación/comunicar; Técnico creador pierde acceso al sortear y 403 en VB final;
    Encargada 403 en descargos AC055; **IDOR cruzado entre dos expedientes**; el
    asignatario legítimo sí opera su causa; VB final solo Encargada (201).
  - `:557` — **negativas de orden**: informe sin descargos → 422 (clave
    `catalogo_actuado_id`) sin crear actuado; doble comunicación → 422 (clave
    `expediente`); roles equivocados → 403; reparto antes del VB final → 403; cierre
    correcto en orden; NUREJ `CONCLUIDO_REMITIDO` rechaza nuevo reparto y nuevo VB final.
- **`tests/Feature/AdminHttpCoverageTest.php` (4 tests, 20 aserciones, helpers `ahc*`).**
  Las 4 rutas web `/administrador/*` × {sin sesión → 302 a
  `route('login')`, rol no ADMIN → 403, ADMIN inactivo → 403 (`EnsureAdmin.php:20-22`),
  ADMIN activo → 200}. **Cobertura previa (no era cero):** solo
  `SeguridadIdorTest.php:230-236` hacía un GET a `/administrador/dashboard` (1 ruta × 1 rol
  esperando 403); F16 añade las 4 rutas × 4 escenarios.
- **`MATRIZ_PRUEBAS.md` §5-§12** completados con evidencia `archivo:línea` (resultados,
  tests agregados, cobertura por frente, brechas, gates, hallazgos, archivos, conclusiones).
- **PLAN §5:** fila F16 → **ENTREGADA (2026-10-02, pendiente validación)**.
- **Gates de cierre:** `vendor/bin/pint --dirty --format agent` → `fixed` 1 archivo
  (`AdminHttpCoverageTest`, fixer `blank_line_between_import_groups`); y
  `php artisan test --compact` → **303 tests · 296 OK · 1 fallo (AUD-0001, deuda
  preexistente) · 0 errores · 6 omitidos · 1309 aserciones** (baseline 296 · 289 · 1 · 0 · 6
  → **+7 tests, sin regresiones**).

**Decisiones/observaciones a informar (no auto-registradas):**

- **AUD-0068 NO creada: es duplicado exacto de AUD-0030.** El candidato detectado
  (`evaluar()` no cierra EVALUACION al admitir → 2 plazos VIGENTE simultáneos) está
  textualmente en `BACKLOG_AUDITORIA.md:386-392` ("**No cierran: EVALUACION tras
  ADMISION/OBSERVACION, EJECUCION tras VB final/reparto**"), estado OPEN/P1/NO aplicar.
  Confirmado en flujo (`FlujoIntegralTest.php:333`). **`BACKLOG_AUDITORIA.md` sin cambios**
  (no hay hallazgo nuevo); el próximo ID sigue siendo AUD-0068.
- **Conflicto de asignación admin (para decidir):** `MATRIZ_SEGURIDAD.md:85` deja las 6
  mutations `/api/admin/*` para **F17**; `MAPA_FUNCIONAL.md:196` decía "0 tests" pero los
  GET `/api/admin/*` ya estaban cubiertos (`ReportesAdminTest:122-197`,
  `SeguridadIdorTest:212-240`). F16 agregó solo los GET **web** `/administrador/*`; las
  mutations quedan intactas para F17. Documentado en `MATRIZ_PRUEBAS.md` §8.1.
- **`MAPA_FUNCIONAL.md:196` desactualizado** → informar; no editado (artefacto de F5,
  fuera del alcance de F16).
- **2 ajustes de test al comportamiento real (nunca producción):** (a) el sub-reloj
  `DESCARGOS` no tiene `fecha_pausa` — solo se pausa el reloj `EJECUCION`
  (`DescargoFinancieroService:243-253`); (b) el Bloqueo de Salida lanza bajo la clave
  `catalogo_actuado_id`, no `expediente`.
- **Observación informativa:** AC055 no tiene `ParametroPlazo` de `EJECUCION`
  (`ParametroPlazoSeeder:29-31`) → reloj con parámetro null y 0 días, límite explícito del
  MPA: comportamiento previsto por RN-05, no defecto.
- `ADMITIDO` sin actuado con `estado_origen=ADMITIDO` (dead-end de impugnación) confirmado
  como limitación de cobertura en `MATRIZ_PRUEBAS.md` §4.2 (no se modifica producción).

**Pendiente (al momento de la entrega):** validación del usuario sobre F16 y decisión sobre
los puntos anteriores. *Validación ejecutada el 2026-10-02 → ver "## Cierre formal de F16"
más abajo; las decisiones sobre los puntos anteriores siguen pendientes.*
*CORTE HISTÓRICO / PRE-VALIDACIÓN (marcado 2026-10-02): la instrucción de no iniciar
F17 de esta entrega previa quedó superada — F17 ejecutada el 2026-10-02; ver
"## Fase 17 - Hardening" al final de este archivo.*

---

## Cierre formal de F16 — VALIDADA Y CERRADA (2026-10-02)

**Estado:** F16 **VALIDADA Y CERRADA (2026-10-02)**. Validación de cierre ejecutada sobre
los entregables de la fase; correcciones documentales aplicadas **solo** en
`MATRIZ_PRUEBAS.md`, `PROGRESO.md` y `PLAN_EJECUCION_AUDITORIA.md`.

**Correcciones aplicadas (coherentes con lo verificado en la validación):**

1. `MATRIZ_PRUEBAS.md` §1: `tests/Feature/` **47** archivos (no 50); helpers globales
   **139** al cierre de F16 = 122 previos + 17 de F16 (declaraciones `function` de nivel
   superior: 134 en `tests/Feature` + 5 en `tests/Unit`).
2. `MATRIZ_PRUEBAS.md` §6: helpers `fi*` **15** funciones (no 14); bucle de seeders en
   **`:37-47`** (no `:35-64`).
3. `MATRIZ_PRUEBAS.md` §6 + §7 y esta sección: cobertura HTTP **previa** a F16 reconocida
   (`SeguridadIdorTest.php:230-236`, 1 ruta × 1 rol) — se elimina la afirmación falsa de
   "ningún test HTTP"; se conserva la distinción entre cobertura previa y cobertura añadida.
4. `MATRIZ_PRUEBAS.md` §6: rama de **ADMIN inactivo → `EnsureAdmin.php:20-22`**; rama de
   rol → `EnsureAdmin.php:24-26` (sin alterar su referencia).
5. `MATRIZ_PRUEBAS.md` §6/§12 y esta sección: aserciones por etapa descritas como realmente
   implementadas — etapas 1-5 y 8-10 (estado + banda + shape); **etapa 6**: estado + shape +
   plazos, **sin banda**; **etapa 7**: shape + plazos, **ni estado ni banda**.
6. `PROGRESO.md`: la "Nota de continuidad F16" queda marcada como **CORTE HISTÓRICO /
   PRE-EJECUCIÓN** (trazabilidad conservada, sin borrado).

**Gates de verificación del cierre (sin alterar el baseline):**

- `vendor/bin/pint --dirty --test --format agent` → `passed` (dry-run, sin cambios).
- `php artisan test --compact` → **303 tests · 296 OK · 1 fallo (AUD-0001, deuda conocida,
  `SecurityCompartimentosTest`) · 0 errores · 6 omitidos · 1309 aserciones**.
- Comparación con baseline 296 · 289 · 1 · 0 · 6 → **+7 tests, +7 OK, +160 aserciones,
  sin regresiones nuevas** (mismo único fallo, mismo mensaje).
- Los 6 omitidos siguen siendo los env-gated preexistentes (5 `RUN_STRESS_TESTS` +
  1 `RUN_CONCURRENCY_TEST`); ninguno de F16.

**Confirmaciones del cierre:**

- **Sin cambios de producción** (`app/`, `routes/`, `database/`, `resources/`, config).
- **Sin tests preexistentes modificados**; solo los 2 archivos nuevos de F16.
- **Sin creación de AUD-0068**: sigue descartada por duplicación exacta con **AUD-0030**
  (`BACKLOG_AUDITORIA.md:386-392`); el próximo ID disponible sigue siendo AUD-0068.
- `BACKLOG_AUDITORIA.md`, `MATRIZ_SEGURIDAD.md` y `MAPA_FUNCIONAL.md` **intactos**.
- **Sin resolver** (para sus fases/decisiones correspondientes): AUD-0030; asignación de
  las 6 mutaciones `/api/admin/*` a F17 (`MATRIZ_SEGURIDAD.md:85`); `MAPA_FUNCIONAL.md:196`
  desactualizado; ADMITIDO sin transición entrante (§4.2).

**NO iniciar F17.** — *CORTE HISTÓRICO (marcado 2026-10-02): F17 ejecutada el
2026-10-02; ver la sección siguiente.*

---

## Fase 17 - Hardening (entrada 2026-10-02)

### Alcance (NO redefinido - plan existente)

- `PLAN_EJECUCION_AUDITORIA.md:84`: "F17 Hardening: secretos, `dd()`/debug, rate
  limiting, exposición de datos, logs".
- Artefacto nuevo: `docs/auditoria/MATRIZ_HARDENING.md` (fila añadida en §1 del plan).
- Corrección documental aprobada dentro de F17: `PLAN_EJECUCION_AUDITORIA.md:121` —
  Fase 13 pasó de "ENTREGADA (pendiente)" a **VALIDADA Y CERRADA (2026-09-30)**;
  `PROGRESO.md:652` ya decía "VALIDADA Y CERRADA", verificado por grep (ambas fuentes
  coinciden; no se tocó nada más del histórico).

### Método

- Verificación **estática** (grep recursivo sobre `app/`, `routes/`, `config/`, `database/`,
  `resources/` y sobre lo trackeado en git) + verificación **dinámica** (tests feature).
- **Solo tests + documentación**: cero cambios de producción.
- Se cubre únicamente lo no cubierto por fases anteriores: las 7 mutaciones `/api/admin/*`
  y los 2 gaps de hardening reales; el resto se **referencia** (F3, F14, F16) sin repetir.
- 4 condiciones de autorización por mutación (401 / 403 rol distinto de ADMIN / 403 ADMIN
  inactivo / éxito con efecto persistido), siempre con payloads válidos porque los
  FormRequests devuelven `authorize(): true` (la validación 422 iría antes que el 403).

### Resultados

- **Estática:** 0 `dd()/dump()/var_dump()/print_r()`; 0 `Log::` en `app/`; 0 secretos
  hardcodeados (solo lectura de cookie `XSRF-TOKEN` en cliente); `.env` no trackeado
  (solo `.env.example` con `APP_KEY=` vacío y plantillas de entorno local); ningún `.log`
  en el repo (`storage/logs/.gitignore:1`).
- **Rate limiting:** login 5/min por IP (previo, `AuthFeatureTest.php:219` → 429) +
  **API 60/min (`AppServiceProvider.php:27`) nuevo: 60 requests 200 seguidas y la 61ª → 429**.
- **Exposición de datos:** `GET /api/me` **sin `password_hash`** (test con `assertJsonMissingPath`
  + verificación de cuerpo crudo), respaldado por `Usuario.php:27-28` (`$hidden`) y
  `UsuarioResource.php:21-34`; el único `console.log` del frontend
  (`monitoreo.blade.php:469`) es **AUD-0058 (F14)**, referenciado sin duplicar.
- **Mutaciones admin:** 7/7 cubiertas × 4 condiciones = **28 escenarios de autorización**,
  con efectos persistidos (hash, `activo`, `AuditoriaUsuario`, alta/baja de feriados) y
  **6 validaciones 422** (`ci`, `password`, `rol_id`, `fecha`).

### Entregable

- `docs/auditoria/MATRIZ_HARDENING.md` (§0-§9) + `tests/Feature/AdminMutacionesApiTest.php`
  (5 tests) + `tests/Feature/HardeningApiTest.php` (2 tests).

### Estado

- **ENTREGADA — PENDIENTE DE VALIDACIÓN (2026-10-02).**
- **Gates de entrega:** `vendor/bin/pint --dirty --format agent` → **passed**;
  `php artisan test --compact` → **310 tests · 303 OK · 1 fallo (AUD-0001, deuda
  conocida, `SecurityCompartimentosTest`) · 0 errores · 6 omitidos · 1453 aserciones**.
  Delta vs baseline de F16 (303 · 296 · 1 · 0 · 6 · 1309): **+7 tests, +7 OK, +144
  aserciones (las de los tests nuevos), 0 regresiones**, mismo fallo y mismos 6 omitidos
  (5 `RUN_STRESS_TESTS` + 1 `RUN_CONCURRENCY_TEST`).
- **Sin cambios de producción** y **sin tests preexistentes modificados** (solo los 2
  archivos nuevos de F17 + documentación).
- **0 hallazgos nuevos → AUD-0068 no creada** (sigue reservada); `MATRIZ_SEGURIDAD.md`,
  `BACKLOG_AUDITORIA.md` y `MAPA_FUNCIONAL.md` intactos.
- **Pendientes referenciados, no resueltos aquí:** AUD-0001 (`ExpedientePolicy.php:80-82`,
  decisión de hardening en su fase); O-A superficie duplicada de `inactivar` (2 URIs para
  la misma acción); O-B checklist de despliegue `APP_DEBUG=false` para F18; fila
  `MATRIZ_SEGURIDAD.md:85` sin modificar (conflicto de asignación resuelto solo para
  cobertura de pruebas).

---

## Cierre formal de F17 - VALIDADA Y CERRADA (2026-10-02)

**Estado:** F17 **VALIDADA Y CERRADA (2026-10-02)**. Validación de consistencia y alcance
(ejecución no repetida; suite no re-ejecutada por no haber modificación de PHP) sobre
`MATRIZ_HARDENING.md`, la fila §5 del PLAN y la sección F17 de este archivo.

**Correcciones del cierre (solo documentales, 4):**

1. `MATRIZ_HARDENING.md` (§9) → cita de la corrección F13: `PLAN_EJECUCION_AUDITORIA.md:120`
   → **`:121`**.
2. `PROGRESO.md:1121` → misma cita corregida a `PLAN_EJECUCION_AUDITORIA.md:121`.
3. `PROGRESO.md:1118` → cita del alcance F17 corregida a
   `PLAN_EJECUCION_AUDITORIA.md:84`.
4. `MATRIZ_HARDENING.md` (§3) → rango de rutas acotado a las líneas reales de las 7
   mutaciones: `routes/api.php:99-102` (usuarios) y `routes/api.php:106-108` (feriados);
   contenido funcional sin cambios.

Causa de 1-3: la fila de artefacto añadida en §1 del PLAN (+1 línea) desplazó las
referencias escritas antes de esa inserción.

**Confirmaciones del cierre:**

- **Cifras sin cambios** (última corrida, 2026-10-02): `vendor/bin/pint --dirty --test
  --format agent` → **passed**; `php artisan test --compact` → **310 tests · 303 OK ·
  1 fallo (AUD-0001, deuda conocida, `SecurityCompartimentosTest`) · 0 errores ·
  6 omitidos · 1453 aserciones** (delta vs F16: +7/+7/+144, sin regresiones).
- **Producción y tests intactos**: `git diff` vacío sobre `app/`, `routes/`, `config/`,
  `database/`, `resources/`, `public/`, `bootstrap/` y `tests/`; los cambios del cierre
  son solo .md (`MATRIZ_HARDENING.md`, `PROGRESO.md` y la fila §5 del PLAN).
- **Hallazgos sin tocar**: AUD-0001 y AUD-0030 intactas; **AUD-0068 no creada**; O-A y O-B
  siguen como observaciones pendientes; la cobertura de las 7 mutaciones
  `/api/admin/*` queda **validada en F17**.
- **F18 permanece PENDIENTE** (fila del PLAN §5), sin artefacto `MATRIZ_COBERTURA_FINAL.md`.

**NO iniciar F18 hasta la validación de F17.** *(condición cumplida: F17 validada y
cerrada el 2026-10-02; F18 autorizada posteriormente por el usuario.)*

---

## Fase 18 - Auditoría final - ENTREGADA - PENDIENTE DE VALIDACIÓN (2026-10-02)

**Estado:** F18 **ENTREGADA — PENDIENTE DE VALIDACIÓN** (no cerrada; la validación
corresponde al usuario).

**Artefacto:** `docs/auditoria/MATRIZ_COBERTURA_FINAL.md` (§0-§8), fila ya declarada en
`PLAN_EJECUCION_AUDITORIA.md:37`.

**Método:** consolidación documental de F0-F17 (sin repetir sus auditorías) + **segunda
ronda de re-verificación** de los 39 hallazgos P1/P2 abiertos reutilizando la evidencia
citada en `BACKLOG_AUDITORIA.md`, confirmada con greps/consultas puntuales el
2026-10-02. Sin implementación de cambios; sin creación de hallazgos (grep de duplicados:
próximo ID AUD-0068 reservado por duplicado con AUD-0030, no usado).

**Resultados clave:**

- **§1 (19 requisitos + DT-01/DT-02):** 5 IMPLEMENTADA · 4 PARCIAL · 1 INCONSISTENTE
  (RF-03/AUD-0001) · 8 FALTANTE (RF-05, RF-R01…R06, RF-R08) · 1 DIFERENCIA TECNOLÓGICA.
- **§3 (checklist Plan §79, 20 ítems):** 9 CUMPLE · 4 PARCIAL · 6 NO CUMPLE ·
  1 NO VERIFICABLE (backups). **`APP_DEBUG=true` documentado como incumplimiento
  explícito** (`config:show app.debug` = true; `.env.example:APP_DEBUG=true`), conforme
  a la instrucción del usuario; sin modificar configuración.
- **§4 (release checklist Plan §116, 27 ítems):** 13 CUMPLE · 6 PARCIAL · 4 NO CUMPLE ·
  4 NO VERIFICABLE (backup, rollback, build — no re-ejecutado por decisión del usuario —,
  UAT).
- **§5 (2ª ronda, 39 fichas):** 37 VIGENTE (AUD-0012/0017/0020 con drift u observación de
  evidencia), 2 VIGENTE PARCIAL con propuesta de reevaluación (**AUD-0018**: las bandejas
  sí tienen `paginate(15)` desde 2026-09-02 y F15 ya lo había documentado; queda sin
  búsqueda — 0 rutas `buscar|search`; **AUD-0022**: `FlujoIntegralTest` y
  `FlujoJuridicoTest` ya usan seeders reales), 0 dejaron de aplicar, 0 P0 nuevos.
- **§6:** 10 decisiones bloqueantes D-1…D-10 (AUD-0001, alcance de AUD-0018/0022,
  cadena de estados AUD-0021/0030-0033, O-A, O-B, `MAPA_FUNCIONAL.md:196` + ADMITIDO,
  firma RF-06, DT-01/DT-02, RF-R05).
- **§7:** veredicto documental **NOT READY FOR PRODUCTION** con 6 fundamentos y
  condiciones mínimas de re-evaluación.
- **§8 gates (2026-10-02):** `vendor/bin/pint --dirty --test --format agent` → **passed**;
  `php artisan test --compact` → **310 tests · 303 OK · 1 fallo (AUD-0001,
  `SecurityCompartimentosTest`) · 0 errores · 6 omitidos · 1453 aserciones** (línea base
  intacta); `npm run build` **no ejecutado** (decisión del usuario: gates = pint + suite).

**Alcance de cambios:** solo `.md` (`MATRIZ_COBERTURA_FINAL.md` nuevo, fila F18 del PLAN,
esta sección). Sin cambios en `app/`, `routes/`, `config/`, `database/`, `resources/`,
`public/`, `bootstrap/` ni `tests/`; `git status --porcelain` final comparado contra el
baseline en memoria (12 líneas preexistentes de F13-F17: 4 `M` + 8 `??`), sin agregados
fuera de `.md`.

**Pendiente:** validación de F18 por el usuario (consistencia de la matriz, checklist
§79/§116 y veredicto §7). **NO cerrar F18 hasta esa validación.**

---

## Cierre de AUD-0001 — tarea B2.0 (2026-10-05)

**Estado:** **AUD-0001 CERRADA (2026-10-05)** — eliminada la última deuda P1 de
seguridad que dejaba la suite en rojo.

**Cambio de producción (mínimo y aislado):**

- `app/Policies/ExpedientePolicy.php` — eliminada la rama
  `if (($user->rol?->codigo ?? null) === Rol::CODIGO_ADMIN) { return true; }` en
  `view()` (antes `:80-82`). `esRolConAccesoCatalogos()` y `crearActuado()`
  intactos; ningún otro comportamiento modificado. Único archivo PHP tocado.

**Efecto:** `GET /api/expedientes/{e}` —y vía `authorize('view', $expediente)`
también `requisitos`, descarga de adjuntos y detalle workstation— devuelve **403
para ADMIN sin asignación activa**. El "monitoreo" del SRS del ADMIN queda en
`AdminDashboardController`/`AdminMonitoreoController` (endpoints propios, sin
cambios).

**Validación (sin modificar ningún test):**

- `vendor/bin/pest tests/Feature/SecurityCompartimentosTest.php` → **antes:
  9 tests · 8 OK · 1 fallo** (`:165`, esperaba 403 y recibía 200 = AUD-0001) ·
  **después: 9/9 OK · 12 aserciones.**
- `php artisan test --compact` → **310 tests · 304 OK · 0 fallos · 0 errores ·
  6 omitidos · 1453 aserciones** (2 corridas: antes y después de Pint, idénticas).
  Delta vs baseline F17/F18 (310 · 303 · 1 fallo AUD-0001 · 6 omitidos · 1453
  aserciones): **mismos 310 tests, +1 OK, −1 fallo, mismos 6 omitidos**
  (env-gated preexistentes: 5 `RUN_STRESS_TESTS` + 1 `RUN_CONCURRENCY_TEST`).
- `vendor/bin/pint --dirty --format agent` → `fixed` sobre
  `ExpedientePolicy.php` (espaciado de operadores unarios, solo estilo) →
  tests re-ejecutados en verde después del formateo.

**Documentación actualizada en este cierre:** `BACKLOG_AUDITORIA.md` (ficha y
resumen → CERRADO), `MATRIZ_SEGURIDAD.md` (fila 14 → BLOQUEADO (TEST); FALLA
CONOCIDA → 0; BLOQUEADO (TEST) 44 → 45 para mantener la suma 54), esta sección.

**Fuera de alcance (no tocados, reportados):** `MATRIZ_COBERTURA_FINAL.md:21`,
`MATRIZ_PRUEBAS.md:137,304`, `MATRIZ_HARDENING.md:105` y citas históricas de este
archivo siguen mencionando AUD-0001 como abierta / "único fallo" (son registros
de sus fases; actualizar solo si el usuario lo pide).

---

## Cierre de tarea B0.8 — Anexo SRS y diseño de unidades (2026-10-05)

**Estado:** **B0.8 VALIDADA / CERRADA (2026-10-05)** — anexo de enmiendas
arquitectónicas al SRS y diseño corto de unidades inter entregados. Sin cambios
de código, BD ni tests.

**Se crearon (únicos archivos del diff):**

- `docs/auditoria/ANEXO_SRS_MODIFICACIONES.md`
- `docs/plan/DISENO_UNIDADES_INTER.md`

**Contenido:**

- **AM-01:** MySQL 9.7.0/InnoDB frente a PostgreSQL del SRS (`SRS_EXTRAIDO.txt:61`,
  `:116` RNF-04, `:187`), documentado como **diferencia tecnológica** (DT-01/DT-02 de
  `MATRIZ_SRS_IMPLEMENTACION.md` §0), no brecha funcional; evidencia `SELECT VERSION()`
  y `SHOW VARIABLES` en modo solo lectura.
- **AM-02:** unidades organizacionales **sin `unidad_id` en `usuarios`** (la propuesta
  original de D-5 queda descartada; refinada según COORDINACION §5.3), con diseño de
  datos, flujo y contratos CTR-04/CTR-05 listo para la tarea **B2.1**. Distinción
  explícita entre Unidades externas / `REMITIDO_UNIDAD_EXTERNA` y Reparto Institucional
  (cierre definitivo, nunca transita por `REMITIDO_UNIDAD_EXTERNA`).

**Validación:**

- `php artisan test --compact` → **320 tests · 314 OK · 0 fallos · 6 omitidos**
  (idéntico al baseline post-B0.3).
- `vendor/bin/pint --dirty --format agent` → `passed` (sin PHP modificado).
- **Sin cambios de código, BD ni tests.** `SRS_EXTRAIDO.txt` permaneció **intacto**
  (solo se escribieron los 2 archivos nuevos; comandos ejecutados en solo lectura).

**Pendiente de este cierre:** registro aquí mismo (realizado con esta entrada);
la entrada se mantiene en diff aislado de la tarea.

---

## Cierre de tarea B1.8 — Inmutabilidad normativa / RN-06 (2026-10-05)

**Estado:** **B1.8 COMPLETADA / VALIDADA Y CERRADA (2026-10-05)** — garantía de
inmutabilidad del reglamento procesal por expediente (RN-06): una causa nace y
concluye bajo el mismo `reglamento_id`; sin cambios de BD, migraciones ni rutas.

**Cambio de producción (mínimo):**

- `app/Models/Expediente.php` — hook `static::updating()` en `booted()` que lanza
  `DomainException` cuando `isDirty('reglamento_id')`, bloqueando cualquier
  actualización posterior a la creación. Sin clase de excepción nueva y sin
  tocar `bootstrap/app.php` (decisión del usuario: sin mapeo HTTP 422 porque no
  existe endpoint que permita esa mutación).
- `app/Services/ExpedienteService.php` — únicamente PHPDoc de trazabilidad
  RN-06 en `aperturaCausa` (el servicio ya fija `reglamento_id` al crear y no lo
  modifica); **sin cambio funcional**.

**Test nuevo (único archivo `tests/` del diff):**

- `tests/Feature/VersionadoNormativaTest.php` — **5 tests · 12 aserciones**:
  cobertura de `update()`, `forceFill()->save()`, asignación directa + `save()`,
  creación intacta de expediente (`aperturaCausa`) e hijo
  (`Expediente::create` con `reglamento_id`) y consistencia downstream: tras el
  intento fallido, `ParametroPlazo` se resuelve con el reglamento de origen
  (misma query que `ActuadoService@abrirPlazoSiAplica`). Además verifica en
  `Route::getRoutes()` que ninguna ruta de expedientes expone PUT/PATCH/DELETE.

**Validación (sin modificar ningún test existente):**

- `vendor/bin/pest tests/Feature/VersionadoNormativaTest.php` → **5/5 OK ·
  12 aserciones.**
- `php artisan test --compact` → **330 tests · 324 OK · 0 fallos · 6 omitidos ·
  1617 aserciones** (baseline 325/319/6 + 5/12 nuevos; ningún test existente
  afectado).
- `vendor/bin/pint --dirty --format agent` → `passed` (sin archivos modificados
  por Pint; re-test confirmó 5/5 OK).

**Alcance de cambios:** solo `app/Models/Expediente.php`,
`app/Services/ExpedienteService.php` (PHPDoc) y el test nuevo.
`StoreExpedienteRequest` (B3.2), `NurejHijoService`, `routes/`, `bootstrap/`,
`.env`/`config/`, `database/` (migraciones/seeders) y documentación **intactos**;
sin operaciones git.

**Límite conocido:** la protección es a nivel de **modelo Eloquent** — un
`DB::table('expedientes')->update(...)` directo (query builder) la evadiría, pero
está verificado que **no existe uso de query builder de escritura sobre
`expedientes` en el proyecto**; sin trigger en BD por estar las migraciones fuera
del alcance de la tarea.

**No se registró como resuelto ningún otro hallazgo** (RN-06 "historial de
versiones normativas" sigue PARCIAL según `MATRIZ_SRS_IMPLEMENTACION.md:73`;
AUD-0039 y demás permanecen con su estado previo).

---

## Cierre de tarea B0.1 — Seguridad de modelos y loader de rutas (2026-10-05)

**Estado:** **B0.1 COMPLETADA / VALIDADA Y CERRADA (2026-10-05)** — registro
retroactivo del cierre validado en su día (los cierres de esta sesión se
agrupan al final del archivo; los de más arriba son los originales).

**Cambios de producción:**

- `password_hash`, `hash_sha256` y `created_at` fuera de `$fillable` en
  `Usuario`, `Adjunto` y `Expediente` (lista blanca de masa de asignación), +
  trait `Notifiable`.
- Call-sites adaptados a `forceCreate`/`unguarded` puntuales:
  `AdminUsuariosController`, `ExpedienteService`, `AdjuntoService`,
  `UsuarioSeeder`, `UsuariosExtraSeeder` y `SeguridadIdorTest:88`.
- Loader de rutas refactorizado: `routes/api.php` + `routes/api/{core,
  operativo, admin, reportes, notificaciones}.php` con **diff de
  `php artisan route:list` vacío** (misma superficie de endpoints).
- `app/Providers/AuditoriaServiceProvider.php` creado y registrado en
  `bootstrap/providers.php`.

**Test nuevo:** `tests/Unit/ModelSecurityTest.php` — 7 tests (superficie de
`$fillable` de los modelos sensibles).

**Validación:** suite **317 tests · 311 OK · 0 fallos · 6 omitidos**; Pint
`passed`.

---

## Cierre de tarea B0.3 — Constantes de rol en seeders (2026-10-05)

**Estado:** **B0.3 COMPLETADA / VALIDADA Y CERRADA (2026-10-05)** — registro
retroactivo.

**Cambios de producción:**

- 25 literales de rol reemplazados por `Rol::CODIGO_*` en `RolSeeder`,
  `CatalogoActuadoSeeder`, `UsuarioSeeder` y `UsuariosExtraSeeder`
  (reemplazos byte-safe Latin1: bytes no-ASCII 10/20/24/136 preservados,
  `php -l` limpio en los 4).
- Literales fuera de alcance clasificados sin tocar: tokens
  `responsable=TECNICO|AUDITOR` (`AdminMonitoreoController:72,136`), vías
  (`StoreExpedienteRequest:22`, `SorteoAlgorithmService:31`), seeders demo y
  usernames.

**Test nuevo:** `tests/Feature/RolConstantesTest.php` — 3 tests.

**Validación:** suite **320 tests · 314 OK · 0 fallos · 6 omitidos**; Pint
`passed`.

**Incidencia resuelta:** un `Set-Content -Encoding Byte` truncó
`RolSeeder.php` a 0 bytes → restaurado byte-exacto (956 bytes) vía
`git show HEAD:database/seeders/RolSeeder.php` (solo lectura); posteriormente
los reemplazos se hicieron con round-trip Latin1.

---

## Cierre de tarea B3.3 — Timezone institucional America/La_Paz (2026-10-05)

**Estado:** **B3.3 COMPLETADA / VALIDADA Y CERRADA (2026-10-05)** — registro
retroactivo. Corrección de AUD-0043 (corte UTC prematuro) decidida por el
usuario.

**Cambios de producción (único archivo de código):**

- `config/app.php:68` → `'timezone' => env('APP_TIMEZONE', 'America/La_Paz')`
  (default La Paz garantiza el criterio sin tocar `.env`; `APP_TIMEZONE`
  disponible para Brayan en `.env.example` — mensaje entregado, archivo no
  modificado).

**Test nuevo:** `tests/Feature/TimezoneBoliviaTest.php` — 5 tests · 20
aserciones (config + TZ efectiva `-04:00`, cierre 23:59:59 La Paz, persistencia
de expedientes/actuados/plazos sin corrimiento, transición de medianoche
UTC↔La Paz, `daysRemaining` consistente).

**Validación:** `config:show app.timezone` → **America/La_Paz**; suite
**325 tests · 319 OK · 0 fallos · 6 omitidos · 1605 aserciones**; Pint
`passed` (sin modificaciones). `routes/console.php` intacto: el schedule
`->daily()` hereda la TZ → corte a medianoche boliviana.

**Efecto documental registrado hoy:** cierre administrativo de **AUD-0043** en
`BACKLOG_AUDITORIA.md` (fila + ficha), `MATRIZ_COBERTURA_FINAL.md` y
`MATRIZ_JOBS_CRON.md` → CERRADA (2026-10-05, B3.3).

**Límite documentado:** `actuados.fecha_hora` usa `CURRENT_TIMESTAMP` de MySQL
con sesión `SYSTEM` (UTC-4 = La Paz en este entorno); en servidores con otra TZ
sería necesario `'timezone'` en `config/database.php` (fuera de alcance,
requiere autorización).

---

## Cierre de tarea B1.1 — Informes finales Técnico/Jurídico (2026-10-06)

**Estado:** **B1.1 COMPLETADA / VALIDADA Y CERRADA (2026-10-06)** — registro
retroactivo (la instrucción de la sesión fue cerrar sin git ni PROGRESO.md;
se registra hoy por pedido explícito de actualizar el progreso al estado real).

**Cambio de producción (único archivo):**

- `database/seeders/CatalogoActuadoSeeder.php` — **+6 informes finales**:
  4 Técnico (AC022, conforme SRS `:374-413`) + 2 Jurídico (AC054, CON/SIN
  responsabilidad). Todos: fase `INVESTIGACION`, `estado_origen` =
  `EN_EJECUCION`, `estado_destino` = `PENDIENTE_VISTO_BUENO_FINAL`,
  `requiere_adjunto` y pivote por reglamento. `ACT_INFORME_FINAL` **intacto**
  (destino `null` diferido a B1.2/D-6a).

**Test nuevo:** `tests/Feature/ContratosAdmisibilidadTest.php` — **11 tests ·
86 aserciones** (contratos CTR de informes contra la implementación real).

**Validación (sin modificar ningún test existente):**

- `php artisan test --compact` → **341 tests · 335 OK · 0 fallos · 6 omitidos**
  (baseline +6 tests/+6 OK; los 6 omitidos: 5 `RUN_STRESS_TESTS` + 1
  `RUN_CONCURRENCY_TEST`, preexistentes).
- `vendor/bin/pint --dirty --format agent` → `passed`.

**Contratos CTR verificados contra implementación real (sin cambios de código):**

- **CTR-01:** divergencia — columnas `codigo`/`nombre`/`cumplido` del contrato
  **ausentes** en la implementación (reportado, pendiente de decisión).
- **CTR-02:** divergencia — payload/respuesta real ≠ contrato (reportado,
  pendiente de decisión).
- **CTR-03:** resuelta — sin cambios necesarios.

**Alcance:** sin operaciones git; sin tocar `PROGRESO.md` en su momento.

---

## Cierre de tarea B1.2 — Huecos del grafo, lock pesimista y ADMITIDO→PLANIFICACIÓN (2026-10-06)

**Estado:** **B1.2 COMPLETADA / VALIDADA Y CERRADA (2026-10-06)** — D-6a, D-6b
y D-6g aplicados; sin migraciones, sin cambios de rutas y **sin tocar
`app/Http/Requests/*`** (evitado el bloqueo histórico B0.2 de Brayan).

**Decisiones del usuario aplicadas (4/4):** (1) seeder incluido en alcance;
(2) actualizar los 3 tests afectados; (3) D-6g validando `estado_origen_id` en
`registerActuado` para **todos** los llamadores; (4) hook auto-paso genérico en
`ActuadoService` (no en `ImpugnacionService`).

**Cambios de producción:**

- `database/seeders/CatalogoActuadoSeeder.php` — **D-6a:** `ACT_INFORME_FINAL`
  `estado_destino_id` `null` → `PENDIENTE_VISTO_BUENO_FINAL`; **D-6b:** fila
  `ACT_PASO_PLANIFICACION` (`ADMITIDO` → `EN_PLANIFICACION`, `es_automatico`,
  `rol_id` ADMIN, sin entrada en `MAPA_TIPO_PLAZO`, **sin pivote** — mismo
  patrón que `ACT_ARCHIVO_POR_ABANDONO`).
- `app/Services/ActuadoService.php` — constante
  `CODIGO_PASO_PLANIFICACION`; `estadoAdmitidoId()` tolerante a ausencia de
  estado ADMITIDO; al inicio de la transacción de `registerActuado`:
  `Expediente::query()->lockForUpdate()->findOrFail()` + `verificarEstadoOrigen()`
  → `ValidationException` 422 (key `estado_origen_id`; `null` = sin validación);
  hook: si el estado nuevo es ADMITIDO → `registrarPasoPlanificacion()` en la
  misma transacción (mismo emisor, metadatos `tipo: AUTOMATICO`, `motivo:
  PASO_ADMITIDO_PLANIFICACION`, sin `usuario_destino`).
- `app/Services/EvaluacionAdmisibilidadService.php` — `lockForUpdate` del
  expediente al inicio de `evaluar()`.

**Tests:**

- Nuevo `tests/Feature/MaquinaEstadosTransicionesTest.php` — **5 tests** (4
  ejecutados + 1 opt-in `RUN_CONCURRENCY_TEST` con subprocesos tinker,
  verificado **5/5** en corrida dedicada; patrón heredado de
  `CadenaHashConcurrenciaTest`).
- Actualizados los 3 tests afectados: `ImpugnacionRechazoTest` (`:309`),
  `RelojProcesualTest`, `ContratosAdmisibilidadTest` (+1 test D-6b).

**Validación:**

- `php artisan test --compact` → **347 tests · 340 OK · 0 fallos · 7 omitidos**
  (delta vs B1.1: +6 tests, +5 OK, +1 omitido = nuevo opt-in concurrencia; los
  7 omitidos: 5 `RUN_STRESS_TESTS` + 2 `RUN_CONCURRENCY_TEST`).
- `vendor/bin/pint --dirty --format agent` → `passed`.

**Hallazgos de ejecución (reportados, sin salir del alcance):**

1. **Bug propio corregido en la tarea:** `estadoAdmitidoId()` con `firstOrFail`
   reventaba el arranque de la suite cuando el estado ADMITIDO no existe en la
   BD del test (30 fallos / 8 errores 404-ModelNotFound) → hecho tolerante
   (retorna `null` = sin hook).
2. **AUD-0031 no materializado:** archivo fuera de `EN_SUBSANACION` ahora
   fallaría con 422 (validación D-6g) en vez de saltarse el estado;
   `ArchivoPorAbandonoService` queda **sin filtro propio** — auditar en **B1.4**.
3. **`ACT_PASO_PLANIFICACION` sin pivote** por reglamento (automático, igual
   que `ACT_ARCHIVO_POR_ABANDONO`) — decisión tomada, documentada.
4. **Comentario obsoleto** en `FlujoJuridicoTest` (`:63-81,:184`, "Réplica del
   seeder") — el seeder ya difiere (D-6a); **sin tocar** (fuera de alcance).
5. Re-bloqueo anidado del expediente (savepoint) **sin deadlock**; fixtures
   propias de tests requirieron la fila `ACT_PASO_PLANIFICACION`.

**Alcance del diff (sin commitear, sin stashes):** 5 `M`
(`ActuadoService`, `EvaluacionAdmisibilidadService`, `CatalogoActuadoSeeder`,
`ImpugnacionRechazoTest`, `RelojProcesualTest`) + 2 `??`
(`MaquinaEstadosTransicionesTest`, `ContratosAdmisibilidadTest`).

**Documentación actualizada en este cierre:** esta sección + filas
AUD-0020/0021/0033 de «Decisiones del usuario — RESUELTAS» (arriba).
`BACKLOG_AUDITORIA.md` **intacto** (sus fichas siguen diciendo "fix NO
aplicado" — fuera de alcance, reportado).

---

## Cierre de tarea B1.3 — Cierre formal de plazos al transicionar entre fases (2026-10-06)

**Estado:** **B1.3 COMPLETADA / VALIDADA Y CERRADA (2026-10-06)** — hallazgo
**AUD-0030** (P1) cerrado; sin migraciones, sin cambios de rutas, sin tocar
`PlazoCalculatorService`, `VerificarVencimientoPlazosCommand` ni
`SemaforoPlazoService`.

**Decisiones del usuario aplicadas (7/7):** (1) Opción A: `estado='CERRADO'` +
`actuado_cierre_id`, sin columna `fecha_cierre` (fecha derivada de
`actuados.fecha_hora`); (2) cierre dentro de `registerActuado()` en la misma
transacción/lock de B1.2; (3) mapa limitado a los actuados de la fase (no cierra
`SUSPENDIDO` ni pausas de descargos, no adelanta SUBSANACION); (4) sin tocar
otros servicios (auditados con grep); (5) solo se actualizó el test que
codificaba el bug; (6) gates; (7) cierre documental solo con todo en verde.

**Cambios de producción:**

- `app/Services/ActuadoService.php` — constante `MAPA_CIERRA_PLAZO` (13
  entradas: `ACT_ADMISION`/`ACT_OBSERVACION` → `EVALUACION`; 9 informes
  finales → `EJECUCION`/`EJECUCION_AMPLIADA`; `ACT_VISTO_BUENO_FINAL` y
  `ACT_REPARTO_INSTITUCIONAL` → `EJECUCION`/`EJECUCION_AMPLIADA`); método
  `cerrarPlazosDeFase()` (update idempotente, solo `VIGENTE` de los tipos del
  mapa, con `actuado_cierre_id=$actuado->id`); llamada en `registerActuado`
  antes de `abrirPlazoSiAplica`, dentro de la transacción.
- `app/Services/CierreExpedienteService.php` — **sin cambios** (todo flujo ya
  pasa por `registerActuado`).

**Tests:**

- Nuevo `tests/Feature/CierrePlazosFasesTest.php` — **7 tests** (admisión
  cierra EVALUACION; observación cierra EVALUACION y abre SUBSANACION; informe
  cierra EJECUCION + EJECUCION_AMPLIADA con evidencia; VB final cierra residual
  y no toca `SUSPENDIDO`, reparto deja 0 VIGENTE; ampliación NO cierra; ciclo
  AC055 pausa/reanudación intacta; CRON no estampa cerrados).
- `tests/Feature/FlujoIntegralTest.php:333` — assertion actualizada de
  `VIGENTE` → `CERRADO` + `actuado_cierre_id` no nulo (el test codificaba
  AUD-0030: "la admisión NO cierra el plazo de EVALUACION"); comentario
  actualizado. Único test tocado de la suite.

**Validación:**

- `php artisan test --compact --filter=CierrePlazosFasesTest` → **7/7 OK**.
- `vendor/bin/pint --dirty --format agent` → `passed`.
- `php artisan test --compact` → **354 tests · 347 OK · 0 fallos · 7 omitidos
  · 1836 aserciones** (delta vs B1.2: +7 tests / +7 OK = `CierrePlazosFasesTest`;
  los 7 omitidos: 5 `RUN_STRESS_TESTS` + 2 `RUN_CONCURRENCY_TEST`).

**Hallazgos de ejecución (reportados, sin salir del alcance):**

1. **Test propio corregido en la tarea:** `CierrePlazosFasesTest` usaba la misma
   instancia de `Expediente` en dos llamadas seguidas y quedaba desactualizada
   (en HTTP cada request reconsulta) → `refresh()` + comentario.
2. **`FlujoIntegralTest` era el único test que codificaba el bug** (el resto de
   aserciones `VIGENTE` de la suite corresponden a relojes vivos o a fases no
   afectadas); se actualizó tras verificar que el fallo era de AUD-0030.

**Alcance del diff (sin commitear, sin stashes):** 2 `M`
(`ActuadoService`, `FlujoIntegralTest`) + 1 `??`
(`CierrePlazosFasesTest`).

**Documentación actualizada en este cierre:** esta sección + fila AUD-0030 de
«Decisiones del usuario — RESUELTAS» (arriba) + fila y ficha AUD-0030 en
`BACKLOG_AUDITORIA.md` (ambas CERRADO).

---

## Cierre de tarea B1.4 — Flujo de subsanación y protección del archivo por abandono (2026-10-06)

**Estado:** **B1.4 COMPLETADA / VALIDADA Y CERRADA (2026-10-06)** — hallazgos
**AUD-0031 y AUD-0032** (ambos P1) cerrados; sin migraciones, sin tocar
`VerificarVencimientoPlazosCommand`, `MarcarPlazosVencidosService`,
`SemaforoPlazoService`, controllers, requests ni frontend.

**Decisiones del usuario aplicadas (5/5):** (1) seeder del catálogo incluido
(fila + pivote, sin migración); (2) sin reloj EVALUACION nuevo al aceptar;
(3) huérfanos de SUBSANACION fuera de alcance (solo filtro estricto);
(4) aceptación con plazo vencido → **422** (vencimiento temporal
independiente de la corrida del CRON); (5) `requiere_adjunto=false`.

**Cambios de producción:**

- `app/Services/ArchivoPorAbandonoService.php` — filtro estricto
  `whereHas('expediente', estado_actual = EN_SUBSANACION)` (const
  `ESTADO_EXPEDIENTE_SUBSANACION`) + `try/catch (\Throwable)` por expediente
  dentro del bucle con `Log::error` (plazo_id, expediente_id, clase, mensaje)
  y `continue` sin incrementar el contador — una falla no aborta la corrida.
- `app/Services/ActuadoService.php` — const `CODIGO_ACEPTACION_SUBSANACION`;
  entrada `'ACT_SUBSANACION_ACEPTADA' => ['SUBSANACION']` en
  `MAPA_CIERRA_PLAZO` (cierre vía mecanismo B1.3, con `actuado_cierre_id`);
  guard `verificarPlazoSubsanacionNoVencido()` tras `verificarEstadoOrigen()`:
  422 (`plazo`) si el plazo SUBSANACION está `VENCIDO` o `fecha_limite` ya
  superó `today()` — la columna es `date` sin hora y se compara contra la
  fecha actual en `app.timezone` = **America/La_Paz** (verificado con
  `php artisan config:show app.timezone`); sin plazo → permite.
- `database/seeders/CatalogoActuadoSeeder.php` — fila
  `ACT_SUBSANACION_ACEPTADA` (`EN_SUBSANACION → EN_EVALUACION`, fase
  ADMISIBILIDAD, sin adjunto) + pivote `perfilesEvaluacion` (3 perfiles).

**Tests:**

- Nuevo `tests/Feature/SubsanacionExitoTest.php` — **9 tests**: flujo completo
  observación → aceptación (HTTP 201) → admisión con cierre del reloj y sin
  reloj EVALUACION nuevo; 403 sin pivot (Encargada) y sin asignación activa;
  422 D-6g fuera de `EN_SUBSANACION`; 422 por `fecha_limite` superada; 422 por
  plazo `VENCIDO`; aceptación permitida sin plazo parametrizado; AUD-0031
  negativo (expediente fuera de fase → 0 archivados); corrida mixta (1 de 2);
  aislamiento con mock de `ActuadoService` + `Log::spy()` (fallo en un
  expediente no cancela al resto). Escritura en rojo primero: los tests 7/8/9
  reproducían el aborte real del CRON post-D-6g.

**Validación:**

- `php artisan test --compact --filter=SubsanacionExitoTest` → **9/9 OK**.
- Regresión dirigida (`ArchivoPorAbandonoTest`, `VerificarVencimientoPlazosTest`,
  `SemaforoPenalizacionTest`, `EvaluacionAdmisibilidadTest`,
  `CierrePlazosFasesTest`) → **29/29 OK**.
- `vendor/bin/pint --dirty --format agent` → `passed` (formateó el test nuevo).
- `php artisan test --compact` → **363 tests · 356 OK · 0 fallos · 7 omitidos
  · 1885 aserciones** (delta vs B1.3: +9 tests / +9 OK = `SubsanacionExitoTest`;
  los 7 omitidos: 5 `RUN_STRESS_TESTS` + 2 `RUN_CONCURRENCY_TEST`).

**Hallazgos / residuales (reportados, fuera de alcance):**

1. **Huérfanos SUBSANACION (decisión del usuario, no resueltos):** un plazo
   SUBSANACION `VIGENTE` de un expediente que salió de la fase por un actuado
   con `estado_origen_id = null` queda sin procesar para siempre
   (`marcarVencidos` excluye SUBSANACION; el semáforo toma el VIGENTE más
   antiguo). No mezclar con AUD-0042.
2. **Plan §12** prevé "Subsanación → Planificación"; la tarea B1.4 define
   retorno a **EN_EVALUACION** (se implementó lo segundo; re-evaluación
   completa antes de admitir).
3. El resto de salidas de fase con origen `null` (derivaciones, registro de
   digitalización) sigue pudiendo sacar un expediente de `EN_SUBSANACION`
   dejando reloj huérfano — cubierto por el residual 1.

**Alcance del diff (sin commitear, sin stashes):** 3 `M` de B1.4
(`ActuadoService`, `ArchivoPorAbandonoService`, `CatalogoActuadoSeeder`) +
1 `??` (`SubsanacionExitoTest`), acumulado con los cambios de B1.3 aún sin
commitear (`FlujoIntegralTest`, `CierrePlazosFasesTest` y este log).

**Documentación actualizada en este cierre:** esta sección + fila
AUD-0031/0032 de «Decisiones del usuario — RESUELTAS» (arriba) + filas y
fichas AUD-0031 y AUD-0032 en `BACKLOG_AUDITORIA.md` (todas CERRADO).

## Cierre de tarea B1.5 — Derivación de NUREJ Hijo con especialidad de destino (2026-10-06)

- **Estado:** **TÉCNICA Y DOCUMENTALMENTE CERRADA (2026-10-06)** — revisión
  y aceptación del usuario ("B1.5 técnica revisada y aceptada").
- **Hallazgo:** **AUD-0039** (P2) cerrado. **Errata registrada:**
  `TAREAS_BRUNO.md` referenciaba AUD-0035; el hallazgo correcto es
  AUD-0039 (AUD-0035, UI de impugnación, sin cambios).
- **Decisiones implementadas** (todas en `MATRIZ_DERIVACIONES.md` §5):
  - **D-P1 (SÍ):** el servidor exige informe técnico habilitante previo en
    el padre (M1-M4, `ACT_INFORME_TECNICO_CON/SIN_RESPONSABILIDAD_RECOMENDACION`)
    y `padre.via = TECNICO`; si no → 422 y 0 hijos.
  - **D-P2 (SÍ):** whitelist estricta `DESTINOS_PERMITIDOS = [JURIDICO,
    FINANCIERO]`; M5 (TECNICO), M6/M7/M8 y cualquier combinación no
    contemplada → 422.
  - **D-P3 (NO):** no se implementa regla global "destino ≠ padre"; **la
    matriz es la autoridad**.
  - **C8:** `via_destino` y `reglamento_destino_id` en los `metadatos`
    existentes de `registerActuado()` → columna `contenido` del actuado
    `ACT_CREACION_NUREJ_HIJO` (sin columnas ni estructuras nuevas).
  - Reglamento destino server-side `MAPA_VIA_DESTINO_REGLAMENTO`:
    JURIDICO→`AC_054_2018`, FINANCIERO→`AC_055_2018` (TECNICO→`AC_022_2018`
    para el caso heredado); guard RN-10 de AUD-0038 **antes** de la matriz.
- **Archivos modificados (código):** `app/Services/NurejHijoService.php`
  (consts de matriz, `crearHijo(+viaDestino)`, `verificarDerivable()`, C8),
  `app/Http/Requests/DerivarNurejHijoRequest.php` (`via_destino` sintaxis),
  `app/Http/Controllers/ExpedienteController.php` (pasa `via_destino`).
- **Tests:** **nuevo** `tests/Feature/NurejHijoEspecialidadTest.php` (7
  tests: M1, M2, M3, M5, M6, M8, sintaxis); actualizados
  `NurejHijoTest` y `FlujoNurejTest` (semillas con habilitantes + payloads
  `via_destino`, aserciones JURIDICO/`AC_054_2018`, contadores +1 por el
  actuado habilitante).
- **Gates:** tests nuevos 7/7 (46 aserciones) → regresión dirigida
  `NurejHijoTest|FlujoNurejTest|NurejGeneratorServiceTest` 15/15 (82) →
  `pint --dirty` (solo estilo en `NurejHijoService`) → **suite completa
  370 tests · 363 OK · 0 fallos · 7 omitidos · 1931 aserciones**
  (baseline B1.4: 363 · 356 OK · 7 omitidos · 1885).
- **Sin:** migraciones, cambios en seeders/policies/sorteo/estados/plazos,
  frontend, configuración ni Git.
- **Documentación actualizada en este cierre:** esta sección + fila
  AUD-0039 de «Decisiones del usuario» (arriba) + fila y ficha AUD-0039 en
  `BACKLOG_AUDITORIA.md` (CERRADO) + `MATRIZ_DERIVACIONES.md` (estado
  APROBADA, §1 D-P3, §2 estado de implementación, §3 C4/C8/C9, §5 decisiones
  resueltas, §6 fuentes).