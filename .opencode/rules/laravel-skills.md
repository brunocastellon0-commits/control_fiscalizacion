# Skill: Laravel + MySQL nivel profesional / gubernamental

Aplica esto en TODO código que generes o modifiques. No es opcional.

## 1. Base de datos — MySQL, nunca Postgres

- Motor: `InnoDB` siempre (soporta transacciones y foreign keys). Nunca `MyISAM`.
- Charset/collation: `utf8mb4` / `utf8mb4_unicode_ci` en todas las tablas y columnas de texto.
- Prohibido usar sintaxis o tipos exclusivos de PostgreSQL: nada de `jsonb`,
  arrays nativos (`text[]`), `SERIAL`, `RETURNING`, `ILIKE`, etc.
  - JSON en MySQL: usar el tipo `JSON` nativo (Laravel: `$table->json('campo')`).
  - Búsqueda insensible a mayúsculas: usar collation `_ci` o `LOWER()`, no `ILIKE`.
- IDs: `bigIncrements` / `bigInteger unsigned` por defecto, salvo que el
  esquema ya use UUID (revisar migraciones existentes antes de decidir).
- Fechas: `timestamp`/`datetime` de MySQL, cuidado con el rango de `timestamp`
  (año 1970–2038) si se manejan fechas antiguas o muy futuras — en ese caso usar `datetime`.
- Toda relación con integridad referencial lleva **foreign key real** en la
  migración (`->foreign()->references()->on()->onDelete(...)`), no solo la
  convención de nombre de columna.
- Índices: toda columna usada en `WHERE`, `JOIN` o `ORDER BY` frecuente debe
  tener índice. No indexar "por si acaso" columnas que no se consultan.
- Nunca `SELECT *` en código de producción: seleccionar columnas explícitas.
- Operaciones que tocan más de una tabla / con efectos secundarios (pagos,
  transferencias, creación de registros relacionados) van dentro de
  `DB::transaction()`.
- Migraciones: siempre reversibles (`down()` implementado correctamente).
  Nunca modificar una migración ya ejecutada en otros entornos: crear una
  migración nueva.

## 2. Eloquent y consultas

- Nunca concatenar input de usuario en queries. Usar siempre bindings
  (Eloquent, Query Builder con `?`/named bindings, o `DB::raw()` SOLO con
  valores ya sanitizados/controlados, jamás con input directo del usuario).
- Todo modelo con `$fillable` explícito (lista blanca). Prohibido `$guarded = []`
  en modelos que reciben input de formularios/API.
- Relaciones Eloquent (`hasMany`, `belongsTo`, etc.) solo se agregan si están
  verificadas contra las foreign keys reales de las migraciones.
- Evitar el problema N+1: usar `with()` / `load()` cuando se accede a
  relaciones dentro de loops o listados.
- Paginar siempre listados que puedan crecer (`paginate()`, nunca `get()` sin límite en endpoints públicos).

## 3. Validación y autorización

- Toda entrada de usuario se valida con **Form Request classes**
  (`php artisan make:request`), no validación inline en el controlador salvo casos triviales.
- Reglas de validación explícitas por tipo real de dato (`integer`, `email`,
  `exists:tabla,columna`, `max:`, etc.), nunca `sometimes` o reglas laxas "para que pase".
- Autorización con **Policies** o **Gates**, nunca `if ($user->role == 'admin')`
  disperso en controladores. Un solo lugar de verdad por recurso.
- Todo endpoint que modifica datos debe verificar autorización explícitamente,
  incluso si "solo lo usa el admin" — nunca confiar en que el frontend oculta el botón.

## 4. Seguridad (obligatorio, sistema gubernamental)

- CSRF activo en todo formulario web (Laravel lo trae por defecto: no desactivar).
- Rate limiting (`throttle:`) en endpoints de login, recuperación de contraseña
  y APIs públicas.
- Contraseñas: `Hash::make()` (bcrypt/argon2 de Laravel), nunca md5/sha1 ni texto plano.
- Datos sensibles (documentos de identidad, datos personales protegidos por
  ley) se marcan y, si aplica, se encriptan en reposo (`encrypted` cast de Eloquent).
- Logs de auditoría en acciones críticas: quién hizo qué y cuándo (crear,
  modificar, eliminar registros sensibles). No loguear contraseñas, tokens ni datos sensibles en texto plano.
- Nunca exponer `.env`, stack traces completos, ni mensajes de error de
  base de datos crudos al usuario final. `APP_DEBUG=false` en producción.
- Prohibido dejar rutas, controladores o comandos de debug/test accesibles en producción.
- Subida de archivos: validar tipo real (mime), tamaño, y nunca ejecutar/servir
  directamente archivos subidos por el usuario desde una ruta ejecutable.

## 5. Estructura y estilo

- PSR-12 para el código PHP.
- Lógica de negocio compleja va en **Service classes** o **Actions**, no
  amontonada en el controlador. Controladores delgados.
- Nombres en inglés para código (clases, métodos, variables); nombres de
  tablas/columnas siguiendo la convención ya usada en el proyecto (revisar
  antes de asumir snake_case vs camelCase).
- PHPDoc en métodos públicos no triviales, breve y útil, no relleno.

## 6. Testing

- Toda entrega de código con lógica de negocio, seguridad o cambios de esquema
  lleva pruebas automatizadas con **Pest** (feature tests preferentemente).
- Crear tests con `php artisan make:test --pest {Name}Test` (sin el prefijo
  `Feature/`). Ejecutar con `vendor/bin/pest` o `php artisan test --compact`.
- Nunca borrar ni comentar tests existentes sin aprobación explícita.
- Cobertura obligatoria — sistema gubernamental, la seguridad se testea:
  - Autorización y compartimentos estancos (RF-03): un operador recibe `403`
    ante expedientes ajenos o sin asignación activa.
  - Transiciones de estado y cálculo de plazos (días hábiles reales con feriados).
  - Cadena de custodia: `hash_anterior`/`hash_actuado` encadenados e
    inmutabilidad de `actuados` (UPDATE/DELETE bloqueados por trigger → el test
    debe esperar que esa operación falle).
  - Autenticación y auditoría de sesiones (`sesiones_acceso`).
- Los tests no dependen de red, hora local ni datos "de memoria": usar
  factories/seeders controlados y `RefreshDatabase`.
- Antes de declarar una tarea terminada, correr la suite completa:
  `php artisan test --compact`.