<?php

/**
 * Notificaciones in-app — propiedad de BRAYAN (COORDINACION §2.1).
 * Alertas y notificaciones de plazos (B3.4).
 * Creado vacío por Bruno en B0.1; Brayan agrega sus rutas aquí.
 */

use App\Http\Controllers\NotificacionController;
use Illuminate\Support\Facades\Route;

// Notificaciones in-app (plazos y avisos)
Route::get('/notificaciones/resumen', [NotificacionController::class, 'resumen']);
Route::post('/notificaciones/marcar-leida', [NotificacionController::class, 'marcarLeida']);
