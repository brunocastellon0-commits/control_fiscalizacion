# CONTEXTO DE INICIACIÓN Y OPERACIÓN — DESARROLLADOR: BRUNO

> **Propósito:** Este documento es autosuficiente y de lectura obligatoria para cualquier agente de IA o desarrollador que asuma el rol de **Bruno (Dominio, Seguridad y Datos)**. Contiene todas las directrices, reglas de negocio, límites de propiedad, convenciones y contratos necesarios para ejecutar sus tareas sin ambigüedades.

---

## 1. QUÉ ES EL PROYECTO

El sistema es la plataforma institucional de **Control y Fiscalización del Consejo de la Magistratura** (Distrito Cochabamba, Estado Plurinacional de Bolivia). Su función es registrar, sortear, evaluar y sustanciar denuncias e investigaciones contra funcionarios judiciales en una red intranet gubernamental aislada (sin internet público).

- **Stack Tecnológico:** Backend en **Laravel** (PHP 8.3+), base de datos **MySQL 8.0+ / 9.0+** (triggers nativos, procedimientos e índices B-Tree), frontend reactivo con **Blade + Alpine.js + Tailwind CSS**, y suite de pruebas con **Pest PHP**.
- **Carga Horaria de Bruno:** **110 horas** estimadas, distribuidas en 5 Sprints (Sprint 1: 20 h, Sprint 2: 27 h, Sprint 3: 26 h, Sprint 4: 17 h, Sprint 5: 20 h).
- **Los 3 Reglamentos Procesales:**
  1. *Acuerdo 022/2018 (Vía Técnica):* Denuncias disciplinarias contra personal judicial ordinario; 2 días hábiles para evaluación de admisibilidad, 3 días hábiles para subsanación si se observa, 2 días hábiles para planificación tras admisión; fase de ejecución: **10 días hábiles si es de naturaleza Jurisdiccional o 15 días hábiles si es de naturaleza Administrativa, con posibilidad de +5 días hábiles de ampliación en ambos casos** aprobados por la Encargada.
  2. *Acuerdo 054/2018 (Vía Auditoría Jurídica):* Control legal de fallos y plazos procesales; 5 días hábiles para evaluación de admisibilidad; fase de ejecución sujeta a fecha fija de MPA.
  3. *Acuerdo 055/2018 (Vía Auditoría Financiera):* Revisión de depósitos y valores; fase de descargos de 5 días hábiles normativos tras comunicación de hallazgos.
- **Los 5 Roles Institucionales del Sistema:**
  - `TECNICO`, `AUD_JURIDICO`, `AUD_FINANCIERO`: Operadores que evalúan y ejecutan expedientes asignados en sus respectivas bandejas.
  - `ENCARGADA`: Supervisa la unidad, sortea causas, otorga Visto Bueno a planificaciones e informes, y registra de forma exclusiva la remisión y recepción de causas hacia/desde unidades externas.
  - `ADMIN`: Administra usuarios, feriados y catálogos. **REGLA CRÍTICA:** El Administrador **NO puede ver contenido de causas, ni descargar adjuntos probatorios, ni mover expedientes, ni acceder a reportes operativos** (retorna HTTP 403 Forbidden).

---

## 2. GLOSARIO DE TÉRMINOS OPERATIVOS

- **NUREJ:** Número Único de Registro Judicial. Código correlativo e irrepetible asignado en la apertura de causa (formato `YYYY-NNNNN`, regido por RF-01).
- **NUREJ Padre / Hijo:** Cuando un informe final recomienda la intervención de otra especialidad (ej. de Técnico a Jurídico), se deriva un NUREJ Hijo (`YYYY-NNNNN-X`) que tramita de forma procesalmente independiente y con su propio reglamento (RN-10).
- **Actuado:** Hito procesal inmutable (append-only) dentro de la causa (RF-02). Posee cadena de custodia criptográfica con hash SHA-256 encadenado y triggers MySQL anti-mutación.
- **Visto Bueno (VB):** Aprobación formal emitida por la Encargada sobre cronogramas, ampliaciones, derivaciones por incompetencia o informes finales.
- **MPA:** Memorando de Planificación de Auditoría (Acuerdos 054 y 055).
- **Subsanación:** Plazo perentorio de 3 días hábiles otorgado al denunciante cuando faltan requisitos de forma (RN-03).
- **Descargos:** Periodo normativo de 5 días hábiles otorgado a los auditados en causas financieras (AC055) tras comunicar hallazgos.
- **Sorteo Ponderado:** Algoritmo determinista de ruleta basado en números aleatorios CSPRNG que balancea la carga de causas entre operadores activos según pesos históricos.
- **Semáforo de Plazos (Umbrales Reales en Código):** Verificado en `SemaforoPlazoService.php`:
  - **ROJO:** 0 o 1 días hábiles restantes (`diasRestantes <= 1`), vence hoy o el próximo día hábil.
  - **AMARILLO:** Plazos cortos (`<= 3` días) con exactamente 2 días restantes (`diasRestantes === 2`); plazos largos (`> 3` días) cuando los días restantes caen en el último tercio otorgado (`diasRestantes <= ceil(totalOtorgado / 3)`).
  - **VERDE:** Resto de días hábiles.
  - **FUERA DE PLAZO:** Marca estampada por el cron cuando `fecha_limite < hoy`. No bloquea la causa pero penaliza la puntualidad.
