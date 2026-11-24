<?php

namespace App\Services;

use App\Models\Expediente;
use App\Models\Usuario;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Boleta de recepción entregada por Ventanilla al ciudadano al aperturar la
 * causa. Es una constancia informativa, no un actuado procesal.
 */
class BoletaAperturaService
{
    public function __construct(
        protected DocumentoPdfService $documentoPdf,
    ) {}

    /**
     * Genera, guarda y vincula la boleta al expediente.
     *
     * Debe invocarse dentro de la transacción de apertura: si falla la
     * persistencia de la ruta, el archivo físico se elimina y el caller
     * revierte la creación completa.
     */
    public function generar(Expediente $expediente, Usuario $ventanilla): string
    {
        $expediente->loadMissing([
            'reglamento',
            'estadoActual',
            'creador',
            'partesVigentes',
        ]);

        $sello = $this->documentoPdf->sello($expediente, [
            'BOLETA_APERTURA',
            $ventanilla->id,
        ]);

        $contenido = $this->documentoPdf->renderizar('expedientes.boleta-apertura-pdf', [
            'expediente' => $expediente,
            'sello' => $sello,
            'logo' => $this->documentoPdf->logoBase64(),
            'generadoPor' => trim(($ventanilla->nombres ?? '').' '.($ventanilla->apellidos ?? '')),
            'generadoEn' => now(),
        ]);

        $ruta = $this->documentoPdf->rutaBoletaApertura($expediente);

        Storage::disk(DocumentoPdfService::DISCO)->put($ruta, $contenido);

        try {
            $expediente->forceFill(['boleta_apertura_path' => $ruta])->save();
        } catch (Throwable $e) {
            $this->documentoPdf->eliminar($ruta);

            throw $e;
        }

        return $ruta;
    }

    public function descargar(Expediente $expediente): StreamedResponse
    {
        return $this->documentoPdf->descargar(
            $expediente->boleta_apertura_path,
            $this->documentoPdf->nombreBoletaApertura($expediente),
        );
    }
}
