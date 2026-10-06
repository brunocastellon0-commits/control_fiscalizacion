# MATRIZ SRS → IMPLEMENTACIÓN

**Archivo:** `docs/auditoria/MATRIZ_SRS_IMPLEMENTACION.md`
**Fase:** 2 · **Fecha:** 2026-09-29
**Fuente:** `docs/auditoria/SRS_EXTRAIDO.txt` (extracción verificada del `.docx`), con números de línea como ubicación.
**Método:** cada requisito se contrastó contra código real verificado en Fases 0-1 (rutas, policies, services, migraciones, seeders, tests). No se copia estado de documentación previa.
**Columnas:** ID · Requisito · Ubicación SRS · Implementación · Archivos relacionados · Estado · Evidencia · Brecha · Acción · Prueba.

**Leyenda de Estado:** `IMPLEMENTADA` · `PARCIAL` · `FALTANTE` · `INCORRECTA` · `INCONSISTENTE` · `NO VERIFICADA` · `FUERA DE ALCANCE` · `AMBIGÜEDAD` (SRS y código divergen y no se puede decidir sin el usuario) · `DIFERENCIA TECNOLÓGICA` (SRS menciona tecnología X, se usa Y; **no es brecha funcional**).

---

## 0. Diferencias tecnológicas (NO son brechas)

| ID | Requisito | Ubicación SRS | Implementación real | Estado | Tratamiento |
| --- | --- | --- | --- | --- | --- |
| DT-01 | Backend PHP/Laravel + **PostgreSQL con JSONB** para historial de actuados | `SRS_EXTRAIDO.txt:61`, `:116` (RNF-04), `:187` | **MySQL 9.7.0 InnoDB** (verificado en Fase 0); JSON nativo de MySQL (`actuados.contenido` tipo `JSON`), sin `jsonb` | DIFERENCIA TECNOLÓGICA | El requisito funcional (metadatos procesales en JSON) se cumple con el tipo `JSON` de MySQL. No convertir en brecha. Documentar en Fase 18 |
| DT-02 | "Consultas SQL directas a la base de datos PostgreSQL" (criterio de consistencia) | `SRS_EXTRAIDO.txt:187` | BD MySQL | DIFERENCIA TECNOLÓGICA | Ídem DT-01; la consistia Dashboard/Export/SQL aplica sobre MySQL |

---

## 1. Requerimientos Funcionales (RF)

