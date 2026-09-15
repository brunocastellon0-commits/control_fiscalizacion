<?php

namespace App\Http\Controllers;

use App\Http\Requests\DerivarTransparenciaRequest;
use App\Http\Requests\RemitirTransparenciaRequest;
use App\Http\Resources\ActuadoResource;
use App\Models\Actuado;
use App\Models\Expediente;
use App\Services\TransparenciaService;
use Illuminate\Http\JsonResponse;

class TransparenciaController extends Controller
{
    public function __construct(
        protected TransparenciaService $transparenciaService,
    ) {}

    /**
     * E5-S5: el operador deriva el expediente a la Encargada por incompetencia;
     * congela los relojes y reasigna la bandeja.
     */
    public function derivar(DerivarTransparenciaRequest $request, Expediente $expediente): JsonResponse
    {
        $actuado = $this->transparenciaService->derivarPorIncompetencia(
            expediente: $expediente,
            operador: $request->user(),
            justificacion: $request->input('justificacion'),
            adjunto: $request->file('adjunto'),
            ipOrigen: $request->ip(),
        );

        return (new ActuadoResource($this->cargarRelaciones($actuado)))->response()->setStatusCode(201);
    }

    /**
     * E5-S5: la Encargada remite el NUREJ a Transparencia; queda en
     * DERIVADO_TRANSPARENCIA y sale de los tableros operativos.
     */
    public function remitir(RemitirTransparenciaRequest $request, Expediente $expediente): JsonResponse
    {
        $actuado = $this->transparenciaService->remitirTransparencia(
            expediente: $expediente,
            encargada: $request->user(),
            notaRemision: $request->input('nota_remision'),
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
