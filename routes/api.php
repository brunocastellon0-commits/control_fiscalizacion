<?php

use App\Http\Controllers\ActuadoController;
use App\Http\Controllers\AdjuntoController;
use App\Http\Controllers\AmpliacionController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogoActuadoController;
use App\Http\Controllers\CatalogoEstadoController;
use App\Http\Controllers\CierreExpedienteController;
use App\Http\Controllers\DescargoFinancieroController;
use App\Http\Controllers\EvaluacionAdmisibilidadController;
use App\Http\Controllers\ExpedienteController;
use App\Http\Controllers\ImpugnacionController;
use App\Http\Controllers\PlanificacionController;
use App\Http\Controllers\ReglamentoController;
use App\Http\Controllers\TransparenciaController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

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
    Route::get('/expedientes/{expediente}/requisitos', [EvaluacionAdmisibilidadController::class, 'requisitos']);
    Route::post('/expedientes/{expediente}/evaluacion', [EvaluacionAdmisibilidadController::class, 'store']);

    // Impugnaciones de rechazo (RN-08)
    Route::post('/expedientes/{expediente}/impugnacion/remitir', [ImpugnacionController::class, 'remitir']);
    Route::post('/expedientes/{expediente}/impugnacion/resolver', [ImpugnacionController::class, 'resolver']);

    // Planificación, Visto Bueno y Devolución (US-2.4 / US-2.5)
    Route::post('/expedientes/{expediente}/planificacion', [PlanificacionController::class, 'store']);
    Route::post('/expedientes/{expediente}/planificacion/visto-bueno', [PlanificacionController::class, 'vistoBueno']);
    Route::post('/expedientes/{expediente}/planificacion/devolver', [PlanificacionController::class, 'devolver']);

    // Ampliación de plazo en ejecución (US-2.6, solo AC022)
    Route::post('/expedientes/{expediente}/ampliacion', [AmpliacionController::class, 'solicitar']);
    Route::post('/expedientes/{expediente}/ampliacion/aprobar', [AmpliacionController::class, 'aprobar']);

    // Derivación NUREJ Hijo (E9-S1, RN-10)
    Route::post('/expedientes/{expediente}/nurej-hijo', [ExpedienteController::class, 'derivarNurejHijo']);

    // Cierre y salida institucional (E10-S1/S2, RN-09/RN-12)
    Route::post('/expedientes/{expediente}/cierre/visto-bueno', [CierreExpedienteController::class, 'aprobarVistoBueno']);
    Route::post('/expedientes/{expediente}/cierre/reparto', [CierreExpedienteController::class, 'ejecutarReparto']);

    // Derivación por incompetencia vía Transparencia (E5-S5, RN-09)
    Route::post('/expedientes/{expediente}/derivacion-transparencia', [TransparenciaController::class, 'derivar']);
    Route::post('/expedientes/{expediente}/transparencia/remitir', [TransparenciaController::class, 'remitir']);

    // Descargos de auditoría financiera (E7-S*, RN-09, AC055)
    Route::post('/expedientes/{expediente}/descargos/comunicar', [DescargoFinancieroController::class, 'comunicar']);
    Route::post('/expedientes/{expediente}/descargos/recibir', [DescargoFinancieroController::class, 'recibir']);
});
