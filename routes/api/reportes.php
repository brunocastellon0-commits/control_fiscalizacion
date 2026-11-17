<?php

use App\Http\Controllers\ReporteController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| REPORTES SRS (RF-R01 a RF-R09) — CTR-08 / exportaciones
|--------------------------------------------------------------------------
| Rutas propiedad de Brayan. El cargador `routes/api.php` las incluye dentro
| del grupo `auth:sanctum` + `throttle:api`.
|
| `/api/reportes/{codigo}` (CTR-08) pertenece a Bruno (`routes/api/core.php`).
| Aquí solo se publican el respaldo local de datos, las descargas y la
| carátula oficial para no colisionar con ese contrato.
*/

Route::get('/reportes/caratula/{expediente}/pdf', [ReporteController::class, 'caratulaPdf']);

Route::get('/reportes/{codigo}/datos', [ReporteController::class, 'datos']);
Route::get('/reportes/{codigo}/exportar/excel', [ReporteController::class, 'exportarExcel']);
Route::get('/reportes/{codigo}/exportar/pdf', [ReporteController::class, 'exportarPdf']);