| ID | Requisito | Ubicación SRS | Implementación | Archivos relacionados | Estado | Evidencia | Brecha | Acción | Prueba |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| RF-01 | NUREJ Padre/Hijo correlativo, alfanumérico, irrepetible, no editable; base de la Carátula Oficial | `:106` | Generador con secuencia por tipo (`nurej_sequences`), FK autorreferencial `nurej_padre_id`, endpoint `POST .../nurej-hijo`; no existe ningún endpoint de edición de NUREJ (sin PUT/PATCH de `expedientes`) | `NurejGeneratorService`, `NurejHijoService`, `ExpedienteController`, `routes/api.php` | IMPLEMENTADA (carátula → RF-R08) | Route list (sin endpoint update de expediente); tests `NurejGeneratorServiceTest`, `NurejHijoTest` | Carátula oficial FALTANTE (RF-R08) | Cerrar con RF-R08 | `NurejGeneratorServiceTest` ✅ |
| RF-02 | Motor de actuados append-only; sin ediciones/borrados; corrección vía "Actuado de Enmienda" (dato original + nuevo) | `:107` | Actuados append-only con triggers que bloquean UPDATE/DELETE y cadena de hash; **pero no existe el tipo "Enmienda"** (ni endpoint, ni fila en catálogo) | `ActuadoService`, triggers `create_actuados_triggers` / `add_hash_trigger_lock`, `CatalogoActuadoSeeder` | PARCIAL | Única mención de "Enmienda" en todo el código: mensaje de error del trigger `2026_08_28_182631_create_actuados_triggers.php:23` (grep app+database+views = 1 coincidencia, verificada) | No hay actuado de Enmienda: la corrección de partes/datos no tiene flujo legal en el sistema | Implementar catálogo + flujo de Enmienda (tras aprobación) | `CadenaCustodiaTest` ✅ (inmutabilidad) — sin test de enmienda |
| RF-03 | Bandejas privadas: solo asignados; acceso ajeno = "Acceso Denegado" | `:108` | Policies con ownership (`asignacionActiva.usuario_id`), `operadorBandeja`, `SecurityCompartimentosTest` | `ExpedientePolicy`, `ExpedienteController`, `WorkstationController` | INCONSISTENTE | `ExpedientePolicy:80-82` da `view` a ADMIN sin asignación; test `SecurityCompartimentosTest:165` espera 403 y recibe 200 (falla verificada en baseline) | Bypass de ADMIN contradictorio con RF-03 y con la restricción del perfil ADMIN (`:86`) | **AUD-0001** — resolver en Fase 3 con decisión del usuario; no ajustar el test | `SecurityCompartimentosTest` 🔴 (1 fallo) |
| RF-04 | Evaluación dinámica de requisitos por reglamento, registro histórico inmutable | `:109` | `GET .../requisitos` filtra `catalogo_requisitos` por `reglamento_id` del expediente; `POST .../evaluacion` persiste `evaluaciones_admisibilidad` + motor de reglas | `EvaluacionAdmisibilidadController`, `EvaluacionAdmisibilidadService`, `CatalogoRequisito` | IMPLEMENTADA (FE pendiente: sin UI de checklist) | Route list; `EvaluacionAdmisibilidadTest` + unit | UI de checklist no verificada (Fase 14); endpoint global de catálogo → AUD-0014 | UI en Fase 14; endpoint global si SRS lo exige (Fase 6) | `EvaluacionAdmisibilidadTest` ✅ |
| RF-05 | Transferencia inter-unidad: actuado "Remisión" (origen) + "Recepción" (destino) obligatorios | `:110` | Tabla `transferencias` + modelo existen; **sin endpoints de Remisión/Recepción** (la remisión a Transparencia es otro flujo, E5-S5) | `Transferencia.Php` (AUD-0016), `TransparenciaService` (no es este flujo) | FALTANTE | Route list sin endpoints de transferencia; grep | Todo el RF-05 | Diseñar flujo (Fase posterior, con SRS) | Ninguna |
| RF-06 | Transiciones entre perfiles exclusivamente por actuados formales "firmados digitalmente" | `:111` | Toda transición de estado pasa por `ActuadoService@registerActuado` dentro de `DB::transaction`; firma = cadena de hash SHA-256 (`hash_anterior`/`hash_actuado`) | `ActuadoService`, services de transición | IMPLEMENTADA (interpretación) | 24 `DB::transaction`; hash verificado en Fase 0 | "Firma digital" entendida como cadena de hash (no hay PKI/firma electrónica); **confirmar interpretación con el usuario** si se exige firma con certificado | Preguntar en Fase 3/18 | `CadenaHashIntegrityTest`, `CadenaHashConcurrenciaTest` ✅ |

---

## 2. Requerimientos No Funcionales (RNF)

| ID | Requisito | Ubicación SRS | Implementación | Archivos relacionados | Estado | Evidencia | Brecha | Acción | Prueba |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| RNF-01 | Seguridad: contraseñas encriptadas + RBAC con aislamiento | `:113` | `Hash::make` (3 usos verificados), roles + policies + middleware `EnsureAdmin`/`auth:sanctum`, throttle en login | `Usuario`, `Rol`, policies, `LoginRequest` | IMPLEMENTADA | grep `Hash::make`=3; `AuthFeatureTest` | — | Detalle de hardening en Fase 17 | `AuthFeatureTest`, `SeguridadSesionesTest` ✅ |
| RNF-02 | Transacciones nativas + rollback; hash de seguridad en actuados | `:114` | 24 `DB::transaction`; hash encadenado; triggers bloquean UPDATE/DELETE | todos los services, triggers MySQL | IMPLEMENTADA | grep `DB::transaction`=24; triggers verificados | — | — | `CadenaCustodiaTest`, `CadenaHashConcurrenciaTest` ✅ |
| RNF-03 | Rendimiento: carga ágil de bandejas y **búsquedas** con **paginación server-side** | `:115` | **0** usos de `paginate()`/`simplePaginate()`/`cursorPaginate()` en `app/` (grep verificado); **sin rutas de búsqueda** (grep `buscar|search` en `routes/` = 0); solo `limit/take` en dashboards (`EncargadaDashboardService:71,166,191,195`) | `ExpedienteController@bandejaOperador` (devuelve conjunto completo) | FALTANTE | Grep verificado en Fase 2 | Sin paginación server-side ni búsqueda: riesgo con volumen histórico (RNF-03 lo exige) | **AUD-0018** — implementar paginación + búsqueda (Fase 15/16) | Ninguna |
| RNF-04 | Laravel + PostgreSQL/JSONB (ver DT-01) | `:116` | Laravel 13 + MySQL | `composer.json` | DIFERENCIA TECNOLÓGICA | Fase 0 | Ver DT-01 | Documentar | — |

