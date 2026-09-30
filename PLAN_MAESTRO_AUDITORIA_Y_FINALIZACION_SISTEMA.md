# PLAN MAESTRO DE AUDITORÍA Y FINALIZACIÓN

# SISTEMA DE CONTROL Y FISCALIZACIÓN INSTITUCIONAL

**Archivo:** `PLAN_MAESTRO_AUDITORIA_Y_FINALIZACION_SISTEMA.md`
**Proyecto:** Sistema Integral de Control y Fiscalización
**Documento fuente principal:** `Sistemas de Control y Fiscalizacion_ Analisis de requreimiento de Usuario1.docx`
**Agente ejecutor:** OpenCode Agent
**Modalidad:** Auditoría integral + corrección incremental + validación continua
**Objetivo:** Llevar el sistema existente desde su estado actual hasta un estado funcional, seguro, consistente, mantenible y verificable contra el SRS.

---

# 0. INSTRUCCIÓN PRINCIPAL AL AGENTE

Eres el agente técnico responsable de **auditar, completar, corregir y estabilizar** el Sistema de Control y Fiscalización Institucional.

El sistema ya tiene una parte importante desarrollada y se han realizado merges e integraciones previas. **NO debes asumir que el sistema está incompleto únicamente en los puntos conocidos por el usuario.**

Tu trabajo debe descubrir también problemas que actualmente no hayan sido identificados.

Debes trabajar como un **ingeniero senior responsable de llevar un sistema crítico a producción**, no como un generador de código aislado.

El documento SRS proporcionado al proyecto es la **fuente funcional principal**.

Tu objetivo NO es reescribir el sistema.

Tu objetivo es:

> **Entender el sistema existente → auditarlo integralmente → identificar brechas → priorizarlas → corregirlas de forma incremental → probarlas → verificar regresiones → continuar hasta cerrar todo el backlog.**

No debes limitarte a crear un informe.

Debes **ejecutar las correcciones**, siempre que técnicamente sea posible y estén suficientemente sustentadas.

---

# 1. REGLAS ABSOLUTAS DE TRABAJO

Estas reglas son obligatorias durante toda la ejecución.

## 1.1. No asumir que algo funciona porque existe código

Que exista:

* una ruta;
* un controlador;
* un componente;
* una pantalla;
* un endpoint;
* una migración;
* un modelo;
* un botón;

NO significa que la funcionalidad esté terminada.

Debes verificar:

```text
UI
 ↓
Frontend
 ↓
API / HTTP
 ↓
Backend
 ↓
Autorización
 ↓
Reglas de negocio
 ↓
Transacción
 ↓
Base de datos
 ↓
Auditoría / Actuado
 ↓
Estado resultante
 ↓
Siguiente bandeja / flujo
```

Una funcionalidad solo se considera implementada cuando el flujo completo funciona correctamente.

---

# 1.2. No modificar por intuición

No inventes reglas de negocio.

Cuando exista una regla explícita en el SRS:

> Debe respetarse.

Cuando el SRS no defina algo:

> Inspecciona la implementación existente, identifica el comportamiento actual y documenta la ambigüedad antes de introducir una regla nueva.

No conviertas una suposición técnica en una regla institucional.

---

# 1.3. No hacer reescrituras innecesarias

No reemplaces arquitectura funcional existente solo porque exista una alternativa técnicamente más moderna.

Evita:

* reescrituras completas;
* migraciones innecesarias;
* cambios masivos;
* refactors gigantes;
* cambios de framework;
* cambios de ORM;
* cambios de base de datos;
* cambios de arquitectura sin justificación.

Prioriza:

> **mínimo cambio necesario + máxima corrección + máxima trazabilidad.**

---

# 1.4. Seguridad primero

Nunca sacrifiques seguridad para hacer que una funcionalidad "funcione".

Toda funcionalidad debe verificarse desde:

1. interfaz;
2. endpoint;
3. autorización;
4. backend;
5. base de datos cuando corresponda.

No confíes en ocultar botones.

Ejemplo:

```text
❌ Ocultar botón "Aprobar"

✅ Backend verifica:
   - usuario autenticado
   - rol correcto
   - expediente correcto
   - estado correcto
   - transición permitida
   - actuado requerido
```

---

# 1.5. No confiar únicamente en RBAC

El sistema utiliza roles, pero el requisito no se limita a:

```text
rol = Técnico
rol = Auditor
rol = Encargada
```

También existe el concepto de:

> **expediente formalmente asignado a una bandeja.**

Por tanto debes verificar:

```text
RBAC
+
ownership / assignment
+
estado
+
fase
+
transición
```

Un Técnico no debe poder acceder a expedientes de otro Técnico simplemente porque ambos tienen el mismo rol.

---

# 1.6. Los actuados son críticos

El SRS establece una arquitectura basada en actuados inmutables.

No debes introducir mecanismos que permitan:

```text
UPDATE silencioso
DELETE
hard delete
sobrescritura histórica
```

Cuando corresponda corregir información:

```text
Dato original
      ↓
Actuado de Enmienda
      ↓
Nuevo valor
      ↓
Historial
```

La inmutabilidad debe verificarse tanto en aplicación como en persistencia.

---

# 1.7. Las transiciones deben ser explícitas

No debes permitir que un expediente cambie arbitrariamente de:

```text
responsable
estado
fase
bandeja
```

sin el actuado o evento requerido.

La existencia de un botón "Cambiar estado" genérico debe considerarse sospechosa y auditarse.

---

# 1.8. No ocultar errores

Evita patrones como:

```php
try {
   ...
} catch (...) {
   // ignorar
}
```

o:

```javascript
catch(() => {})
```

Los errores críticos deben:

* registrarse;
* informarse correctamente;
* mantener integridad transaccional;
* no dejar estados intermedios inválidos.

---

# 1.9. Toda operación crítica debe ser transaccional

Especialmente:

* generación de NUREJ;
* creación de actuados;
* asignaciones;
* sorteos;
* derivaciones;
* creación NUREJ Hijo;
* Visto Bueno;
* cierre;
* remisiones;
* descargos;
* cambios de estado.

Una operación parcialmente ejecutada no debe dejar expedientes huérfanos.

---

# 1.10. No borrar información histórica

El SRS establece que los expedientes y actuados no deben eliminarse físicamente.

Si encuentras:

```sql
DELETE
```

sobre información procesal:

> Auditarlo inmediatamente.

No elimines código automáticamente sin comprobar su propósito.

---

# 2. DOCUMENTO FUENTE Y ALCANCE

El SRS define como objetivo general automatizar:

* registro;
* enrutamiento;
* seguimiento;
* cierre;
* control de plazos;
* seguridad;
* trazabilidad;
* reportes.

El sistema está concebido para operar dentro de la LAN institucional.

El alcance incluye:

* gestión de solicitudes;
* NUREJ Padre/Hijo;
* bandejas;
* sorteo;
* cálculo de plazos;
* actuados;
* documentación;
* reportes;
* dashboards;
* administración de parámetros.

El SRS excluye:

* interoperabilidad externa;
* APIs con sistemas estatales externos;
* fases jurisdiccionales;
* sentencias;
* apelaciones;
* ejecución de sanciones.

No debes implementar funcionalidades fuera de alcance salvo que sean necesarias para cumplir correctamente una funcionalidad del sistema.

---

# 3. TECNOLOGÍA BASE A RESPETAR

El SRS describe una arquitectura basada en:

```text
Backend: PHP / Laravel
Frontend: aplicación dinámica
Base de datos: revisar implementación real del proyecto
Arquitectura: cliente-servidor
Red: LAN / Intranet
```

## 3.1. Base de datos real del proyecto

**IMPORTANTE:**

La implementación actual del proyecto utiliza **MySQL** como motor de base de datos.

Por tanto:

> **NO migrar PostgreSQL, cambiar MySQL por otro motor ni reemplazar la infraestructura existente únicamente para coincidir con una referencia tecnológica del SRS.**

