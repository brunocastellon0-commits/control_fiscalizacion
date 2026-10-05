# PLAN DE TAREAS — DESARROLLADOR: BRUNO

> **Lectura Obligatoria Previa:** Antes de ejecutar cualquier tarea de este plan, es indispensable leer exhaustivamente [**`CONTEXTO_BRUNO.md`**](CONTEXTO_BRUNO.md) y [**`COORDINACION.md`**](COORDINACION.md).

**Rol:** Dominio, Seguridad y Datos (Backend, Máquina de Estados, Autorización, Plazos, Unidades y Triggers MySQL)  
**Total Horas Estimadas:** 110 horas  
**Estructura de Ramas:** `bruno/<id_tarea>-<slug_descriptivo>`  

---

## RESUMEN DE SPRINTS Y DISTRIBUCIÓN DE HORAS

| Sprint / Periodo | Enfoque Principal | Tareas Asignadas | Horas Estimadas |
|---|---|---|---|
| **Sprint 1** | Fase Cero, Eliminación Bypass ADMIN, Fundamentos, Timezone y Diseño de Unidades | B2.0, B0.1, B0.3, B0.8, B3.3, B1.8 | 20 h |
| **Sprint 2** | Máquina de Estados, Grafo Procesal, Subsanación con Try/Catch y Plazos | B1.1, B1.2, B1.3, B1.4, B1.5 | 27 h |
| **Sprint 3** | Catálogo Unidades, Incompetencia, Enmienda, Policies por Asignación y Búsqueda | B1.6, B1.7, B2.1, B2.2, B3.2, B2.3, B2.6 | 26 h |
| **Sprint 4** | Consultas Reportes SRS (CTR-08), ReportePolicy y Auditoría Append-Only | B5.0, B2.4, B2.5 | 17 h |
| **Sprint 5** | Suite Integral de Pruebas de Dominio, Hardening y Cobertura > 40% | B8.1, B8.2, B8.3, B8.4, B8.5, B8.6, B8.7, B8.8 | 20 h |

---

## DETALLE DE TAREAS POR SPRINT

### SPRINT 1: FASE CERO, ELIMINACIÓN BYPASS ADMIN, FUNDAMENTOS Y TIMEZONE

#### Tarea B2.0: Corrección del bypass de Administrador en `ExpedientePolicy` (AUD-0001)
- **ID:** B2.0
- **Título:** Eliminación del bypass indebido de ADMIN en políticas de expedientes
- **Referencia Auditoría:** AUD-0001 (P0) / RF-03
- **Descripción:** Modificar `app/Policies/ExpedientePolicy.php` para suprimir la regla que permitía al rol `ADMIN` acceder a cualquier expediente sin tener una asignación activa ni ser Encargada. Ejecutada al inicio absoluto del Sprint 1 sin dependencias para que la suite de seguridad pase a verde inmediatamente.
- **Documentos a leer:**
  - `docs/auditoria/MATRIZ_SEGURIDAD.md` (§1 "Bypass de Administrador").
  - `docs/auditoria/BACKLOG_AUDITORIA.md` (AUD-0001).
- **Archivos que toca:**
  - `app/Policies/ExpedientePolicy.php`
- **No tocar:**
  - `resources/views/**` (propiedad de Brayan).
- **Dependencias:**
  - De sí mismo: Ninguna (sin dependencias, primera tarea en ejecutarse).
  - De Brayan: Ninguna.
- **Horas Estimadas:** 3 h
- **Criterios de Aceptación:**
  - La prueba `tests/Feature/SecurityCompartimentosTest.php` pasa al 100% en verde (espera 403 Forbidden para ADMIN).
- **Rama Sugerida:** `bruno/B2.0-fix-admin-bypass`

---

#### Tarea B0.1: Reemplazo masivo de `$guarded = []` por `$fillable`, loader de rutas y trait Notifiable
- **ID:** B0.1
- **Título:** Asignación masiva segura en modelos, particionamiento de rutas y configuración de usuarios
- **Referencia Auditoría:** AUD-0016 (P0) / RNF-04 / restricciones.md §1
- **Descripción:** Auditar todos los modelos Eloquent de `app/Models/` eliminando cualquier `$guarded = []` y definiendo un array `$fillable` estricto que proteja columnas críticas (`id`, timestamps, hashes, passwords). Agregar el trait `Illuminate\Notifications\Notifiable` en el modelo `app/Models/Usuario.php` para habilitar el sistema de alertas. Estructurar `database/seeders/DatabaseSeeder.php`. Crear la estructura de subdirectorios en `routes/api/` (`core.php`, `operativo.php`, `admin.php`, `reportes.php`, `notificaciones.php`) con su loader en `routes/api.php` y crear `app/Providers/AuditoriaServiceProvider.php` (registrado en `bootstrap/providers.php`).
- **Documentos a leer:**
  - `docs/auditoria/MATRIZ_HARDENING.md` (§1 y §2).
  - [COORDINACION.md](COORDINACION.md) (§2.1 y §3).
- **Archivos que toca:**
  - `app/Models/*.php` (todos los modelos existentes)
  - `app/Models/Usuario.php` (agrega `Notifiable`)
  - `database/seeders/DatabaseSeeder.php`
  - `routes/api.php` (loader modular)
  - `routes/api/core.php` (creación)
  - `routes/api/operativo.php` (creación)
  - `routes/api/admin.php` (creación)
  - `routes/api/reportes.php` (creación)
  - `routes/api/notificaciones.php` (creación)
  - `app/Providers/AuditoriaServiceProvider.php` (creación)
  - `bootstrap/providers.php`
- **No tocar:**
  - `app/Providers/AppServiceProvider.php` (propiedad de Brayan).
  - `app/Http/Requests/*.php` (propiedad de Brayan, excepto los asignados a Bruno).
  - `resources/views/**` (propiedad de Brayan).
- **Dependencias:**
  - De sí mismo: B2.0 mergeado.
  - De Brayan: Brayan NO toca `app/Models/` hasta que esta tarea esté mergeada.
- **Horas Estimadas:** 5 h
- **Criterios de Aceptación:**
  - `grep -r '\$guarded = \[\]' app/Models/` retorna 0 coincidencias.
  - Columnas sensibles nunca están en `$fillable`.
  - `php artisan route:list` carga las rutas limpiamente sin errores.
  - Test `tests/Unit/ModelSecurityTest.php` verifica la protección de atributos.
- **Rama Sugerida:** `bruno/B0.1-fillable-models`

---

#### Tarea B0.3: Consolidación de roles como constantes en el dominio
- **ID:** B0.3
- **Título:** Refactorización de constantes de roles en el código backend de `app/`
- **Referencia Auditoría:** AUD-0008 (P1)
- **Descripción:** Consolidar las constantes en `app/Models/Rol.php` (`CODIGO_ADMIN`, `CODIGO_ENCARGADA`, `CODIGO_TECNICO`, `CODIGO_AUD_JURIDICO`, `CODIGO_AUD_FINANCIERO`) y reemplazar todas las comparaciones de cadenas de texto literales en modelos, controladores y servicios por constantes fuertemente tipadas.
  - **Dependencia obligatoria:** Depende de B0.2 de Brayan para evitar pisar controladores durante la inyección de FormRequests.
  - **Excepción única de propiedad en `app/`:** Bruno está facultado para modificar cualquier archivo de `app/` donde existan cadenas literales de roles según el grep real (`Rol.php`, `ExpedientePolicy.php`, `FeriadoPolicy.php`, `UsuarioPolicy.php`, `SorteoAlgorithmService.php`, `AmpliacionService.php`, `ArchivoPorAbandonoService.php`, `ImpugnacionService.php`, `PlanificacionService.php`, `TransparenciaService.php`, `AdminMonitoreoController.php`, `AdminDashboardController.php`, `UsuarioController.php`, `RolSeeder.php`).
  - Las vistas Blade en `resources/views/` serán saneadas por Brayan en B0.7.
