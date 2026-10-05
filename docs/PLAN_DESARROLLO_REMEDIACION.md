# PLAN DE DESARROLLO — Remediación y Cierre Post-Auditoría

**Fecha:** 2026-10-02
**Base:** F1–F18 completadas y F18 validada. 24 documentos de `docs/auditoria/`.
**Estado de decisiones:** D-1…D-10 **TODAS RESUELTAS** (2026-10-02).

---

## 1. ESTADO GLOBAL

| Métrica | Valor |
|---------|-------|
| Hallazgos abiertos | ~60 |
| P0 / P1 / P2 / P3 | 10 / 18 / ~21 / ~17 |
| Cobertura SRS | 27% implementado · 37% parcial · 37% faltante |
| Suite de tests | 310: 303 OK · 1 fallo (AUD-0001) · 0 errores · 6 omitidos |
| Decisiones pendientes | **0** |

---

## 2. DECISIONES RESUELTAS

### D-1: Rate limiting → **(a) Laravel nativo — YA RESUELTO**
- `throttle:login` (5/min) y `throttle:api` (60/min) ya funcionan y están testeados.
- **Sin acción requerida.**

### D-2: Fuente de feriados → **(a) Tabla BD editable por ADMIN — YA EXISTE**
- Tabla `feriados`, modelo, CRUD completo, `PlazoCalculatorService` ya lee de ahí.
- **Acción:** poblar con feriados oficiales reales del año en curso.

### D-3: Exportación de reportes → **(a) Laravel Excel + DomPDF**
- Instalar `maatwebsite/excel` + `barryvdh/laravel-dompdf`.
- Requiere aprobación para modificar `composer.json` — **APROBADO por el usuario**.

### D-4: Notificaciones → **(a) Solo en-app por ahora**
- Tabla `notifications` de Laravel, badge en UI.
- Sin servidor SMTP. Se puede añadir email después sin cambiar arquitectura.

### D-5: Compartimentos estancos → **(c) Rol + unidad organizacional**
- **Alcance ampliado:** se manejarán unidades organizacionales.
- Los expedientes se enviarán entre unidades (ej. informe final con resolución
  que corresponde a Transparencia → se envía; Transparencia puede devolver
  con observación).
- **Requiere:** tabla `unidades`, campo `unidad_id` en `usuarios` y/o
  `expedientes`, lógica de transferencia inter-unidad, policy por unidad +
  asignación.
- El aislamiento por `asignacionActiva` existente se mantiene como capa base.

### D-6: Máquina de estados → **Todas (i) con condiciones**

| Sub | Decisión | Condición |
|-----|----------|-----------|
| D-6a | `ACT_INFORME_FINAL` → destino `PENDIENTE_VISTO_BUENO_FINAL` | — |
| D-6b | `ADMITIDO → EN_PLANIFICACION` automático | **Se registra como actuado en la cadena de custodia** (trazabilidad completa). |
| D-6c | Crear `ACT_SUBSANACION_ACEPTADA` (EN_SUBSANACION → EN_EVALUACION) | — |
| D-6d | Crear 4 informes Técnico (CON/SIN resp. + recomendación) → destino `PENDIENTE_VISTO_BUENO_FINAL` | — |
| D-6e | Crear 2 informes Jurídico (CON/SIN resp.) → destino `PENDIENTE_VISTO_BUENO_FINAL` | — |
| D-6f | Marcar estados huérfanos como inactivos/legacy (no eliminar) | **Verificar que no hay referencias históricas en BD antes de inactivar.** |
| D-6g | Validar `estado_origen_id` en endpoint genérico | **Dentro de transacción con lock del expediente** (`lockForUpdate`). Test por cada transición. |

### D-7: Logs de auditoría → **(a) Tabla BD**
- **Tabla append-only** con triggers `BEFORE UPDATE` / `BEFORE DELETE` que
  rechazan la operación (mismo patrón de `actuados`).
- **Escritura que sobreviva al rollback:** usar conexión separada o
  `DB::afterCommit()` no aplica para append-only; la inserción del log se
  hace **antes** del commit de la operación auditada (si la operación falla,
  el log se revierte también — aceptable porque la operación no se ejecutó).
  Alternativa: cola síncrona con `DB::connection('log')` separada si se
  necesita que el log sobreviva al rollback.

### D-8: Driver de caché → **(b) Database**
- Tabla `cache` de Laravel. Sin infraestructura adicional.
- El singleton (Bloque 0.5) resuelve el 60% del problema sin caché.

