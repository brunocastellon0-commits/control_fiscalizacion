<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEvaluacionAdmisibilidadRequest;
use App\Http\Resources\CatalogoRequisitoResource;
use App\Http\Resources\EvaluacionAdmisibilidadResource;
use App\Models\CatalogoRequisito;
use App\Models\EvaluacionAdmisibilidad;
use App\Models\Expediente;
use App\Services\EvaluacionAdmisibilidadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EvaluacionAdmisibilidadController extends Controller
{
    public function __construct(
        protected EvaluacionAdmisibilidadService $evaluacionService,
    ) {}

    /**
     * Catálogo dinámico de requisitos activos del reglamento del expediente
     * para armar el checklist de evaluación (RF-04). Autorizado por RF-03.
     */
    public function requisitos(Expediente $expediente): AnonymousResourceCollection
    {
        $this->authorize('view', $expediente);

        $evaluaciones = EvaluacionAdmisibilidad::where('expediente_id', $expediente->id)
            ->orderByDesc('id')
            ->get()
            ->keyBy('requisito_id');

        $requisitos = CatalogoRequisito::where('reglamento_id', $expediente->reglamento_id)
            ->where('activo', true)
            ->with('reglamento')
            ->orderBy('orden')
            ->get()
            ->map(function (CatalogoRequisito $requisito) use ($evaluaciones): CatalogoRequisito {
                $evaluacion = $evaluaciones->get($requisito->id);

                $requisito->setAttribute(
                    'cumple',
                    $evaluacion !== null ? (bool) $evaluacion->cumple : null,
                );

                return $requisito;
            });

        return CatalogoRequisitoResource::collection($requisitos);
    }

    /**
     * Evalúa la admisibilidad del expediente: persiste el checklist inmutable,
     * ejecuta el motor de reglas (admisión/observación/rechazo) y transiciona
     * el estado. Autorizado por RF-03.
     */
    public function store(StoreEvaluacionAdmisibilidadRequest $request, Expediente $expediente): JsonResponse
    {
        $resultado = $this->evaluacionService->evaluar(
            expediente: $expediente,
            operador: $request->user(),
            requisitos: $request->validated('requisitos'),
            ipOrigen: $request->ip(),
            detalleObservacion: $request->validated('detalle_observacion'),
        );

        return response()->json([
            'resumen' => $resultado['resumen'],
            'evaluaciones' => EvaluacionAdmisibilidadResource::collection(
                $resultado['evaluaciones'],
            ),
        ], 201);
    }
}