---

## 3. Requerimientos de Salida (Reportes)

Preámbulo (`:118`): todos los reportes con filtros por rango de fechas, usuario asignado y estado; salida multiformato (pantalla, Excel `.xlsx`, PDF). **Verificación:** `composer.json` sin librerías de Excel/PDF (grep `dompdf|excel|csv|snappy|mpdf` = 0) y sin endpoints de reporte en `routes/`.

| ID | Requisito | Ubicación SRS | Implementación | Estado | Evidencia | Brecha | Acción | Prueba |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| RF-R01 | Carga laboral por usuario (pendientes por perfil) | `:119` | — | FALTANTE | Grep de rutas/controllers de reportes = 0 | Completo | AUD-0011 (Fase 12) | Ninguna |
| RF-R02 | Solicitudes registradas (libro matriz de ingresos) | `:120` | — | FALTANTE | ídem | Completo | AUD-0011 | Ninguna |
| RF-R03 | Recepción inter-unidad | `:121` | — | FALTANTE | ídem + RF-05 inexistente | Completo (depende de RF-05) | AUD-0011 + RF-05 | Ninguna |
| RF-R04 | Estado de evaluación (Admitidos/Observados/Rechazados) | `:122` | — | FALTANTE | ídem | Completo | AUD-0011 | Ninguna |
| RF-R05 | Notificaciones pendientes (plazo de subsanación) | `:123` | — | FALTANTE | ídem; **no existe sistema de notificaciones** en `app/` | Completo | AUD-0011 | Ninguna |
| RF-R06 | Resoluciones finales (Demanda/Sumariante/Archivo) | `:124` | — | FALTANTE | ídem | Completo | AUD-0011 | Ninguna |
| RF-R07 | Estadístico por vía (gráficas en Dashboard) | `:125` | Dashboard de la Encargada expone resumen (`EncargadaDashboardService`); sin gráficas ni segmentación "por vía" verificada | PARCIAL | `GET /api/encargada/dashboard` existe; sin frontend de gráficas en Fase 1 | Parcial | AUD-0011 (Fase 12 + 14) | `EncargadaDashboardTest` 🔴 (5 errores AUD-0003) |
| RF-R08 | Carátula Oficial (foja 0 del expediente, PDF) | `:126` | — | FALTANTE | Sin endpoints/PDF (grep) | Completo; RF-01 la referencia | AUD-0011 | Ninguna |
| RF-R09 | Trazabilidad Padre-Hijo (vincula recomendaciones con auditorías) | `:127` | NUREJ Padre↔Hijo existe (FK `nurej_padre_id`, `NurejHijoService`), pero sin reporte | PARCIAL | FK y endpoint `nurej-hijo` verificados | Reporte específico falta | AUD-0011 | `NurejHijoTest` ✅ (dato), sin prueba de reporte |

---

## 4. Reglas de Negocio (RN)

