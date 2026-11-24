<?php

namespace App\Http\Controllers\Encargada;

use App\Http\Controllers\Controller;
use App\Models\Expediente;
use Illuminate\Contracts\View\View;

class EncargadaHistorialController extends Controller
{
    public function index(): View
    {
        $this->authorize('historialGeneralEncargada', Expediente::class);

        return view('encargada.historial');
    }

    public function show(Expediente $expediente): View
    {
        $this->authorize('verDetalleHistorialEncargada', $expediente);

        return view('encargada.historial-detalle', [
            'expedienteId' => $expediente->id,
        ]);
    }
}
