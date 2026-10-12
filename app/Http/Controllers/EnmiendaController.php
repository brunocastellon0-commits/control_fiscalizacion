<?php

namespace App\Http\Controllers;

use App\Http\Requests\EnmiendaRequest;
use App\Http\Resources\ExpedienteResource;
use App\Models\Expediente;
use App\Services\EnmiendaService;
use Illuminate\Http\JsonResponse;

class EnmiendaController extends Controller
{
    public function __construct(
        protected EnmiendaService $enmiendaService,
    ) {}

    /**
     * CTR-09 (RF-02): emite el Actuado de Enmienda con datos_anteriores y
     * datos_nuevos en el payload y aplica la corrección en la misma
     * transacción. El estado procesal de la causa no cambia.
     *
     * @return JsonResponse 200 {actuado_enmienda_id, expediente_actualizado}
     */
    public function store(EnmiendaRequest $request, Expediente $expediente): JsonResponse
    {
        $datos = $request->validated();

        $actuado = $this->enmiendaService->registrar(
            expediente: $expediente,
            emisor: $request->user(),
            campoModificado: $datos['campo_modificado'],
            valorNuevo: $datos['valor_nuevo'],
            motivo: $datos['motivo'],
            ipOrigen: $request->ip(),
        );

        return response()->json([
            'actuado_enmienda_id' => $actuado->id,
            'expediente_actualizado' => new ExpedienteResource(
                $expediente->refresh()->load($this->relacionesDetalle())
            ),
        ], 200);
    }

    /**
     * Mismo conjunto de relaciones del detalle de causa (GET
     * /api/expedientes/{id}) para que `expediente_actualizado` tenga la
     * forma que consume la UI (B4.2).
     *
     * @return array<int, string>
     */
    private function relacionesDetalle(): array
    {
        return [
            'reglamento',
            'estadoActual',
            'creador',
            'asignacionActiva.usuario',
            'asignacionActiva.rol',
            'partesVigentes',
            'plazos',
            'actuados.tipoActuado',
            'actuados.estadoAnterior',
            'actuados.estadoNuevo',
            'actuados.usuario',
            'actuados.adjuntos',
        ];
    }
}
