<?php

/**
 * Núcleo del dominio — propiedad de BRUNO (COORDINACION §2.1).
 * Auth me/logout, bandejas, catálogos de soporte, adjuntos, expedientes,
 * actuados, sorteo, NUREJ Hijo, unidades inter (CTR-04/CTR-05) y dashboard
 * de la Encargada (CTR-07).
 */

use App\Http\Controllers\ActuadoController;
use App\Http\Controllers\AdjuntoController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogoActuadoController;
use App\Http\Controllers\CatalogoEstadoController;
use App\Http\Controllers\DocumentoExpedienteController;
use App\Http\Controllers\Encargada\EncargadaDashboardController;
use App\Http\Controllers\Encargada\EncargadaSupervisionController;
use App\Http\Controllers\ExpedienteController;
use App\Http\Controllers\HistorialEncargadaController;
use App\Http\Controllers\HistorialOperativoController;
use App\Http\Controllers\ReglamentoController;
use App\Http\Controllers\TransferenciaUnidadController;
use App\Http\Controllers\UnidadController;
use Illuminate\Support\Facades\Route;

// Sesión de usuario (Sanctum)
Route::get('/me', [AuthController::class, 'me']);
Route::post('/logout', [AuthController::class, 'logout']);

// Bandejas (operador y sorteo de la Encargada)
Route::get('/bandeja/sorteo', [ExpedienteController::class, 'bandejaSorteo']);
Route::post('/bandeja/sorteo/todos', [ExpedienteController::class, 'sortearTodos']);
Route::get('/bandeja', [ExpedienteController::class, 'bandejaOperador']);

// Historial de solo lectura de la Ventanilla
Route::get('/ventanilla/aperturas', [ExpedienteController::class, 'historialAperturas']);
Route::get('/ventanilla/aperturas/{expediente}', [ExpedienteController::class, 'detalleApertura']);

// Historial de solo lectura de los operativos (Técnico y auditores)
Route::get('/operativo/historial', [HistorialOperativoController::class, 'index']);
Route::get('/operativo/historial/{expediente}', [HistorialOperativoController::class, 'show']);

// Catálogo de reglamentos (lectura para el select)
Route::get('/reglamentos', [ReglamentoController::class, 'index']);

// Catálogo de actuados del rol (lectura para el modal "Emitir actuado")
Route::get('/catalogo/actuados', [CatalogoActuadoController::class, 'index']);

// Catálogo de estados (lectura para filtros de bandeja y detalle)
Route::get('/estados', [CatalogoEstadoController::class, 'index']);
Route::get('/unidades', [UnidadController::class, 'index']);

// Descarga de adjuntos de respaldo (autorizado por RF-03)
Route::get('/adjuntos/{adjunto}/descargar', [AdjuntoController::class, 'descargar']);

// Expedientes (apertura, detalle, sorteo, actuados, NUREJ Hijo)
Route::post('/expedientes', [ExpedienteController::class, 'store']);
Route::get('/expedientes/{expediente}', [ExpedienteController::class, 'show']);
Route::post('/expedientes/{expediente}/sortear', [ExpedienteController::class, 'sortear']);
Route::post('/expedientes/{expediente}/actuados', [ActuadoController::class, 'store']);
Route::post('/expedientes/{expediente}/pasar-fase', [ActuadoController::class, 'pasarFase']);

// Documentos informativos del expediente (boleta de apertura y ficha de sorteo)
Route::get('/expedientes/{expediente}/boleta-apertura', [DocumentoExpedienteController::class, 'boletaApertura']);
Route::get('/expedientes/{expediente}/ficha-sorteo', [DocumentoExpedienteController::class, 'fichaSorteo']);
Route::post('/expedientes/{expediente}/ficha-sorteo/regenerar', [DocumentoExpedienteController::class, 'regenerarFichaSorteo']);
// Procedimiento de observación: formato on-demand del actuado de observación
Route::get('/expedientes/{expediente}/actuados/{actuado}/formato-observacion', [DocumentoExpedienteController::class, 'formatoObservacion']);

// Derivación NUREJ Hijo (E9-S1, RN-10)
Route::post('/expedientes/{expediente}/nurej-hijo', [ExpedienteController::class, 'derivarNurejHijo']);

// Unidades inter: par Remisión/Recepción (RF-05 / CTR-04 y CTR-05, solo Encargada)
Route::post('/expedientes/{expediente}/remitir-unidad', [TransferenciaUnidadController::class, 'remitir']);
Route::post('/expedientes/{expediente}/registrar-recepcion', [TransferenciaUnidadController::class, 'registrarRecepcion']);

// Dashboard operativo de la Encargada
Route::get('/encargada/dashboard', [EncargadaDashboardController::class, 'index']);

// Bandeja de supervisión de la Encargada (cronogramas informativos)
Route::get('/encargada/bandeja-supervision', [EncargadaSupervisionController::class, 'index']);

// Historial general de solo lectura de la Encargada
Route::get('/encargada/historial', [HistorialEncargadaController::class, 'index']);
Route::get('/encargada/historial/{expediente}', [HistorialEncargadaController::class, 'show']);
