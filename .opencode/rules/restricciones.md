# Restricciones — LÍMITES DUROS, no sugerencias

Si una acción entra en conflicto con este archivo, este archivo gana.
Ante la duda: pregunta, no asumas.

## 1. Prohibido alucinar sobre la base de datos

- **Nunca** inventar nombres de tablas, columnas, tipos de dato o relaciones.
  Si no se ha verificado en este mismo turno de trabajo contra
  `database/migrations/`, el modelo Eloquent correspondiente, o el resultado
  real de una consulta al esquema, no se asume — se verifica primero.
- Antes de escribir una query, migración o modelo que toque una tabla
  existente: leer la migración real de esa tabla (o listar el esquema con
  `php artisan db:table nombre_tabla` / `SHOW COLUMNS`). No repetir de
  memoria una estructura vista en un turno anterior si ya pasó mucho tiempo
  o hubo cambios en el chat — reverificar si hay la mínima duda.
- Prohibido generar migraciones que modifiquen o borren columnas/tablas
  existentes sin: (1) mostrar antes el diff/cambio propuesto, (2) esperar
  confirmación explícita del usuario.
- Prohibido "arreglar" un error asumiendo que falta una columna o tabla y
  creándola sin confirmar que realmente no existe.
- Si el esquema real y lo que pide el usuario no coinciden, decirlo
  explícitamente en vez de improvisar una solución que "cuadre por las buenas".

## 2. Seguridad — cero excepciones

- Prohibido desactivar CSRF, validaciones, autenticación o autorización
  "temporalmente para que funcione" o "para probar más rápido".
- Prohibido hardcodear credenciales, tokens, API keys o strings de conexión
  en el código. Todo va en `.env` y se accede vía `config()`.
- Prohibido dejar `dd()`, `dump()`, `var_dump()`, `print_r()`,
  `Log::info()` de debug, o rutas de prueba en código que se entrega como final.
- Prohibido construir queries con input de usuario concatenado directamente
  (SQL injection). Si se usa `DB::raw`, debe justificarse y usar bindings.
- Prohibido debilitar reglas de validación o de permisos para "que pase el
  caso de prueba" sin decírselo explícitamente al usuario primero.
- Cualquier cambio en autenticación, autorización, manejo de sesiones,
  encriptación o permisos requiere **confirmación explícita** antes de
  aplicarse, incluso si el usuario solo pidió "arregla este bug".
- Cualquier operación destructiva sobre datos (borrar registros, truncar
  tablas, migraciones `down()` que eliminen columnas con datos) requiere
  confirmación explícita, mostrando antes qué se va a perder.

## 3. Alcance del trabajo

- No modificar archivos fuera de lo pedido. Si al resolver algo se detecta
  que otro archivo también debería cambiar, se informa, no se cambia sin avisar.
- No borrar código, comentarios o configuración existente "para limpiar"
  sin que se haya pedido explícitamente.
- No cambiar la arquitectura del proyecto (patrón usado, estructura de
  carpetas, convenciones ya establecidas) por preferencia propia. Se sigue
  el patrón que ya existe en el proyecto, aunque no sea el ideal en teoría.
- Cambios grandes (varios archivos, refactors, nuevas dependencias): primero
  un plan corto (2-5 líneas) de qué se va a hacer, luego ejecutar.

## 4. Uso de tokens — este modelo es gratuito, no desperdiciar contexto

- No leer archivos completos si solo se necesita una parte: usar
  búsqueda dirigida (grep/ripgrep, o rangos de líneas) en vez de volcar
  archivos enteros al contexto.
- No re-mostrar en la respuesta código que no cambió. Mostrar solo el diff o
  las líneas modificadas.
- No repetir explicaciones ya dadas en el mismo hilo. Ser directo.
- Respuestas técnicas: concisas. Nada de relleno, resúmenes innecesarios,
  ni repetir la pregunta del usuario antes de responder.
- Antes de explorar todo el repositorio "para entender el contexto", ir
  primero a lo específico que pide la tarea. Explorar más solo si es
  realmente necesario para no romper algo.
- No generar código, tests o documentación que no se pidió "por si acaso".
- Si una tarea es ambigua pero de bajo riesgo (naming, formato menor):
  tomar la decisión razonable y avisar en una línea, en vez de gastar un
  intercambio completo preguntando.
- Si una tarea es ambigua y de riesgo alto (seguridad, BD, dinero, datos
  personales): sí preguntar, aunque cueste un intercambio — ahí el costo de
  equivocarse es mayor que el costo de preguntar.

## 5. Checklist mental antes de dar una tarea por terminada

1. ¿Verifiqué el esquema real de BD en vez de asumirlo?
2. ¿Hay input de usuario que llegue a una query sin validar/bindear?
3. ¿Toqué autenticación, autorización o datos sensibles sin confirmación?
4. ¿Dejé algún `dd()`, credencial, o código de debug?
5. ¿Me salí del alcance pedido?
6. ¿La respuesta es tan corta como puede ser sin perder precisión?

Si alguna respuesta es "no lo sé" en los puntos 1-4, no se entrega: se
verifica o se pregunta primero.