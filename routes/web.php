<?php

use App\Http\Controllers\WorkstationController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Administrador\DashboardController;
use App\Http\Middleware\EnsureAdmin;

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

Route::middleware('auth')->prefix('administrador')->group(function () {
    Route::get('/dashboard', function () {
        return view('administrador.dashboard');
    })->name('administrador.dashboard');
});
/*
|--------------------------------------------------------------------------
| ADMINISTRADOR
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', EnsureAdmin::class])
    ->prefix('administrador')
    ->name('administrador.')
    ->group(function () {

        Route::get('/dashboard', [DashboardController::class, 'index'])
            ->name('dashboard');

    });
Route::middleware(['auth'])->prefix('administrador')->group(function () {

    Route::get('/dashboard', function () {
        return view('administrador.dashboard');
    })->name('administrador.dashboard');

    Route::get('/usuarios', function () {
        return view('administrador.usuarios');
    })->name('administrador.usuarios');

    Route::get('/feriados', function () {
        return view('administrador.feriados');
    })->name('administrador.feriados');

    Route::get('/parametros', function () {
        return view('administrador.parametros');
    })->name('administrador.parametros');

    Route::get('/monitoreo', function () {
        return view('administrador.monitoreo');
    })->name('administrador.monitoreo');

});