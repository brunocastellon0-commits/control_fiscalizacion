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


use App\Http\Controllers\Administrador\AdminDashboardController;

// Ruta pública para iniciar sesión (con rate limiting anti fuerza bruta)
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

// Rutas protegidas por Sanctum + rate limiting global de API
Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/bandeja/sorteo', [ExpedienteController::class, 'bandejaSorteo']);
    Route::post('/bandeja/sorteo/todos', [ExpedienteController::class, 'sortearTodos']);
    Route::get('/bandeja', [ExpedienteController::class, 'bandejaOperador']);

    Route::get('/usuarios', [UsuarioController::class, 'indexOperativos']);
    Route::post('/usuarios/{usuario}/inactivar', [UsuarioController::class, 'inactivar']);

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
    // -------------------------------------
    //* ADMINISTRADOR
    // -------------------------------------
    Route::get('/admin/dashboard', [AdminDashboardController::class, 'index']);
    
    // Gestión de usuarios (RF Administrador): listado completo sin las
    // restricciones de /usuarios (que es solo para el sorteo de la
    // Encargada), creación, edición y activación/inactivación.
    Route::get('/admin/usuarios', [AdminUsuariosController::class, 'index']);
    Route::post('/admin/usuarios', [AdminUsuariosController::class, 'store']);
    Route::put('/admin/usuarios/{usuario}', [AdminUsuariosController::class, 'update']);
    Route::post('/admin/usuarios/{usuario}/activar', [AdminUsuariosController::class, 'activar']);
    Route::post('/admin/usuarios/{usuario}/inactivar', [UsuarioController::class, 'inactivar']);
});