- **Documentos a leer:**
  - `docs/auditoria/MAPA_FUNCIONAL.md` (§3 "Cobertura de autorización").
  - [COORDINACION.md](COORDINACION.md) (§3 "Paso 0.3").
- **Archivos que toca:**
  - Archivos listados por el grep real en `app/` y `database/seeders/RolSeeder.php`.
- **No tocar:**
  - `resources/views/**` (Brayan limpia las vistas en B0.7).
- **Dependencias:**
  - De sí mismo: B0.1 mergeado.
  - De Brayan: B0.2 mergeado.
- **Horas Estimadas:** 3 h
- **Criterios de Aceptación:**
  - `grep -rn "'ADMIN'\|'ENCARGADA'\|'TECNICO'\|'AUD_JURIDICO'\|'AUD_FINANCIERO'" app/` no arroja comparaciones desprotegidas.
  - Test unitario validando correspondencia con base de datos.
- **Rama Sugerida:** `bruno/B0.3-roles-constantes`

---

#### Tarea B0.8: Anexo técnico de cambios al SRS (Unidades + Motor MySQL)
- **ID:** B0.8
- **Título:** Formalización documental de discrepancias técnicas normativas
- **Referencia Auditoría:** Adición solicitada (criterio derivado de la auditoría) / SRS_EXTRAIDO.txt
- **Descripción:** Redactar en `docs/auditoria/ANEXO_SRS_MODIFICACIONES.md` y en [DISENO_UNIDADES_INTER.md](DISENO_UNIDADES_INTER.md) las enmiendas arquitectónicas normativas: (1) Adopción oficial de MySQL 8.x/9.x en reemplazo de PostgreSQL; (2) Redefinición del modelo de Unidades Organizacionales externas sin usuarios en sistema (catálogo de unidades, remisión física y registro de recepción exclusivo por la Encargada, transicionando a estado `REMITIDO_UNIDAD_EXTERNA` fuera de bandejas operativas normales). El diseño corto de unidades queda formalizado aquí en Sprint 1 para habilitar su implementación directa en Sprint 3.
- **Documentos a leer:**
  - `docs/auditoria/SRS_EXTRAIDO.txt` (sección arquitectónica y de infraestructura).
  - `docs/PLAN_DESARROLLO_REMEDIACION.md` (§2 Decisiones D-5).
  - [COORDINACION.md](COORDINACION.md) (§5.3).
- **Archivos que toca:**
  - `docs/auditoria/ANEXO_SRS_MODIFICACIONES.md` (creación)
  - `docs/plan/DISENO_UNIDADES_INTER.md` (creación diseño corto)
- **No tocar:**
  - `docs/auditoria/SRS_EXTRAIDO.txt` (fuente histórica inmutable).
- **Dependencias:**
  - De sí mismo: Ninguna.
  - De Brayan: Ninguna.
- **Horas Estimadas:** 3 h
- **Criterios de Aceptación:**
  - Documento técnico formal aprobado para auditorías gubernamentales y diseño corto de unidades listo para codificación.
- **Rama Sugerida:** `bruno/B0.8-anexo-srs`

---

#### Tarea B3.3: Configuración de Zona Horaria Institucional `America/La_Paz` y verificación de fechas
- **ID:** B3.3
- **Título:** Configuración de huso horario Bolivia (UTC-4) e integridad de datos
- **Referencia Auditoría:** AUD-0043 (P2) / SRS `:443`
- **Descripción:** Modificar la configuración de zona horaria institucional de UTC a `America/La_Paz` (Bolivia, UTC-4) en `config/app.php`. Entregar la variable `APP_TIMEZONE=America/La_Paz` a Brayan para `.env.example`. Diseñar un script de verificación que inspeccione los registros de `expedientes`, `actuados` y `plazos` en la base de datos para constatar que las fechas guardadas no sufran corrimiento o desfase de horario.
- **Documentos a leer:**
  - `docs/auditoria/MATRIZ_JOBS_CRON.md` (§3 "Timezone").
  - `docs/auditoria/MATRIZ_PLAZOS.md` (§1).
- **Archivos que toca:**
  - `config/app.php`
  - `tests/Feature/TimezoneBoliviaTest.php` (creación)
- **No tocar:**
  - `.env.example` (propiedad de Brayan; Bruno le entrega la variable).
- **Dependencias:**
  - De sí mismo: B0.1 mergeado.
  - De Brayan: Ninguna.
- **Horas Estimadas:** 3 h
- **Criterios de Aceptación:**
  - `php artisan config:show app.timezone` retorna `America/La_Paz`.
  - El cierre de plazos de medianoche opera a las 23:59:59 hora de Bolivia.
- **Rama Sugerida:** `bruno/B3.3-timezone-bolivia`

---

#### Tarea B1.8: Versionado y preservación de normativa procesal (RN-06)
- **ID:** B1.8
- **Título:** Garantía de inmutabilidad del reglamento procesal por expediente
- **Referencia Auditoría:** RN-06
- **Descripción:** Asegurar mediante validación de dominio que todo expediente iniciado bajo un reglamento normativo específico (AC_022_2018, AC_054_2018, AC_055_2018) tramite todos sus actuados, plazos y recursos bajo ese mismo reglamento hasta su archivo o conclusión, impidiendo mutaciones de normativa a mitad de causa.
- **Documentos a leer:**
  - `docs/auditoria/SRS_EXTRAIDO.txt` (RN-06 `:150`).
  - `docs/auditoria/MAQUINA_ESTADOS.md` (§1).
- **Archivos que toca:**
  - `app/Services/ExpedienteService.php`
  - `app/Models/Expediente.php`
  - `tests/Feature/VersionadoNormativaTest.php` (creación)
- **No tocar:**
  - `app/Http/Requests/StoreExpedienteRequest.php` (se toca en B3.2).
- **Dependencias:**
  - De sí mismo: B0.1 y B0.3 mergeados.
  - De Brayan: Ninguna.
- **Horas Estimadas:** 3 h
- **Criterios de Aceptación:**
  - No existe endpoint o servicio que permita actualizar `reglamento_id` de una causa existente.
- **Rama Sugerida:** `bruno/B1.8-versionado-normativa`

---

### SPRINT 2: MÁQUINA DE ESTADOS, GRAFO PROCESAL, SUBSANACIÓN Y PLAZOS

#### Tarea B1.1: Catálogo de actuados completo y verificación de contratos CTR-01, CTR-02 y CTR-03
- **ID:** B1.1
- **Título:** Homologación del catálogo de actuados y verificación de endpoints de admisibilidad
- **Referencia Auditoría:** AUD-0002, AUD-0030, AUD-0034 (P1) / RF-04 / Contratos CTR-01, CTR-02, CTR-03
- **Descripción:** Completar el seeder `CatalogoActuadoSeeder.php` con los actuados faltantes de la máquina de estados. Verificar y asegurar el funcionamiento estricto de los tres primeros contratos:
  1. `GET /api/expedientes/{e}/requisitos` (CTR-01).
  2. `POST /api/expedientes/{e}/evaluacion` (CTR-02).
  3. `GET /api/catalogo/actuados?expediente_id={e}` (CTR-03, validando la regla `expediente_id` inyectada por Brayan en B0.2).
