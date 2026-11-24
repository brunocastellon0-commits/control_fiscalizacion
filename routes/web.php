<?php

use App\Http\Controllers\Administrador\DashboardController;
use App\Http\Controllers\Administrador\FeriadosController;
use App\Http\Controllers\Administrador\MonitoreoController;
use App\Http\Controllers\Administrador\RequisitosController;
use App\Http\Controllers\Administrador\UsuariosController;
use App\Http\Controllers\Encargada\DashboardController as EncargadaDashboardController;
use App\Http\Controllers\Encargada\EncargadaBandejaController;
use App\Http\Controllers\Encargada\EncargadaHistorialController;
use App\Http\Controllers\Financiero\FinancieroDashboardController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\WorkstationController;
use App\Http\Middleware\EnsureAdmin;
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
    Route::get('/ventanilla/aperturas', [WorkstationController::class, 'historialAperturas'])->name('ventanilla.aperturas');
    Route::get('/ventanilla/aperturas/{expediente}', [WorkstationController::class, 'detalleApertura'])->name('ventanilla.aperturas.detalle');
    Route::get('/operativo/historial', [WorkstationController::class, 'historialOperativo'])->name('operativo.historial');
    Route::get('/operativo/historial/{expediente}', [WorkstationController::class, 'detalleHistorialOperativo'])->name('operativo.historial.detalle');
    Route::get('/expedientes/{expediente}', [WorkstationController::class, 'detalle'])->name('expedientes.detalle');
    Route::get('/reportes', [ReporteController::class, 'index'])->name('reportes.index');
});

/*
|--------------------------------------------------------------------------
| ENCARGADA
|--------------------------------------------------------------------------
*/

Route::middleware('auth')
    ->prefix('encargada')
    ->name('encargada.')
    ->group(function () {
        Route::get('/dashboard', [EncargadaDashboardController::class, 'index'])
            ->name('dashboard');
        Route::get('/bandeja-supervision', [EncargadaBandejaController::class, 'index'])
            ->name('bandeja-supervision');
        Route::get('/historial', [EncargadaHistorialController::class, 'index'])
            ->name('historial');
        Route::get('/historial/{expediente}', [EncargadaHistorialController::class, 'show'])
            ->name('historial.detalle');
    });

Route::middleware('auth')
    ->prefix('financiero')
    ->name('financiero.')
    ->group(function () {
        Route::get('/dashboard', [FinancieroDashboardController::class, 'index'])
            ->name('dashboard');
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

        Route::get('/usuarios', [UsuariosController::class, 'index'])
            ->name('usuarios');

        Route::get('/feriados', [FeriadosController::class, 'index'])
            ->name('feriados');

        Route::get('/requisitos', [RequisitosController::class, 'index'])
            ->name('requisitos');

        Route::get('/monitoreo', [MonitoreoController::class, 'index'])
            ->name('monitoreo');

    });

// Route::middleware(['auth'])->prefix('administrador')->group(function () {

//     Route::get('/dashboard', function () {
//         return view('administrador.dashboard');
//     })->name('administrador.dashboard');

//     Route::get('/usuarios', function () {
//         return view('administrador.usuarios');
//     })->name('administrador.usuarios');

//     Route::get('/feriados', function () {
//         return view('administrador.feriados');
//     })->name('administrador.feriados');

//     Route::get('/parametros', function () {
//         return view('administrador.parametros');
//     })->name('administrador.parametros');

//     Route::get('/monitoreo', function () {
//         return view('administrador.monitoreo');
//     })->name('administrador.monitoreo');

// });