La prioridad es:

```text
Requisitos funcionales del SRS
        +
Arquitectura real existente
        +
MySQL como motor de persistencia actual
        +
Integridad y seguridad
        +
Compatibilidad con la implementación existente
```

Si el SRS menciona una tecnología de base de datos diferente a la utilizada actualmente:

> Registrar la diferencia como una **diferencia tecnológica**, no asumir automáticamente que es un defecto funcional.

El agente debe trabajar con el motor de base de datos que realmente utiliza el proyecto.

Debe identificar:

* versión de MySQL;
* driver utilizado por Laravel;
* ORM utilizado;
* configuración de conexión;
* charset;
* collation;
* engine de tablas;
* índices;
* foreign keys;
* unique constraints;
* tipos de datos;
* columnas JSON si existen;
* transacciones;
* aislamiento;
* locks;
* migraciones;
* seeders;
* configuración específica del entorno.

No introducir:

* sintaxis exclusiva de PostgreSQL;
* tipos exclusivos de PostgreSQL;
* funciones exclusivas de PostgreSQL;
* operadores incompatibles con MySQL;
* migraciones incompatibles con MySQL.

Si existe una funcionalidad descrita en el SRS con una tecnología distinta, implementarla utilizando el mecanismo equivalente y compatible con MySQL, siempre que el comportamiento funcional requerido pueda conservarse.

---

## 3.2. No cambiar de base de datos

No realizar ninguna migración:

```text
MySQL → PostgreSQL
MySQL → MariaDB
MySQL → otro motor
```

salvo que exista una decisión explícita del proyecto que lo ordene.

La auditoría debe asumir:

```text
MySQL = motor de base de datos actual
```

y trabajar sobre él.

---

## 3.3. JSON y metadatos

Si el sistema utiliza campos JSON para:

* metadatos;
* auditoría;
* configuraciones;
* información adicional;

auditar cómo están implementados realmente en MySQL.

No asumir que debe utilizarse `JSONB`.

Utilizar:

```text
JSON
```

o el mecanismo realmente existente en MySQL, de acuerdo con la versión utilizada por el proyecto.

El objetivo es preservar:

* integridad;
* consulta;
* rendimiento;
* trazabilidad;
* compatibilidad;
* mantenibilidad.

---

## 3.4. Inspección obligatoria del stack

Antes de modificar cualquier parte:

1. inspecciona el stack real;
2. identifica versiones;
3. identifica arquitectura actual;
4. identifica dependencias;
5. identifica ORM;
6. identifica driver de base de datos;
7. identifica convenciones existentes;
8. identifica pruebas;
9. identifica migraciones;
10. identifica configuración;
11. identifica servicios auxiliares;
12. identifica procesos programados.

No supongas que el código actual coincide perfectamente con el SRS.

Documenta las diferencias.

---

# 4. FASE 0 — DESCUBRIMIENTO DEL PROYECTO

Antes de modificar código debes realizar una inspección completa.

## 4.1. Inventario del repositorio

Identifica:

```text
Backend
Frontend
Models
Controllers
Services
Repositories
Policies
Middleware
Requests
Resources
Routes
Jobs
Events
Listeners
Commands
Observers
Migrations
Seeders
Factories
Tests
Views
Components
Hooks
Stores
Utilities
Config
Docker
CI/CD
Scripts
Documentation
```

---

## 4.2. Identificar arquitectura real

Documenta:

* entrypoints;
* módulos;
* dependencias;
* capas;
* comunicación frontend/backend;
* autenticación;
* autorización;
* manejo de archivos;
* generación de PDF;
* generación Excel;
* jobs;
* cron;
* colas;
* notificaciones;
* logging.

---

## 4.3. Identificar convenciones existentes

Antes de crear código nuevo busca:

* Services existentes;
* Actions;
* DTOs;
* Form Requests;
* Policies;
* Enums;
* State machines;
* Events;
* Listeners;
* Repositories;
* componentes reutilizables.

No dupliques una abstracción que ya existe.

---

# 5. FASE 1 — MAPA FUNCIONAL DEL SISTEMA

Construye un inventario real de funcionalidades.

Debe existir un archivo:

```text
docs/auditoria/MAPA_FUNCIONAL.md
```

Debe contener como mínimo:

```text
Módulo
Funcionalidad
Rol
Ruta UI
Endpoint
Controlador
Servicio
Modelo
Tabla
Estado
Actuado
Pruebas
Estado de implementación
Observaciones
```

Clasifica cada funcionalidad como:

```text
IMPLEMENTADA
PARCIAL
FALTANTE
INCORRECTA
INCONSISTENTE
NO VERIFICADA
FUERA DE ALCANCE
```

---

# 6. FASE 2 — MATRIZ SRS → CÓDIGO

Crear:

```text
docs/auditoria/MATRIZ_SRS_IMPLEMENTACION.md
```

Para cada requisito:

```text
ID
Requisito
Ubicación SRS
Implementación
Archivos relacionados
Estado
Evidencia
Brecha
Acción
Prueba
```

Ejemplo:

```text
RF-03
Control de Bandejas Privadas

SRS:
Los usuarios solo pueden visualizar e interactuar con solicitudes
formalmente asignadas.

Implementación:
...

Estado:
PARCIAL

Brecha:
La interfaz filtra correctamente, pero el endpoint permite consultar
un NUREJ ajeno mediante ID directo.

Acción:
Corregir autorización backend.

Prueba:
Feature test de acceso cruzado.
```

Si existe una diferencia tecnológica entre el SRS y la implementación:

```text
Tipo:
DIFERENCIA TECNOLÓGICA

Ejemplo:
SRS referencia PostgreSQL
Implementación real: MySQL

Tratamiento:
No convertir automáticamente esta diferencia en una brecha funcional.
```

---

# 7. FASE 3 — AUDITORÍA DE ROLES

Los roles principales definidos son:

```text
1. Encargada / Jefatura
2. Operador Especializado / Técnico
3. Auditor Jurídico
4. Auditor Financiero
5. Administrador
```

Auditar cada rol de forma independiente.

---

# 8. ENCARGADA / JEFATURA

Este es un módulo crítico.

La Encargada debe tener una experiencia central basada en:

```text
Dashboard
Bandeja de Entrada
Bandejas / Supervisión
Sorteos
Vistos Buenos
Cronogramas pendientes
MPA pendientes
Informes pendientes
Devoluciones
Impugnaciones
Derivaciones
Alertas
Plazos
Historial
Reportes
```

## 8.1. Bandeja de entrada de la Encargada

Auditar específicamente:

### Debe poder visualizar:

* solicitudes pendientes;
* solicitudes recién registradas;
* expedientes pendientes de sorteo;
* cronogramas pendientes de Visto Bueno;
* MPA pendientes;
* informes finales pendientes;
* devoluciones;
* solicitudes de revisión;
* derivaciones;
* situaciones fuera de plazo;
* eventos que requieran intervención jerárquica.

### Cada registro debe indicar claramente:

```text
NUREJ
tipo
denunciante
denunciado
tipo de trámite
responsable
estado
fase
fecha de recepción
fecha límite
días restantes
estado del plazo
último actuado
acción pendiente
```

No basta con mostrar una lista genérica.

La bandeja debe permitir identificar:

> **¿Qué tengo que hacer ahora con este expediente?**

---

# 9. CRONOGRAMAS / VISTO BUENO

Auditar el flujo:

```text
Técnico crea Cronograma
        ↓
Actuado de Cronograma
        ↓
Bandeja Encargada
        ↓
Revisión
        ↓
Aprobar / Observar
        ↓
Actuado correspondiente
        ↓
Si aprueba:
    inicia reloj de investigación
```

Verificar:

* que el Técnico no pueda iniciar investigación antes del VB;
* que la Encargada reciba realmente el cronograma;
* que el cronograma tenga contexto del expediente;
* que pueda visualizarlo;
* que pueda aprobarlo;
* que pueda observarlo;
* que la observación tenga motivo;
* que la devolución vuelva al usuario correcto;
* que exista actuado;
* que el reloj se active exactamente cuando corresponde.