- **Documentos a leer:**
  - `docs/auditoria/MATRIZ_ACTUADOS.md` (§1 "Inventario de actuados").
  - [COORDINACION.md](COORDINACION.md) (§6 Contratos CTR-01, CTR-02 y CTR-03).
- **Archivos que toca:**
  - `database/seeders/CatalogoActuadoSeeder.php`
  - `app/Http/Controllers/CatalogoActuadoController.php`
  - `app/Http/Controllers/EvaluacionAdmisibilidadController.php`
  - `app/Services/EvaluacionAdmisibilidadService.php`
  - `tests/Feature/ContratosAdmisibilidadTest.php` (creación)
- **No tocar:**
  - `app/Http/Requests/IndexCatalogoActuadosRequest.php` (modificado por Brayan en B0.2).
  - `resources/views/expedientes/detalle/evaluacion.blade.php` (propiedad de Brayan en B4.1).
- **Dependencias:**
  - De sí mismo: B0.1 y B0.3 mergeados.
  - De Brayan: B0.2 mergeado.
- **Horas Estimadas:** 6 h
- **Criterios de Aceptación:**
  - Contratos CTR-01, CTR-02 y CTR-03 responden con las estructuras y códigos de estado exactos.
- **Rama Sugerida:** `bruno/B1.1-catalogo-actuados-contratos`

---

#### Tarea B1.2: Resolución de huecos del grafo, lock pesimista y paso ADMITIDO → PLANIFICACIÓN
- **ID:** B1.2
- **Título:** Consistencia de transiciones procesales y transición automática auditada
- **Referencia Auditoría:** AUD-0033 (P1) / D-6a, D-6b, D-6g
- **Descripción:** Resolver los huecos de transiciones en `ActuadoService.php` mediante validación transaccional con bloqueo pesimista del expediente (`lockForUpdate()`). Implementar la regla de negocio donde la admisión de una causa (`ADMITIDO`) transiciona automáticamente a `EN_PLANIFICACION`, registrando este evento como un actuado formal de sistema (`ACT_PASO_PLANIFICACION`) en la cadena de custodia.
- **Documentos a leer:**
  - `docs/auditoria/MAQUINA_ESTADOS.md` (§2 "Grafo de transiciones").
  - `docs/PLAN_DESARROLLO_REMEDIACION.md` (§2 Decisión D-6b).
- **Archivos que toca:**
  - `app/Services/ActuadoService.php`
  - `app/Services/EvaluacionAdmisibilidadService.php`
  - `tests/Feature/MaquinaEstadosTransicionesTest.php` (creación)
- **No tocar:**
  - `resources/views/expedientes/detalle/acciones-operativas.blade.php`.
- **Dependencias:**
  - De sí mismo: B1.1 mergeado.
  - De Brayan: Ninguna.
- **Horas Estimadas:** 6 h
- **Criterios de Aceptación:**
  - Cero transiciones inválidas permitidas concurrentemente.
  - Paso automático `ADMITIDO` a `EN_PLANIFICACION` inserta actuado con hash encadenado.
- **Rama Sugerida:** `bruno/B1.2-grafo-pesimista`

---

#### Tarea B1.3: Cierre formal de plazos al transicionar entre fases procesales
- **ID:** B1.3
- **Título:** Cierre transaccional e idempotente de plazos activos
- **Referencia Auditoría:** AUD-0027, AUD-0028 (P1) / D-6d
- **Descripción:** Implementar en `ActuadoService.php` el cierre formal (`estado = 'CERRADO'`, `fecha_cierre = now()`) de los plazos normativos vigentes al momento en que se emite el actuado que finaliza una fase procesal (ej. emisión de resolución final, pronunciamiento sobre admisibilidad, o aprobación de informe).
- **Documentos a leer:**
  - `docs/auditoria/MATRIZ_PLAZOS.md` (§3 "Ciclo de vida").
- **Archivos que toca:**
  - `app/Services/ActuadoService.php`
  - `app/Services/CierreExpedienteService.php`
  - `tests/Feature/CierrePlazosFasesTest.php` (creación)
- **No tocar:**
  - `app/Services/PlazoCalculatorService.php`.
- **Dependencias:**
  - De sí mismo: B1.2 mergeado.
  - De Brayan: Ninguna.
- **Horas Estimadas:** 4 h
- **Criterios de Aceptación:**
  - No quedan registros en tabla `plazos` con estado `VIGENTE` para fases ya concluidas.
- **Rama Sugerida:** `bruno/B1.3-cierre-plazos`

---

#### Tarea B1.4: Flujo de subsanación: actuado `ACT_SUBSANACION_ACEPTADA` y tolerancia en abandono
- **ID:** B1.4
- **Título:** Salida exitosa de subsanación y protección transaccional en ArchivoPorAbandonoService
- **Referencia Auditoría:** AUD-0031, AUD-0032 (P1) / D-6c
- **Descripción:** Implementar el actuado `ACT_SUBSANACION_ACEPTADA` que transiciona el expediente de `EN_SUBSANACION` a `EN_EVALUACION` y cierra el plazo de subsanación. Modificar `ArchivoPorAbandonoService` para:
  1. Condicionar de forma estricta que el archivo automático por abandono SOLO se ejecute si el expediente se encuentra actualmente en estado `EN_SUBSANACION`, impidiendo el archivo accidental de causas vivas.
  2. Envolver el procesamiento de cada expediente en un bloque individual `try/catch (\Throwable $e)` dentro del bucle de `archivarVencidos()`, registrando la falla en logs sin abortar la corrida del resto de causas.
- **Documentos a leer:**
  - `docs/auditoria/FLUJO_TECNICO.md` (§4 "AUD-0031 y AUD-0032").
  - `docs/auditoria/MATRIZ_JOBS_CRON.md` (§1 "Archivo por abandono").
- **Archivos que toca:**
  - `app/Services/ActuadoService.php`
  - `app/Services/ArchivoPorAbandonoService.php`
  - `tests/Feature/SubsanacionExitoTest.php` (creación)
- **No tocar:**
  - `app/Console/Commands/VerificarVencimientoPlazosCommand.php` (propiedad de Brayan en B6.2).
- **Dependencias:**
  - De sí mismo: B1.2 y B1.3 mergeados.
  - De Brayan: Ninguna.
- **Horas Estimadas:** 5 h
- **Criterios de Aceptación:**
  - Un expediente observado puede presentar subsanación y retornar a evaluación.
  - `ArchivoPorAbandonoService` filtra obligatoriamente `expedientes.estado_actual_id = EN_SUBSANACION`.
  - Un error inesperado en un expediente no cancela el archivo de los demás.
- **Rama Sugerida:** `bruno/B1.4-subsanacion-flujo`

---

#### Tarea B1.5: Derivación de NUREJ Hijo con selección de especialidad y `DerivarNurejHijoRequest`
- **ID:** B1.5
- **Título:** Backend para derivación de causas hijas y aislamiento de actuados
- **Referencia Auditoría:** AUD-0035 (P1) / RN-10
- **Descripción:** Crear el FormRequest `App\Http\Requests\DerivarNurejHijoRequest` validando que la derivación incluya la vía o especialidad requerida (`AUD_JURIDICO`, `AUD_FINANCIERO`, `TECNICO`) y motivo. Actualizar `ExpedienteController@derivarNurejHijo` para que la causa derivada nazca formalmente con su propio NUREJ derivado (`{nurej}-1`), estado inicial y asignación en la bandeja correspondiente, sin compartir historial de actuados previos con el padre.
- **Documentos a leer:**
  - `docs/auditoria/SRS_EXTRAIDO.txt` (RN-10 `:170`).
  - `docs/auditoria/FLUJO_NUREJ.md` (§1).
