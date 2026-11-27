<?php

namespace App\Services;

use App\Models\Actuado;
use App\Models\EvaluacionAdmisibilidad;
use App\Models\Expediente;
use App\Models\Plazo;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;

/**
 * Formato de observación (procedimiento de observación): documento que se
 * entrega al interesado con el detalle de lo observado (texto libre del
 * operador), los requisitos faltantes, el plazo de subsanación y el sello
 * de verificación.
 *
 * On-demand: no se persiste en disco; el PDF se renderiza bajo petición.
 */
class FormatoObservacionService
{
    public const CODIGO_ACT_OBSERVACION = 'ACT_OBSERVACION';

    public function __construct(
        protected DocumentoPdfService $documentoPdf,
    ) {}

    public function descargar(Expediente $expediente, Actuado $actuado): Response
    {
        return Pdf::loadView('expedientes.formato-observacion-pdf', $this->datos($expediente, $actuado))
            ->setPaper('a4', 'portrait')
            ->download($this->nombreFormato($expediente));
    }

    public function vistaImpresion(Expediente $expediente, Actuado $actuado): View
    {
        return view('expedientes.formato-observacion-pdf', $this->datos($expediente, $actuado));
    }

    /**
     * @return array<string, mixed>
     */
    public function datos(Expediente $expediente, Actuado $actuado): array
    {
        $expediente->loadMissing([
            'reglamento',
            'estadoActual',
            'creador',
            'partesVigentes',
        ]);
        $actuado->loadMissing(['tipoActuado', 'usuario.rol']);

        $contenido = is_array($actuado->contenido) ? $actuado->contenido : [];

        $faltantes = EvaluacionAdmisibilidad::where('actuado_id', $actuado->id)
            ->where('cumple', false)
            ->with('requisito')
            ->orderBy('id')
            ->get();

        $plazo = Plazo::where('expediente_id', $expediente->id)
            ->where('actuado_disparador_id', $actuado->id)
            ->where('tipo_plazo', 'SUBSANACION')
            ->latest('id')
            ->first();

        return [
            'expediente' => $expediente,
            'actuado' => $actuado,
            'detalleObservacion' => $contenido['detalle_observacion']
                ?? (is_string($contenido['descripcion'] ?? null) ? $contenido['descripcion'] : null),
            'faltantes' => $faltantes,
            'plazo' => $plazo,
            'sello' => $this->documentoPdf->sello($expediente, [
                'FORMATO_OBSERVACION',
                $actuado->id,
                $actuado->hash_actuado,
            ]),
            'logo' => $this->documentoPdf->logoBase64(),
            'generadoEn' => now(),
        ];
    }

    private function nombreFormato(Expediente $expediente): string
    {
        return 'formato-observacion-'.(string) preg_replace('/[^A-Za-z0-9_-]/', '-', $expediente->nurej_code).'.pdf';
    }
}
