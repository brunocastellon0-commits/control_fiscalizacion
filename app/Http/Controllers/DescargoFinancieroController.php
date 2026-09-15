<?php

namespace App\Http\Controllers;

use App\Http\Requests\ComunicarHallazgosRequest;
use App\Http\Requests\RecibirDescargosRequest;
use App\Http\Resources\ActuadoResource;
use App\Models\Actuado;
use App\Models\Expediente;
use App\Services\DescargoFinancieroService;
use Illuminate\Http\JsonResponse;

class DescargoFinancieroController extends Controller
{
    public function __construct(
        protected DescargoFinancieroService $descargoFinancieroService,
    ) {}

    /**
     * E7-S* (RN-09): el auditor financiero comunica los hallazgos; congela el
     * reloj de ejecución y abre el sub-reloj de 5 días hábiles para descargos.
     */
    public function comunicar(ComunicarHallazgosRequest $request, Expediente $expediente): JsonResponse
    {
        $actuado = $this->descargoFinancieroService->comunicarHallazgos(
            expediente: $expediente,
            auditor: $request->user(),
            descripcion: $request->input('descripcion'),
            adjunto: $request->file('adjunto'),
            ipOrigen: $request->ip(),
        );

        return (new ActuadoResource($this->cargarRelaciones($actuado)))->response()->setStatusCode(201);
    }

    /**
     * E7-S* (RN-09): el auditor financiero recibe los descargos; cierra el
     * sub-reloj y reanuda el reloj principal con el límite recalculado.
     */
    public function recibir(RecibirDescargosRequest $request, Expediente $expediente): JsonResponse
    {
        $actuado = $this->descargoFinancieroService->recibirDescargos(
            expediente: $expediente,
            auditor: $request->user(),
            descripcion: $request->input('descripcion'),
            adjunto: $request->file('adjunto'),
            ipOrigen: $request->ip(),
        );

        return (new ActuadoResource($this->cargarRelaciones($actuado)))->response()->setStatusCode(201);
    }

    /**
     * Relaciones eager-loaded para la representación del actuado emitido.
     */
    protected function cargarRelaciones(Actuado $actuado): Actuado
    {
        return $actuado->load([
            'tipoActuado',
            'estadoAnterior',
            'estadoNuevo',
            'usuario',
            'adjuntos',
        ]);
    }
}
