<?php

/**
 * Flujos procesales operativos — propiedad de BRUNO (COORDINACION §2.1).
 * Admisibilidad, impugnación, planificación, ampliación, cierre,
 * descargos, enmienda (CTR-09) e incompetencia (CTR-10).
 */

use App\Http\Controllers\AmpliacionController;
use App\Http\Controllers\CierreExpedienteController;
use App\Http\Controllers\DescargoFinancieroController;
use App\Http\Controllers\EvaluacionAdmisibilidadController;
use App\Http\Controllers\ImpugnacionController;
use App\Http\Controllers\PlanificacionController;
use App\Http\Controllers\TransparenciaController;
use Illuminate\Support\Facades\Route;

// Evaluación de admisibilidad
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

// Cierre y salida institucional (E10-S1/S2, RN-09/RN-12)
Route::post('/expedientes/{expediente}/cierre/visto-bueno', [CierreExpedienteController::class, 'aprobarVistoBueno']);
Route::post('/expedientes/{expediente}/cierre/reparto', [CierreExpedienteController::class, 'ejecutarReparto']);

// Derivación por incompetencia vía Transparencia (E5-S5, RN-09)
Route::post('/expedientes/{expediente}/derivacion-transparencia', [TransparenciaController::class, 'derivar']);
Route::post('/expedientes/{expediente}/transparencia/remitir', [TransparenciaController::class, 'remitir']);

// Descargos de auditoría financiera (E7-S*, RN-09, AC055)
Route::post('/expedientes/{expediente}/descargos/comunicar', [DescargoFinancieroController::class, 'comunicar']);
Route::post('/expedientes/{expediente}/descargos/recibir', [DescargoFinancieroController::class, 'recibir']);
