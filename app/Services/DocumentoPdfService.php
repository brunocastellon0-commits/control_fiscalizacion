<?php

namespace App\Services;

use App\Models\Expediente;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Infraestructura compartida para los PDF institucionales del expediente.
 *
 * Los documentos se guardan en el disco privado `local`; la base de datos
 * conserva únicamente la ruta relativa. La descarga siempre pasa por un
 * controlador que autoriza con una ability dedicada.
 */
class DocumentoPdfService
{
    public const DISCO = 'local';

    /**
     * Devuelve el emblema institucional embebido para DomPDF.
     *
     * DomPDF exige la extensión GD para procesar PNG. Si no está disponible en
     * el servidor, se usa el emblema vectorial SVG (sin GD) para que las
     * descargas PDF sigan siendo operativas.
     */
    public function logoBase64(): ?string
    {
        $usaGd = extension_loaded('gd');

        $ruta = public_path($usaGd ? 'images/logoconsejo_sinfondo.png' : 'images/escudo-cm.svg');

        if (! is_file($ruta)) {
            return null;
        }

        $mime = $usaGd ? 'image/png' : 'image/svg+xml';

        return 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($ruta));
    }

    /**
     * Sello de verificación del documento. No reemplaza la cadena de custodia
     * de los actuados; solo permite contrastar la constancia impresa.
     *
     * @param  array<int, int|string|null>  $partesAdicionales
     */
    public function sello(Expediente $expediente, array $partesAdicionales = []): string
    {
        $ultimoHash = $expediente->actuados()->latest('id')->value('hash_actuado');

        $partes = array_merge([
            $expediente->id,
            $expediente->nurej_code,
            $expediente->fecha_ingreso?->toIso8601String() ?? '',
        ], $partesAdicionales, [
            $ultimoHash ?? '',
        ]);

        return strtoupper(substr(hash('sha256', implode('|', $partes)), 0, 32));
    }

    /**
     * Renderiza una vista Blade a PDF A4 vertical.
     *
     * @param  array<string, mixed>  $datos
     */
    public function renderizar(string $vista, array $datos): string
    {
        return Pdf::loadView($vista, $datos)->setPaper('a4', 'portrait')->output();
    }

    /**
     * Guarda el PDF en almacenamiento privado y devuelve la ruta relativa.
     */
    public function guardar(Expediente $expediente, string $carpeta, string $nombreArchivo, string $contenido): string
    {
        $ruta = trim($carpeta, '/').'/'.$expediente->id.'/'.$nombreArchivo;

        Storage::disk(self::DISCO)->put($ruta, $contenido);

        return $ruta;
    }

    public function eliminar(?string $ruta): void
    {
        if (blank($ruta)) {
            return;
        }

        Storage::disk(self::DISCO)->delete($ruta);
    }

    public function descargar(?string $ruta, string $nombreArchivo): StreamedResponse
    {
        abort_if(blank($ruta) || ! Storage::disk(self::DISCO)->exists($ruta), 404, 'El documento no está disponible.');

        return Storage::disk(self::DISCO)->download($ruta, $nombreArchivo);
    }

    public function mostrar(?string $ruta, string $nombreArchivo): StreamedResponse
    {
        abort_if(blank($ruta) || ! Storage::disk(self::DISCO)->exists($ruta), 404, 'El documento no está disponible.');

        return Storage::disk(self::DISCO)->response(
            $ruta,
            $nombreArchivo,
            ['Content-Type' => 'application/pdf'],
        );
    }

    public function nombreBoletaApertura(Expediente $expediente): string
    {
        return 'boleta-apertura-'.$this->codigoSeguro($expediente).'.pdf';
    }

    public function nombreFichaSorteo(Expediente $expediente): string
    {
        return 'ficha-sorteo-'.$this->codigoSeguro($expediente).'.pdf';
    }

    public function rutaBoletaApertura(Expediente $expediente): string
    {
        return 'boletas-apertura/'.$expediente->id.'/'.$this->nombreBoletaApertura($expediente);
    }

    public function rutaFichaSorteo(Expediente $expediente): string
    {
        return 'fichas-sorteo/'.$expediente->id.'/'.$this->nombreFichaSorteo($expediente);
    }

    protected function codigoSeguro(Expediente $expediente): string
    {
        return (string) preg_replace('/[^A-Za-z0-9_-]/', '-', $expediente->nurej_code);
    }
}
