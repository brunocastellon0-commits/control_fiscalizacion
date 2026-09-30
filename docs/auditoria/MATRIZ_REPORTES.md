# Fase 12 — Reportes (MATRIZ_REPORTES)

Estado: **VALIDADA Y CERRADA (2026-09-30)** — cierre con decisiones del
usuario documentadas en `PROGRESO.md` (§ Fase 12 «Cierre de validación»).
Evidencia de consistencia con script tinker temporal de solo lectura
(`f12_admin.php`, BD dev `control_fiscalizacion` sin escrituras) + tests
feature contra `control_fiscalizacion_test`. Único hallazgo nuevo de la
fase: **AUD-0041** (ver BACKLOG). Análisis de diseño mandatado AUD-0039 en
`docs/auditoria/AUD-0039_DISENO.md`.

## §0 Alcance y método

Definición del Plan §3: *"F12 Reportes: dashboard vs API vs Excel/PDF vs SQL
→ `MATRIZ_REPORTES.md`"*.

- **Marco normativo (SRS `:117-127`):**
  - `:118` — **Todos** los reportes: filtrado por rango de fechas / usuario
    asignado / estado del trámite; salida multiformato **pantalla + Excel
    (.xlsx) + PDF**.
  - `RF-R01` `:119` … `RF-R09` `:127` (ver matriz).
  - `:187` — consistencia al 100% dashboard/Excel/PDF/SQL.
  - `:127` RF-R09 (trazabilidad padre-hijo; dato `nurej_padre_id` auditado
    en Fase 11).
- **Superficie auditada:**
  - API: `GET /api/admin/dashboard` (`routes/api.php:95` →
    `AdminDashboardController@index`), `GET /api/admin/monitoreo`
    (`routes/api.php:111` → `AdminMonitoreoController@index`),
    `GET /api/encargada/dashboard` (`routes/api.php:90` →
    `EncargadaDashboardService@obtenerResumen:19,185`).
  - Vistas: `resources/views/administrador/{dashboard,monitoreo}.blade.php`,
    `resources/views/encargada/dashboard.blade.php`.
  - Dependencias de exportación: `composer.json` (0 paquetes PDF/Excel);
    grep de vistas `export|excel|pdf|download` = **0 coincidencias**.
  - Rutas: grep en `routes/` de endpoints de export/descarga = **0**.
- **Método:** (1) lectura de código + rutas; (2) reproducción de la
  consistencia **dashboard/monitoreo vs SQL independiente** en BD dev
  (mismos agregados calculados por dos vías distintas); (3) tests feature
  nuevos con datos sembrados propios.

## §1 Matriz RF-R01…R09

