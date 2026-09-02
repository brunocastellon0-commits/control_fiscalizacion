<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreActuadoRequest;
use App\Http\Resources\ActuadoResource;
use App\Models\CatalogoActuado;
use App\Models\Expediente;
use App\Services\ActuadoService;
use Illuminate\Http\JsonResponse;

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
        );

        return (new ActuadoResource($actuado->load([
            'tipoActuado',
            'estadoAnterior',
            'estadoNuevo',
            'usuario',
            'adjuntos',
        ])))->response()->setStatusCode(201);
    }
}