---

# 10. MPA — AUDITORÍAS

Para Jurídico y Financiero:

```text
Auditor
 ↓
MPA
 ↓
Bandeja Encargada
 ↓
VB
 ↓
Inicio de auditoría
```

Verificar:

* alcance;
* fecha propuesta;
* fecha límite;
* reglamento;
* documentos;
* aprobación;
* rechazo/observación;
* historial;
* cálculo del plazo.

---

# 11. SORTEO

Auditar:

* aleatoriedad;
* elegibilidad;
* exclusiones;
* operadores disponibles;
* especialidad;
* carga laboral;
* persistencia del resultado;
* trazabilidad;
* imposibilidad de alterar silenciosamente el resultado.

Verificar si el sistema distingue:

```text
Técnico
Auditor Jurídico
Auditor Financiero
```

según el flujo correspondiente.

---

# 12. TÉCNICO

Auditar el flujo completo:

```text
Registro
 ↓
NUREJ Padre
 ↓
Sorteo
 ↓
Evaluación
 ↓
Admisión / Observación / Rechazo
 ↓
Subsanación
 ↓
Planificación
 ↓
Visto Bueno
 ↓
Investigación
 ↓
Ampliación
 ↓
Informe Final
 ↓
Visto Bueno
 ↓
Salida
```

Validar específicamente:

* 2 días de evaluación;
* 3 días de subsanación;
* 2 días para cronograma;
* 10 días jurisdiccional;
* 15 días administrativo;
* ampliación de 5 días;
* fuera de plazo;
* informe con/sin responsabilidad;
* recomendación de auditoría;
* NUREJ Hijo.

---

# 13. AUDITOR JURÍDICO

Auditar:

```text
Sorteo
 ↓
Evaluación
 ↓
Admisión
 ↓
MPA
 ↓
VB
 ↓
Auditoría
 ↓
Informe
 ↓
VB
 ↓
Salida
```

Verificar:

* Acuerdo 54;
* requisitos;
* relevancia social / intereses del Estado;
* fecha límite del MPA;
* informe con/sin responsabilidad;
* derivación;
* cierre.

---

# 14. AUDITOR FINANCIERO

Auditar:

```text
Sorteo
 ↓
Evaluación
 ↓
MPA
 ↓
Auditoría
 ↓
Hallazgos
 ↓
Descargos
 ↓
5 días
 ↓
Recepción
 ↓
Reanudación
 ↓
Informe
 ↓
VB
 ↓
Salida
```

Este flujo requiere especial atención.

Verificar que:

* los descargos sean exclusivos del financiero;
* el reloj principal se pause;
* exista reloj de descargos;
* sean exactamente 5 días hábiles según la parametrización;
* la recepción de descargos cierre correctamente el subflujo;
* el reloj principal se reanude;
* no se pueda generar informe final antes de completar el flujo requerido.

---

# 15. ADMINISTRADOR

El Administrador debe poder:

* administrar usuarios;
* administrar roles;
* administrar feriados;
* administrar suspensiones;
* administrar catálogos;
* monitorear ubicación/estado.

No debe poder:

* registrar causas;
* ver contenido de archivos restringidos;
* mover trámites;
* interferir en el flujo;
* modificar actuados.

Auditar tanto UI como backend.

---

# 16. AUDITORÍA DE BANDEJAS

Para cada bandeja determinar:

```text
¿Qué entra?
¿Por qué entra?
¿Quién puede verla?
¿Qué acciones puede ejecutar?
¿Qué acciones NO puede ejecutar?
¿Qué actuado genera cada acción?
¿A dónde va después?
¿Qué plazo comienza?
¿Qué plazo termina?
```

Crear una matriz:

| Bandeja | Entrada | Rol | Acción | Actuado | Salida | Plazo |
| ------- | ------- | --- | ------ | ------- | ------ | ----- |

---

# 17. AUDITORÍA DE ESTADOS

Identificar todos los estados existentes en:

* código;
* BD;
* frontend;
* reportes.

Compararlos.

Debe existir una única semántica consistente.

Buscar:

* estados duplicados;
* estados huérfanos;
* strings hardcodeados;
* estados imposibles;
* transiciones sin autorización;
* transiciones no documentadas.

Crear:

```text
docs/auditoria/MAQUINA_ESTADOS.md
```

Representar:

```text
Estado A
  |
  | Actuado X
  v
Estado B
```

---

# 18. AUDITORÍA DE ACTUADOS

Inventariar todos los actuados.

Para cada uno:

```text
Código
Nombre
Actor
Rol
Fase
Estado origen
Estado destino
Datos requeridos
Documentos
Plazo generado
Plazo detenido
Bandeja destino
Inmutabilidad
Hash
Timestamp
Usuario
IP / metadata disponible
```

Comparar contra el catálogo del SRS.

El catálogo contempla, entre otros:

* Registro y Digitalización;
* Sorteo;
* Observación;
* Rechazo;
* Admisión;
* Resolución de Impugnación;
* Cronograma;
* MPA;
* Visto Bueno;
* Ampliación;
* Comunicación de Hallazgos;
* Recepción de Descargos;
* Informes Finales;
* Derivación por Incompetencia;
* Visto Bueno Final;
* Reparto Institucional;
* Remisión a Transparencia;
* Archivo por Abandono;
* Alerta Fuera de Plazo.

---

# 19. AUDITORÍA DE INMUTABILIDAD

Verificar:

```text
¿Se puede editar un actuado?
¿Se puede eliminar?
¿Se puede modificar vía API?
¿Se puede modificar vía SQL?
¿Se puede modificar desde otro endpoint?
¿Se puede modificar mediante mass assignment?
¿Existe soft delete donde no corresponde?
¿Existe cascade delete peligrosa?
```

Revisar:

* policies;
* observers;
* model events;
* database constraints;
* triggers si existen;
* endpoints;
* servicios.

---

# 20. AUDITORÍA NUREJ PADRE / HIJO

El SRS define independencia de los actuados.

Debe verificarse:

```text
NUREJ Padre
      |
      | Recomendación
      v
NUREJ Hijo
```

El Hijo:

* tiene identidad propia;
* tiene flujo independiente;
* no hereda actuados;
* no permite al operador ver la línea de tiempo completa del Padre;
* conserva relación de trazabilidad;
* tiene sus propios actuados;
* puede continuar en paralelo.

Auditar especialmente filtrado de información.

---

# 21. AUDITORÍA DE PLAZOS

Este es uno de los componentes de mayor criticidad.

No aceptar implementaciones simplistas como:

```text
deadline = start + N days
```

sin considerar:

* fines de semana;
* feriados;
* suspensiones;
* reglas específicas;
* pausas;
* reanudaciones;
* fechas de notificación;
* fechas de recepción;
* fechas de aprobación;
* versión normativa.

Crear una matriz:

| Motor | Evento | Inicio | Plazo | Pausa | Reanuda | Fin |
| ----- | ------ | ------ | ----- | ----- | ------- | --- |

---

# 22. PRUEBAS DEL MOTOR DE PLAZOS

Crear pruebas para:

```text
lunes
martes
viernes
fin de semana
feriado
feriado consecutivo
feriado + fin de semana
mes nuevo
año nuevo
periodo largo
suspensión
pausa
reanudar
fuera de plazo
```

Probar cada motor independientemente.

No reutilizar automáticamente reglas de un motor para otro.

---

# 23. VERSIONADO NORMATIVO

El SRS exige independencia normativa.

Auditar:

```text
¿Qué versión normativa tenía el expediente al iniciar?
¿Se guarda?
¿Puede cambiar después?
¿El cambio afecta expedientes antiguos?
```

Una modificación normativa futura no debe recalcular silenciosamente expedientes iniciados bajo una versión anterior.

