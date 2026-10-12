<?php

namespace App\Http\Controllers;

use App\Http\Requests\SolicitarIncompetenciaRequest;
use App\Http\Requests\VistoBuenoIncompetenciaRequest;
use App\Models\Expediente;
use App\Services\TransparenciaService;
use Illuminate\Http\JsonResponse;

class TransparenciaController extends Controller
{
    public function __construct(
        protected TransparenciaService $transparenciaService,
    ) {}

    /**
     * CTR-10 / B2.2 (RN-09 Fase 5): el operador solicita incompetencia; el
     * caso pasa a espera de visto bueno de la Encargada. Solo el operador
     * con asignación activa (política solicitarIncompetencia).
     *
     * @return JsonResponse 200 {estado: EN_EVALUACION}
     */
    public function solicitar(SolicitarIncompetenciaRequest $request, Expediente $expediente): JsonResponse
    {
        $datos = $request->validated();

        $this->transparenciaService->solicitarIncompetencia(
            expediente: $expediente,
            operador: $request->user(),
            unidadDestinoId: (int) $datos['unidad_destino_id'],
            motivo: $datos['motivo'],
            ipOrigen: $request->ip(),
        );

        return response()->json([
            'estado' => $expediente->refresh()->estadoActual?->codigo,
        ], 200);
    }

    /**
     * CTR-10 / B2.2: la Encargada aprueba (congela relojes y remite a la
     * unidad externa) o rechaza (restaura el estado original) la solicitud
     * de incompetencia.
     *
     * @return JsonResponse 200 {estado: REMITIDO_UNIDAD_EXTERNA | "<estado original>"}
     */
    public function vistoBueno(VistoBuenoIncompetenciaRequest $request, Expediente $expediente): JsonResponse
    {
        $datos = $request->validated();

        $this->transparenciaService->vistoBuenoIncompetencia(
            expediente: $expediente,
            encargada: $request->user(),
            aprobado: (bool) $datos['aprobado'],
            observaciones: $datos['observaciones'] ?? null,
            ipOrigen: $request->ip(),
        );

        return response()->json([
            'estado' => $expediente->refresh()->estadoActual?->codigo,
        ], 200);
    }
}