- **Unidad:** Catálogo institucional de destinos y orígenes de causas físicas (Transparencia, Ministerio Público, Régimen Disciplinario, etc.). Las unidades externas **NO son usuarios del sistema** (no poseen cuentas, roles ni login).
- **Remisión y Recepción Inter-Unidad (RF-05):** Flujo físico registrado exclusivamente por la Encargada. La remisión (`ACT_REMISION_UNIDAD`, CTR-04) registra la transferencia con `estado_previo_id` y transiciona la causa a `REMITIDO_UNIDAD_EXTERNA` (solo lectura, plazos congelados). La recepción (`ACT_RECEPCION_UNIDAD`, CTR-05) lee `estado_previo_id` para reincorporar la causa en su etapa procesal previa exacta (`EN_EVALUACION` o `EN_PLANIFICACION`) y reanudar plazos.
- **Distinción con Reparto Institucional:** El *Reparto Institucional* es un **cierre procesal definitivo** (`CONCLUIDO` / `ARCHIVO` / resolución final firme) y **NO usa** `REMITIDO_UNIDAD_EXTERNA`. Los estados de cierre de Reparto Institucional son inmutables y nunca deben ser inactivados por B1.6.

---

## 3. REGLAS DE NEGOCIO CRÍTICAS PARA BRUNO

Estas son las reglas del SRS oficial y auditoría que rigen estrictamente tus tareas de dominio:

| Regla | Descripción y Comportamiento Obligatorio | Tareas Vinculadas |
|---|---|---|
| **RN-01** | *Inmutabilidad de la Información (Universal):* Ningún actuado, dictamen o registro procesal puede ser editado o eliminado una vez insertado en la base de datos. Triggers MySQL abortan cualquier sentencia `UPDATE` o `DELETE` con error 45000. | B2.4, B2.5, B8.6 |
| **RF-01** | *Correlatividad y Generación de NUREJ Único (Padre):* Numeración secuencial anual inquebrantable mediante lock transaccional en tabla `nurej_sequences`. | B0.1 |
| **RF-02** | *Motor de Actuados Universal y Actuado de Enmienda:* Registro append-only estricto. Correcciones a datos del expediente graban `datos_anteriores` y `datos_nuevos` en el payload JSON del actuado inmutable `ACT_ENMIENDA` (CTR-09). | B1.7 |
| **RF-03 / RNF-01** | *Compartimentos Estancos:* Operadores solo ven y actúan sobre expedientes asignados a su bandeja activa (los usuarios no pertenecen a una unidad). Administrador bloqueado (HTTP 403) de ver expedientes, actuaciones o reportes. Causas en `REMITIDO_UNIDAD_EXTERNA` blindadas en solo lectura. | B2.0, B2.3, B2.6, B8.1, B8.5 |
| **RF-04** | *Evaluación Dinámica de Requisitos:* Selección parametrizada de admisibilidad según reglamento. | B1.1 |
| **RF-05** | *Transferencia Inter-Unidad:* Remisión física con guardado de `estado_previo_id` y Recepción física que restaura el estado original (CTR-04 y CTR-05). | B2.1, B8.7 |
| **RF-06** | *Transiciones Basadas en Actuados:* Todo movimiento entre perfiles se dispara mediante un actuado formal firmado en base de datos. | B1.1, B1.2 |
| **RN-02** | *Disparador de Plazo de Evaluación:* Apertura y sorteo inician el cómputo de días hábiles. | B1.1, B8.3 |
| **RN-03** | *Subsanación de 3 Días:* Si se emite `ACT_OBSERVACION`, el plazo es de 3 días hábiles. Si vence y el expediente sigue en `EN_SUBSANACION`, el cron ejecuta Archivo por Abandono con tolerancia por expediente. Si se subsana a tiempo, se emite `ACT_SUBSANACION_ACEPTADA` y retorna a `EN_EVALUACION`. | B1.4 |
| **RN-04** | *Planificación (2 Días):* Tras ser admitida la causa, el operador tiene 2 días hábiles para presentar cronograma/MPA. | B1.2, B1.3 |
| **RN-05** | *Ejecución de Causa (Técnico AC022):* Plazo base de **10 días hábiles para causas Jurisdiccionales o 15 días hábiles si es Administrativa**. Admite ampliación excepcional de **+5 días hábiles** aprobada por la Encargada. | B3.2, B1.3 |
| **RN-06** | *Inmutabilidad Normativa:* Toda causa iniciada bajo un reglamento específico concluye obligatoriamente bajo ese reglamento. | B1.8 |
| **RN-07** | *Control Jerárquico Estricto:* Vistos Buenos exclusivos de la Encargada. | B1.2, B2.2 |
| **RN-08** | *Impugnación de Rechazo:* 1 día hábil para remitir a Encargada (`ACT_REMITIR_IMPUGNACION`) y 3 días hábiles para que la Encargada resuelva ratificando o revocando. | B1.2 |
| **RN-09** | *Cierre y Descargos:* Comunicación de hallazgos en AC055 abre plazo de 5 días hábiles congelando la ejecución. Informes finales transicionan a `PENDIENTE_VISTO_BUENO_FINAL`. Incompetencia requiere VB y congela plazos. | B1.1, B1.2, B2.2 |
| **RN-10** | *Independencia NUREJ Hijo:* El hijo no hereda actuados, plazos ni asignaciones del padre. Permite seleccionar especialidad destino (`via_destino`). | B1.5, B8.4 |
| **RF-R01…R09** | *9 Reportes Normativos del SRS:* Consultas base SQL en JSON expuestas por Bruno en `routes/api/core.php` (CTR-08), protegidas por `ReportePolicy` (ADMIN 403). | B5.0 |
| **RNF-02** | *Integridad Transaccional:* Transacciones nativas, hashes SHA-256 encadenados y triggers MySQL. | B1.2, B2.4, B8.6 |

---

### 3.1 MÁQUINA DE ESTADOS Y TRANSICIONES VÁLIDAS DE DOMINIO

El sistema opera bajo una máquina de estados determinista gobernada por `catalogo_actuados` y `ActuadoService@registerActuado`:

```
[PENDIENTE_SORTEO] ──(Sorteo Algorítmico)──► [EN_EVALUACION]
                                                    │
       ┌───────────────────────┬────────────────────┼───────────────────────┐
       ▼                       ▼                    ▼                       ▼
 (ACT_RECHAZO)         (ACT_OBSERVACION)     (ACT_ADMISION)         (ACT_SOL_INCOMPETENCIA + VB)
       │                       │                    │                       │
       ▼                       ▼                    ▼                       ▼
  [RECHAZADO]          [EN_SUBSANACION]     [EN_PLANIFICACION]    [REMITIDO_UNIDAD_EXTERNA]
       │                       │                    │             (Solo lectura / Plazo congelado)
       ├─(Impugnación)         ├─(Subsanado)        │                       │
       │  (1d + 3d Encargada)  │  ACT_SUBSANACION   │                       │ (ACT_RECEPCION_UNIDAD)
       ▼                       ▼  _ACEPTADA         │                       ▼ (Restaura estado_previo_id)
  [ADMITIDO] ──────────────────┴────────────────────┘             [EN_EVALUACION / PLANIFICACION]
       │
       ▼ (Paso automático D-6b con Actuado Inmutable)
  [EN_PLANIFICACION] ──► [PENDIENTE_APROBACION_PLANIF] ──(VB Encargada)──► [EN_EJECUCION]
                                                                                │
                   ┌───────────────────────────────┬────────────────────────────┤
                   ▼                               ▼                            ▼
            (ACT_SOL_AMPLIACION)            (ACT_COMUNICA_DESCARGOS)     (ACT_INFORME_FINAL_*)
             + VB Encargada                  AC055 Financiero (5 días)          │
                   ▼                               ▼                            ▼
              [AMPLIADO]                     [EN_DESCARGOS]            [PENDIENTE_VB_FINAL]
             (+5 días hábiles)                                                  │
                                                                       ┌────────┴────────┐
                                                                       ▼                 ▼
                                                                 (VB Favorable)    (Observado)
                                                                       │                 │
                                                                       ▼                 ▼
                                                            [APROBADO_CON/SIN_RESP] [INFORME_OBS]
                                                                       │
                                                        ┌──────────────┴──────────────┐
                                                        ▼                             ▼
                                               [CONCLUIDO_REPARTO]               [ARCHIVADO]
```