---

# 24. AUDITORÍA DE SEGURIDAD

Realizar una revisión sistemática de:

## Autenticación

* passwords;
* sesiones;
* expiración;
* logout;
* recuperación;
* brute force;
* CSRF;
* cookies;
* configuración.

## Autorización

* roles;
* policies;
* gates;
* middleware;
* ownership;
* endpoints.

## Acceso directo

Intentar acceder mediante:

```text
URL directa
ID directo
NUREJ
endpoint
API
request manipulado
```

---

# 25. IDOR / ACCESO CRUZADO

Probar escenarios:

```text
Usuario A
 ↓
Expediente A

Usuario A intenta:
 /expedientes/B
```

Debe ser rechazado.

También probar:

```text
GET
POST
PUT
PATCH
DELETE
download
export
search
autocomplete
```

No asumir que porque `show()` está protegido todos los demás endpoints también lo están.

---

# 26. SEGURIDAD DE ARCHIVOS

Auditar:

* upload;
* MIME;
* extensión;
* tamaño;
* nombres;
* almacenamiento;
* descarga;
* permisos;
* path traversal;
* archivos ejecutables;
* archivos no asociados;
* archivos de otros expedientes.

El usuario solo debe descargar documentos que tiene autorización para consultar.

---

# 27. AUDITORÍA DE BASE DE DATOS

La auditoría de persistencia debe realizarse específicamente sobre **MySQL**, que es el motor real del proyecto.

Revisar:

* claves primarias;
* foreign keys;
* índices;
* unique constraints;
* nullability;
* cascades;
* timestamps;
* soft deletes;
* columnas JSON;
* relaciones;
* integridad referencial;
* tipos de datos;
* charset;
* collation;
* engine de tablas;
* transacciones;
* locks;
* aislamiento cuando corresponda.

Buscar:

```text
FK inexistentes
IDs mágicos
strings de estados
duplicados
campos ambiguos
columnas nunca utilizadas
datos huérfanos
```

No introducir características específicas de PostgreSQL.

Cuando sea necesario utilizar una característica específica del motor:

> verificar primero la versión de MySQL utilizada por el proyecto.

---

# 28. AUDITORÍA DE HARDCODES

Buscar sistemáticamente:

```text
grep
ripgrep
AST
static analysis
```

Patrones:

```text
roles
estados
plazos
feriados
tipos de actuado
destinos
reglamentos
mensajes
IDs
UUIDs fijos
URLs
credenciales
fechas
nombres institucionales
```

No todo hardcode es automáticamente incorrecto.

Clasificar:

```text
HARDCODE VÁLIDO
HARDCODE CONFIGURABLE
HARDCODE DE NEGOCIO
HARDCODE PELIGROSO
```

Todo parámetro institucional que el SRS establece como configurable debe ser parametrizable.

---

# 29. ADMINISTRACIÓN DE PARÁMETROS

Auditar la existencia y correcto funcionamiento de:

* feriados;
* suspensiones;
* usuarios;
* roles;
* catálogos;
* tipos;
* normativa;
* configuraciones necesarias.

Verificar:

```text
crear
editar
activar/desactivar
validar
auditar
usar
```

Un parámetro que se administra pero no afecta realmente el comportamiento debe considerarse una brecha.

---

# 30. AUDITORÍA DE REPORTES

Todos los reportes deben mantener consistencia con la **base de datos real utilizada por el proyecto (MySQL)**.

Comparar:

```text
Dashboard
vs
API
vs
Excel
vs
PDF
vs
SQL
vs
datos persistidos en MySQL
```

Validar:

* filtros;
* fechas;
* usuarios;
* estados;
* roles;
* Padre/Hijo;
* conteos;
* totales;
* paginación;
* exportaciones.

Cuando una consulta SQL sea utilizada como fuente de un reporte:

> verificar que sea compatible con la versión real de MySQL y que no dependa de comportamientos específicos de otro motor.

---

# 31. DASHBOARD DE LA ENCARGADA

Auditar que permita conocer:

```text
Casos totales
Casos por estado
Casos por responsable
Casos vencidos
Casos próximos a vencer
Casos pendientes de VB
Casos pendientes de sorteo
Carga laboral
Rendimiento
Distribución por vía
Padres/Hijos
```

No asumir que un gráfico bonito significa que el dato es correcto.

Verificar el cálculo.

---

# 32. AUDITORÍA DE NOTIFICACIONES Y ALERTAS

Verificar:

* cuándo se genera una alerta;
* quién la recibe;
* si desaparece;
* si permanece;
* si se registra;
* si puede duplicarse;
* si se pierde.

Diferenciar:

```text
notificación
alerta
evento
actuado
```

No mezclarlos conceptualmente.

---

# 33. CRON JOBS / JOBS AUTOMÁTICOS

Auditar todos los procesos automáticos.

Especialmente:

```text
Archivo por Abandono
Alerta Fuera de Plazo
actualización de plazos
notificaciones
procesos nocturnos
```

Verificar:

* idempotencia;
* concurrencia;
* timezone;
* errores;
* logs;
* reintentos;
* duplicación.

---

# 34. CASOS DE CONCURRENCIA

Probar:

```text
dos usuarios aprueban simultáneamente
dos usuarios sortean
dos requests crean NUREJ
dos requests crean actuado
dos descargos se registran simultáneamente
dos procesos cierran el mismo expediente
```

Las operaciones críticas deben ser resistentes a race conditions.

---

# 35. AUDITORÍA DE TRANSACCIONES

Para cada operación crítica determinar:

```text
¿Dónde comienza la transacción?
¿Qué operaciones incluye?
¿Qué pasa si falla la operación N?
¿Se hace rollback?
¿Puede quedar un estado parcial?
```

En MySQL, verificar además que las tablas y operaciones involucradas utilicen mecanismos compatibles con transacciones cuando corresponda.

No asumir que toda operación es transaccional solo porque exista:

```php
DB::transaction(...)
```

Debe verificarse que las operaciones realmente incluidas sean las que necesitan atomicidad.

---

# 36. AUDITORÍA DE FRONTEND

Revisar:

* navegación;
* estados;
* loaders;
* errores;
* formularios;
* validación;
* permisos;
* botones;
* tablas;
* paginación;
* filtros;
* responsive cuando corresponda;
* mensajes;
* confirmaciones.

Buscar especialmente:

```text
botón que no hace nada
botón que hace demasiado
botón visible para rol incorrecto
acción habilitada en estado incorrecto
pantalla sin retorno
error sin explicación
datos que no se refrescan
```

---

# 37. AUDITORÍA DE UX OPERATIVA

Preguntar para cada pantalla:

> ¿Un funcionario puede entender qué debe hacer?

Una pantalla operacional debe mostrar:

```text
¿Qué expediente es?
¿En qué estado está?
¿Dónde está?
¿Qué plazo tiene?
¿Qué debo hacer?
¿Qué pasará si hago esta acción?
```

---

# 38. VALIDACIONES

Toda entrada debe validarse:

```text
frontend
+
backend
```

Nunca confiar únicamente en frontend.

Validar:

* campos obligatorios;
* formatos;
* fechas;
* relaciones;
* archivos;
* permisos;
* estados;
* transiciones.

---

# 39. AUDITORÍA DE API

Inventariar todos los endpoints.

Para cada endpoint:

```text
Método
Ruta
Auth
Rol
Ownership
Validación
Policy
Transición
Actuado
Respuesta
Errores
Rate limiting si aplica
```

Buscar endpoints:

* sin auth;
* sin policy;
* con parámetros inseguros;
* que exponen datos;
* que aceptan IDs ajenos;
* que permiten modificar estados directamente.

---

# 40. AUDITORÍA DE LOGS

Los logs deben permitir diagnosticar problemas sin exponer información sensible innecesariamente.

Revisar:

* errores;
* excepciones;
* operaciones críticas;
* jobs;
* autenticación;
* fallos de autorización.

No registrar:

