<?php

namespace App\Http\Controllers;

use App\Models\Expediente;
use Illuminate\Contracts\View\View;

class WorkstationController extends Controller
{
    /**
     * Bandeja de expedientes asignados al operador (roles operativos).
     */
    public function bandejaOperador(): View
    {
        $this->authorize('operadorBandeja', Expediente::class);

        return view('expedientes.bandeja-operador');
    }

    /**
     * Bandeja de sorteo de la Encargada.
     */
    public function bandejaSorteo(): View
    {
        $this->authorize('bandejaSorteo', Expediente::class);

        return view('expedientes.bandeja-sorteo');
    }

    /**
     * Historial de lectura de las causas aperturadas por la Ventanilla.
     */
    public function historialAperturas(): View
    {
        $this->authorize('historialAperturas', Expediente::class);

        return view('expedientes.ventanilla-aperturas');
    }

    /**
     * Detalle de solo lectura de una causa aperturada por la Ventanilla.
     */
    public function detalleApertura(Expediente $expediente): View
    {
        $this->authorize('verDetalleApertura', $expediente);

        return view('expedientes.ventanilla-apertura-detalle', [
            'expedienteId' => $expediente->id,
        ]);
    }

    /**
     * Historial de solo lectura de los operativos (Técnico y auditores).
     */
    public function historialOperativo(): View
    {
        $this->authorize('historialAsignaciones', Expediente::class);

        return view('expedientes.historial-operativo');
    }

    /**
     * Detalle de consulta de un expediente del historial operativo.
     */
    public function detalleHistorialOperativo(Expediente $expediente): View
    {
        $this->authorize('verDetalleHistorialAsignado', $expediente);

        return view('expedientes.historial-operativo-detalle', [
            'expedienteId' => $expediente->id,
        ]);
    }

    /**
     * Detalle de un expediente (RF-03): solo asignado activo o Encargada.
     */
    public function detalle(Expediente $expediente): View
    {
        $this->authorize('view', $expediente);

        return view('expedientes.detalle', [
            'expedienteId' => $expediente->id,
        ]);
    }

    /**
     * Apertura de causa nueva (rol VENTANILLA).
     */
    public function apertura(): View
    {
        $this->authorize('aperturaCausa', Expediente::class);

        return view('expedientes.apertura');
    }
}