- **Archivos que toca:**
  - `app/Http/Requests/DerivarNurejHijoRequest.php` (creación por Bruno)
  - `app/Http/Controllers/ExpedienteController.php`
  - `app/Services/NurejHijoService.php`
  - `tests/Feature/NurejHijoEspecialidadTest.php` (creación)
- **No tocar:**
  - `resources/views/expedientes/detalle/acciones-operativas.blade.php`.
- **Dependencias:**
  - De sí mismo: B1.2 mergeado.
  - De Brayan: Ninguna.
- **Horas Estimadas:** 6 h
- **Criterios de Aceptación:**
  - El NUREJ Hijo se genera con sufijo correlativo y especialidad requerida.
- **Rama Sugerida:** `bruno/B1.5-nurej-hijo-especialidad`

---

### SPRINT 3: UNIDADES, INCOMPETENCIA, ENMIENDA, POLICIES POR ASIGNACIÓN Y BÚSQUEDA

#### Tarea B1.6: Inactivación segura y verificada de estados huérfanos históricos
- **ID:** B1.6
- **Título:** Auditoría de integridad e inactivación de estados no operativos
- **Referencia Auditoría:** D-6f / `MAQUINA_ESTADOS.md`
- **Descripción:** Crear script/migración que verifique si los estados huérfanos sin uso normativo (`EN_INVESTIGACION`, `EN_DESCARGOS`) tienen registros vinculados en `expedientes` o `actuados`. Si tienen 0 referencias, marcar `activo = false` en `catalogo_estados`. Si tienen datos históricos, preservarlos con marca de desuso pero sin eliminar filas. **Verificación taxativa:** El *Reparto Institucional* es un cierre definitivo que requiere su estado correspondiente (`CONCLUIDO` / `ARCHIVO`); se verificará rigurosamente que B1.6 **NO inactive** ningún estado requerido por el Reparto Institucional ni por salidas finales firmes.
- **Documentos a leer:**
  - `docs/auditoria/MAQUINA_ESTADOS.md` (§1 "Inventario de estados").
  - [COORDINACION.md](COORDINACION.md) (§5.3).
- **Archivos que toca:**
  - `database/migrations/YYYY_MM_DD_10XXXX_inactivar_estados_huerfanos.php` (creación)
  - `database/seeders/CatalogoEstadoSeeder.php`
  - `tests/Feature/EstadosHuerfanosInactivacionTest.php` (creación)
- **No tocar:**
  - `app/Models/CatalogoEstado.php`.
- **Dependencias:**
  - De sí mismo: B1.1 mergeado.
  - De Brayan: Ninguna.
- **Horas Estimadas:** 2 h
- **Criterios de Aceptación:**
  - Ningún estado huérfano inactivo aparece en la selección de nuevos trámites; el estado de Reparto Institucional permanece activo y funcional.
- **Rama Sugerida:** `bruno/B1.6-inactivar-estados-huerfanos`

---

#### Tarea B1.7: Mecanismo de Actuado de Enmienda con preservación de datos (RF-02)
- **ID:** B1.7
- **Título:** Actuado de enmienda transaccional con historial anterior y posterior (CTR-09)
- **Referencia Auditoría:** RF-02 / Contrato CTR-09
- **Descripción:** Diseñar y construir el servicio y actuado de Enmienda (`ACT_ENMIENDA`) y su endpoint `POST /api/expedientes/{e}/enmienda` (CTR-09). Cuando un operador autorizado o Encargada corrige datos caratulados de una causa, el sistema debe registrar en la columna `contenido` (JSON) del actuado la captura exacta antes del cambio (`datos_anteriores`) y los nuevos valores introducidos (`datos_nuevos`), generando hash criptográfico inmutable en la cadena de custodia.
- **Documentos a leer:**
  - `docs/auditoria/SRS_EXTRAIDO.txt` (RF-02 `:107`).
  - `docs/auditoria/MATRIZ_ACTUADOS.md`.
  - [COORDINACION.md](COORDINACION.md) (§6 Contrato CTR-09).
- **Archivos que toca:**
  - `app/Services/EnmiendaService.php` (creación)
  - `app/Http/Controllers/EnmiendaController.php` (creación)
  - `routes/api/operativo.php`
  - `tests/Feature/ActuadoEnmiendaTest.php` (creación)
- **No tocar:**
  - `resources/views/expedientes/detalle/modal-enmienda.blade.php` (Brayan construye la UI en B4.2).
- **Dependencias:**
  - De sí mismo: B1.2 mergeado.
  - De Brayan: Ninguna.
- **Horas Estimadas:** 4 h
- **Criterios de Aceptación:**
  - La enmienda no sobreescribe destructivamente el historial del expediente.
  - El actuado de enmienda se inserta con hash SHA-256 encadenado y publica CTR-09.
- **Rama Sugerida:** `bruno/B1.7-actuado-enmienda`

---

#### Tarea B2.1: Catálogo de unidades, tabla transferencias_unidad, actuados de remisión/recepción y estado REMITIDO_UNIDAD_EXTERNA
- **ID:** B2.1
- **Título:** Backend para el flujo de unidades externas con trazabilidad de estado previo
- **Referencia Auditoría:** D-5 / B0.8 / RF-05 / Contratos CTR-04 y CTR-05
- **Descripción:** Construir el backend para la remisión y recepción física inter-unidad a partir de `docs/plan/DISENO_UNIDADES_INTER.md`:
  1. *Catálogo `unidades`:* Crear migración para `unidades` (`id`, `codigo`, `nombre`, `activa`, timestamps), seeder `UnidadSeeder.php` y modelo `Unidad.php`. Reutilizable para remisión de causas y para el Reparto Institucional final. Sin roles ni usuarios de unidades externas, ni columna `unidad_id` en `usuarios`.
  2. *Tabla `transferencias_unidad`:* Crear migración (`id`, `expediente_id`, `unidad_id`, `tipo` enum `REMISION`|`RECEPCION`, `actuado_id`, `motivo`, `fecha`, **`estado_previo_id`** unsignedBigInteger nullable FK a `catalogo_estados`, timestamps) y modelo `TransferenciaUnidad.php`.
  3. *Catálogo de estados y actuados:* Listar en `CatalogoActuadoSeeder` y `CatalogoEstadoSeeder` (o migración aditiva de catálogo): el nuevo estado `REMITIDO_UNIDAD_EXTERNA` y los actuados `ACT_REMISION_UNIDAD` y `ACT_RECEPCION_UNIDAD`, habilitados exclusivamente para la Encargada.
  4. *Endpoints API:* En `routes/api/core.php`:
     - `POST /api/expedientes/{e}/remitir-unidad` (CTR-04): payload `{unidad_id, motivo, adjunto?}`, guarda `estado_previo_id` en la transferencia y transiciona la causa a `REMITIDO_UNIDAD_EXTERNA`.
     - `POST /api/expedientes/{e}/registrar-recepcion` (CTR-05): payload `{unidad_origen_id, motivo, adjunto?}`, lee `estado_previo_id` de la última remisión para restaurar el expediente a su fase previa y reactivar los plazos.
- **Documentos a leer:**
  - `docs/auditoria/ANEXO_SRS_MODIFICACIONES.md` (formalizado en B0.8).
  - [DISENO_UNIDADES_INTER.md](DISENO_UNIDADES_INTER.md) (creado en B0.8).
  - [COORDINACION.md](COORDINACION.md) (§5.3 y §6 Contratos CTR-04 y CTR-05).