| ID | Requisito | Ubicación SRS | Implementación | Archivos relacionados | Estado | Evidencia | Brecha | Acción | Prueba |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| RN-01 | Inmutabilidad universal: actuados permanentes; corrección solo por Enmienda | `:131-133` | Triggers MySQL bloquean UPDATE/DELETE con mensaje que exige Enmienda; hash encadenado | `create_actuados_triggers`, `add_hash_trigger_lock`, `ActuadoService` | PARCIAL (inmutabilidad ✅ / Enmienda ❌) | Trigger `:23` menciona la Enmienda inexistente | Sin flujo de Enmienda (ver RF-02) | Implementar Enmienda | `CadenaCustodiaTest` ✅; falta test de UPDATE esperado-fallo (Fase 4) |
| RN-02 | Plazo de evaluación: Técnico 2 días; Jurídico/Financiero **3 o 5 según complejidad** | `:134-137` | `parametros_plazo`: AC022=2 ✅; AC054=**5 fijo**; AC055=**5 fijo** — no existe campo "complejidad" ni opción 3 días | `ParametroPlazoSeeder:17,23,25`, `PlazoCalculatorService` | PARCIAL | Seeder verificado línea por línea | El rango "3 a 5 según complejidad" no está parametrizable (solo 5 fijo); el caso "3" no existe | AUD-0019 — confirmar con usuario si basta 5 fijo o exige selector de complejidad | `PlazoCalculatorServiceTest` ✅ (días), sin caso "3" |
| RN-03 | Subsanación 3 días + Archivo Automático por caducidad | `:138-140` | SUBSANACION=3 días (AC022); `ArchivoPorAbandonoService` dispara actuado automático → `ARCHIVO_POR_ABANDONO` | `ParametroPlazoSeeder:18`, `ArchivoPorAbandonoService` | IMPLEMENTADA | Seeder + test | — | — | `ArchivoPorAbandonoTest` ✅ |
| RN-04 | Traba de inicio: sin VB no investiga; Técnico 2 días para Cronograma; Auditores MPA | `:141-143` | `PLANIFICACION`=2 días (AC022/054/055); `cargarPlanificacion` exige estado `EN_PLANIFICACION`; `aprobarPlanificacion` (VB) exige `PENDIENTE_VISTO_BUENO` y bloquea `EN_EJECUCION` | `PlanificacionService`, `ExpedientePolicy:169-222` | IMPLEMENTADA | Policy verificada | Para Auditores el SRS dice "sin plazo estricto" y el código pone 2 días (según tabla comparativa `:277-278` el MPA no tiene plazo fijo) — **posible desajuste** | Verificar en Fase 4/5 con el usuario (2 días MPA vs "sin plazo") | `PlanificacionTest` ✅ |
| RN-05 | Ejecución: Técnico 10/15 días + 5 ampliación; Auditores = fecha MPA aprobada | `:144-148` | `EJECUCION` 10 (JURISDICCIONAL)/15 (ADMINISTRATIVA) + `EJECUCION_AMPLIADA` 5 ✅; Auditores: límite = `fecha_limite` del plazo de ejecución creado al VB con parámetro/reglamento — **la fecha proviene del MPA cargado** (verificar en Fase 5 que se use la fecha pedida por el auditor, no un número fijo) | `ParametroPlazoSeeder`, `AmpliacionService`, `PlanificacionService` | NO VERIFICADA (parcialmente implementada) | Parámetros verificados; lógica de fecha-MPA pendiente de auditar | Confirmar en Fase 5 | Fase 5 (`MATRIZ_PLAZOS.md`) | `AmpliacionTest` ✅ (5 días); Fase 5 completa |
| RN-06 | Independencia normativa: concluir con el mismo reglamento de inicio; conservar historial de versiones | `:150-152` | `reglamentos` con columna `version` (unique `codigo+version`, migración `:18,:23`); `expedientes.reglamento_id` = FK a versión concreta | `Reglamento`, `create_reglamentos_table`, `Expediente` (guard `updating()`) | PARCIAL | Migración verificada; inmutabilidad de `reglamento_id` protegida por guard Eloquent (B1.8, 2026-10-05) | Sin evidencia de múltiples versiones cargadas ni flujo de transición normativa (historial "conservado" solo si se insertan más versiones) | Verificar en Fase 6/18 si hay 1 sola versión en BD | Ninguna |
| RN-07 | Control jerárquico: VB de la Encargada antes de salida | `:153-155` | `aprobarPlanificacion`, `aprobarVistoBuenoFinal`, `ejecutarRepartoInstitucional` todos exigen ENCARGADA + estado | `ExpedientePolicy:206-292` | IMPLEMENTADA | Policy verificada | — | — | `PlanificacionTest`, `CierreExpedienteTest` ✅ |
| RN-08 | Impugnación: operador 1 día para remitir; Encargada 3 días hábiles para resolver | `:156-158` | `IMPUGNACION_REMITIR`=1 y `IMPUGNACION_RESOLVER`=3 por AC022/054/055 (`ParametroPlazoSeeder:31-40`); `ImpugnacionService` abre/cierra esos plazos | `ImpugnacionService:30-32,49-61` | IMPLEMENTADA | Seeder + service verificados | — | — | `ImpugnacionRechazoTest` ✅ |
| RN-09 | Flujo de cierre: descargos (solo Financiero) → informe → filtro Encargada → salidas; Fase 5 Transparencia | `:159-168` | Descargos (comunicar/recibir con pausa/reanudación) ✅; VB final/reparto ✅; derivación Transparencia ✅; **informes finales sin flujo propio** (ver catálogo §5) | `DescargoFinancieroService`, `CierreExpedienteService`, `TransparenciaService` | PARCIAL | Fases verificadas | Falta emisión/flujo de Informes Finales por perfil (AUD-0008) | AUD-0008 (Fase 2/9) | `DescargoFinancieroTest` 🔴 (AUD-0002), `CierreExpedienteTest` ✅ |
| RN-10 | NUREJ Hijo: enlace de origen, independencia de actuados, salidas asíncronas | `:170-174` | `NurejHijoService` crea expediente nuevo con sus propios actuados; `nurej_padre_id` solo como enlace; filtrado de línea de tiempo pendiente de verificar | `NurejHijoService`, `Expediente` | IMPLEMENTADA (línea de tiempo: verificar Fase 11) | Tests `NurejHijoTest` ✅ | Independencia de timeline Padre/Hijo → Fase 11 | Fase 11 | `NurejHijoTest` ✅ |