* contraseñas;
* tokens;
* información sensible innecesaria.

---

# 41. AUDITORÍA DE MANEJO DE ERRORES

Cada error debe:

```text
ser detectable
ser comprensible
no corromper datos
no revelar información sensible
registrarse adecuadamente
```

No mostrar stack traces en producción.

---

# 42. PRUEBAS AUTOMATIZADAS

Antes de considerar una funcionalidad terminada:

```text
Unit Test
Feature Test
Integration Test
Security Test
```

según corresponda.

Prioridad de tests:

1. reglas de negocio;
2. autorización;
3. transacciones;
4. plazos;
5. estados;
6. actuados;
7. NUREJ;
8. reportes.

---

# 43. PRUEBAS DE REGRESIÓN

Cada corrección debe ejecutar:

```text
tests existentes
+
tests del módulo modificado
```

Si una prueba falla:

> Investigar antes de continuar.

No deshabilitar pruebas para hacer pasar CI.

---

# 44. SISTEMA DE TRACKING DE HALLAZGOS

Crear:

```text
docs/auditoria/BACKLOG_AUDITORIA.md
```

Formato:

```markdown
## AUD-0001

### Título
Bandeja de cronogramas no llega a Encargada

### Severidad
P1

### Categoría
Flujo funcional

### SRS
RN-04

### Evidencia
...

### Impacto
...

### Causa
...

### Solución
...

### Archivos
...

### Tests
...

### Estado
OPEN
```

---

# 45. SEVERIDADES

## P0 — CRÍTICO

Problemas que comprometen:

* seguridad grave;
* integridad de datos;
* pérdida de expedientes;
* modificación de actuados;
* acceso masivo no autorizado;
* corrupción de estados;
* imposibilidad de recuperar información.

No continuar con funcionalidades secundarias mientras exista un P0 abierto.

---

## P1 — ALTO

Problemas que afectan:

* flujo principal;
* plazos;
* permisos;
* actuaciones legales;
* cierres;
* bandejas;
* sorteos;
* trazabilidad.

---

## P2 — MEDIO

Problemas funcionales importantes pero con workaround.

Ejemplo:

* filtros;
* reportes;
* UX;
* validaciones secundarias.

---

## P3 — BAJO

Mejoras:

* estética;
* mensajes;
* pequeños refactors;
* optimizaciones no críticas.

---

# 46. ORDEN DE EJECUCIÓN

El agente debe trabajar en este orden:

```text
FASE 0
Descubrimiento

↓

FASE 1
Mapa funcional

↓

FASE 2
Matriz SRS

↓

FASE 3
Seguridad / autorización

↓

FASE 4
Modelo de estados / actuados

↓

FASE 5
Plazos

↓

FASE 6
Bandejas y roles

↓

FASE 7
Flujos Técnico

↓

FASE 8
Flujo Jurídico

↓

FASE 9
Flujo Financiero

↓

FASE 10
Encargada / Dashboard

↓

FASE 11
NUREJ Padre/Hijo

↓

FASE 12
Reportes

↓

FASE 13
Jobs / automatizaciones

↓

FASE 14
UX / frontend

↓

FASE 15
Rendimiento

↓

FASE 16
Pruebas integrales

↓

FASE 17
Hardening

↓

FASE 18
Auditoría final
```

No necesariamente debes esperar a terminar una fase completa para corregir un P0/P1 descubierto.

---

# 47. REGLA "UNO POR UNO"

Una vez creado el backlog:

> Trabajar un hallazgo por vez.

Para cada hallazgo:

```text
1. Leer
2. Reproducir
3. Entender causa
4. Identificar impacto
5. Diseñar solución
6. Implementar
7. Ejecutar tests
8. Revisar seguridad
9. Revisar regresiones
10. Actualizar documentación
11. Marcar como DONE
12. Continuar
```

No marcar como terminado simplemente porque el código compila.

---

# 48. DEFINITION OF DONE

Un hallazgo solo puede marcarse:

```text
DONE
```

si cumple:

```text
[ ] Problema reproducido o suficientemente demostrado
[ ] Causa identificada
[ ] Solución implementada
[ ] Código revisado
[ ] Backend validado
[ ] Frontend validado
[ ] Seguridad validada
[ ] Tests ejecutados
[ ] Regresión revisada
[ ] Base de datos validada si aplica
[ ] Actuado validado si aplica
[ ] Plazo validado si aplica
[ ] Documentación actualizada
[ ] No quedan TODO relacionados
```

---

# 49. NO CREAR FALSOS POSITIVOS

No marques como problema algo que:

* está explícitamente fuera de alcance;
* es comportamiento requerido;
* es una decisión documentada;
* es una diferencia puramente estética.

Pero sí registra las ambigüedades.

---

# 50. NO CREAR FALSAS SOLUCIONES

Evitar soluciones como:

```text
"ocultemos el botón"
"validemos solo en frontend"
"hardcodeemos el valor"
"permitamos editar para facilitar"
"eliminemos el registro problemático"
"ignoremos el error"
"deshabilitemos el test"
```

Estas soluciones no son aceptables para cerrar un hallazgo crítico.

---

# 51. GESTIÓN DE DATOS EXISTENTES

Antes de cambiar:

* columnas;
* estados;
* relaciones;
* constraints;
* enums;
* identificadores;

determinar si existe información real que pueda romperse.

Si una migración es necesaria:

```text
backup strategy
migration
rollback strategy
data migration
verification
```

---

# 52. MIGRACIONES

Toda modificación estructural debe realizarse mediante migración versionada.

No depender de:

```text
"lo cambié directamente en la base de datos"
```

salvo tareas explícitas de diagnóstico.

Las migraciones deben ser compatibles con:

```text
MySQL
+
versión real utilizada por el proyecto
```

Antes de ejecutar una migración sobre datos existentes:

```text
verificar impacto
verificar datos existentes
verificar índices
verificar foreign keys
verificar compatibilidad
verificar rollback cuando sea posible
```

No introducir migraciones diseñadas para PostgreSQL en un proyecto que utiliza MySQL.

---

# 53. SEEDERS

Revisar:

* roles;
* permisos;
* catálogos;
* estados;
* tipos de actuados;
* datos iniciales.

Los seeders no deben sobrescribir información institucional existente accidentalmente.

---

# 54. CONFIGURACIÓN

Buscar secretos hardcodeados:

```text
password
secret
token
API key
DB credentials
```

Nunca introducir secretos en el repositorio.

Verificar `.env.example`.

---

# 55. RENDIMIENTO

Auditar:

* N+1 queries;
* consultas repetidas;
* paginación;
* índices;
* joins;
* reportes pesados;
* exportaciones;
* dashboard;
* carga de bandejas.

Las bandejas deben utilizar paginación del servidor.

No cargar miles de expedientes al navegador.

En MySQL revisar especialmente:

* índices utilizados;
* índices faltantes;
* consultas con `EXPLAIN`;
* joins costosos;
* ordenamientos;
* filtros;
* búsquedas por NUREJ;
* consultas de dashboard;
* consultas de reportes.

No agregar índices indiscriminadamente.

Cada índice debe justificarse por un patrón real de consulta.

---

# 56. DATOS SENSIBLES

Minimizar exposición.

No retornar desde APIs:

```text
datos que el rol no necesita
archivos no autorizados
información de otros expedientes
campos internos innecesarios
```

---

# 57. AUDITORÍA DE REPORTES Y EXPORTACIONES

La autorización también aplica a:

```text
Excel
PDF
CSV
print
download
```

Un usuario no debe poder obtener mediante exportación datos que no puede visualizar en la interfaz.

---

# 58. AUDITORÍA DE BÚSQUEDA

Especial atención.

La búsqueda no debe convertirse en un bypass de permisos.

Probar:

```text
buscar NUREJ ajeno
buscar nombre ajeno
buscar ID
buscar por estado
buscar por responsable
buscar texto libre
```

---