---

### 3.2 INVARIANTES DURAS DE DOMINIO Y SEGURIDAD

1. **Concurrencia Pesimista (Lock):** Toda validación de estado de origen y emisión de actuados DEBE ejecutarse dentro de `DB::transaction()` con `$expediente = Expediente::where('id', $id)->lockForUpdate()->firstOrFail();` (D-6g).
2. **Inmutabilidad Absoluta en MySQL:** Triggers MySQL `BEFORE UPDATE` y `BEFORE DELETE` sobre tablas `actuados` y `auditoria_logs` disparan `SIGNAL SQLSTATE '45000'`.
3. **Guard en Eloquent con Exception Self-Render:** El modelo `Actuado` sobrescribe `save()` y `delete()` arrojando `ActuadoInmutableException`. Se comprobó que `ActuadoService` no realiza updates post-inserción (solo invoca `$actuado->refresh()`). La excepción responde directamente en JSON sin tocar `bootstrap/app.php` (D-9).
4. **Auditoría Fuera de Transacción:** Configurar `Gate::after` y listener `RequestHandled` en `AuditoriaServiceProvider` utilizando una conexión de base de datos dedicada y separada, garantizando que el registro de accesos no autorizados e intentos denegados (401, 403, 422) sobreviva a rollbacks de la transacción principal (D-7).
5. **Aislamiento NUREJ Padre/Hijo:** El hijo nace con su propio correlativo (`YYYY-NNNNN-X`), su propia vía y reglamento (`via_destino`), sin heredar actuados ni compartir línea de tiempo (RN-10).
6. **Bloqueo Restrictivo del Administrador:** El rol `ADMIN` recibe HTTP 403 Forbidden ante cualquier intento de ver detalles procesales, descargar fojas, mover causas o acceder a reportes (AUD-0001 y B2.0).

---

## 4. CAMBIOS RESPECTO AL SRS ORIGINAL Y DECISIONES VIGENTES

1. **Zona Horaria Institucional:** Se fijó `America/La_Paz` (UTC-4). Todos los cierres operan a las 23:59:59 hora local de Bolivia.
2. **Motor MySQL:** El sistema corre en MySQL 8.0+ / 9.0+. Prohibido escribir sintaxis o tipos exclusivos de PostgreSQL o SQLite.
3. **Mecánica de Unidades Externas (RF-05):** Las unidades externas **NO son usuarios del sistema**. La Encargada registra de manera exclusiva la Remisión (`ACT_REMISION_UNIDAD`, CTR-04) guardando `estado_previo_id`, y la Recepción (`ACT_RECEPCION_UNIDAD`, CTR-05) restaurando la etapa previa.
4. **Distinción de Reparto Institucional:** El Reparto Institucional es un **cierre procesal definitivo** (`CONCLUIDO_REPARTO`), no un estado remitido temporal.
5. **Decisión PENDIENTE (NO IMPLEMENTAR):**
   - **AUD-0011 (D-10):** Doble confirmación financiera está en pausa por análisis normativo. **Bruno no debe programar nada de esta función.**

---

## 5. MAPA DE DOCUMENTOS DE REFERENCIA

