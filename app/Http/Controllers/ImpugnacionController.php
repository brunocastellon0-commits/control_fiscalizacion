<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImpugnacionRemitirRequest;
use App\Http\Requests\ResolverImpugnacionRequest;
use App\Http\Resources\ExpedienteResource;
use App\Models\Expediente;
use App\Services\ImpugnacionService;
use Illuminate\Http\JsonResponse;

class ImpugnacionController extends Controller
{
    public function __construct(
        protected ImpugnacionService $impugnacionService,
    ) {}

    /**
     * RN-08: remite un expediente rechazado a la bandeja de la Encargada.
     */
    public function remitir(ImpugnacionRemitirRequest $request, Expediente $expediente): JsonResponse
    {
        $impugnacion = $this->impugnacionService->remitirImpugnacion(
            expediente: $expediente,
            emisor: $request->user(),
            descripcion: $request->input('descripcion'),
        );

        return (new ExpedienteResource($expediente->load($this->relacionesDetalle())))
            ->additional([
                'impugnacion' => [
                    'id' => $impugnacion->id,
                    'fecha_limite_resolucion' => $impugnacion->fecha_limite_resolucion->format('Y-m-d'),
                    'resultado' => $impugnacion->resultado,
                ],
            ])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * RN-08: la Encargada resuelve la impugnación (ratifica o revoca el rechazo).
     */
    public function resolver(ResolverImpugnacionRequest $request, Expediente $expediente): ExpedienteResource
    {
        $this->impugnacionService->resolverImpugnacion(
            expediente: $expediente,
            ratifica: (bool) $request->input('ratifica'),
            justificacion: $request->input('justificacion'),
            encargada: $request->user(),
        );

        return new ExpedienteResource($expediente->load($this->relacionesDetalle()));
    }

    /**
     * Relaciones eager-loaded para el detalle del expediente tras la operación.
     *
     * @return array<int, string>
     */
    protected function relacionesDetalle(): array
    {
        return [
            'reglamento',
            'estadoActual',
            'creador',
            'asignacionActiva.usuario',
            'asignacionActiva.rol',
            'partesVigentes',
            'plazos.parametroPlazo',
            'actuados.tipoActuado',
            'actuados.estadoAnterior',
            'actuados.estadoNuevo',
            'actuados.usuario',
            'actuados.adjuntos',
        ];
    }
}
