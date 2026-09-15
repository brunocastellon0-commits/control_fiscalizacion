<?php

namespace App\Http\Controllers;

use App\Http\Requests\AprobarAmpliacionRequest;
use App\Http\Requests\SolicitarAmpliacionRequest;
use App\Http\Resources\ActuadoResource;
use App\Models\Actuado;
use App\Models\Expediente;
use App\Services\AmpliacionService;
use Illuminate\Http\JsonResponse;

class AmpliacionController extends Controller
{
    public function __construct(
        protected AmpliacionService $ampliacionService,
    ) {}

    /**
     * US-2.6: el Técnico (AC022) solicita la ampliación del plazo de ejecución;
     * el expediente queda PENDIENTE_APROBACION_AMPLIACION en la bandeja de la
     * Encargada. El reloj EJECUCION sigue corriendo.
     */
    public function solicitar(SolicitarAmpliacionRequest $request, Expediente $expediente): JsonResponse
    {
        $actuado = $this->ampliacionService->solicitarAmpliacion(
            expediente: $expediente,
            tecnico: $request->user(),
            justificacion: $request->input('justificacion'),
            ipOrigen: $request->ip(),
        );

        return (new ActuadoResource($this->cargarRelaciones($actuado)))->response()->setStatusCode(201);
    }

    /**
     * US-2.6: la Encargada aprueba la ampliación; se cierra el plazo EJECUCION
     * original, se abre EJECUCION_AMPLIADA (5 días hábiles sobre el vencimiento
     * original) y el expediente regresa a EN_EJECUCION en la bandeja del Técnico.
     */
    public function aprobar(AprobarAmpliacionRequest $request, Expediente $expediente): JsonResponse
    {
        $actuado = $this->ampliacionService->aprobarAmpliacion(
            expediente: $expediente,
            encargada: $request->user(),
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