| Ruta del Archivo | Qué Contiene | Cuándo Consultarlo (Tareas) |
|---|---|---|
| [`COORDINACION.md`](COORDINACION.md) | Reglas de merge, contratos CTR y archivos calientes. | **Siempre** antes de crear ramas o PRs. |
| [`TAREAS_BRUNO.md`](TAREAS_BRUNO.md) | Tu desglose detallado de tareas con criterios y horas (110 h). | **Siempre** para verificar alcance de tarea. |
| [`docs/plan/DISENO_UNIDADES_INTER.md`](DISENO_UNIDADES_INTER.md) | Diseño corto de catálogo de unidades y transferencias. | En B0.8, B2.1, B2.2, B8.7. |
| [`docs/auditoria/MAQUINA_ESTADOS.md`](../auditoria/MAQUINA_ESTADOS.md) | Catálogo de 21 estados, 25 actuados y huecos del grafo. | En B1.1, B1.2, B1.3, B1.4, B1.6. |
| [`docs/auditoria/MATRIZ_ACTUADOS.md`](../auditoria/MATRIZ_ACTUADOS.md) | Relación de actuados por rol, reglamento y validación. | En B1.1, B1.2, B1.7. |
| [`docs/auditoria/MATRIZ_SEGURIDAD.md`](../auditoria/MATRIZ_SEGURIDAD.md) | Auditoría de los 54 endpoints y falla AUD-0001 (ADMIN). | En B2.0, B2.3, B2.4, B8.1, B8.5. |
| [`docs/auditoria/MATRIZ_PLAZOS.md`](../auditoria/MATRIZ_PLAZOS.md) | Aritmética de cálculo, días hábiles y 17 parámetros. | En B1.3, B3.2, B3.3, B8.3. |
| [`docs/auditoria/FLUJO_NUREJ.md`](../auditoria/FLUJO_NUREJ.md) | Flujo y desacoplamiento de NUREJ Padre e Hijo. | En B1.5, B8.4. |
| [`docs/auditoria/SRS_EXTRAIDO.txt`](../auditoria/SRS_EXTRAIDO.txt) | Especificación de requisitos original del sistema. | Para citar RN-xx y RF-xx exactas. |

---

## 6. PROPIEDAD ESTRICTA DE ARCHIVOS (BRUNO)

### Archivos y Directorios que BRUNO SÍ puede modificar:
- `app/Models/*.php` (todos los modelos, relaciones y `$fillable`).
- `app/Policies/*.php` (`ExpedientePolicy.php`, `ActuadoPolicy.php`, `ReportePolicy.php`).
- `app/Services/` (servicios de dominio: `ActuadoService`, `ExpedienteService`, `NurejHijoService`, `NurejGeneratorService`, `TransparenciaService`, `SorteoAlgorithmService`, `EnmiendaService`, `TransferenciaUnidadService`, `ReportesQueryService`, `ArchivoPorAbandonoService`, `PlazoCalculatorService`, `SemaforoPlazoService`).
- `app/Http/Requests/DerivarNurejHijoRequest.php` y `app/Http/Requests/StoreExpedienteRequest.php` (FormRequests nuevos del dominio).
- `app/Http/Controllers/` (controladores de dominio: `ActuadoController`, `ExpedienteController`, `EnmiendaController`, `TransferenciaUnidadController`, `ExpedienteBusquedaController`, `EncargadaSupervisionController`, `ReportesApiController`, `ReglamentoController`, `CatalogoEstadoController`, `CatalogoActuadoController`, `AdjuntoController`).
- `app/Providers/AuditoriaServiceProvider.php` y `bootstrap/providers.php`.
- `app/Observers/AuditoriaObserver.php`.
- `app/Exceptions/ActuadoInmutableException.php` (con método `render()`).
- `routes/api/core.php` y `routes/api/operativo.php`.
- `routes/api.php` (solo para estructurar el loader en B0.1).
- `database/seeders/` (`CatalogoActuadoSeeder`, `CatalogoEstadoSeeder`, `RolSeeder`, `UnidadSeeder`, y estructura inicial de `DatabaseSeeder`).
- `database/migrations/` (migraciones matutinas con prefijo `10XXXX` sin fechas del calendario fijadas a priori).
- `tests/Feature/` (tests de dominio B8.1 a B8.8).