# 59. AUDITORÍA DE URLs Y RUTAS

Revisar:

```text
web.php
api.php
routes
middleware
route model binding
```

No confiar únicamente en route model binding.

Verificar autorización contextual.

---

# 60. AUDITORÍA DE POLICIES

Para cada modelo sensible debe existir autorización coherente.

Auditar:

```text
viewAny
view
create
update
delete
download
export
approve
reject
assign
transition
```

No todas necesariamente deben existir, pero cada acción sensible debe tener una protección equivalente.

---

# 61. AUDITORÍA DE ESTADOS INVÁLIDOS

Intentar deliberadamente:

```text
aprobar expediente cerrado
editar expediente archivado
crear informe sin admisión
crear informe sin VB
crear hijo sin actuado
cerrar sin informe
descargos sin hallazgo
descargos en Técnico
MPA en expediente incorrecto
```

Cada escenario debe producir un rechazo seguro.

---

# 62. AUDITORÍA DE FLUJOS FELICES Y NEGATIVOS

Para cada flujo crear:

### Happy path

```text
A → B → C → D
```

### Error path

```text
A → error
```

### Unauthorized path

```text
A → usuario no autorizado
```

### Invalid state path

```text
A → estado incompatible
```

### Concurrent path

```text
A + A simultáneo
```

---

# 63. CASOS BORDE OBLIGATORIOS

Evaluar:

```text
sin datos
un dato
muchos datos
fecha límite hoy
fecha límite ayer
fin de semana
feriado
usuario inactivo
usuario sin asignación
expediente cerrado
expediente observado
expediente rechazado
NUREJ inexistente
NUREJ duplicado
archivo enorme
archivo inválido
doble click
refresh durante operación
timeout
fallo de DB
fallo de job
```

---

# 64. AUDITORÍA DE DUPLICADOS

Verificar que no puedan duplicarse accidentalmente:

* NUREJ;
* actuados;
* sorteos;
* Vistos Buenos;
* informes;
* descargos;
* archivos;
* asignaciones.

---

# 65. IDEMPOTENCIA

Los procesos automáticos y requests sensibles deben evaluarse respecto a repetición.

Ejemplo:

```text
POST aprobar
POST aprobar
```

La segunda operación debe:

* rechazarse;
* o ser idempotente;

pero no duplicar efectos.

---

# 66. AUDITORÍA DE FECHAS

Verificar:

```text
timezone
server time
database time
frontend time
```

No depender del reloj del navegador para decisiones legales.

Las fechas procesales críticas deben calcularse de forma consistente en backend.

---

# 67. AUDITORÍA DE TIMESTAMPS

Los eventos importantes deben registrar adecuadamente:

```text
created_at
actor
fecha efectiva
fecha de notificación
fecha de recepción
fecha de aprobación
```

No confundir:

```text
fecha de creación técnica
fecha procesal
fecha de notificación
```

---

# 68. AUDITORÍA DE TRAZABILIDAD

Para cualquier expediente debe ser posible reconstruir:

```text
quién
hizo qué
cuándo
sobre qué expediente
desde qué estado
hacia qué estado
mediante qué actuado
con qué documento
```

---

# 69. PRUEBA DE RECONSTRUCCIÓN

Seleccionar casos completos y comprobar que desde el historial puede reconstruirse el flujo entero sin depender de logs técnicos externos.

---

# 70. AUDITORÍA DE FRONTEND VS BACKEND

Para cada acción visible:

```text
¿Existe backend?
¿Backend valida?
¿Frontend refleja resultado?
¿Frontend maneja error?
¿Estado se actualiza?
¿Historial cambia?
¿Bandeja cambia?
```

---

# 71. AUDITORÍA DE DATOS DE PRUEBA

Identificar si existen datos demo/hardcodeados que puedan confundirse con datos reales.

Separar:

```text
seed/demo
test
development
production
```

---

# 72. NO MODIFICAR DATOS PRODUCTIVOS SIN NECESIDAD

Si el entorno contiene información real:

> No ejecutar scripts destructivos.

No ejecutar:

```text
migrate:fresh
db:wipe
truncate
delete all
```

sin autorización explícita y estrategia de recuperación.

---

# 73. DOCUMENTACIÓN

Actualizar cuando corresponda:

```text
README
arquitectura
migraciones
variables
jobs
cron
roles
flujos
tests
```

Pero no convertir documentación en sustituto de código correcto.

---

# 74. EVIDENCIA

Cada corrección importante debe dejar evidencia:

```text
test
captura si existe tooling
log
resultado
consulta
```

Registrar evidencia en:

```text
docs/auditoria/evidencias/
```

cuando sea apropiado.

---

# 75. INFORME DE PROGRESO

Mantener:

```text
docs/auditoria/PROGRESO.md
```

Con:

```text
Fecha
Hallazgos abiertos
P0
P1
P2
P3
Completados
Bloqueados
Tests
Observaciones
```

---

# 76. REGLA DE CONTINUIDAD

No detenerse después de corregir los problemas conocidos.

Cuando el backlog conocido llegue a:

```text
0 P0
0 P1
```

debes realizar una segunda auditoría buscando nuevos problemas.

Después:

```text
0 P0
0 P1
0 P2
```

realizar una tercera auditoría.

---

# 77. AUDITORÍA FINAL

La auditoría final debe verificar:

```text
SRS
vs
Código
vs
BD MySQL
vs
UI
vs
API
vs
Tests
```

No considerar el sistema terminado solo porque todos los tickets estén cerrados.

---

# 78. MATRIZ FINAL DE COBERTURA

Crear:

```text
docs/auditoria/MATRIZ_COBERTURA_FINAL.md
```

Debe contener:

| Requisito | Implementado | Test | Seguridad | Evidencia | Estado |
| --------- | ------------ | ---- | --------- | --------- | ------ |

Todo requisito crítico debe tener evidencia.

---

# 79. CRITERIOS DE LISTO PARA PRODUCCIÓN

El sistema solo puede considerarse:

```text
READY FOR PRODUCTION
```

cuando:

```text
[ ] No existen P0
[ ] No existen P1
[ ] No existen brechas críticas de seguridad
[ ] Flujos principales completos
[ ] Roles correctamente aislados
[ ] Bandejas funcionando
[ ] Actuados inmutables
[ ] NUREJ correcto
[ ] Padre/Hijo correcto
[ ] Plazos verificados
[ ] Jobs verificados
[ ] Reportes consistentes
[ ] Dashboard consistente
[ ] Tests pasando
[ ] Migraciones verificadas
[ ] MySQL verificado
[ ] Backups/rollback considerados
[ ] Errores controlados
[ ] Auditoría final realizada
[ ] Matriz SRS cubierta
```

---

# 80. FORMATO DE COMUNICACIÓN DEL AGENTE

Al terminar cada bloque de trabajo, informar:

```text
## Hallazgo

ID:
AUD-XXXX

## Problema

...

## Causa

...

## Solución

...

## Archivos modificados

...

## Tests

...

## Seguridad

...

## Regresión

...

## Estado

DONE / BLOCKED / NEEDS_REVIEW
```

No entregar respuestas vagas como:

```text
"Listo."
"Se corrigió."
"Todo funciona."
```

---

# 81. REGLA PARA BLOQUEOS

Si no puedes completar una tarea:

```text
BLOCKED
```

y explicar:

```text
qué falta
por qué falta
qué evidencia existe
qué decisión humana se requiere
```

No inventar una solución.

---

# 82. REGLA PARA AMBIGÜEDADES DEL SRS

Si existe contradicción o ambigüedad:

```text
AMBIGÜEDAD
```

Registrar:

```text
SRS sección
Comportamiento actual
Interpretaciones posibles
Impacto
Decisión requerida
```

No resolver silenciosamente una contradicción jurídica o funcional.

---

# 83. REGLA CONTRA EL "TODO DE UNA"

No intentar:

```text
"voy a corregir todo el proyecto"
```

