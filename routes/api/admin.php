<?php

/**
 * Panel del Administrador — propiedad de BRAYAN (COORDINACION §2.1).
 * Usuarios, feriados, monitoreo y catálogo de requisitos admin.
 * Migrado desde routes/api.php en la tarea B0.1 (Bruno); a partir de ese
 * cambio, solo Brayan edita este archivo.
 */

use App\Http\Controllers\Administrador\AdminDashboardController;
use App\Http\Controllers\Administrador\AdminFeriadosController;
use App\Http\Controllers\Administrador\AdminMonitoreoController;
use App\Http\Controllers\Administrador\AdminUsuariosController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

// Usuarios operativos (bandejas) y su inactivación
Route::get('/usuarios', [UsuarioController::class, 'indexOperativos']);
Route::post('/usuarios/{usuario}/inactivar', [UsuarioController::class, 'inactivar']);

// Dashboard del panel admin
Route::get('/admin/dashboard', [AdminDashboardController::class, 'index']);

// Gestión de usuarios (RF Administrador)
Route::get('/admin/usuarios', [AdminUsuariosController::class, 'index']);
Route::post('/admin/usuarios', [AdminUsuariosController::class, 'store']);
Route::put('/admin/usuarios/{usuario}', [AdminUsuariosController::class, 'update']);
Route::post('/admin/usuarios/{usuario}/activar', [AdminUsuariosController::class, 'activar']);
Route::post('/admin/usuarios/{usuario}/inactivar', [UsuarioController::class, 'inactivar']);

// Gestión de feriados
Route::get('/admin/feriados', [AdminFeriadosController::class, 'index']);
Route::post('/admin/feriados', [AdminFeriadosController::class, 'store']);
Route::put('/admin/feriados/{feriado}', [AdminFeriadosController::class, 'update']);
Route::delete('/admin/feriados/{feriado}', [AdminFeriadosController::class, 'destroy']);

// Monitoreo administrativo
Route::get('/admin/monitoreo', [AdminMonitoreoController::class, 'index']);
