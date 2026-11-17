# CHANGELOG — Sistema de Control y Fiscalización

**Consejo de la Magistratura — Cochabamba**
Formato basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/)
y versionado semántico institucional.

## [1.0.0] — 2026-11-16

### Agregado

- **B1.9 — CRUD de requisitos de admisibilidad.** Pantalla administrativa,
  API `/api/admin/requisitos`, Form Requests de alta/edición, validación de
  reglamento y borrado lógico (`activo = false`). Incluye prueba
  `AdminRequisitosCrudTest`.
- **B5.1 — Reportes institucionales RF-R01 a RF-R09.** Pantalla `/reportes`
  con pestañas, filtros por fecha, usuario y estado, y tabla reactiva para los
  9 reportes normativos. El menú oculta el enlace al rol `ADMIN` y el
  controlador devuelve `403` a usuarios `ADMIN` o inactivos.
- **B5.2 — Exportación XLSX y PDF.** Se incorporaron
  `maatwebsite/excel` (4.x), `barryvdh/laravel-dompdf` (3.x) y
  `phpoffice/phpspreadsheet`. Endpoints de descarga para los 9 reportes con
  formato institucional y los mismos filtros de la pantalla.
- **B5.3 — Carátula oficial / Foja Cero.** PDF A4 con NUREJ padre, NUREJ
  derivados, fecha de ingreso, vía, reglamento, estado, partes vigentes y sello
  de custodia SHA-256. Endpoint
  `GET /api/reportes/caratula/{expediente}/pdf`.
- **B9.1–B9.5 — Paquete de entrega.**
  - Guía técnica actualizada con las rutas, módulos y contratos de los
    entregables B1.9, B5.1–B5.3 y B9.1–B9.5.
  - `.env.example` documentado con `APP_TIMEZONE`, sesión/Sanctum, MySQL,
    cache, colas, correo y filesystem.
  - Favicon institucional (`public/favicon.ico`), icono PNG para
    `apple-touch-icon` y `theme-color` institucional.
  - Tooltips de ayuda contextual en filtros de reportes, modal de requisitos y
    modales de actuado, enmienda y remisión/recepción.
- Pruebas nuevas: `ReportesPantallaTest`, `ExportacionReportesTest` y
  `CaratulaOficialPdfTest`.

### Cambiado

- `config/app.php` lee `APP_TIMEZONE`; el valor recomendado para el reloj
  procesual es `America/La_Paz`.
- `phpunit.xml` fija `memory_limit=512M` para que la suite completa pueda
  generar los PDF/XLSX de prueba sin agotar el límite PHP por defecto.
- El sidebar incorpora la sección **Reportes** para todos los roles excepto
  `ADMIN`.
- Los reportes usan `ReporteDatosService` como respaldo local del contrato
  CTR-08 mientras `ReportesQueryService` y `routes/api/core.php` no estén
  integrados.
- Actualización de dependencias de Composer y `composer.lock`.

### Corregido

- **Seguridad:** actualización de `symfony/yaml` de `v8.1.5` a `v8.1.11` para
  remediar CVE-2026-123792 y CVE-2026-109413. `composer audit` queda sin
  avisos.
- **Compatibilidad DomPDF sin GD.** Si la extensión `gd` no está habilitada,
  las exportaciones PDF usan el emblema vectorial
  `public/images/escudo-cm.svg` en lugar de fallar al procesar el PNG
  institucional.

### Notas de despliegue

- Ejecutar `composer install --no-interaction` y `php artisan migrate --force`.
- Configurar `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` con HTTPS y
  `SESSION_SECURE_COOKIE=true`.
- Ajustar `SANCTUM_STATEFUL_DOMAINS` al dominio institucional exacto.
- Para el escudo PNG en PDF se recomienda habilitar `ext-gd`; sin GD las
  descargas funcionan con el emblema SVG de respaldo.
- El contrato CTR-08 pertenece a Bruno. El endpoint `/api/reportes/{codigo}/datos`
  es transitorio y debe retirarse cuando CTR-08 esté mergeado.

### Pendientes conocidos

- `ExpedientePolicy::view` conserva un bypass para `ADMIN` sin asignación
  activa; `SecurityCompartimentosTest` falla con `200` en lugar de `403`.
  Corresponde a la tarea B2.0 (backend de Bruno) y no se modificó en esta
  entrega por la regla de propiedad de archivos.
- El respaldo `/datos` de reportes no replica aún el filtrado por
  compartimentos de CTR-08; no debe exponerse fuera de la red institucional.