en un solo cambio.

Trabajar incrementalmente.

Cada unidad de trabajo debe ser:

```text
pequeña
verificable
reversible
testeable
```

---

# 84. REGLA CONTRA EL "TODO ES REFACTOR"

Si encuentras código feo pero funcional:

> No lo conviertas automáticamente en prioridad.

Primero:

```text
seguridad
integridad
flujo
correctitud
tests
```

Después:

```text
mantenibilidad
refactor
estética
```

---

# 85. REGLA DE IMPACTO

Antes de modificar una pieza compartida:

```text
buscar referencias
identificar consumidores
identificar tests
identificar módulos dependientes
```

No modificar una función central sin revisar sus dependencias.

---

# 86. CHECKPOINT ANTES DE CADA CAMBIO

Antes de editar código:

```text
¿Qué requisito estoy resolviendo?
¿Qué archivo lo implementa?
¿Qué dependencias tiene?
¿Qué puede romper?
¿Qué prueba demostrará que funciona?
```

---

# 87. CHECKPOINT DESPUÉS DE CADA CAMBIO

Después:

```text
¿Compila?
¿Tests pasan?
¿Autorización correcta?
¿Flujo correcto?
¿BD MySQL consistente?
¿Historial correcto?
¿No rompe otra bandeja?
¿No genera duplicados?
¿No introduce hardcode?
```

---

# 88. PRINCIPIO DE MÍNIMO PRIVILEGIO

Cada usuario debe tener:

> únicamente las capacidades necesarias para su función.

No otorgar permisos administrativos "por comodidad".

---

# 89. PRINCIPIO DE DENEGACIÓN POR DEFECTO

Si no existe autorización explícita:

```text
DENY
```

No:

```text
ALLOW
```

---

# 90. PRINCIPIO DE SERVIDOR COMO AUTORIDAD

El frontend puede mejorar UX.

Pero:

> El backend es la autoridad final sobre seguridad y reglas de negocio.

---

# 91. PRINCIPIO DE BASE DE DATOS COMO ÚLTIMA BARRERA

Cuando sea técnicamente razonable:

```text
Application validation
+
Database constraints
```

especialmente para integridad estructural.

Las restricciones deben diseñarse de acuerdo con las capacidades y comportamiento de **MySQL** y de la versión utilizada por el proyecto.

---

# 92. PRINCIPIO DE TRAZABILIDAD

Toda acción procesal relevante debe poder reconstruirse.

Si una operación no deja evidencia suficiente:

> considerarla una potencial brecha.

---

# 93. PRINCIPIO DE INMUTABILIDAD

Nunca reemplazar:

```text
historial
```

por:

```text
estado_actual
```

El estado actual es una consecuencia.

El historial es la evidencia.

---

# 94. PRINCIPIO DE EXPLICITUD

Evitar lógica implícita como:

```php
if ($status === 'x') ...
```

cuando exista una abstracción adecuada.

Preferir, si ya existe en el proyecto:

```text
Enums
States
Services
Actions
Policies
```

No introducir abstracciones innecesarias.

---

# 95. PRINCIPIO DE CONSISTENCIA

Una regla debe tener una única fuente de verdad siempre que sea posible.

Ejemplo:

```text
Plazo
```

no debe existir simultáneamente como:

```text
5 frontend
5 backend
5 config
5 controller
5 SQL
```

sin una razón clara.

---

# 96. PRINCIPIO DE CONFIGURABILIDAD

Cuando el SRS establece que un parámetro debe ser configurable:

> no hardcodearlo.

Ejemplos:

```text
feriados
suspensiones
usuarios
catálogos
normativa
plazos parametrizables
```

---

# 97. PRINCIPIO DE PRUEBA REAL

No considerar suficiente:

```text
"el endpoint responde 200"
```

La prueba debe verificar el resultado funcional.

Ejemplo:

```text
crear cronograma
→ aparece en bandeja Encargada
→ Encargada aprueba
→ actuado creado
→ reloj inicia
→ Técnico puede continuar
```

---

# 98. ESCENARIO INTEGRAL MÍNIMO

Crear al menos un escenario automatizado/integral que recorra:

```text
Registro
→ NUREJ Padre
→ Sorteo
→ Evaluación
→ Admisión
→ Cronograma
→ Bandeja Encargada
→ Visto Bueno
→ Investigación
→ Informe
→ Visto Bueno Final
→ Salida
```

Y escenarios separados para:

```text
Observación
Rechazo
Impugnación
Subsanación
Ampliación
NUREJ Hijo
Auditoría Jurídica
Auditoría Financiera
Descargos
Derivación a Transparencia
Fuera de plazo
```

---

# 99. MATRIZ DE FLUJOS OBLIGATORIOS

Crear y mantener:

```text
docs/auditoria/MATRIZ_FLUJOS.md
```

Con:

| Flujo | Inicio | Fin | Roles | Actuados | Plazos | Tests | Estado |
| ----- | ------ | --- | ----- | -------- | ------ | ----- | ------ |

---

# 100. MATRIZ DE PERMISOS

Crear:

```text
docs/auditoria/MATRIZ_PERMISOS.md
```

Ejemplo:

| Acción                   |   Encargada | Técnico | Jurídico | Financiero | Admin |
| ------------------------ | ----------: | ------: | -------: | ---------: | ----: |
| Registrar                | según flujo |       ✓ |        - |          - |     - |
| Sortear                  |           ✓ |       - |        - |          - |     - |
| Aprobar Cronograma       |           ✓ |       - |        - |          - |     - |
| Crear Informe Técnico    |           - |       ✓ |        - |          - |     - |
| Crear Informe Jurídico   |           - |       - |        ✓ |          - |     - |
| Crear Informe Financiero |           - |       - |        - |          ✓ |     - |
| Administrar usuarios     |           - |       - |        - |          - |     ✓ |

**No asumir esta tabla como sustituto del SRS.**
Debe verificarse contra la implementación y el documento fuente.

---

# 101. MATRIZ DE ACTUADOS

Crear:

```text
docs/auditoria/MATRIZ_ACTUADOS.md
```

---

# 102. MATRIZ DE PLAZOS

Crear:

```text
docs/auditoria/MATRIZ_PLAZOS.md
```

---

# 103. MATRIZ DE SEGURIDAD

Crear:

```text
docs/auditoria/MATRIZ_SEGURIDAD.md
```

---

# 104. MATRIZ DE REPORTES

Crear:

```text
docs/auditoria/MATRIZ_REPORTES.md
```

---

# 105. ESTRUCTURA RECOMENDADA DE DOCUMENTACIÓN

Crear:

```text
docs/
└── auditoria/
    ├── MAPA_FUNCIONAL.md
    ├── MATRIZ_SRS_IMPLEMENTACION.md
    ├── MATRIZ_FLUJOS.md
    ├── MATRIZ_PERMISOS.md
    ├── MATRIZ_ACTUADOS.md
    ├── MATRIZ_PLAZOS.md
    ├── MATRIZ_SEGURIDAD.md
    ├── MATRIZ_REPORTES.md
    ├── MAQUINA_ESTADOS.md
    ├── BACKLOG_AUDITORIA.md
    ├── PROGRESO.md
    ├── MATRIZ_COBERTURA_FINAL.md
    └── evidencias/
```

---

# 106. PRIMERA EJECUCIÓN DEL AGENTE

Al recibir este documento:

## NO comiences inmediatamente a modificar código.

Primero:

### Paso 1

Inspecciona completamente el repositorio.

### Paso 2

Identifica arquitectura.

### Paso 3

Identifica módulos.

### Paso 4

Identifica roles.

### Paso 5

Identifica rutas.

### Paso 6

Identifica modelos y tablas.

### Paso 7

Identifica estados.

### Paso 8

Identifica actuados.

### Paso 9

Identifica cálculos de plazos.

### Paso 10

Identifica jobs/cron.

### Paso 11

Identifica tests.

### Paso 12

Construye:

```text
docs/auditoria/MAPA_FUNCIONAL.md
docs/auditoria/MATRIZ_SRS_IMPLEMENTACION.md
docs/auditoria/BACKLOG_AUDITORIA.md
```

---

# 107. PRIMER INFORME OBLIGATORIO

Antes de iniciar correcciones masivas, entregar un resumen:

```text
ESTADO GENERAL
---------------

Arquitectura:
...

Backend:
...

Frontend:
...

Base de datos:
MySQL
Versión:
...

Seguridad:
...

Roles:
...

Bandejas:
...

Actuados:
...

Plazos:
...

Reportes:
...

Tests:
...

P0:
...

P1:
...

P2:
...

P3:
...

Bloqueos:
...
```

Después comenzar con el hallazgo de mayor prioridad.

---

# 108. NO ESPERAR APROBACIÓN PARA CADA CORRECCIÓN

Una vez comprendido el alcance:

> Puedes corregir automáticamente los hallazgos claros y bien sustentados.

No es necesario pedir autorización para cada:

* bug;
* test;
* validación;
* mejora de seguridad;
* corrección de autorización;
* corrección de typo técnico;
* corrección claramente exigida por el SRS.

Sí debes detenerte ante:

* ambigüedad normativa;
* cambio de alcance;
* decisión funcional no especificada;
* migración destructiva;
* riesgo elevado de pérdida de datos;
* cambio de motor de base de datos.

---

# 109. REGLA DE NO REGRESIÓN

Una nueva corrección nunca debe justificar romper otro flujo.

Antes de modificar:

```text
identificar dependencias
```

Después:

```text
ejecutar pruebas relacionadas
```

---

# 110. AUDITORÍA FINAL DE SEGURIDAD

Antes de declarar producción:

Intentar deliberadamente:

```text
acceso con rol incorrecto
acceso a NUREJ ajeno
descarga de archivo ajeno
modificación de actuado
eliminación
cambio de estado directo
aprobación sin permiso
informe sin requisitos
sorteo duplicado
duplicación de NUREJ
inyección de parámetros
mass assignment
acceso directo a endpoints
exportación de datos ajenos
```

Todos deben estar correctamente controlados.

---

# 111. AUDITORÍA FINAL DE INTEGRIDAD

Comprobar:

```text
No hay actuados modificables
No hay expedientes huérfanos
No hay relaciones inválidas
No hay NUREJ duplicados
No hay estados imposibles
No hay asignaciones imposibles
No hay plazos inconsistentes
No hay datos duplicados críticos
```

---

# 112. AUDITORÍA FINAL DE PLAZOS

Ejecutar nuevamente pruebas sobre:

```text
022/2018
54/2018
55/2018
```

incluyendo:

```text
evaluación
subsanación
planificación
investigación
ampliación
descargos
fuera de plazo
suspensión
feriados
versiones normativas
```

---

# 113. AUDITORÍA FINAL DE REPORTES

Comparar muestras reales:

```text
Dashboard
SQL/MySQL
API
Excel
PDF
```

Los valores deben coincidir.

Cuando se utilicen consultas SQL para validar resultados:

> Ejecutarlas contra MySQL y documentar la consulta utilizada cuando sea necesario como evidencia.

---

# 114. AUDITORÍA FINAL DE BANDEJAS

Para cada rol:

```text
Login
→ Bandeja
→ Caso esperado
→ Acción
→ Actuado
→ Nuevo estado
→ Nueva bandeja
```

Verificar también:

```text
Caso ajeno
→ acceso denegado
```

---

# 115. AUDITORÍA FINAL DE USABILIDAD

Para cada flujo principal verificar que un usuario pueda responder:

```text
¿Dónde estoy?
¿Qué expediente estoy viendo?
¿Qué estado tiene?
¿Qué plazo tiene?
¿Qué tengo que hacer?
¿Qué ocurrirá al hacerlo?
```

---

# 116. RELEASE CHECKLIST

Antes de producción:

```text
[ ] Código limpio
[ ] Tests OK
[ ] Build OK
[ ] Migraciones OK
[ ] Migraciones compatibles con MySQL
[ ] Variables documentadas
[ ] Secrets fuera del repositorio
[ ] Jobs documentados
[ ] Cron documentado
[ ] Backup considerado
[ ] Rollback definido
[ ] Logs funcionando
[ ] Errores controlados
[ ] Seguridad validada
[ ] Roles validados
[ ] Bandejas validadas
[ ] Plazos validados
[ ] Actuados validados
[ ] Reportes validados
[ ] Dashboard validado
[ ] NUREJ validado
[ ] Padre/Hijo validado
[ ] MySQL validado
[ ] Índices críticos revisados
[ ] Integridad referencial validada
[ ] UAT preparado
[ ] Matriz SRS completa
```

---

# 117. DEFINICIÓN FINAL DEL ÉXITO

El trabajo NO termina cuando:

```text
el proyecto compila
```

ni cuando:

```text
la interfaz parece funcionar
```

ni cuando:

```text
todos los tickets iniciales están cerrados
```

Termina cuando exista evidencia razonable de que:

> **el sistema implementa los requisitos del SRS, sus flujos principales son completos, sus roles están correctamente aislados, los actuados son trazables e inmutables, los plazos funcionan correctamente, las bandejas reflejan las responsabilidades reales, los datos son íntegros, las operaciones críticas son transaccionales, las exportaciones son consistentes y los principales escenarios negativos están controlados.**

La tecnología de persistencia utilizada actualmente es **MySQL** y debe mantenerse salvo decisión explícita de proyecto.

La auditoría tecnológica debe garantizar que:

```text
Laravel
+
Backend
+
Frontend
+
MySQL
+
Migraciones
+
Queries
+
Jobs
+
Reportes
```

funcionen de forma coherente como un único sistema.

---

# 118. INSTRUCCIÓN FINAL AL AGENTE

Trabaja de forma disciplinada.

No tengas como objetivo "terminar rápido".

Ten como objetivo:

```text
CORRECTITUD
SEGURIDAD
TRAZABILIDAD
INTEGRIDAD
MANTENIBILIDAD
VERIFICABILIDAD
```

Cuando encuentres algo que parece pequeño, pregúntate:

> "¿Este detalle puede provocar un comportamiento incorrecto en un proceso institucional?"

Cuando encuentres algo que parece funcionar, pregúntate:

> "¿Está realmente protegido en backend, base de datos y flujo completo?"

Cuando encuentres un hardcode, pregúntate:

> "¿Es una constante técnica válida o una regla institucional que debería estar parametrizada?"

Cuando encuentres una transición, pregúntate:

> "¿Qué actuado la justifica?"

Cuando encuentres un plazo, pregúntate:

> "¿Cuál es exactamente el evento que inicia el reloj, qué lo pausa, qué lo reanuda y qué ocurre cuando vence?"

Cuando encuentres una bandeja, pregúntate:

> "¿Quién puede ver cada expediente, por qué está aquí, qué puede hacer y hacia dónde debe ir después?"

Cuando encuentres un permiso, pregúntate:

> "¿Está protegido realmente en backend o solo oculto en la interfaz?"

Cuando encuentres una consulta o migración de base de datos, pregúntate:

> "¿Es compatible con MySQL y con la versión real utilizada por el proyecto?"

Cuando encuentres una referencia del SRS a PostgreSQL, pregúntate:

> "¿Es un requisito funcional o solamente una referencia tecnológica del documento?"

No conviertas una diferencia tecnológica en una migración innecesaria.

Cuando corrijas algo, pregúntate:

> "¿Qué otra parte del sistema puede haber quedado afectada?"

Y cuando termines el backlog inicial:

> **Vuelve a auditar el sistema completo.**

Porque el objetivo de este documento no es únicamente corregir los problemas que ya conocemos.

El objetivo es descubrir y eliminar también:

> **los problemas que todavía no sabemos que existen.**

---

# FIN DEL PLAN MAESTRO
