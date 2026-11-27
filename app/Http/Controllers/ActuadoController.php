<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreActuadoRequest;
use App\Http\Resources\ActuadoResource;
use App\Models\CatalogoActuado;
use App\Models\Expediente;
use App\Services\ActuadoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActuadoController extends Controller
{
    public function __construct(
        protected ActuadoService $actuadoService,
    ) {}

    /**
     * Emite un actuado sobre un expediente (autorizado por ExpedientePolicy).
     */
    public function store(StoreActuadoRequest $request, Expediente $expediente): JsonResponse
    {
        $catalogoActuadoId = (int) $request->input('catalogo_actuado_id');

        $actuado = $this->actuadoService->registerActuado(
            expediente: $expediente,
            catalogoActuado: CatalogoActuado::findOrFail($catalogoActuadoId),
            emisor: $request->user(),
            descripcion: $request->input('descripcion'),
            usuarioDestinoId: $request->input('usuario_destino_id') !== null
                ? (int) $request->input('usuario_destino_id')
                : null,
            metadatos: ['tipo' => 'ACTUADO'],
            ipOrigen: $request->ip(),
            adjunto: $request->file('adjunto'),
            cambiarFase: $request->boolean('cambiar_fase', true),
        );

        return (new ActuadoResource($actuado->load([
            'tipoActuado',
            'estadoAnterior',
            'estadoNuevo',
            'usuario',
            'adjuntos',
        ])))->response()->setStatusCode(201);
    }

    /**
     * P1: aplica el cambio de fase pendiente del expediente (el actuado
     * emitido con cambiar_fase=false), autorizado con la misma ability
     * crearActuado sobre el catálogo del actuado pendiente.
     */
    public function pasarFase(Request $request, Expediente $expediente): JsonResponse
    {
        $pendiente = $this->actuadoService->fasePendiente($expediente);

        if ($pendiente === null) {
            return response()->json([
                'message' => 'No hay un cambio de fase pendiente por aplicar.',
            ], 422);
        }

        $this->authorize('crearActuado', [$expediente, $pendiente->tipoActuado]);

        $actuado = $this->actuadoService->pasarFase($expediente, $request->user());

        return (new ActuadoResource($actuado->load([
            'tipoActuado',
            'estadoAnterior',
            'estadoNuevo',
            'usuario',
            'adjuntos',
        ])))->response();
    }
}