### Archivos y Directorios que BRUNO TIENE PROHIBIDO TOCAR:
- `resources/views/**` (todas las vistas Blade y parciales son propiedad de **Brayan**).
- `app/Providers/AppServiceProvider.php` (propiedad de **Brayan**).
- `bootstrap/app.php` (propiedad de **Brayan**).
- `composer.json` y `composer.lock` (propiedad de **Brayan**).
- `.env.example` (propiedad de **Brayan**; Bruno le entrega sus variables).
- `routes/api/admin.php`, `reportes.php`, `notificaciones.php` (propiedad de **Brayan**).
- `routes/web.php` (propiedad de **Brayan**).
- `app/Http/Requests/*.php` (FormRequests existentes son de **Brayan**).
- `app/Console/Commands/VerificarVencimientoPlazosCommand.php` (propiedad de **Brayan**).
- `app/Services/CatalogoCacheService.php` (creado por **Brayan**).

---

## 7. CONTRATOS QUE BRUNO DEBE PUBLICAR PARA BRAYAN

1. **CTR-01:** `GET /api/expedientes/{e}/requisitos`
2. **CTR-02:** `POST /api/expedientes/{e}/evaluacion`
3. **CTR-03:** `GET /api/catalogo/actuados?expediente_id={e}`
4. **CTR-04:** `POST /api/expedientes/{e}/remitir-unidad` (guarda `estado_previo_id`)
5. **CTR-05:** `POST /api/expedientes/{e}/registrar-recepcion` (restaura `estado_previo_id`)
6. **CTR-06:** `GET /api/expedientes/buscar?q={query}` (Sprint 3, respeta asignación activa)
7. **CTR-07:** `GET /api/encargada/bandeja-supervision` (Sprint 3)
8. **CTR-08:** `GET /api/reportes/{codigo}` (Sprint 4, en `routes/api/core.php`, protegido por `ReportePolicy`)
9. **CTR-09:** `POST /api/expedientes/{e}/enmienda` (Sprint 3, actuado de enmienda RF-02)
10. **CTR-10:** `POST /api/expedientes/{e}/incompetencia/solicitar` y `POST .../visto-bueno` (Sprint 3, solicitud y VB Encargada)

---

## 8. CONVENCIONES TÉCNICAS Y RECOMENDACIONES

- **Ramas Git:** Nombrar estrictamente `bruno/<id_tarea>-<slug>` (ej. `bruno/B2.0-fix-admin-bypass`).
- **Migraciones:** Usar prefijo horario matutino: `YYYY_MM_DD_10XXXX_nombre.php` (sin fechas fijas del calendario).
- **Formateo:** Correr siempre `vendor/bin/pint --dirty --format agent` antes de finalizar.
- **Ejecución de Tests:**
  - Correr prueba aislada: `vendor/bin/pest tests/Feature/TuTest.php`.
  - Correr suite completa: `php artisan test --compact`.
- **Comandos Prohibidos:** **NUNCA ejecutar `migrate:fresh`** en desarrollo. Crear migraciones reversibles con `down()`.

---

## 9. DEFINICIÓN DE TERMINADO (DoD) DE BRUNO

Una tarea se considera 100% finalizada únicamente si:
1. Todos los tests nuevos escritos para la tarea pasan en verde (`PASS`).
2. La suite general de pruebas se mantiene en verde (cero regresiones).
3. `vendor/bin/pint --dirty --format agent` no reporta problemas de estilo.
4. El contrato CTR asociado (si aplica) está operativo y documentado.
5. La rama tiene rebase limpio sobre `main` sin conflictos.
6. Se entrega un reporte final listando: archivos tocados, tests ejecutados y supuestos.

---

## 10. PROMPT DE ARRANQUE PARA SESIONES DE BRUNO

```text
Hola. Actúas como el agente de IA para BRUNO (Dominio, Seguridad y Datos) en el proyecto control_fiscalizacion.
Antes de hacer nada, lee atentamente docs/plan/CONTEXTO_BRUNO.md y docs/plan/COORDINACION.md.
Hoy trabajaremos exclusivamente la tarea [ID_DE_TAREA] de docs/plan/TAREAS_BRUNO.md.
Reglas estrictas:
1. Respeta la propiedad de archivos de Bruno; no toques archivos asignados a Brayan.
2. Si requieres un archivo o vista de Brayan, detente y genera una propuesta de contrato CTR.
3. Todo código PHP debe seguir el estilo de Pint y pasar las pruebas de Pest con MySQL.
4. Al finalizar, reporta los archivos modificados, los tests ejecutados y cualquier supuesto técnico.
¿Entendido? Confirma y empezamos con la tarea indicada.
```
