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
     * Apertura de causa nueva (rol TECNICO).
     */
    public function apertura(): View
    {
        $this->authorize('aperturaCausa', Expediente::class);

        return view('expedientes.apertura');
    }
}
