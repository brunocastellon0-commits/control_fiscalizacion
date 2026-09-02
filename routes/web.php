<?php

use App\Http\Controllers\WorkstationController;
use Illuminate\Support\Facades\Route;

Route::get('/login', function () {
    return view('auth.login');
})->name('login');

// Workstation (SPA stateful): protegida por sesión del guard `web`.
// Cada ruta valida la autorización por rol mediante Policy.
Route::middleware('auth')->group(function () {
    Route::get('/expedientes', [WorkstationController::class, 'bandejaOperador'])->name('expedientes.bandeja');
    Route::get('/bandeja/sorteo', [WorkstationController::class, 'bandejaSorteo'])->name('expedientes.bandeja-sorteo');
    Route::get('/expedientes/nuevo', [WorkstationController::class, 'apertura'])->name('expedientes.apertura');
    Route::get('/expedientes/{expediente}', [WorkstationController::class, 'detalle'])->name('expedientes.detalle');
});
