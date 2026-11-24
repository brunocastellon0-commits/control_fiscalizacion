<?php

namespace App\Services;

use App\Models\Actuado;
use App\Models\Expediente;
use App\Models\Usuario;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Ficha informativa de la asignación producida por el sorteo. Se entrega a la
 * Encargada junto con el papeleo físico y no sustituye al actuado de sorteo.
 */
class FichaSorteoService
{
    public function __construct(
        protected DocumentoPdfService $documentoPdf,
    ) {}

    /**
     * Genera, guarda y vincula la ficha al expediente.
     *
     * Debe invocarse dentro de la transacción del sorteo: si falla la
     * persistencia de la ruta, se elimina el archivo y el caller revierte la
     * asignación completa.
     */
    public function generar(
        Expediente $expediente,
        Usuario $ganador,
        Usuario $encargada,
        ?string $descripcion,
        Actuado $actuado,
    ): string {
        $expediente->loadMissing([
            'reglamento',
            'estadoActual',
            'creador',
            'partesVigentes',
        ]);
        $ganador->loadMissing('rol');

        $sello = $this->documentoPdf->sello($expediente, [
            'FICHA_SORTEO',
            $ganador->id,
            $actuado->id,
            $actuado->hash_actuado,
        ]);

        $contenido = $this->documentoPdf->renderizar('expedientes.ficha-sorteo-pdf', [
            'expediente' => $expediente,
            'ganador' => $ganador,
            'encargada' => $encargada,
            'descripcion' => $descripcion,
            'actuado' => $actuado,
            'sello' => $sello,
            'logo' => $this->documentoPdf->logoBase64(),
            'generadoEn' => now(),
        ]);

        $ruta = $this->documentoPdf->rutaFichaSorteo($expediente);

        Storage::disk(DocumentoPdfService::DISCO)->put($ruta, $contenido);

        try {
            $expediente->forceFill(['ficha_sorteo_path' => $ruta])->save();
        } catch (Throwable $e) {
            $this->documentoPdf->eliminar($ruta);

            throw $e;
        }

        return $ruta;
    }

    /**
     * Reconstruye la ficha cuando el PDF no está disponible, usando el último
     * sorteo y la asignación registrada.
     */
    public function regenerar(Expediente $expediente, Usuario $encargada): string
    {
        $actuado = $expediente->actuados()
            ->whereHas('tipoActuado', fn ($query) => $query->where('codigo', 'ACT_SORTEO_INICIAL'))
            ->latest('id')
            ->first();

        $asignacion = $expediente->asignaciones()
            ->with(['usuario', 'rol'])
            ->latest('id')
            ->first();

        if ($actuado === null || $asignacion?->usuario === null) {
            throw ValidationException::withMessages([
                'ficha_sorteo' => 'El expediente todavía no tiene un sorteo registrado.',
            ]);
        }

        $descripcion = $actuado->contenido['descripcion'] ?? null;

        return $this->generar(
            expediente: $expediente,
            ganador: $asignacion->usuario,
            encargada: $encargada,
            descripcion: is_string($descripcion) ? $descripcion : null,
            actuado: $actuado,
        );
    }

    public function descargar(Expediente $expediente): StreamedResponse
    {
        return $this->documentoPdf->descargar(
            $expediente->ficha_sorteo_path,
            $this->documentoPdf->nombreFichaSorteo($expediente),
        );
    }

    public function mostrar(Expediente $expediente): StreamedResponse
    {
        return $this->documentoPdf->mostrar(
            $expediente->ficha_sorteo_path,
            $this->documentoPdf->nombreFichaSorteo($expediente),
        );
    }

    /**
     * Vista HTML de la ficha para imprimir desde el navegador.
     *
     * Reconstruye los mismos datos usados al generar el PDF y reutiliza la
     * plantilla Blade institucional, sin reemplazar la descarga attachment.
     */
    public function vistaImpresion(Expediente $expediente): View
    {
        $expediente->loadMissing([
            'reglamento',
            'estadoActual',
            'creador',
            'partesVigentes',
        ]);

        $actuado = $expediente->actuados()
            ->whereHas('tipoActuado', fn ($query) => $query->where('codigo', 'ACT_SORTEO_INICIAL'))
            ->latest('id')
            ->first();

        $asignacion = $expediente->asignaciones()
            ->with('usuario.rol')
            ->latest('id')
            ->first();

        abort_unless(
            $actuado !== null && $asignacion?->usuario !== null,
            404,
            'La ficha de sorteo todavía no está disponible.',
        );

        $ganador = $asignacion->usuario;
        $ganador->loadMissing('rol');

        $descripcion = $actuado->contenido['descripcion'] ?? null;

        return view('expedientes.ficha-sorteo-pdf', [
            'expediente' => $expediente,
            'ganador' => $ganador,
            'encargada' => $actuado->usuario ?? $ganador,
            'descripcion' => is_string($descripcion) ? $descripcion : null,
            'actuado' => $actuado,
            'sello' => '',
            'logo' => null,
            'generadoEn' => now(),
        ]);
    }

    public function eliminar(?string $ruta): void
    {
        $this->documentoPdf->eliminar($ruta);
    }
}
