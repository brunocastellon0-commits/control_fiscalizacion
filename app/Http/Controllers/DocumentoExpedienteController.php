<?php

namespace App\Http\Controllers;

use App\Models\Expediente;
use App\Services\BoletaAperturaService;
use App\Services\DocumentoPdfService;
use App\Services\FichaSorteoService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentoExpedienteController extends Controller
{
    public function __construct(
        protected BoletaAperturaService $boletaAperturaService,
        protected FichaSorteoService $fichaSorteoService,
        protected DocumentoPdfService $documentoPdfService,
    ) {}

    public function boletaApertura(Request $request, Expediente $expediente): View|StreamedResponse
    {
        $this->authorize('descargarBoletaApertura', $expediente);

        abort_unless(
            filled($expediente->boleta_apertura_path),
            404,
            'La boleta de apertura todavía no está disponible.',
        );

        if ($request->boolean('impresion')) {
            return $this->boletaAperturaService->vistaImpresion($expediente);
        }

        return $this->boletaAperturaService->descargar($expediente);
    }

    public function fichaSorteo(Request $request, Expediente $expediente): View|StreamedResponse
    {
        $this->authorize('descargarFichaSorteo', $expediente);

        abort_unless(
            filled($expediente->ficha_sorteo_path),
            404,
            'La ficha de sorteo todavía no está disponible.',
        );

        if ($request->boolean('impresion')) {
            return $this->fichaSorteoService->vistaImpresion($expediente);
        }

        return $this->fichaSorteoService->descargar($expediente);
    }

    public function regenerarFichaSorteo(Request $request, Expediente $expediente): JsonResponse
    {
        $this->authorize('descargarFichaSorteo', $expediente);

        $this->fichaSorteoService->regenerar($expediente, $request->user());
        $expediente->refresh();

        return response()->json([
            'message' => 'Ficha de sorteo generada correctamente.',
            'data' => [
                'url' => url('/api/expedientes/'.$expediente->id.'/ficha-sorteo'),
                'nombre' => $this->documentoPdfService->nombreFichaSorteo($expediente),
            ],
        ]);
    }
}