---

## 5. Catálogo paramétrico de actuados (SRS `:301-446`)

Comparación 1 a 1 contra `database/seeders/CatalogoActuadoSeeder.php` (leído en Fase 1):

| Fase SRS | Actuado exigido (SRS) | Equivalente en código | Estado | Nota |
| --- | --- | --- | --- | --- |
| Ingreso | Registro y Digitalización (Técnico) | `ACT_REGISTRO_DIGITALIZACION` (seeder:53) | IMPLEMENTADA | — |
| Ingreso | Sorteo Inicial / Sorteo Derivado (Encargada) | `ACT_SORTEO_INICIAL` (seeder:54) + `ACT_CREACION_NUREJ_HIJO` (seeder:73) | IMPLEMENTADA | "Derivado" cubierto por el actuado de NUREJ Hijo |
| Evaluación | Observación (Técnico/Auditor) | `ACT_OBSERVACION` (seeder:55) | IMPLEMENTADA | Abre subsanación (RN-03) |
| Evaluación | Rechazo (Técnico/Auditor) | `ACT_RECHAZO` (seeder:57) | IMPLEMENTADA | Habilita impugnación |
| Evaluación | Admisión (Técnico/Auditor) | `ACT_ADMISION` (seeder:56) | IMPLEMENTADA | — |
| Impugnación | Resolución Ratifica / Revoca (Encargada) | `ACT_RESOLUCION_RATIFICA_RECHAZO` / `ACT_RESOLUCION_REVOCA_RECHAZO` (seeder:69-70) | IMPLEMENTADA | — |
| Planificación | Cronograma de Trabajo (Técnico) | `ACT_CRONOGRAMA_TRABAJO` (seeder:59) | IMPLEMENTADA | — |
| Planificación | MPA (Auditor Jurídico/Financiero) | `ACT_MPA` (seeder:60) | IMPLEMENTADA | — |
| Planificación | Visto Bueno a Planificación (Encargada) | `ACT_VISTO_BUENO_PLANIFICACION` (seeder:58) | IMPLEMENTADA | Arranca reloj de ejecución |
| Planificación | (implícito RF-06) Devolución por Observación | `ACT_DEVOLUCION_OBSERVACION` (seeder:61) | IMPLEMENTADA | Reabre plazo 2 días |
| Ejecución | Ampliación de Plazo (Técnico, +5) | `ACT_SOLICITAR_AMPLIACION` + `ACT_APROBAR_AMPLIACION` (seeder:64-65) | IMPLEMENTADA | El SRS lo define como 1 actuado; el código usa 2 (solicitud + aprobación). **Diferencia aceptable de modelado**, documentar |
| Descargos | Comunicación de Hallazgos (Financiero) | `ACT_COMUNICACION_HALLAZGOS` (seeder:80) | IMPLEMENTADA | Pausa reloj + sub-reloj 5 días |
| Descargos | Recepción de Descargos (Financiero) | `ACT_RECEPCION_DESCARGOS` (seeder:81) | IMPLEMENTADA | Reanuda reloj; habilita informe (validación `DescargoFinancieroService:157-168`) |
| Conclusión Técnico | **4 informes Técnico:** CON/SIN Responsabilidad × CON/SIN Recomendación de Auditoría (`:374-391`) | **0 en catálogo** (grep del seeder: ningún actuado con `rol_id` Técnico de informe) | FALTANTE | **AUD-0008** — sin informe final Técnico (AC022) |
| Conclusión Jurídico | **2 informes Jurídico:** CON/SIN Responsabilidad (`:393-402`) | **1 genérico**: `ACT_INFORME_FINAL` (seeder:62, rol AUD_JURIDICO) | PARCIAL | Falta diferenciar CON/SIN Responsabilidad → AUD-0008 |
| Conclusión Financiero | 2 informes Financiero CON/SIN Responsabilidad (`:404-413`) | `ACT_INFORME_AUDITORIA_FINANCIERA_CON/SIN_RESPONSABILIDAD` (seeder:82-83) | IMPLEMENTADA | Exigen descargos previos (RN-09) |
| Transversal | Derivación por Incompetencia (3 perfiles) | `ACT_DERIVACION_INCOMPETENCIA` (seeder:77) | IMPLEMENTADA | — |
| Cierre | Visto Bueno Final (Encargada) | `ACT_VISTO_BUENO_FINAL` (seeder:74) | IMPLEMENTADA | — |
| Cierre | Reparto Institucional con destino exacto (Encargada) | `ACT_REPARTO_INSTITUCIONAL` (seeder:75) | IMPLEMENTADA (destino: verificar Fase 9) | `-> CONCLUIDO_REMITIDO` |
| Cierre | Remisión a Transparencia (Encargada) | `ACT_REMISION_TRANSPARENCIA` (seeder:78) | IMPLEMENTADA | — |
| Automático | Archivo por Abandono (Sistema, medianoche) | `ACT_ARCHIVO_POR_ABANDONO` (seeder:66, `es_automatico=true`) | IMPLEMENTADA | `ArchivoPorAbandonoTest` ✅ |
| Automático | Alerta de Estado Fuera de Plazo (Sistema) | **no es actuado**: flag `plazos.fuera_de_plazo` + `MarcarPlazosVencidosService` + alertas en dashboards | PARCIAL | El SRS la lista como actuado automático (historial inmutable); el código la persiste como flag en `plazos`, **sin registro en la línea de tiempo de actuados** — ver CA-3 |

