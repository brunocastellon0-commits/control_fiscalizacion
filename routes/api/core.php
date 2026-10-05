<?php

/**
 * Núcleo del dominio — propiedad de BRUNO (COORDINACION §2.1).
 * Auth me/logout, bandejas, catálogos de soporte, adjuntos, expedientes,
 * actuados, sorteo, NUREJ Hijo y dashboard de la Encargada (CTR-07).
 */

use App\Http\Controllers\ActuadoController;
use App\Http\Controllers\AdjuntoController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogoActuadoController;
use App\Http\Controllers\CatalogoEstadoController;
use App\Http\Controllers\Encargada\EncargadaDashboardController;
use App\Http\Controllers\ExpedienteController;
use App\Http\Controllers\ReglamentoController;
use Illuminate\Support\Facades\Route;

// Sesión de usuario (Sanctum)
Route::get('/me', [AuthController::class, 'me']);
Route::post('/logout', [AuthController::class, 'logout']);

// Bandejas (operador y sorteo de la Encargada)
Route::get('/bandeja/sorteo', [ExpedienteController::class, 'bandejaSorteo']);
Route::post('/bandeja/sorteo/todos', [ExpedienteController::class, 'sortearTodos']);
Route::get('/bandeja', [ExpedienteController::class, 'bandejaOperador']);

// Catálogo de reglamentos (lectura para el select)
Route::get('/reglamentos', [ReglamentoController::class, 'index']);

// Catálogo de actuados del rol (lectura para el modal "Emitir actuado")
Route::get('/catalogo/actuados', [CatalogoActuadoController::class, 'index']);

// Catálogo de estados (lectura para filtros de bandeja y detalle)
Route::get('/estados', [CatalogoEstadoController::class, 'index']);

// Descarga de adjuntos de respaldo (autorizado por RF-03)
Route::get('/adjuntos/{adjunto}/descargar', [AdjuntoController::class, 'descargar']);

// Expedientes (apertura, detalle, sorteo, actuados, NUREJ Hijo)
Route::post('/expedientes', [ExpedienteController::class, 'store']);
Route::get('/expedientes/{expediente}', [ExpedienteController::class, 'show']);
Route::post('/expedientes/{expediente}/sortear', [ExpedienteController::class, 'sortear']);
Route::post('/expedientes/{expediente}/actuados', [ActuadoController::class, 'store']);

// Derivación NUREJ Hijo (E9-S1, RN-10)
Route::post('/expedientes/{expediente}/nurej-hijo', [ExpedienteController::class, 'derivarNurejHijo']);

// Dashboard operativo de la Encargada
Route::get('/encargada/dashboard', [EncargadaDashboardController::class, 'index']);