| RF | Requerimiento (SRS) | Pantalla | Excel | PDF | Filtros `:118` | Fuente real | Tests | Estado |
|---|---|---|---|---|---|---|---|---|
| RF-R01 | Carga laboral por usuario `:119` | Parcial: `carga_operadores` en dashboard Encargada (`EncargadaDashboardService:143-185`) | **NO** | **NO** | **NO** (endpoint fijo, sin params) | `Asignacion` agrupada | `FlujoEncargadaTest` (contexto) | **PARCIAL** |
| RF-R02 | Solicitudes registradas (libro matriz de ingresos) `:120` | Parcial: `AdminMonitoreoController@index` lista expedientes (solo ADMIN) | **NO** | **NO** | Parcial: `estado` ✓ (`:302`), `responsable` = por rol (no usuario) (`:68-79`), **sin filtro de fecha** | `Expedientes` + filtros | `ReportesAdminTest` (nuevo) | **PARCIAL** |
| RF-R03 | Recepción inter-unidad (derivados/aceptados) `:121` | **NO** (existen actuados `ACT_DERIVACION_INCOMPETENCIA`/`ACT_REMISION_TRANSPARENCIA` sin reporte) | **NO** | **NO** | — | — | — | **AUSENTE** |
| RF-R04 | Estado de evaluación (Admitidos/Observados/Rechazados) `:122` | Parcial: `expedientes.por_estado` agregado (dashboard admin/encargada) + filtro `estado` en monitoreo | **NO** | **NO** | Parcial: estado ✓, sin fecha/usuario | `catalogo_estados` dinámico | `ReportesAdminTest` (nuevo) | **PARCIAL** |
| RF-R05 | Notificaciones pendientes (subsanación) `:123` | Parcial: semáforo global + filtro `estado=EN_SUBSANACION` en monitoreo; **sin "días restantes de subsanación"** por caso | **NO** | **NO** | Parcial | `SemaforoPlazoService` + plazos | `ReportesAdminTest` (semáforo) | **PARCIAL** |
| RF-R06 | Resoluciones finales (demanda/sumariante/archivo) `:124` | **NO** (sin listado de salidas; `ACT_REPARTO_INSTITUCIONAL` existe, sin reporte) | **NO** | **NO** | — | — | — | **AUSENTE** |
| RF-R07 | Estadístico por vía (gráficas) `:125` | Parcial: `expedientes.por_via` con barras CSS (`administrador/dashboard.blade.php:188`; `encargada/dashboard.blade.php:213`), sin librería de gráficas | **NO** | **NO** | NO aplica (dashboard) | `Expediente.via` | `ReportesAdminTest` (por_via) | **PARCIAL** |
| RF-R08 | Carátula oficial PDF (foja 0) `:126` | **NO** | — | **NO** (0 paquetes PDF en `composer.json`) | — | — | — | **AUSENTE** |
| RF-R09 | Trazabilidad derivaciones padre-hijo `:127` | **NO** (dato disponible: `expedientes.nurej_padre_id` + `ACT_CREACION_NUREJ_HIJO`; sin listado) | **NO** | **NO** | — | — | Fase 11 (flujo, no reporte) | **AUSENTE** |

Resumen multiformato `:118`: **pantalla = parcial en 5/9; Excel = 0/9;
PDF = 0/9**; filtros globales fecha/usuario/estado: solo `estado` existe
(exclusivo de ADMIN, `AdminMonitoreoController:302`).

## §2 Consistencia dashboard/monitoreo vs SQL (`:187`)

Ejecutado en BD dev (2026-09-30, solo lectura) — mismos agregados por vía
controlador y por SQL independiente:

| Agregado | API (controlador) | SQL independiente | Coincide |
|---|---|---|---|
| `usuarios.total` / activos / inactivos | 15 / 15 / 0 | 15 / 15 / 0 | ✓ |
| `expedientes.total` | 24 | 24 | ✓ |
| `expedientes.sin_asignar` | 19 | 19 (NOT EXISTS asignación activa) | ✓ |
| `asignaciones.activas` | 5 | 5 | ✓ |
| `por_estado` (3 estados) | PENDIENTE_SORTEO 19, EN_EVALUACION 4, EN_SUBSANACION 1 | idéntico | ✓ |
| `por_via` | TECNICO 10, FINANCIERO 7, JURIDICO 7 | idéntico (orden por empate difiere, patrón ya documentado en F10) | ✓ |
| `semaforo.total_fuera_de_plazo` | 5 | 5 (expedientes con plazo vigente) | ✓ |
| `seguridad.intentos_fallidos_24h` | 0 | 0 | ✓ |
| `feriados_proximos` | 1 (2026-12-25) | 1 | ✓ |
| Monitoreo `resumen` | total 24 / sin_asignar 19 / fuera_plazo 5 | coincide con anteriores | ✓ |

**Conclusión `:187` en pantalla:** consistencia **SQL ↔ dashboard ↔
monitoreo verificada para todos los agregados** (Excel/PDF inexistentes →
nada que comparar). Caveat: los 5 fuera-de-plazo todos vencidos →
`en_tramite`/`por_vencer` = 0 es coherente, no es bug.

## §3 AUD-0041 (hallazgo nuevo de la fase)

`AdminDashboardController:28-29` valida **solo rol ADMIN, no `activo`**;
`AdminMonitoreoController:26-31` sí valida `activo` + rol (y `EnsureAdmin`
web `:21`, `UsuarioPolicy:45,55,65` también). Reproducido con usuario
sintético en tinker:

```
ADMIN INACTIVO: dashboard status=200 | monitoreo status=403
TECNICO ACTIVO:  dashboard status=403 | monitoreo status=403
```

