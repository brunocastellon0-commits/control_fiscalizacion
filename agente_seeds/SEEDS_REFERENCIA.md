# Seeders de Catálogos y Datos Base

Referencia consolidada de los seeders generados para desbloquear el desarrollo
y pruebas del sistema (Laravel 11 + MySQL).

## Ubicación

Todos los seeders funcionales viven en `database/seeders/` (requisito de
`php artisan db:seed`). Este directorio `agente_seeds/` solo contiene la
documentación/referencia de los mismos.

## Orden de ejecución (DatabaseSeeder)

Respetado según las Foreign Keys:

1. `RolSeeder` — sin dependencias
2. `CatalogoEstadoSeeder` — auto-referencia interna (padre/hijo)
3. `ReglamentoSeeder` — sin dependencias
4. `CatalogoRequisitoSeeder` — requiere `Reglamento`
5. `ParametroPlazoSeeder` — requiere `Reglamento`
6. `FeriadoSeeder` — sin dependencias
7. `UsuarioSeeder` — requiere `Rol`
8. `CatalogoActuadoSeeder` — requiere `Rol` y `CatalogoEstado`

## 1. RolSeeder
Tabla `roles`. Idempotencia: `codigo` (único).

| codigo | nombre | descripcion |
|---|---|---|
| ENCARGADA | Encargada | Responsable de la unidad de fiscalización |
| TECNICO | Técnico | Técnico de fiscalización |
| AUD_JURIDICO | Auditor Jurídico | Auditoría jurídica |
| AUD_FINANCIERO | Auditor Financiero | Auditoría financiera |
| ADMIN | Administrador | Administración del sistema |

## 2. CatalogoEstadoSeeder
Tabla `catalogo_estados`. Idempotencia: `codigo` (único). Los subestados
OBSERVADO y RECHAZADO se vinculan a EN_EVALUACION vía `estado_padre_id`
(se resuelven en una segunda pasada por `codigo`).

| codigo | es_final | padre |
|---|---|---|
| PENDIENTE_SORTEO | false | - |
| EN_EVALUACION | false | - |
| OBSERVADO | false | EN_EVALUACION |
| RECHAZADO | false | EN_EVALUACION |
| ADMITIDO | false | - |
| EN_INVESTIGACION | false | - |
| EN_DESCARGOS | false | - |
| CONCLUIDO | **true** | - |
| ARCHIVO_DEFINITIVO | **true** | - |

## 3. ReglamentoSeeder
Tabla `reglamentos`. Idempotencia: `[codigo, version]` (única).

| codigo | version | vigente_desde | activo |
|---|---|---|---|
| AC_022_2018 | 1.0 | 2018-05-15 | true |
| AC_054_2018 | 1.0 | 2018-09-01 | true |
| AC_055_2018 | 1.0 | 2018-09-01 | true |

## 4. CatalogoRequisitoSeeder
Tabla `catalogo_requisitos`. Idempotencia: `[reglamento_id, descripcion]`.
Requiere que los reglamentos existan (se resuelven por `codigo`).
- AC_022_2018: Formulario de denuncia, Documento de identidad, Pruebas documentales
- AC_054_2018: Declaración jurada de ingresos, Comprobantes de respaldo
- AC_055_2018: Escrito de descargos, Pruebas de descargo

## 5. ParametroPlazoSeeder
Tabla `parametros_plazo`. Idempotencia: `[reglamento_id, tipo_plazo, subtipo]`.

| reglamento | tipo_plazo | dias_habiles |
|---|---|---|
| AC_022_2018 | EVALUACION | 2 |
| AC_022_2018 | SUBSANACION | 3 |
| AC_022_2018 | EJECUCION_JURISDICCIONAL | 10 |
| AC_022_2018 | EJECUCION_ADMINISTRATIVA | 15 |
| AC_054_2018 | EVALUACION | 5 |
| AC_055_2018 | EVALUACION | 5 |
| AC_055_2018 | DESCARGOS | 5 |

## 6. FeriadoSeeder
Tabla `feriados`. Idempotencia: `fecha` (única). 5 feriados de referencia
para cálculo de días hábiles (2026).

## 7. UsuarioSeeder
Tabla `usuarios`. Idempotencia: `username` (único). Requiere `Rol`.
Un usuario por rol, contraseña `password123` hasheada con bcrypt (`Hash::make`).

| username | rol | cargo |
|---|---|---|
| encargada | ENCARGADA | Encargada |
| tecnico | TECNICO | Técnico |
| aud_juridico | AUD_JURIDICO | Auditor Jurídico |
| aud_financiero | AUD_FINANCIERO | Auditor Financiero |
| admin | ADMIN | Administrador |

## 8. CatalogoActuadoSeeder
Tabla `catalogo_actuados`. Idempotencia: `codigo` (único). Requiere `Rol` y
`CatalogoEstado`. `rol_id`, `estado_origen_id`, `estado_destino_id` resueltos
por código.

| codigo | fase | rol | origen → destino |
|---|---|---|---|
| ACT_REGISTRO_DIGITALIZACION | REGISTRO | TECNICO | - → PENDIENTE_SORTEO |
| ACT_SORTEO_INICIAL | ADMISIBILIDAD | ENCARGADA | PENDIENTE_SORTEO → EN_EVALUACION |
| ACT_OBSERVACION | ADMISIBILIDAD | AUD_JURIDICO | EN_EVALUACION → OBSERVADO |
| ACT_ADMISION | ADMISIBILIDAD | AUD_JURIDICO | EN_EVALUACION → ADMITIDO |
| ACT_RECHAZO | ADMISIBILIDAD | AUD_JURIDICO | EN_EVALUACION → RECHAZADO |
| ACT_INFORME_FINAL | INVESTIGACION | AUD_JURIDICO | ADMITIDO → - |

## Notas de edición

- Todos los seeders usan `updateOrCreate()` → **idempotentes** (re-ejecutables).
- Los datos son placeholders de referencia; editar valores en los `$array` de cada seeder.
- Contraseñas de prueba: se cambian en `UsuarioSeeder` (campo `password_hash`).
- Fe después de cambiar datos que impactan FKs, verificar el orden en `DatabaseSeeder`.
