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
| AUD-0020 | P1/OPEN; validar estado origen; antes: matriz `actuado→origen→destino` | DECIDIDO, fix no aplicado |
| AUD-0021 | P1/OPEN; grafo actual NO aceptado; antes: matriz `estado→actuados→destinos→roles` (SRS, sin inventar) | DECIDIDO, fix no aplicado |
| AUD-0023 | P2/OPEN; NO `db:seed` global; sync idempotente de catálogos; ejecución dev con autorización específica | DECIDIDO, fix no aplicado |
| AUD-0024 | P1 confirmado, NO fuera de alcance; campo `naturaleza` (no `via`); antes: impacto de migración+tests | DECIDIDO, fix no aplicado |
| AUD-0025 | P2 confirmado; AC022=2d, AC054/055 MPA sin reloj rígido; ejecución = fecha MPA; antes: cambio mínimo+tests | DECIDIDO, fix no aplicado |
| AUD-0027 | P2/OPEN; validar destino (activo+rol+institucional) en servidor; antes: matriz `actuado→roles/destinos` | DECIDIDO, fix no aplicado |
| AUD-0028 | P1/OPEN; dashboard agregado NO sustituye supervisión operativa; diseñar bandeja desde casos de uso | DECIDIDO, fix no aplicado |
| AUD-0029 | P2/OPEN; reasignación explícita con actuado+trazabilidad, sin reasignación silenciosa; antes: flujo institucional | DECIDIDO, fix no aplicado |
| AUD-0033 | P1/OPEN; NO `estado_nuevo_id` nullable; resolver en el grafo (destino informe → espera VB → verificar → cambio mínimo) | DECIDIDO, fix no aplicado |
| AUD-0034 | P2 (funcional, no seguridad); fix conceptual autorizado (catálogo contextual + validación servidor); NO aplicar hasta AUD-0033/0020/0021 | DECIDIDO, fix no aplicado |
| AUD-0035 | P1 confirmado; endpoints NO sustituyen UI; verificar conjunto de operaciones jurídicas y diseñar UI contra flujo normativo | DECIDIDO, fix no aplicado |
| AUD-0039 | **Opción B adoptada** (`AUD-0039_DISENO.md` §7): `via_destino` explícito en servidor, mapeo vía→reglamento (JURIDICO→AC054, FINANCIERO→AC055), matriz normativa de combinaciones | DECIDIDO, fix no aplicado (pendiente matriz) |
| AUD-0040 | **(cierre F12)** Opción (a): **MONITORIZAR** — flake/no reproducido, causa raíz abierta; sin cambio en helper ni producto sin reproducción fiable o evidencia de causa raíz | OPEN/P3, monitorización |
| AUD-0041 | P2; fix AUTORIZADO y **APLICADO**: check `activo`+rol en `AdminDashboardController` (patrón Monitoreo) + test; gates OK | **CERRADO (2026-09-30, con validación de F12)** |
| AUD-0038 | Autorización mantenida (ya aplicada en Fase 11): 422 controlado + test; sin cambios relacionados con AUD-0039 | CERRADO |
| O-4 | Observación, NO elevada: verificado contra RF-03 → `bandejaOperador:72-76` filtra por usuario y `show:85` exige policy → sin lectura de expedientes ajenos | Resuelta (observación) |
| O-6 | **(cierre F12) CERRADA: "Comportamiento aceptado provisionalmente; el SRS no establece prohibición explícita de autoasignación."** Sin exclusión del creador en esta fase; si se decide luego → cambio funcional separado con tests | Cerrada (aceptada provisionalmente) |
| O-7 | **(cierre F12) CERRADA como sub-caso de AUD-0023** (sin ficha P2): BD dev sin `IMPUGNACION_RESOLVER` (0 filas) → fallback `now()` con límite inmediato; sin modificar; candidato de corrección en la sync de parámetros | Cerrada (asociada a AUD-0023) |
| AUD-0030/0031/0032 | **(cierre F12) Incorporados formalmente a la lista de decisiones pendientes; NO aplicar.** Props: 0030 decidir cerrar plazos al salir de fase vs filtrar CRON/semáforo; 0031 condicionar archivo a `EN_SUBSANACION` y/o cierre del plazo; 0032 actuado de salida de subsanación (migración de catálogo sujeta a aprobación). No bloquean F12 | OPEN/P1, decisión pendiente |

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
  - **AUD-0042/AUD-0043 (P2, Fase 13):** decisión pendiente del usuario
    (revisión posterior a este cierre, antes de F14).
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

## Fase 13 — Jobs/cron (ENTREGADA, 2026-09-30 — pendiente de validación)

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
- **AUD-0043 (P2/OPEN):** corte UTC (00:00 UTC = 21:00 ART) archiva/marca
  hasta 3 h antes de la medianoche local que pide el SRS; reproducido con
  test. Fix = decisión de zona horaria institucional (config global) NO
  aplicado.
- **O-10 (observación, NO elevada):** `daily()` sin
  `withoutOverlapping()`/lock + sin índice único → corridas solapadas
  podrían duplicar actuados; no reproducido → no clasificado.
- **AUD-0037:** acción (a) «mapear catálogos requeridos vs seeders»
  cumplida en `MATRIZ_JOBS_CRON.md` §5; (b) sigue pendiente de autorización.
- Sin cambios de producto en la fase (solo tests + documentación).

### Salida de fase

**Fase 13 ENTREGADA (2026-09-30), pendiente de validación.**
**Backlog vigente: 39 hallazgos abiertos: 16 P1 · 17 P2 · 6 P3 — 4 cerrados
(AUD-0002, AUD-0003, AUD-0038, AUD-0041).**
**`NEEDS_REVIEW`: NINGUNO** (resueltos con el cierre de Fase 12).
**Gates:** `vendor/bin/pint --dirty --format agent` → OK;
`php artisan test --compact` → **296 tests: 289 OK · 1 fallo (AUD-0001)
· 0 errores · 6 omitidos** (+3 tests nuevos).