- **Archivos que toca:**
  - `database/migrations/YYYY_MM_DD_10XXXX_create_unidades_table.php` (creación)
  - `database/migrations/YYYY_MM_DD_10XXXX_create_transferencias_unidad_table.php` (creación)
  - `database/seeders/CatalogoActuadoSeeder.php` / `CatalogoEstadoSeeder.php`
  - `app/Models/Unidad.php` (creación)
  - `app/Models/TransferenciaUnidad.php` (creación)
  - `database/seeders/UnidadSeeder.php` (creación)
  - `app/Services/TransferenciaUnidadService.php` (creación)
  - `app/Http/Controllers/TransferenciaUnidadController.php` (creación)
  - `routes/api/core.php`
  - `tests/Feature/TransferenciaUnidadesTest.php` (creación)
- **No tocar:**
  - `resources/views/expedientes/detalle/remision-unidad.blade.php` (propiedad de Brayan en B4.9).
- **Dependencias:**
  - De sí mismo: B0.1, B0.8 y B1.2 mergeados.
  - Entrega a Brayan: Publica Contratos CTR-04 y CTR-05 para que Brayan construya la interfaz B4.9.
- **Horas Estimadas:** 4 h
- **Criterios de Aceptación:**
  - Causa transiciona a `REMITIDO_UNIDAD_EXTERNA` al remitir con CTR-04 y guarda `estado_previo_id`.
  - Recepción con CTR-05 restaura la causa a su estado procesal original exacto.
- **Rama Sugerida:** `bruno/B2.1-unidades-organizacionales`

---

#### Tarea B2.2: Redefinición del flujo de Derivación por Incompetencia inter-unidad
- **ID:** B2.2
- **Título:** Flujo de incompetencia procesal con congelamiento de plazos y visto bueno (CTR-10)
- **Referencia Auditoría:** RN-09 (`:170`) / Contrato CTR-10
- **Descripción:** Refactorizar `TransparenciaService` para alinearlo con el modelo de unidades externas:
  1. Registrar en catálogo de actuados `ACT_SOLICITUD_INCOMPETENCIA` (operador) y `ACT_VB_INCOMPETENCIA` (Encargada).
  2. Endpoints CTR-10:
     - `POST /api/expedientes/{e}/incompetencia/solicitar`: el operador solicita incompetencia y envía a la Encargada en espera de VB.
     - `POST /api/expedientes/{e}/incompetencia/visto-bueno`: la Encargada aprueba, congelando los plazos vigentes de la causa (`SUSPENDIDO`) y remitiendo a `REMITIDO_UNIDAD_EXTERNA`.
  3. Si la unidad externa devuelve el expediente, el registro de recepción por la Encargada (CTR-05) reanuda el cómputo de plazos y devuelve la causa a la bandeja del operador asignado.
- **Documentos a leer:**
  - `docs/auditoria/SRS_EXTRAIDO.txt` (RN-09 `:164`).
  - [DISENO_UNIDADES_INTER.md](DISENO_UNIDADES_INTER.md).
  - [COORDINACION.md](COORDINACION.md) (§6 Contrato CTR-10).
- **Archivos que toca:**
  - `database/seeders/CatalogoActuadoSeeder.php`
  - `app/Services/TransparenciaService.php`
  - `app/Http/Controllers/TransparenciaController.php`
  - `routes/api/operativo.php`
  - `tests/Feature/IncompetenciaInterUnidadTest.php` (creación)
- **No tocar:**
  - `resources/views/expedientes/detalle.blade.php`.
- **Dependencias:**
  - De sí mismo: B2.1 y B1.3 mergeados.
  - Entrega a Brayan: Publica Contrato CTR-10 para la UI de B4.2.
- **Horas Estimadas:** 4 h
- **Criterios de Aceptación:**
  - Solicitud de incompetencia requiere VB de la Encargada antes de remitir.
  - Plazos se congelan automáticamente durante la remisión y se reanudan al registrar recepción.
- **Rama Sugerida:** `bruno/B2.2-incompetencia-inter-unidad`

---

#### Tarea B3.2: Parametrización de Naturaleza de Causa y `StoreExpedienteRequest`
- **ID:** B3.2
- **Título:** Soporte backend de naturaleza de causa y cálculo diferencial de plazos
- **Referencia Auditoría:** AUD-0005 (P1) / CA-1
- **Descripción:** Crear migración agregando la columna enum `naturaleza` (`JURISDICCIONAL`, `ADMINISTRATIVA`) en la tabla `expedientes`. Actualizar `StoreExpedienteRequest.php` para validar este campo obligatorio en la apertura. Actualizar `PlazoCalculatorService` para aplicar la matriz diferencial: (1) Apertura vía TECNICO Jurisdiccional = 10 días hábiles; (2) Apertura vía TECNICO Administrativa = 15 días hábiles; (3) Ampliación = +5 días hábiles en ambas naturalezas.
- **Documentos a leer:**
  - `docs/auditoria/MATRIZ_PLAZOS.md` (§1 "Parámetros técnicos").
- **Archivos que toca:**
  - `database/migrations/YYYY_MM_DD_10XXXX_add_naturaleza_to_expedientes_table.php` (creación)
  - `app/Models/Expediente.php`
  - `app/Http/Requests/StoreExpedienteRequest.php` (asignado a Bruno)
  - `app/Services/ExpedienteService.php`
  - `app/Services/PlazoCalculatorService.php`
  - `tests/Feature/NaturalezaExpedientePlazoTest.php` (creación)
- **No tocar:**
  - `resources/views/expedientes/apertura.blade.php` (Brayan construye el select en B3.5).
- **Dependencias:**
  - De sí mismo: B0.1 mergeado.
  - Entrega a Brayan: Entrega el backend para que Brayan integre el selector en `apertura.blade.php` (Tarea B3.5).
- **Horas Estimadas:** 4 h
- **Criterios de Aceptación:**
  - Apertura rechaza peticiones sin `naturaleza`.
  - Los expedientes administrativos reciben 15 días hábiles normativos.
- **Rama Sugerida:** `bruno/B3.2-naturaleza-expediente`

---

#### Tarea B2.3: Policies de autorización exhaustivas por asignación activa y protección de estado REMITIDO
- **ID:** B2.3
- **Título:** Blindaje de asignación activa, restricción de ADMIN y protección de causas remitidas
- **Referencia Auditoría:** AUD-0007, AUD-0010 (P1) / RF-03, RNF-01
- **Descripción:** Implementar `ActuadoPolicy` y blindar `ExpedientePolicy` para verificar pertenencia por asignación activa:
  1. Un operador (Técnico, Jurídico, Financiero) solo puede consultar y actuar sobre expedientes que tenga asignados activamente.
  2. Las causas en estado `REMITIDO_UNIDAD_EXTERNA` son estrictamente de solo lectura; ningún operador puede emitir nuevos actuados mientras se encuentren remitidas.
  3. La Encargada dispone de permisos de supervisión distrital y registro exclusivo de remisión/recepción.
  4. El rol `ADMIN` no tiene acceso a expedientes, actuados ni adjuntos (bloqueo HTTP 403). Enlazar en `routes/api/` los middlewares `can:` en todas las rutas críticas.
- **Documentos a leer:**
  - `docs/auditoria/MATRIZ_SEGURIDAD.md` (§1 y §2).
  - [DISENO_UNIDADES_INTER.md](DISENO_UNIDADES_INTER.md).
- **Archivos que toca:**
  - `app/Policies/ExpedientePolicy.php`
  - `app/Policies/ActuadoPolicy.php` (creación)
  - `routes/api/core.php`
  - `routes/api/operativo.php`
  - `tests/Feature/ExpedientePolicyStrictTest.php` (creación)
