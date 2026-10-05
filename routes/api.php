<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

// Ruta pública para iniciar sesión (con rate limiting anti fuerza bruta)
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

// Rutas protegidas por Sanctum + rate limiting global de API.
// Partición modular (COORDINACION §2.1): este archivo es solo el loader;
// cada desarrollador edita únicamente sus archivos en routes/api/.
Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    require __DIR__.'/api/core.php';          // Bruno
    require __DIR__.'/api/operativo.php';     // Bruno
    require __DIR__.'/api/admin.php';         // Brayan
    require __DIR__.'/api/reportes.php';      // Brayan
    require __DIR__.'/api/notificaciones.php'; // Brayan
});
