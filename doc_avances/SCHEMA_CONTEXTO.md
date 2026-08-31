# SCHEMA_CONTEXTO — Referencia Técnica Canónica

> Documento de referencia del esquema real de la base de datos `control_fiscalizacion`
> (MySQL 8.0+, InnoDB, utf8mb4). Fuente de verdad: el script SQL y las migraciones.
> **Regla:** adaptar el software al esquema, nunca al revés. No asumir columnas/relaciones.

## Patrón arquitectónico

- **Append-Only Ledger / Event Sourcing:** los `actuados` son inmutables (no UPDATE/DELETE).
- La transición de estado, bandeja y plazo siempre nace de un `actuado` (unidad atómica de verdad legal).
- Hash encadenado tipo "blockchain" calculado por **trigger de MySQL** (no en PHP).
- Business rules (RN-01..RN-10) documentadas al final.

## Tablas (columna real ↔ modelo Eloquent)

Cada tabla lista sus columnas reales. El `$table` de cada modelo ya está alineado.

### roles (modelo `Rol`)
`id, codigo (UNIQUE), nombre, descripcion`
- códigos: ENCARGADA, TECNICO, AUD_JURIDICO, AUD_FINANCIERO, ADMIN

### usuarios (modelo `Usuario`)
`id, ci (UNIQUE), nombres, apellidos, cargo, username (UNIQUE), password_hash, rol_id, activo, created_at, updated_at`
- `rol_id` FK → roles. `getAuthPassword()` devuelve `password_hash`.

### sesiones_acceso (modelo `SesionAcceso`)
`id, usuario_id, ip_origen, login_at, logout_at, exitoso`

### reglamentos (modelo `Reglamento`)
`id, codigo, nombre, version, vigente_desde, vigente_hasta, activo`
- Único `(codigo, version)`. RN-06: expediente anclado a una fila, no al código genérico.

### catalogo_estados (modelo `CatalogoEstado`)
`id, codigo (UNIQUE), nombre, estado_padre_id, es_final`
- `estado_padre_id` FK autorreferente → subestados (ej. OBSERVADO hijo de EN_EVALUACION).
- `es_final=true`: CONCLUIDO, ARCHIVO_DEFINITIVO.

### catalogo_actuados (modelo `CatalogoActuado`)
`id, codigo (UNIQUE), nombre, fase, rol_id, reglamento_id, estado_origen_id, estado_destino_id, es_automatico, requiere_adjunto, descripcion`
- FKs: rol_id→roles, reglamento_id→reglamentos, estado_origen_id/estado_destino_id→catalogo_estados (nullable).
- **NO tiene columna de plazo.** El vínculo actuado→plazo se resuelve en PHP (`ActuadoService::MAPA_TIPO_PLAZO`).

### catalogo_requisitos (modelo `CatalogoRequisito`)
`id, reglamento_id, descripcion, orden, activo`

### feriados (modelo `Feriado`)
`id, fecha (UNIQUE), descripcion, ambito (NACIONAL/DEPARTAMENTAL)`

### suspensiones_plazo (modelo `SuspensionPlazo`)
`id, fecha_inicio, fecha_fin, motivo, creado_por, created_at`
- `created_at` es DEFAULT CURRENT_TIMESTAMP (no insertar manualmente).

### parametros_plazo (modelo `ParametroPlazo`)
`id, reglamento_id, tipo_plazo, subtipo, dias_habiles, base_legal, activo`
- Único `(reglamento_id, tipo_plazo, subtipo)`.
- `tipo_plazo`: EVALUACION, SUBSANACION, PLANIFICACION, EJECUCION, DESCARGOS, AMPLIACION.
- `subtipo`: JURISDICCIONAL / ADMINISTRATIVA (para EJECUCION 10/15 días).

### expedientes (modelo `Expediente`)
`id, nurej_code (UNIQUE), nurej_padre_id, via, reglamento_id, estado_actual_id, resumen_hechos, fecha_ingreso, creado_por, created_at`
- `nurej_padre_id` FK autorreferente (Padre-Hijo, solo enlace, no herencia — RN-10).
- `via`: TECNICO / JURIDICO / FINANCIERO. `reglamento_id` fijo al nacer (RN-06).

### partes (modelo `Parte`)
`id, expediente_id, tipo, nombre_completo, documento_identidad, cargo_institucion, actuado_origen_id, vigente_desde, vigente_hasta, es_version_actual`
- Versionado append-only (RF-02): corregir = cerrar vieja + insertar nueva.

