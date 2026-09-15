<?php

namespace App\Http\Controllers;

use App\Http\Requests\DevolverPlanificacionRequest;
use App\Http\Requests\StorePlanificacionRequest;
use App\Http\Requests\VistoBuenoPlanificacionRequest;
use App\Http\Resources\ActuadoResource;
use App\Models\Actuado;
use App\Models\Expediente;
use App\Services\PlanificacionService;
use Illuminate\Http\JsonResponse;

class PlanificacionController extends Controller
{
    public function __construct(
        protected PlanificacionService $planificacionService,
    ) {}

    /**
     * US-2.4: carga el Cronograma (AC022) o el MPA (AC054/055) y remite el
     * expediente a la bandeja de la Encargada en PENDIENTE_VISTO_BUENO.
     */
    public function store(StorePlanificacionRequest $request, Expediente $expediente): JsonResponse
    {
        $actuado = $this->planificacionService->cargarPlanificacion(
            expediente: $expediente,
            emisor: $request->user(),
            descripcion: $request->input('descripcion'),
            fechaLimitePropuesta: $request->input('fecha_limite_propuesta'),
            adjunto: $request->file('adjunto'),
        );

        return (new ActuadoResource($this->cargarRelaciones($actuado)))->response()->setStatusCode(201);
    }

    /**
     * US-2.4: la Encargada aprueba la planificación; arranca el reloj de
     * ejecución y el expediente regresa a la bandeja del operador original.
     */
    public function vistoBueno(VistoBuenoPlanificacionRequest $request, Expediente $expediente): JsonResponse
    {
        $actuado = $this->planificacionService->aprobarVistoBueno(
            expediente: $expediente,
            encargada: $request->user(),
            descripcion: $request->input('descripcion'),
        );

        return (new ActuadoResource($this->cargarRelaciones($actuado)))->response()->setStatusCode(201);
    }

    /**
     * US-2.5: la Encargada devuelve la planificación con observaciones; el
     * expediente retrocede a EN_PLANIFICACION y se reabre su plazo para el
     * operador original.
     */
    public function devolver(DevolverPlanificacionRequest $request, Expediente $expediente): JsonResponse
    {
        $actuado = $this->planificacionService->devolverPlanificacion(
            expediente: $expediente,
            encargada: $request->user(),
            justificacion: $request->input('justificacion'),
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
