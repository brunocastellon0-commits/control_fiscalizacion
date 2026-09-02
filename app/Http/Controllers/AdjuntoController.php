<?php

namespace App\Http\Controllers;

use App\Models\Adjunto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdjuntoController extends Controller
{
    /**
     * Descarga de un adjunto (RF-03). Solo usuarios con permiso de ver el
     * expediente padre pueden descargar el respaldo del mismo.
     */
    public function descargar(Request $request, Adjunto $adjunto): StreamedResponse
    {
        $expediente = $adjunto->actuado?->expediente;

        abort_if($expediente === null, 404, 'Adjunto sin expediente asociado.');

        $this->authorize('view', $expediente);

        if (! Storage::disk('local')->exists($adjunto->ruta_almacenamiento)) {
            abort(404, 'El archivo adjunto no esta disponible.');
        }

        return Storage::disk('local')->download(
            $adjunto->ruta_almacenamiento,
            $adjunto->nombre_original,
        );
    }
}
