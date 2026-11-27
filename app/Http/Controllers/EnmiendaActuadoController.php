<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEnmiendaActuadoRequest;
use App\Http\Resources\ExpedienteResource;
use App\Models\Actuado;
use App\Models\Expediente;
use App\Services\EnmiendaActuadoService;
use Illuminate\Http\JsonResponse;

class EnmiendaActuadoController extends Controller
{
    public function __construct(
        protected EnmiendaActuadoService $enmiendaActuadoService,
    ) {}

    /**
     * Enmienda de un actuado (Fase B): emite un ACT_ENMIENDA que referencia
     * el actuado original (append-only) con la descripción corregida y el
     * motivo. El estado procesal de la causa no cambia.
     *
     * El actuado se resuelve acotado al expediente de la ruta: un actuado
     * ajeno o inexistente responde 404.
     *
     * @param  mixed  $actuado  ID del actuado (parámetro sin model binding).
     * @return JsonResponse 200 {actuado_enmienda_id, expediente_actualizado}
     */
    public function store(
        StoreEnmiendaActuadoRequest $request,
        Expediente $expediente,
        $actuado,
    ): JsonResponse {
        $actuadoModel = Actuado::query()
            ->where('expediente_id', $expediente->id)
            ->findOrFail($actuado);

        $datos = $request->validated();

        $enmienda = $this->enmiendaActuadoService->registrar(
            expediente: $expediente,
            actuado: $actuadoModel,
            emisor: $request->user(),
            descripcion: $datos['descripcion'],
            motivo: $datos['motivo'],
            ipOrigen: $request->ip(),
        );

        return response()->json([
            'actuado_enmienda_id' => $enmienda->id,
            'expediente_actualizado' => new ExpedienteResource(
                $expediente->refresh()->load($this->relacionesDetalle())
            ),
        ], 200);
    }

    /**
     * Mismo conjunto de relaciones del detalle de causa (GET
     * /api/expedientes/{id}) para que `expediente_actualizado` tenga la
     * forma que consume la UI.
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
