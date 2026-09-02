<?php

use App\Http\Controllers\ActuadoController;
use App\Http\Controllers\AdjuntoController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogoActuadoController;
use App\Http\Controllers\CatalogoEstadoController;
use App\Http\Controllers\ExpedienteController;
use App\Http\Controllers\ReglamentoController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

// Ruta pública para iniciar sesión (con rate limiting anti fuerza bruta)
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

// Rutas protegidas por Sanctum + rate limiting global de API
Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/bandeja/sorteo', [ExpedienteController::class, 'bandejaSorteo']);
    Route::get('/bandeja', [ExpedienteController::class, 'bandejaOperador']);

    Route::get('/usuarios', [UsuarioController::class, 'indexOperativos']);

    // Catálogo de reglamentos (lectura para el select)
    Route::get('/reglamentos', [ReglamentoController::class, 'index']);

    // Catálogo de actuados del rol (lectura para el modal "Emitir actuado")
    Route::get('/catalogo/actuados', [CatalogoActuadoController::class, 'index']);

    // Catálogo de estados (lectura para filtros de bandeja y detalle)
    Route::get('/estados', [CatalogoEstadoController::class, 'index']);

    // Descarga de adjuntos de respaldo (autorizado por RF-03)
    Route::get('/adjuntos/{adjunto}/descargar', [AdjuntoController::class, 'descargar']);

    Route::post('/expedientes', [ExpedienteController::class, 'store']);
    Route::get('/expedientes/{expediente}', [ExpedienteController::class, 'show']);
    Route::post('/expedientes/{expediente}/sortear', [ExpedienteController::class, 'sortear']);
    Route::post('/expedientes/{expediente}/actuados', [ActuadoController::class, 'store']);
});