### D-9: Protección de actuados → **Triggers + guard Eloquent**
- Triggers MySQL existentes se mantienen (ya testeados).
- **Guard mínimo en el modelo** (`Actuado`): override de `update()` y
  `delete()` que lance excepción amigable (`ActuadoInmutableException`)
  antes de llegar al trigger.
- **El usuario de BD de la aplicación NO debe tener privilegios
  `DROP`/`TRIGGER`** — documentar en checklist de despliegue.

### D-10: Doble confirmación financiera → **(a) Reautenticación**
- Modal con **reautenticación validada en backend**: contraseña →
  token temporal (~5 min por operación).
- **Registro en auditoría** de la reautenticación y la operación.
- **Antes de implementar:** confirmar en el SRS que no exige aprobación
  por segundo usuario (si lo exige, escalar a workflow).

---

## 3. PLAN DE EJECUCIÓN — 10 BLOQUES

### Dependencias

```
B0 Fundacionales ─────→ B1 Máquina de estados
                              │
                         B2 Autorización + Unidades ──┐
                              │                       │
                         B3 Plazos completos ─────────├─→ B4 Frontend/UI
                                                      │         │
                                                      │    B5 Reportes
                                                      │    B6 Jobs/Notif.
                                                      │         │
                                                      │    B7 Rendimiento
                                                      │         │
                                                      │    B8 Tests
                                                      │         │
                                                      │    B9 Docs/Cierre
```

---

### BLOQUE 0 — Fundacionales (sin dependencia)

> ~8h estimadas. Ejecutable inmediatamente.

#### 0.1 `$guarded = []` → `$fillable` explícito (AUD-0016, P0)

| Campo | Detalle |
|-------|---------|
| Archivos | Todos los modelos en `app/Models/` con `$guarded = []` |
| Acción | Reemplazar por `$fillable` listando solo columnas asignables |
| Tests | Test unitario por modelo: columnas sensibles (`id`, `password_hash`, timestamps) excluidas |
| Criterio | `grep '$guarded = \[\]' app/Models/` = 0; suite sin regresiones |

#### 0.2 Validación backend completa (AUD-0003, P1)

| Campo | Detalle |
|-------|---------|
| Archivos | Todos los FormRequests en `app/Http/Requests/` |
| Acción | Auditar reglas contra modelos; añadir `exists`, `max`, tipos explícitos; eliminar validación inline |
| Tests | Feature test por endpoint → 422 en datos inválidos |
| Criterio | Cada FormRequest con reglas explícitas; 0 validación inline en controllers |

#### 0.3 Roles como constantes (AUD-0008, P1)

| Campo | Detalle |
|-------|---------|
| Archivos | `app/Models/Rol.php` + archivos con strings literales de rol |
| Acción | Reemplazar strings dispersos por `Rol::CODIGO_*` |
| Tests | Test: cada constante corresponde a un rol real en BD |
| Criterio | Grep de strings literales = solo `Rol.php` |

#### 0.4 Hardening headers HTTP (AUD-0030, 0031, 0032, P2)