---

## 6. Perfil Administrador, restricciones y criterios de aceptación

| ID | Requisito | Ubicación SRS | Implementación | Estado | Evidencia | Brecha | Acción | Prueba |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| PERF-ADMIN | ADMIN: gestiona usuarios/feriados/catálogos; monitoreo de estado/ubicación; **no** registra causas, **no** ve contenido de archivos, **no** mueve trámites | `:84-86` | Gestión ✅; monitoreo ✅ (dashboard/monitoreo); **pero `ExpedientePolicy::view` da acceso completo incl. descarga de adjuntos** | AMBIGÜEDAD | Policy `:80-82`; `SecurityCompartimentosTest:165` falla; menú ADMIN sin enlace a expedientes | Bypass contradice la restricción de "ver el contenido de los archivos" y RF-03 | **AUD-0001** — Fase 3 + decisión del usuario | `SecurityCompartimentosTest` 🔴 |
| REST-LAN | Arquitectura cliente-servidor solo en LAN; **sin requerir internet público** | `:176`, `:190` | Sin integraciones externas de backend; **pero el layout carga Tailwind/FontAwesome/Alpine por CDN público** (`app.blade.php:8-10`) | PARCIAL | Fase 0/AUD-0006 | La UI depende de CDNs de Internet (contradicción con la restricción) | AUD-0006 — localizar assets (Fase 17, con aprobación) | Ninguna |
| REST-NO-DELETE | Prohibido borrado físico de expedientes y actuados | `:177` | Triggers MySQL rechazan UPDATE/DELETE en `actuados` | IMPLEMENTADA | Triggers verificados; mensaje RN-01 | Falta el test que **espere el fallo** (previsto Fase 4) | Fase 4 | `CadenaCustodiaTest` + test de fallo esperado (Fase 4) |
| CA-1 | Auditoría de plazos multimotor (omite sáb/dom/feriados; calculadora por reglamento) | `:181` | `PlazoCalculatorService` + `parametros_plazo` por AC022/054/055 + feriados | NO VERIFICADA (a fondo) | Parámetros y feriados verificados; fallo AUD-0002 abierto | Auditoría completa de reglas pendiente | Fase 4/5 + **AUD-0002** | `PlazoCalculatorServiceTest` ✅; `DescargoFinancieroTest` 🔴 |
| CA-2 | Privacidad de bandejas: acceso ajeno = siempre denegado | `:183` | Ownership + policies; 1 caso de bypass (ADMIN) | INCONSISTENTE | Ver RF-03 / AUD-0001 | Bypass ADMIN | AUD-0001 | `SecurityCompartimentosTest` 🔴 |
| CA-3 | Vencimiento: no bloquea continuidad; estampa "Fuera de Plazo" en historial y tableros con alerta permanente | `:185` | Flag `fuera_de_plazo` persistente + semáforo + alertas en dashboards de Encargada/Admin ✅; **sin actuado de alerta en el historial**; **"no bloquea continuidad" sin verificar** | PARCIAL | `MarcarPlazosVencidosService:29-31`, `SemaforoPlazoService`, dashboards | 1) sin registro en línea de tiempo; 2) continuidad NO VERIFICADA | Fases 4/7/16 | `SemaforoPenalizacionTest`, `RelojProcesualTest` ✅ (parcial) |
| CA-4 | Consistencia 100% Dashboard = Excel/PDF = SQL | `:187` | No hay reportes ni exportaciones | FALTANTE | Ver RF-R01…R09 | Todo | AUD-0011 (Fase 12) | Ninguna |
| FUERA-ALCANCE | Fase jurisdiccional/sancionatoria y exterior (sentencias, sanciones, Fiscalía) | `:52`, `:249`, `:251-253` | El sistema cierra en informes/reparto/remisión a Transparencia | FUERA DE ALCANCE | Flujo termina en `CONCLUIDO_REMITIDO`/`DERIVADO_TRANSPARENCIA` | — | No implementar | — |