### actuados (modelo `Actuado`) — TABLA CRÍTICA
`id, expediente_id, catalogo_actuado_id, usuario_id, fecha_hora, estado_anterior_id, estado_nuevo_id, contenido (JSON), actuado_referencia_id, ip_origen, hash_actuado, hash_anterior`
- **`hash_actuado`/`hash_anterior` los calcula el trigger** `trg_actuados_hash_before_insert`. NO insertar manualmente ni en `$fillable`.
- `fecha_hora` es DEFAULT CURRENT_TIMESTAMP.
- Columnas que la IA inventó y NO existen: `usuario_emisor_id`, `descripcion`, `hash_integridad`, `metadatos`. Usar `usuario_id`, `contenido` (JSON), `hash_actuado`.
- Inmutable: trigger bloquea UPDATE/DELETE.
- El `contenido` JSON lleva la descripción y metadatos flexibles (`contenido['descripcion']`).

### adjuntos (modelo `Adjunto`)
`id, actuado_id, nombre_original, ruta_almacenamiento, hash_sha256, mime_type, tamanio_bytes, subido_por, subido_at`

### asignaciones (modelo `Asignacion`)
`id, expediente_id, usuario_id, rol_id, actuado_origen_id, fecha_asignacion, activa`
- Una sola bandeja activa por expediente (índice único parcial WHERE activa=TRUE).
- Columnas que la IA inventó: `fecha_inicio`/`fecha_fin`. Real: `fecha_asignacion`.

### plazos (modelo `Plazo`) — TABLA CRÍTICA
`id, expediente_id, tipo_plazo, parametro_plazo_id, dias_habiles_otorgados, fecha_inicio, fecha_limite, estado, fecha_pausa, fecha_reanudacion, fuera_de_plazo, actuado_disparador_id, actuado_cierre_id`
- `estado`: VIGENTE / PAUSADO / CUMPLIDO / VENCIDO.
- FKs a parametros_plazo (nullable → plazos dinámicos MPA) y a actuados (disparador/cierre).
- Columnas que la IA inventó: `actuado_origen_id`, `dias_habiles`, `fecha_vencimiento`. Real: `actuado_disparador_id`, `dias_habiles_otorgados`, `fecha_limite`.

### evaluaciones_admisibilidad (modelo `EvaluacionAdmisibilidad`)
`id, expediente_id, requisito_id, cumple, actuado_id, fecha`

### impugnaciones (modelo `Impugnacion`)
`id, expediente_id, actuado_rechazo_id, fecha_presentacion, fecha_limite_resolucion, resultado, actuado_resolucion_id`
- `resultado`: PENDIENTE / RATIFICA / REVOCA.

### transferencias (modelo `Transferencia`)
`id, expediente_id, unidad_destino, actuado_remision_id, actuado_recepcion_id, estado`

## Trigger de hash (MySQL) — no replicar en PHP

`trg_actuados_hash_before_insert` calcula:
```
hash_anterior = último hash_actuado del mismo expediente
hash_actuado = SHA2(concat(hash_anterior, expediente_id, catalogo_actuado_id, usuario_id, fecha_hora, contenido))
```

## Cómo se decide el plazo de un actuado (PHP, no BD)

`ActuadoService::MAPA_TIPO_PLAZO` (codigo del actuado → tipo_plazo):

| codigo actuado | tipo_plazo | subtipo |
|---|---|---|
| ACT_SORTEO_INICIAL | EVALUACION | - |
| ACT_OBSERVACION | SUBSANACION | - |
| ACT_ADMISION | EJECUCION | JURIDICO→JURISDICCIONAL, otro→ADMINISTRATIVA |

Si no está en el mapa → no abre plazo. Fallback a `subtipo=NULL` si no hay coincidencia por subtipo.

## Reglas de negocio resumidas

- **RN-01** Inmutabilidad universal; actuados permanentes y auditables.
- **RN-02** Evaluación Técnico: 2 días hábiles.
- **RN-03** Subsanación: 3 días hábiles.
- **RN-04** Planificación (Auditor): según MPA (dinámico).
- **RN-05** Ejecución Técnico: 10 (Jurisdiccional) / 15 (Administrativa).
- **RN-06** Independencia normativa: expediente anclado a la versión de reglamento al nacer.
- **RN-08** Impugnación de rechazo: 1 día remisión + 3 días resolución (Art. 25, Ac. 022).
- **RN-09** Fase de Descargos (Financiero): pausa reloj por 5 días hábiles.
- **RN-10** NUREJ Padre-Hijo: solo enlace de origen, no herencia de datos.
- **RF-01** NUREJ irrepetible. **RF-02** Append-only + enmiendas. **RF-03** Bandejas privadas. **RF-04** Evaluación dinámica de requisitos. **RF-05** Transferencias (remisión+recepción).