| Campo | Detalle |
|-------|---------|
| Archivos | Nuevo middleware `SecurityHeaders` |
| Acción | `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `X-XSS-Protection`, `Referrer-Policy`, CSP, HSTS |
| Tests | Feature test verificando cada header |
| Criterio | 6 headers presentes en todas las respuestas |

#### 0.5 Singleton `SemaforoPlazoService` (AUD-0062, P2)

| Campo | Detalle |
|-------|---------|
| Archivos | `app/Providers/AppServiceProvider.php` |
| Acción | `$this->app->singleton(SemaforoPlazoService::class)` |
| Tests | Test identidad de instancia + conteo de queries |
| Criterio | Bandeja: 43 → ~17 queries (-60%) |

#### 0.6 Índices de BD (AUD-0064, 0065, 0066, P3)

| Campo | Detalle |
|-------|---------|
| Archivos | 3 migraciones nuevas |
| Acción | (1) `expedientes.fecha_ingreso` idx, (2) `sesiones_acceso.(exitoso, login_at)`, (3) `plazos.(estado, fecha_limite)` |
| Tests | EXPLAIN verifica uso |
| Criterio | Consultas: `type=ALL` → `type=ref/range` |

#### 0.7 UX menores (P3, ~14 ítems)

| AUD | Acción | Archivo |
|-----|--------|---------|
| 0044 | Gate enlace sidebar por rol | `layouts/app.blade.php:135-139` |
| 0045 | Renderizar `error`/`cargando` en monitoreo | `monitoreo.blade.php` |
| 0046 | Eliminar "Actualización automática" o implementar | `monitoreo.blade.php:21-23` |
| 0048 | Eliminar "¿Olvidaste?" sin ruta o implementar | `login.blade.php:274` |
| 0049 | Traducir 429; manejar no-JSON | `login.blade.php` |
| 0050 | Asignar `meta.total` | `bandeja-operador.blade.php` |
| 0051 | Activar/eliminar `errorGral` | `apertura.blade.php` |
| 0053 | Reiniciar `datosListos` en fallo | dashboards |
| 0054 | Error sorteo visible fuera del modal | `bandeja-sorteo.blade.php` |
| 0055 | Página fuera de rango tras sorteo | `bandeja-sorteo.blade.php:219` |
| 0056 | Ocultar "Inactivar" en propia cuenta | `usuarios.blade.php` |
| 0057 | Errores en logout/cargarUsuario | `layouts/app.blade.php` |
| 0058 | Eliminar `console.log` | monitoreo, feriados |
| 0059 | Eliminar `welcome.blade.php` inalcanzable | `resources/views/` |

---

### BLOQUE 1 — Máquina de estados y catálogo de actuados

> Prerequisito: Bloque 0 completado. Decisión D-6 resuelta ✅.

#### 1.1 Completar catálogo de actuados (AUD-0008, 0009, P0/P1)

| Campo | Detalle |
|-------|---------|
| Archivos | `CatalogoActuadoSeeder.php`, nueva migración para datos de catálogo |
| Acción | Añadir: 4 informes Técnico (CON/SIN resp + recomendación), 2 Jurídico (CON/SIN resp), `ACT_SUBSANACION_ACEPTADA`, `ACT_ENMIENDA`. Cada uno con `estado_destino_id` explícito |
| Tests | Test: cada actuado del catálogo tiene destino no null (excepto no-op declarados como descargos/NUREJ Hijo) |
| Criterio | Catálogo alineado con SRS `:374-413`; 0 destinos null no intencionales |

#### 1.2 Resolver huecos del grafo (AUD-0020, 0021, 0033, P0/P1)

| Campo | Detalle |
|-------|---------|
| Archivos | `ActuadoService.php`, `StoreActuadoRequest.php`, seeder |
| Acción | (a) Informe jurídico → destino `PENDIENTE_VISTO_BUENO_FINAL`, (b) `ADMITIDO → EN_PLANIFICACION` automático **con actuado registrado en cadena de custodia**, (c) validar `estado_origen_id` en endpoint genérico **dentro de transacción con `lockForUpdate`** sobre el expediente |
| Tests | **Test por cada transición** del catálogo: emitir actuado desde estado incorrecto → 422; flujo completo AC022/AC054/AC055 de inicio a cierre |
| Criterio | 0 huecos; todos los flujos llegan a cierre por arista válida |

#### 1.3 Cierre de plazos al transicionar (AUD-0030, P1)

| Campo | Detalle |
|-------|---------|
| Archivos | `ActuadoService.php`, `EvaluacionAdmisibilidadService.php`, `CierreExpedienteService.php` |
| Acción | Al transicionar fuera de una fase → plazo anterior a `CERRADO`: EVALUACION al admitir/observar, EJECUCION al cierre/reparto, SUBSANACION al aceptar subsanación |
| Tests | Test por transición: plazo anterior queda CERRADO; semáforo correcto |
| Criterio | Expedientes concluidos: semáforo VERDE; 0 falsos positivos |

#### 1.4 Subsanación: salida + protección (AUD-0031, 0032, P1)

| Campo | Detalle |
|-------|---------|
| Archivos | Seeder (nuevo actuado), `ArchivoPorAbandonoService.php` |
| Acción | (a) `ACT_SUBSANACION_ACEPTADA` (EN_SUBSANACION → EN_EVALUACION), (b) cerrar plazo SUBSANACION en toda salida, (c) condicionar archivo por abandono al estado actual EN_SUBSANACION |
| Tests | (a) Subsanación exitosa con cierre de plazo, (b) expediente fuera de EN_SUBSANACION NO archivado por cron |
| Criterio | RN-03 completa |

#### 1.5 NUREJ Hijo con especialidad destino (AUD-0039, P2)

| Campo | Detalle |
|-------|---------|
| Archivos | `DerivarNurejHijoRequest.php`, `NurejHijoService.php` |
| Acción | `via_destino` obligatorio en request; mapeo vía→reglamento validado en servidor; hijo nace con especialidad destino |
| Tests | Hijo con `via_destino=JURIDICO` → sorteo asigna a AUD_JURIDICO; 422 sin `via_destino` |
| Criterio | RN-10 `:171,:173,:174` |

#### 1.6 Inactivar estados huérfanos (D-6f)

| Campo | Detalle |
|-------|---------|
| Archivos | `CatalogoEstadoSeeder.php`, migración de flag `activo` si no existe |
| Acción | Verificar referencias históricas de `EN_INVESTIGACION`, `EN_DESCARGOS`, `CONCLUIDO` en tablas `expedientes`, `actuados`. Si 0 refs → marcar inactivo. Si hay refs → documentar y mantener |
| Criterio | 0 estados huérfanos activos sin justificación |

---

### BLOQUE 2 — Autorización, Policies y Unidades (requiere B1)

#### 2.1 Modelo de unidades organizacionales (D-5)

| Campo | Detalle |
|-------|---------|
| Archivos | Nueva migración `create_unidades_table`, modelo `Unidad`, campo `unidad_id` en `usuarios` |
| Acción | (a) Tabla `unidades` (id, codigo, nombre, activa), (b) FK `unidad_id` en `usuarios`, (c) lógica de transferencia inter-unidad: enviar expediente a otra unidad (ej. → Transparencia), recibir con posibilidad de devolver con observación |
| Tests | Test: expediente transferido a Transparencia visible solo por usuarios de esa unidad; devolución con observación funcional |
| Criterio | Aislamiento por asignación + unidad; transferencias trazables |

#### 2.2 Policies completas (AUD-0007, 0009, 0010, P0/P1)

| Campo | Detalle |
|-------|---------|
| Archivos | `ExpedientePolicy.php`, nueva `ActuadoPolicy.php`, `routes/api.php` |
| Acción | (a) Middleware `can:` en rutas críticas, (b) crear `ActuadoPolicy`, (c) resolver bypass ADMIN en `ExpedientePolicy:80-82` (AUD-0001), (d) compartimentos por unidad (D-5) |
| Tests | Cada combinación rol × recurso × acción; ADMIN sin bypass de `view` |
| Criterio | Suite 0 fallos; 0 endpoints críticos sin autorización |

#### 2.3 Auditoría de sesiones y acciones (AUD-0042, AUD-0017, 0018, P0/P1)

| Campo | Detalle |
|-------|---------|
| Archivos | Nueva migración `create_auditoria_logs_table`, modelo `AuditoriaLog`, observer/listener |
| Acción | (a) Tabla **append-only** con triggers `BEFORE UPDATE/DELETE` que rechazan, (b) observer en modelos sensibles: `Expediente`, `Actuado`, `Asignacion`, `Plazo`, `Usuario`, (c) campos: `usuario_id`, `accion`, `modelo`, `modelo_id`, `datos_anteriores`, `datos_nuevos`, `ip`, `created_at` |
| Tests | Test: cada acción crítica genera registro; UPDATE/DELETE en `auditoria_logs` lanza excepción |
| Criterio | RF-13 + RNF-07 cumplidos; tabla inmutable |

#### 2.4 Guard de inmutabilidad en modelo Actuado (D-9)

| Campo | Detalle |
|-------|---------|
| Archivos | `app/Models/Actuado.php` |
| Acción | Override `update()` y `delete()` → lanzar `ActuadoInmutableException` con mensaje amigable. Documentar que el usuario de BD NO debe tener `DROP`/`TRIGGER` |
| Tests | Test: `$actuado->update([...])` lanza excepción; `$actuado->delete()` lanza excepción |
| Criterio | Doble barrera: modelo + trigger; checklist de despliegue actualizado |

---

### BLOQUE 3 — Plazos completos (requiere B1)

#### 3.1 Feriados oficiales (D-2, AUD-0013, 0014)

| Campo | Detalle |
|-------|---------|
| Archivos | Seeder de feriados, CRUD existente |
| Acción | Poblar con feriados nacionales oficiales; parametrizar evaluación 3-5 días según complejidad (AUD-0019) |
| Criterio | Cálculo de plazos usa feriados reales verificados |

#### 3.2 Naturaleza jurisdiccional/administrativa (AUD-0024, P1)

| Campo | Detalle |
|-------|---------|
| Archivos | Migración nueva (campo `naturaleza`), `ActuadoService.php`, `StoreExpedienteRequest.php` |
| Acción | Campo `naturaleza` enum (JURISDICCIONAL/ADMINISTRATIVA) en `expedientes`; `resolveSubtipoEjecucion` lee el campo |
| Tests | Test: expediente ADMINISTRATIVA → plazo EJECUCION = 15 días |
| Criterio | RNF-05 `:147` |

#### 3.3 Zona horaria institucional (AUD-0043, P2)

| Campo | Detalle |
|-------|---------|
| Archivos | `config/app.php`, `.env` |
| Acción | `APP_TIMEZONE=America/Argentina/Buenos_Aires`; scheduler ajustado |
| Tests | Test: vencimiento a las 23:59 hora local, no 21:00 |
| Criterio | SRS `:443` |

#### 3.4 Notificaciones de vencimiento (AUD-0015, D-4, P1)

| Campo | Detalle |
|-------|---------|
| Archivos | Migración `notifications` de Laravel, nueva `PlazoProximoAVencerNotification`, job diario |
| Acción | Job diario: plazos que vencen en ≤2 días hábiles → notificación en-app al operador asignado + Encargada |
| Tests | Test: plazo próximo → notificación creada; plazo lejano → sin notificación |
| Criterio | RF-14 |

---

### BLOQUE 4 — Frontend/UI (requiere B1+B2+B3)

#### 4.1 UI evaluación de admisibilidad (AUD-0060, P1)

| Campo | Detalle |
|-------|---------|
| Archivos | Sección nueva en `detalle.blade.php` o vista dedicada |
| Acción | Checklist de requisitos por reglamento; integrar `GET /api/expedientes/{e}/requisitos` + `POST .../evaluacion` |
| Criterio | RF-04 ejecutable desde UI |

#### 4.2 UI operaciones sin interfaz (AUD-0061, 0035, P1)

| Campo | Detalle |
|-------|---------|
| Archivos | `detalle.blade.php` (secciones por estado/rol) |
| Acción | Botones contextualizados: planificación, VB, devolución, ampliación, impugnación (remitir/resolver), cierre, transparencia, descargos |
| Criterio | 11 endpoints dedicados accesibles desde UI |

#### 4.3 Bandeja Encargada (AUD-0006, 0028, P0/P1)

| Campo | Detalle |
|-------|---------|
| Archivos | Nuevo endpoint + vista |
| Acción | Listados por categoría: VB planificación, MPA, ampliaciones, impugnaciones, informes. Campos §8.1: NUREJ, partes, fecha límite, días restantes, último actuado, acción pendiente |
| Criterio | §8.1: "identificar qué tiene que hacer ahora" |

#### 4.4 Bandeja técnica con filtros (AUD-0044, P1)

| Acción | Filtros por estado en bandeja operador |

#### 4.5 Catálogo actuados contextualizado (AUD-0034, P2)

| Acción | Enviar `expediente_id` + `estado_origen_id` desde vista; validar en FormRequest; solo actuados válidos |

#### 4.6 Dashboard financiero real (AUD-0005, P1)

| Acción | Conectar con datos reales de expedientes/actuados financieros |

#### 4.7 Búsqueda NUREJ funcional (AUD-0004, 0067, P1)

| Acción | Endpoint dedicado con autocompletado por prefijo (`LIKE 'valor%'`) |

#### 4.8 Doble confirmación financiera (AUD-0011, D-10, P1)

| Campo | Detalle |
|-------|---------|
| Archivos | Nuevo middleware/servicio `ReautenticacionService`, endpoint de token temporal |
| Acción | Modal → `POST /api/reautenticar` (contraseña) → token temporal (~5 min) → incluir en header de la operación financiera → backend valida token + registra en auditoría |
| Prerequisito | **Verificar en SRS que no exige segundo usuario.** Si lo exige → escalar a workflow |
| Tests | Test: operación sin token → 403; con token expirado → 403; con token válido → éxito + registro en auditoría |
| Criterio | AUD-0011 cerrado |

#### 4.9 Transferencia inter-unidad (D-5)

| Campo | Detalle |
|-------|---------|
| Archivos | Nuevo controlador/servicio de transferencias, vistas |
| Acción | UI para enviar expediente a otra unidad (ej. Transparencia); UI para recibir/devolver con observación; notificación al destino |
| Tests | Test: transferencia crea actuado + cambia unidad; devolución con observación registrada |
| Criterio | Flujo completo inter-unidad funcional |

---

### BLOQUE 5 — Reportes y exportación (requiere B4)

#### 5.1 Reportes con filtros dinámicos (AUD-0036, 0037, P2)

| Acción | Endpoints RF-R01…R09 con filtros fecha/usuario/estado. Vista con filtros dinámicos |

#### 5.2 Exportación PDF/Excel (AUD-0012, D-3, P1)

| Campo | Detalle |
|-------|---------|
| Archivos | `composer.json` (nuevos paquetes), clases Export/PDF |
| Acción | Instalar `maatwebsite/excel` + `barryvdh/laravel-dompdf`; clase Export por reporte; botones de descarga en UI |
| Tests | Test: export genera archivo válido; PDF se genera sin error |
| Criterio | RF-09: 9/9 reportes con pantalla + Excel + PDF |

---

### BLOQUE 6 — Jobs y notificaciones (requiere B3)

#### 6.1 Jobs de vencimiento y recordatorio (AUD-0027, 0028, P2)

| Acción | Job de recordatorio de plazos próximos; `withoutOverlapping()` en scheduler |

#### 6.2 Monitoreo de jobs (AUD-0029, P2)

| Acción | Tabla `failed_jobs`; degradación controlada en el cron (catch por expediente); log explícito de errores |

#### 6.3 Degradación del cron (AUD-0042, P2)

| Acción | `VerificarVencimientoPlazosCommand`: validar dependencias al inicio; si falta catálogo → log error + continuar con `marcarVencidos()` |

---

### BLOQUE 7 — Rendimiento final (requiere B4)

#### 7.1 Dashboard admin optimizado (AUD-0063)

| Acción | Agregados en SQL puro (patrón `EncargadaDashboardService`); eliminar `get()` sin límite |

#### 7.2 Caché database (D-8)

| Acción | Migración `cache` de Laravel; cachear catálogos, feriados, suspensiones |

#### 7.3 Paginación faltante

| Acción | `paginate()` en monitoreo, dashboard admin, usuarios admin |

---

### BLOQUE 8 — Tests integrales (requiere B1-B7)

#### 8.1 Tests de autorización — cada combinación rol × recurso × acción
#### 8.2 Tests de transiciones de estado — flujo completo por vía (AC022, AC054, AC055)
#### 8.3 Tests de cálculo de plazos — feriados, suspensiones, ampliaciones, descargos
#### 8.4 Tests de compartimentos + unidades
#### 8.5 Cobertura > 40%

---

### BLOQUE 9 — Documentación y cierre (requiere B8)

#### 9.1 Documentación técnica actualizada (AUD-0038)
#### 9.2 Variables de entorno documentadas (AUD-0047)
#### 9.3 CHANGELOG.md (AUD-0048)
#### 9.4 Favicon/branding institucional (AUD-0046)
#### 9.5 Tooltips/ayuda contextual (AUD-0045)
#### 9.6 Checklist de despliegue
- `APP_DEBUG=false`
- Usuario BD sin `DROP`/`TRIGGER`
- Timezone configurado
- Feriados poblados
- Paquetes de exportación instalados

---

## 4. PRIMER BLOQUE A EJECUTAR

**BLOQUE 0 — Fundacionales.**

Orden:
```
0.1 $fillable         (P0, ~1h)
0.2 Validación         (P1, ~2h)
0.3 Roles constantes   (P1, ~30min)
0.4 Headers            (P2, ~1h)
0.5 Singleton          (P2, ~30min)
0.6 Índices            (P3, ~30min)
0.7 UX menores         (P3, ~3h)
```

---

## 5. CONTRADICCIONES DOCUMENTALES

| # | Contradicción | Documentos |
|---|--------------|-----------|
| C-1 | BACKLOG header dice 53 hallazgos; F14/F15/F17 crearon ~18 más sin actualizar | BACKLOG vs matrices |
| C-2 | AUD-0019 significado distinto en BACKLOG (N+1) vs MATRIZ_RENDIMIENTO (paginación) | BACKLOG vs MATRIZ_RENDIMIENTO |
| C-3 | AUD-002x doble asignación en algunos documentos | BACKLOG vs docs de fases |
| C-4 | Conteo de cerrados: BACKLOG (5) vs realidad (8) | BACKLOG vs matrices |