---

## 5.1 Resumen de estados

| Estado | Cantidad (IDs) |
| --- | --- |
| IMPLEMENTADA | RF-01, RF-04, RF-06*, RNF-01, RNF-02, RN-03, RN-04*, RN-07, RN-08, RN-10*, REST-NO-DELETE |
| PARCIAL | RF-02, RF-05*, RNF-03†, RN-01, RN-02, RN-05*, RN-06, RN-09*, RF-R07, RF-R09, CA-3, REST-LAN, catálogo (Ampliación, Informes Jurídicos, Alerta) |
| FALTANTE | RF-R01…R06, RF-R08, informes Técnico (×4), CA-4 |
| INCONSISTENTE / AMBIGÜEDAD | RF-03, CA-2, PERF-ADMIN (= AUD-0001) |
| NO VERIFICADA | RN-05 (fecha MPA), CA-1 (a fondo) |
| DIFERENCIA TECNOLÓGICA | DT-01, DT-02, RNF-04 |
| FUERA DE ALCANCE | FUERA-ALCANCE |

`*` con matices en la fila · `†` RNF-03 marcado FALTANTE en su fila (sin paginación/búsqueda)

**Nuevos hallazgos de Fase 2 → `BACKLOG_AUDITORIA.md`:** AUD-0018 (RNF-03 sin paginación ni búsqueda), AUD-0019 (RN-02 rango 3-5 días no parametrizable).