- **No tocar:**
  - `routes/api/admin.php` o `routes/api/notificaciones.php` (propiedad de Brayan).
- **Dependencias:**
  - De sí mismo: B2.0, B2.1 y B2.2 mergeados.
  - De Brayan: Ninguna.
- **Horas Estimadas:** 4 h
- **Criterios de Aceptación:**
  - Cero endpoints operativos accesibles sin política activa.
  - Causas en `REMITIDO_UNIDAD_EXTERNA` blindadas en solo lectura.
  - Operadores no pueden acceder a causas asignadas a otros colegas.
- **Rama Sugerida:** `bruno/B2.3-policies-compartimentos`

---

#### Tarea B2.6: Endpoints de búsqueda NUREJ respetando asignación (CTR-06) y bandeja Encargada (CTR-07)
- **ID:** B2.6
- **Título:** Endpoints de búsqueda segura y supervisión para la Encargada
- **Referencia Auditoría:** AUD-0004, AUD-0009, AUD-0067 (P1) / RF-03 / Contratos CTR-06 y CTR-07
- **Descripción:** Implementar en `routes/api/core.php`:
  1. `GET /api/expedientes/buscar?q={query}` (CTR-06): Búsqueda rápida por prefijo de NUREJ que valida la asignación activa del usuario autenticado (un operador no ve causas ajenas; la Encargada ve todas; los usuarios no pertenecen a una unidad).
  2. `GET /api/encargada/bandeja-supervision` (CTR-07): Bandeja distrital paginada con filtros por categoría operativa (cronogramas pendientes de VB, MPA pendientes, informes finales pendientes, devoluciones).
- **Documentos a leer:**
  - `docs/auditoria/MATRIZ_BANDEJAS.md` (§5 "Bandeja Encargada").
  - [COORDINACION.md](COORDINACION.md) (§6 Contratos CTR-06 y CTR-07).
- **Archivos que toca:**
  - `app/Http/Controllers/ExpedienteBusquedaController.php` (creación)
  - `app/Http/Controllers/Encargada/EncargadaSupervisionController.php` (creación)
  - `app/Services/EncargadaDashboardService.php`
  - `routes/api/core.php`
  - `tests/Feature/ContratosBusquedaSupervisionTest.php` (creación)
- **No tocar:**
  - `resources/views/encargada/bandeja-supervision.blade.php` (Brayan la construye en B4.3).
  - `resources/views/layouts/buscador.blade.php` (Brayan en B4.7).
- **Dependencias:**
  - De sí mismo: B2.3 mergeado.
  - Entrega a Brayan: Publica Contratos CTR-06 y CTR-07 para que Brayan integre las vistas B4.3 y B4.7.
- **Horas Estimadas:** 4 h
- **Criterios de Aceptación:**
  - CTR-06 no devuelve coincidencias de expedientes que no pertenezcan al operador autenticado.
  - CTR-07 entrega datos paginados estructurados listos para renderizar.
- **Rama Sugerida:** `bruno/B2.6-contratos-busqueda-supervision`

---

### SPRINT 4: CONSULTAS DE REPORTES SRS (CTR-08), REPORTEPOLICY Y AUDITORÍA INMUTABLE

#### Tarea B5.0: Consultas SQL base para los 9 reportes SRS (CTR-08) y creación de ReportePolicy
- **ID:** B5.0
- **Título:** Motor de consultas agregadas optimizadas para los 9 reportes SRS y política de acceso
- **Referencia Auditoría:** AUD-0036, AUD-0037 (P2) / RF-R01…R09 / Contrato CTR-08
- **Descripción:** Construir el servicio de backend `App\Services\ReportesQueryService` y el endpoint `GET /api/reportes/{codigo}` (CTR-08) en `routes/api/core.php` al inicio del Sprint 4.
  1. Implementa consultas SQL puras y optimizadas para los 9 reportes normativos del SRS oficial:
     - RF-R01: Carga Laboral por Usuario.
     - RF-R02: Solicitudes Registradas (libro matriz digital).
     - RF-R03: Recepción Inter-Unidad.
     - RF-R04: Estado de Evaluación.
     - RF-R05: Notificaciones Pendientes.
     - RF-R06: Resoluciones Finales.
     - RF-R07: Estadístico por Vía.
     - RF-R08: Carátula Oficial.
     - RF-R09: Trazabilidad Padre-Hijo.
  2. Crear `App\Policies\ReportePolicy.php` para restringir el acceso a reportes, dashboards operativos y carátula: bloqueo estricto al rol `ADMIN` (403 Forbidden); acceso permitido a roles operativos para sus propias causas y a la Encargada para supervisión general.
- **Documentos a leer:**
  - `docs/auditoria/SRS_EXTRAIDO.txt` (RF-R01 a RF-R09 `:119-127`).
  - `docs/auditoria/MATRIZ_REPORTES.md` (§1).
  - [COORDINACION.md](COORDINACION.md) (§6 Contrato CTR-08 y §7).
- **Archivos que toca:**
  - `app/Services/ReportesQueryService.php` (creación)
  - `app/Policies/ReportePolicy.php` (creación)
  - `app/Http/Controllers/ReportesApiController.php` (creación)
  - `routes/api/core.php`
  - `tests/Feature/ReportesQueryServiceTest.php` (creación)
- **No tocar:**
  - `routes/api/reportes.php` o `resources/views/reportes/**` (propiedad de Brayan en B5.1 y B5.2).
- **Dependencias:**
  - De sí mismo: B1.2, B2.1 y B3.2 mergeados.
  - Entrega a Brayan: Publica contrato CTR-08 al inicio del Sprint 4 para que Brayan desarrolle las pantallas y exportaciones en B5.1 y B5.2.
- **Horas Estimadas:** 8 h
- **Criterios de Aceptación:**
  - Las 9 consultas retornan colecciones estructuradas listas para renderizar o exportar.
  - El rol ADMIN recibe 403 Forbidden ante cualquier consulta de reportes o dashboards operativos.
- **Rama Sugerida:** `bruno/B5.0-consultas-reportes-srs`

---

#### Tarea B2.4: Auditoría append-only con triggers MySQL, observer con Plazo e intentos denegados
- **ID:** B2.4
- **Título:** Tabla de auditoría inmutable en MySQL, observer de Plazo y log de accesos denegados
- **Referencia Auditoría:** AUD-0017, AUD-0018 (P1), AUD-0042 (P0) / D-7 / criterio derivado de la auditoría / RNF-02
- **Descripción:** Crear migración de la tabla `auditoria_logs` (`id`, `usuario_id`, `accion`, `modelo_tipo`, `modelo_id`, `datos_anteriores`, `datos_nuevos`, `ip_origen`, `user_agent`, `created_at`). Crear triggers MySQL `BEFORE UPDATE` y `BEFORE DELETE` sobre `auditoria_logs` que disparen `SIGNAL SQLSTATE '45000'` impidiendo cualquier modificación o eliminación de logs. Crear `AuditoriaObserver` en `app/Observers/` y registrarlo en `AuditoriaServiceProvider` para auditar mutaciones en `Expediente`, `Actuado`, `Asignacion`, `Usuario` y **`Plazo`**.
  - **Captura de Intentos Denegados:** En `AuditoriaServiceProvider`, configurar `Gate::after` y registrar un listener para el evento `Illuminate\Foundation\Http\Events\RequestHandled` capturando respuestas 401, 403 y 422 no autorizadas.
  - **Persistencia Aislada:** Utilizar una conexión de base de datos separada (conexión no-transaccional) para que el registro de auditoría sobreviva a cualquier `rollback` de la transacción de negocio principal.
