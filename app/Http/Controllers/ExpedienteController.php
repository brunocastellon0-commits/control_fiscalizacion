<?php

namespace App\Http\Controllers;

use App\Http\Requests\SortearExpedienteRequest;
use App\Http\Requests\StoreExpedienteRequest;
use App\Http\Resources\ExpedienteResource;
use App\Models\CatalogoActuado;
use App\Models\CatalogoEstado;
use App\Models\Expediente;
use App\Services\ActuadoService;
use App\Services\ExpedienteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\ValidationException;

class ExpedienteController extends Controller
{
    public function __construct(
        protected ExpedienteService $expedienteService,
        protected ActuadoService $actuadoService,
    ) {}

    /**
     * Apertura de causa (rol TECNICO). Crea el expediente, el NUREJ, las
     * partes y el actuado ACT_REGISTRO_DIGITALIZACION (con su adjunto).
     */
    public function store(StoreExpedienteRequest $request): JsonResponse
    {
        $expediente = $this->expedienteService->aperturaCausa(
            datos: $request->validated(),
            tecnico: $request->user(),
            ipOrigen: $request->ip(),
            adjunto: $request->file('adjunto'),
        );

        return (new ExpedienteResource($expediente->load([
            'reglamento',
            'estadoActual',
            'creador',
            'asignacionActiva.usuario',
            'asignacionActiva.rol',
            'partesVigentes',
            'plazos',
        ])))->response()->setStatusCode(201);
    }

    /**
     * Bandeja de sorteo de la Encargada: expedientes pendientes de sorteo.
     */
    public function bandejaSorteo(Request $request): AnonymousResourceCollection
    {
        $this->authorize('bandejaSorteo', Expediente::class);

        $estadoPendiente = CatalogoEstado::where('codigo', 'PENDIENTE_SORTEO')->firstOrFail();

        return ExpedienteResource::collection(
            Expediente::where('estado_actual_id', $estadoPendiente->id)
                ->with($this->relacionesDetalle())
                ->orderBy('fecha_ingreso', 'desc')
                ->paginate(15),
        );
    }

    /**
     * Bandeja del operador: expedientes con asignación activa para el usuario.
     */
    public function bandejaOperador(Request $request): AnonymousResourceCollection
    {
        return ExpedienteResource::collection(
            Expediente::whereHas('asignacionActiva', fn ($q) => $q->where('usuario_id', $request->user()->id))
                ->with($this->relacionesDetalle())
                ->orderBy('fecha_ingreso', 'desc')
                ->paginate(15),
        );
    }

    /**
     * Detalle de un expediente (RF-03: solo si hay asignación activa o es Encargada).
     */
    public function show(Request $request, Expediente $expediente): JsonResource
    {
        $this->authorize('view', $expediente);

        return new ExpedienteResource($expediente->load($this->relacionesDetalle()));
    }

    /**
     * Sorteo/enrutamiento (rol ENCARGADA). Emite ACT_SORTEO_INICIAL hacia el
     * usuario destino si el expediente está en PENDIENTE_SORTEO.
     */
    public function sortear(SortearExpedienteRequest $request, Expediente $expediente): JsonResponse
    {
        $estadoPendiente = CatalogoEstado::where('codigo', 'PENDIENTE_SORTEO')->firstOrFail();

        if ($expediente->estado_actual_id !== $estadoPendiente->id) {
            throw ValidationException::withMessages([
                'expediente' => 'El expediente no está pendiente de sorteo.',
            ]);
        }

        $catalogoActuado = CatalogoActuado::where('codigo', 'ACT_SORTEO_INICIAL')->firstOrFail();

        $this->actuadoService->registerActuado(
            expediente: $expediente,
            catalogoActuado: $catalogoActuado,
            emisor: $request->user(),
            descripcion: $request->input('descripcion') ?? 'Sorteo inicial',
            usuarioDestinoId: (int) $request->input('usuario_destino_id'),
            metadatos: ['tipo' => 'SORTEO_INICIAL'],
            ipOrigen: $request->ip(),
        );

        return (new ExpedienteResource($expediente->fresh($this->relacionesDetalle())))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Relaciones a cargar con with() para evitar N+1 en el recurso.
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
            'plazos',
            'actuados.tipoActuado',
            'actuados.estadoAnterior',
            'actuados.estadoNuevo',
            'actuados.usuario',
            'actuados.adjuntos',
        ];
    }
}
