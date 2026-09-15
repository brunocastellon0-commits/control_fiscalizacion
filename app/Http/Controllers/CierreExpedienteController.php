<?php

namespace App\Http\Controllers;

use App\Http\Requests\AprobarVistoBuenoRequest;
use App\Http\Requests\RepartoInstitucionalRequest;
use App\Http\Resources\ActuadoResource;
use App\Models\Actuado;
use App\Models\Expediente;
use App\Services\CierreExpedienteService;
use Illuminate\Http\JsonResponse;

class CierreExpedienteController extends Controller
{
    public function __construct(
        protected CierreExpedienteService $cierreExpedienteService,
    ) {}

    /**
     * E10-S1: la Encargada aprueba el informe final con Visto Bueno Final; el
     * expediente queda LISTO_PARA_REPARTO en su propia bandeja.
     */
    public function aprobarVistoBueno(AprobarVistoBuenoRequest $request, Expediente $expediente): JsonResponse
    {
        $actuado = $this->cierreExpedienteService->aprobarVistoBueno(
            expediente: $expediente,
            encargada: $request->user(),
            descripcion: $request->input('descripcion'),
            ipOrigen: $request->ip(),
        );

        return (new ActuadoResource($this->cargarRelaciones($actuado)))->response()->setStatusCode(201);
    }

    /**
     * E10-S2: la Encargada ejecuta el reparto institucional; el expediente
     * pasa a CONCLUIDO_REMITIDO y sale de los tableros operativos.
     */
    public function ejecutarReparto(RepartoInstitucionalRequest $request, Expediente $expediente): JsonResponse
    {
        $actuado = $this->cierreExpedienteService->ejecutarReparto(
            expediente: $expediente,
            encargada: $request->user(),
            destino: $request->input('destino'),
            justificacion: $request->input('justificacion'),
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