- **Documentos a leer:**
  - `docs/auditoria/MATRIZ_SEGURIDAD.md` (§1 y §3).
  - `docs/PLAN_DESARROLLO_REMEDIACION.md` (§2 Decisión D-7).
- **Archivos que toca:**
  - `database/migrations/YYYY_MM_DD_10XXXX_create_auditoria_logs_table_and_triggers.php` (creación)
  - `app/Models/AuditoriaLog.php` (creación)
  - `app/Observers/AuditoriaObserver.php` (creación)
  - `app/Services/AuditoriaSeguridadService.php` (creación)
  - `app/Providers/AuditoriaServiceProvider.php`
  - `tests/Feature/AuditoriaInmutableTest.php` (creación)
- **No tocar:**
  - `app/Providers/AppServiceProvider.php` (propiedad de Brayan).
- **Dependencias:**
  - De sí mismo: B0.1 y B2.3 mergeados.
  - De Brayan: Ninguna.
- **Horas Estimadas:** 6 h
- **Criterios de Aceptación:**
  - Sentencias directas de `UPDATE` o `DELETE` sobre `auditoria_logs` fallan a nivel de motor MySQL.
  - Intentos de acceso denegado quedan registrados permanentemente en la base de datos de auditoría incluso si la transacción de negocio hace rollback.
- **Rama Sugerida:** `bruno/B2.4-auditoria-inmutable`

---

#### Tarea B2.5: Guard de inmutabilidad en modelo Actuado con render() propio
- **ID:** B2.5
- **Título:** Doble barrera de inmutabilidad en Eloquent para Actuados
- **Referencia Auditoría:** AUD-0040, AUD-0041 / D-9
- **Descripción:** Implementar en el modelo `App\Models\Actuado` la sobrescritura de los métodos `update()`, `save()` (cuando el modelo ya existe) y `delete()` para que arrojen de forma inmediata una excepción de dominio `ActuadoInmutableException`. Se verificó rigurosamente en el código existente que `ActuadoService` NUNCA actualiza el modelo tras insertarlo (solo invoca `$actuado->refresh()`), garantizando inocuidad para el flujo normal. La clase `ActuadoInmutableException` implementará su propio método `render(Request $request)` para responder directamente con HTTP 422/409 amigable en formato JSON.
- **Documentos a leer:**
  - `docs/auditoria/MATRIZ_HARDENING.md`.
  - [COORDINACION.md](COORDINACION.md) (§2.3).
- **Archivos que toca:**
  - `app/Models/Actuado.php`
  - `app/Exceptions/ActuadoInmutableException.php` (creación con método `render()`)
  - `tests/Feature/ActuadoGuardTest.php` (creación)
- **No tocar:**
  - `bootstrap/app.php` (propiedad de Brayan).
- **Dependencias:**
  - De sí mismo: B0.1 mergeado.
  - De Brayan: Ninguna.
- **Horas Estimadas:** 3 h
- **Criterios de Aceptación:**
  - `$actuado->delete()` arroja `ActuadoInmutableException` y responde JSON 422 limpio sin tocar `bootstrap/app.php`.
- **Rama Sugerida:** `bruno/B2.5-guard-inmutabilidad-actuado`

---

### SPRINT 5: SUITE INTEGRAL DE PRUEBAS DE DOMINIO, HARDENING Y COBERTURA

#### Tarea B8.1: Suite de pruebas de matriz de autorización (Rol × Recurso × Acción)
- **ID:** B8.1
- **Título:** Cobertura de matriz completa de permisos para los 5 roles
- **Referencia Auditoría:** AUD-0024 (testing) / RNF-01
- **Descripción:** Diseñar y codificar en `tests/Feature/MatrizAutorizacionCompletaTest.php` pruebas sistemáticas para cada una de las rutas protegidas del sistema, comprobando los 5 roles existentes del sistema (`ADMIN`, `ENCARGADA`, `TECNICO`, `AUD_JURIDICO`, `AUD_FINANCIERO`) en combinaciones positivas y negativas.
- **Documentos a leer:**
  - `docs/auditoria/MATRIZ_SEGURIDAD.md` (§1 y §2).
- **Archivos que toca:**
  - `tests/Feature/MatrizAutorizacionCompletaTest.php` (creación)
- **No tocar:**
  - `tests/Feature/FormRequestsCoverageTest.php` (propiedad de Brayan).
- **Dependencias:**
  - De sí mismo: B2.3 mergeado.
  - De Brayan: Ninguna.
- **Horas Estimadas:** 3 h
- **Criterios de Aceptación:**
  - Cobertura de permisos al 100% de los endpoints sin falsos positivos.
- **Rama Sugerida:** `bruno/B8.1-test-matriz-autorizacion`

---

#### Tarea B8.2: Suite de pruebas de transiciones completas por reglamento (AC022, AC054, AC055)
- **ID:** B8.2
- **Título:** Verificación end-to-end de los 3 reglamentos desde apertura hasta cierre
- **Referencia Auditoría:** AUD-0025 (testing)
- **Descripción:** Crear feature tests exhaustivos que ejecuten el ciclo de vida completo de una causa para cada reglamento: (1) Técnico AC022 con subsanación, ampliación e informe final; (2) Auditoría Jurídica AC054 con impugnación e informe final; (3) Auditoría Financiera AC055 con descargos e informe final.
- **Documentos a leer:**
  - `docs/auditoria/FLUJO_TECNICO.md`, `FLUJO_JURIDICO.md`, `FLUJO_FINANCIERO.md`.
- **Archivos que toca:**
  - `tests/Feature/CicloCompletoReglamentosTest.php` (creación)
- **No tocar:**
  - `tests/Feature/Ui*.php` (propiedad de Brayan).
- **Dependencias:**
  - De sí mismo: B1.2, B1.3 y B1.4 mergeados.
  - De Brayan: Ninguna.
- **Horas Estimadas:** 3 h
- **Criterios de Aceptación:**
  - Los 3 flujos normativos corren de inicio a fin en verde sin excepciones 500.
- **Rama Sugerida:** `bruno/B8.2-test-ciclos-reglamentos`

---

#### Tarea B8.3: Suite de pruebas de cálculo de plazos (Feriados Bolivia, suspensiones, horario local)
- **ID:** B8.3
- **Título:** Verificación matemática del motor de plazos con calendario boliviano
- **Referencia Auditoría:** AUD-0026 (testing) / CA-1 / SRS `:181`
- **Descripción:** Crear tests en `tests/Feature/CalculoPlazosBoliviaTest.php` que validen los 17 parámetros normativos de plazos: omisión estricta de sábados, domingos, feriados bolivianos y 14 de Septiembre en Cochabamba; cálculo de vencimiento a las 23:59:59 de La Paz; congelamiento en derivación y reanudación exacta de días restantes sin pérdida de tiempo.
- **Documentos a leer:**
  - `docs/auditoria/MATRIZ_PLAZOS.md` (§1 "Motor de cálculo" y §2 "Parámetros").
- **Archivos que toca:**
  - `tests/Feature/CalculoPlazosBoliviaTest.php` (creación)
- **No tocar:**
  - `app/Services/PlazoCalculatorService.php`.
- **Dependencias:**
  - De sí mismo: B1.3 y B3.3 mergeados.
  - De Brayan: B3.1 (Feriados cargados por Brayan) mergeado.
- **Horas Estimadas:** 3 h
- **Criterios de Aceptación:**
  - Aritmética de plazos probada con precisión matemática.
- **Rama Sugerida:** `bruno/B8.3-test-calculo-plazos`

---