Mitigaciones verificadas: login bloquea inactivos (`AuthController:55-64`)
y el único flujo de inactivación (`SeguridadSesionesService@expulsar:50-54`)
**revoca tokens** (`$objetivo->tokens()->delete():52`), por lo que el vector
práctico requiere un token vigente sobre un usuario inactivado fuera de ese
flujo. Clasificación **P2** (check de autorización ausente en endpoint que
expone IPs de sesiones — `AdminDashboardController:154,162`); consistencia
con el resto del módulo. **Fix AUTORIZADO y aplicado (2026-09-30):** chequeo
`! $usuario || ! $usuario->activo || rol !== ADMIN → abort(403)` en
`AdminDashboardController@index` (mismo patrón que `AdminMonitoreoController:26-31`),
+ test en `ReportesAdminTest` (dashboard 403 con ADMIN inactivo); pint OK;
suite 293: 286 OK · 1 fallo (AUD-0001) · 0 errores · 6 omitidos. Estado
mantenido OPEN hasta validación de cierre.

## §4 Hallazgos y confirmaciones de la fase

- **Nuevo:** AUD-0041 (arriba) → BACKLOG, P2, fase 12; **fix autorizado y
  aplicado 2026-09-30** (ver §3).
- **Confirmados con evidencia F12:**
  - AUD-0011 (P1): 0 controllers/rutas/vistas/paquetes de export
    (Excel/PDF); matriz §1 cuantifica 0/9 + 0/9.
  - AUD-0017 (P2): `abort(403)` inline confirmado en ambos controllers
    (`AdminDashboardController:28-29`, `AdminMonitoreoController:31`).
  - AUD-0018 (P2): monitoreo devuelve `$query->get()` sin paginar
    (`AdminMonitoreoController:113`) — RNF-03 `:115`.
- **Brecha de filtrado `:118`:** rango de fechas **no existe** en ningún
  endpoint de reportes; "usuario asignado" solo como `responsable` = código
  de rol (no usuario concreto). Forma parte de AUD-0011/0018; se anota aquí
  para la matriz.

## §5 Entregables y gates de la fase

- Tests: `tests/Feature/ReportesAdminTest.php` — **5 tests / 34
  aserciones** (roles no-ADMIN → 403 en ambos endpoints; agregados del
  dashboard vs conteos independientes; monitoreo: resumen + filtros
  `estado`/`responsable`/`buscar`; dashboard y monitoreo 403 con ADMIN
  inactivo — este último desde el fix AUD-0041).
- Gate: `vendor/bin/pint --dirty --format agent` → **OK** (tras AUD-0041:
  formateo del controlador);
  `php artisan test --compact` → **293 tests: 286 OK · 1 fallo (AUD-0001) ·
  0 errores · 6 omitidos**; en el cierre de validación: **296 tests: 289
  OK · 1 fallo (AUD-0001) · 0 errores · 6 omitidos**.
- Cambios de producto en la fase: **solo el fix autorizado AUD-0041**
  (`AdminDashboardController` check `activo`); resto: tests + documentación.

## §6 Cierre de validación (2026-09-30)

- **Deudas/observaciones aceptadas:** AUD-0001 (deuda conocida; F12 no lo
  introdujo ni agravó; el fallo de suite se conserva visible — **no se
  oculta ni maquilla, no se toca el test**), AUD-0040 (monitorización,
  flake no reproducido), O-6 (comportamiento aceptado provisionalmente),
  O-7 (sub-caso de AUD-0023, fallback `now()` documentado en BD dev).
- **Abiertos para fases posteriores:** AUD-0001 (semántica de ADMIN →
  hardening/seguridad, opciones A/B/C sin aplicar), AUD-0040 (causa raíz),
  AUD-0030/0031/0032 (en la lista de decisiones del usuario; no bloquean
  F12), AUD-0042/0043 (F13, decisión pendiente).
- **La validación no aplicó cambios de producto.** Único cambio de producto
  de F12: fix AUD-0041, autorizado previamente y verificado con test.
- **AUD-0041 CERRADO** con este cierre; `NEEDS_REVIEW` resultante: ninguno.