#### Tarea B8.4: Suite de pruebas de aislamiento procesal Padre/Hijo
- **ID:** B8.4
- **Título:** Verificación de compartimentos e independencia de timeline NUREJ Hijo
- **Referencia Auditoría:** RN-10 (`:173`)
- **Descripción:** Programar pruebas en `tests/Feature/AislamientoNurejPadreHijoTest.php` para verificar que: (1) Las peticiones a `GET /api/expedientes/{hijo}` no retornen en su colección de actuados ningún actuado perteneciente al expediente padre; (2) La emisión de un nuevo actuado en el hijo no modifique el estado ni inserte registros en la línea de tiempo del padre.
- **Documentos a leer:**
  - `docs/auditoria/FLUJO_NUREJ.md` (§2 "Verificaciones contra SQL").
- **Archivos que toca:**
  - `tests/Feature/AislamientoNurejPadreHijoTest.php` (creación)
- **No tocar:**
  - `app/Services/NurejHijoService.php`.
- **Dependencias:**
  - De sí mismo: B1.5 mergeado.
  - De Brayan: Ninguna.
- **Horas Estimadas:** 2 h
- **Criterios de Aceptación:**
  - Aislamiento estricto de historial comprobado en base de datos.
- **Rama Sugerida:** `bruno/B8.4-test-aislamiento-hijo`

---

#### Tarea B8.5: Suite de pruebas de seguridad del rol Administrador
- **ID:** B8.5
- **Título:** Verificación de imposibilidad del Administrador para ver o mover causas
- **Referencia Auditoría:** AUD-0001 (P0) / RF-03
- **Descripción:** Implementar pruebas en `tests/Feature/AdminRestriccionOperativaTest.php` que certifiquen que un usuario con rol `ADMIN` no puede descargar adjuntos probatorios de expedientes, no puede registrar actuados, no puede sortear causas ni acceder al detalle de ningún trámite procesal ni a reportes de causas.
- **Documentos a leer:**
  - `docs/auditoria/MATRIZ_SEGURIDAD.md` (§3).
- **Archivos que toca:**
  - `tests/Feature/AdminRestriccionOperativaTest.php` (creación)
- **No tocar:**
  - `resources/views/administrador/**`.
- **Dependencias:**
  - De sí mismo: B2.0 y B2.3 mergeados.
  - De Brayan: Ninguna.
- **Horas Estimadas:** 2 h
- **Criterios de Aceptación:**
  - Todos los accesos operativos del Administrador retornan 403 Forbidden.
- **Rama Sugerida:** `bruno/B8.5-test-admin-restriccion`

---

#### Tarea B8.6: Suite de pruebas de inmutabilidad y triggers MySQL
- **ID:** B8.6
- **Título:** Pruebas de estrés y resistencia de triggers ante rollback y manipulación
- **Referencia Auditoría:** D-7, D-9 / RNF-02
- **Descripción:** Crear tests en `tests/Feature/TriggersMySqlResistenciaTest.php` que ejecuten sentencias SQL directas (`DB::statement`) simulando ataques de inyección o intentos indebidos de manipulación de registros en `actuados` y `auditoria_logs`. Verificar que el motor MySQL rechaza la operación con código de error 45000 y que la integridad de la cadena SHA-256 se preserva.
- **Documentos a leer:**
  - `docs/auditoria/CADENA_CUSTODIA.md` / `tests/Feature/CadenaCustodiaTest.php`.
- **Archivos que toca:**
  - `tests/Feature/TriggersMySqlResistenciaTest.php` (creación)
- **No tocar:**
  - `database/migrations/**`.
- **Dependencias:**
  - De sí mismo: B2.4 y B2.5 mergeados.
  - De Brayan: Ninguna.
- **Horas Estimadas:** 2 h
- **Criterios de Aceptación:**
  - Pruebas directas contra MySQL validan el bloqueo total de mutaciones no autorizadas.
- **Rama Sugerida:** `bruno/B8.6-test-triggers-resistencia`

---

#### Tarea B8.7: Suite de pruebas de causas en estado REMITIDO_UNIDAD_EXTERNA (solo lectura e inoperabilidad)
- **ID:** B8.7
- **Título:** Verificación de inoperabilidad y solo lectura de causas remitidas
- **Referencia Auditoría:** D-5 / RF-03 / RNF-01
- **Descripción:** Crear en `tests/Feature/ExpedienteRemitidoInoperableTest.php` pruebas que verifiquen que un expediente en estado `REMITIDO_UNIDAD_EXTERNA`:
  1. Es estrictamente de solo lectura para todos los operadores (Técnico, Jurídico, Financiero).
  2. Bloquea cualquier intento de emisión de actuados operativos con HTTP 403 / 422.
  3. No aparece en las bandejas operativas normales de trabajo.
  4. Solo admite la acción de registro de recepción física por parte de la Encargada.
- **Documentos a leer:**
  - [DISENO_UNIDADES_INTER.md](DISENO_UNIDADES_INTER.md).
  - `docs/auditoria/MATRIZ_SEGURIDAD.md`.
- **Archivos que toca:**
  - `tests/Feature/ExpedienteRemitidoInoperableTest.php` (creación)
- **No tocar:**
  - `app/Services/TransferenciaUnidadService.php`.
- **Dependencias:**
  - De sí mismo: B2.1 y B2.3 mergeados.
  - De Brayan: Ninguna.
- **Horas Estimadas:** 2 h
- **Criterios de Aceptación:**
  - Causa en `REMITIDO_UNIDAD_EXTERNA` confirmada como no operable por ningún usuario excepto Encargada para registrar recepción.
- **Rama Sugerida:** `bruno/B8.7-test-remitido-inoperable`

---

#### Tarea B8.8: Verificación y aseguramiento de cobertura global mayor al 40%
- **ID:** B8.8
- **Título:** Auditoría de cobertura de código, integración de CatalogoCache y pruebas complementarias
- **Referencia Auditoría:** AUD-0023 (testing)
- **Descripción:** Ejecutar la suite completa evaluando cobertura de código sobre `app/Services/`, `app/Models/` y `app/Policies/`.
  - **Integración de B7.2 (Caché en Bruno):** Bruno consume `CatalogoCacheService` (creado por Brayan en B7.2) dentro de sus controladores `ReglamentoController` y `CatalogoEstadoController`, así como en `PlazoCalculatorService` para lecturas en memoria sin consultas SQL redundantes.
  - Diseñar pruebas complementarias en `tests/Feature/CoberturaDominioTest.php` para cubrir ramas condicionales de error, casos límite de plazos y transiciones atípicas, asegurando que la cobertura global del backend supere el 40% exigido.
- **Documentos a leer:**
  - `docs/auditoria/MATRIZ_COBERTURA_FINAL.md`.
  - `docs/auditoria/PROGRESO.md`.
- **Archivos que toca:**
  - `app/Http/Controllers/ReglamentoController.php` (consumo de caché)
  - `app/Http/Controllers/CatalogoEstadoController.php` (consumo de caché)
  - `app/Services/PlazoCalculatorService.php` (consumo de caché)
  - `tests/Feature/CoberturaDominioTest.php` (creación)
- **No tocar:**
  - `tests/Feature/ConciliacionReportesTest.php` (propiedad de Brayan en B8.10).
- **Dependencias:**
  - De sí mismo: B8.1 a B8.7 mergeados.
  - De Brayan: B7.2 y B8.9 a B8.11 mergeados.
- **Horas Estimadas:** 3 h
- **Criterios de Aceptación:**
  - `php artisan test --coverage` certifica una cobertura de líneas superior al 40% en componentes de negocio.
- **Rama Sugerida:** `bruno/B8.8-cobertura-global`
