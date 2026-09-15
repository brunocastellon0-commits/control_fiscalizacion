<?php

namespace App\Http\Controllers;

use App\Http\Requests\DerivarNurejHijoRequest;
use App\Http\Requests\SortearExpedienteRequest;
use App\Http\Requests\SortearTodosRequest;
use App\Http\Requests\StoreExpedienteRequest;
use App\Http\Resources\ExpedienteResource;
use App\Models\CatalogoEstado;
use App\Models\Expediente;
use App\Services\ExpedienteService;
use App\Services\NurejHijoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpedienteController extends Controller
{
    public function __construct(
        protected ExpedienteService $expedienteService,
        protected NurejHijoService $nurejHijoService,
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
     * Sorteo/enrutamiento ciego (rol ENCARGADA). Ejecuta el algoritmo
     * probabilístico y asigna al ganador emitiendo ACT_SORTEO_INICIAL.
     * La respuesta incluye `asignacion_activa.usuario` = funcionario ganador.
     */
    public function sortear(SortearExpedienteRequest $request, Expediente $expediente): JsonResponse
    {
        $this->expedienteService->ejecutarSorteo(
            expediente: $expediente,
            encargada: $request->user(),
            descripcion: $request->input('descripcion'),
            ipOrigen: $request->ip(),
        );

        return (new ExpedienteResource($expediente->fresh($this->relacionesDetalle())))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Sorteo en lote de todas las causas pendientes (rol ENCARGADA). Ejecuta
     * el sorteo probabilístico de forma transaccional e indivisible: o se
     * sortean todas o ninguna. Devuelve un resumen ligero con el ganador por
     * causa, sin cargar los recursos completos.
     */
    public function sortearTodos(SortearTodosRequest $request): JsonResponse
    {
        $resultados = $this->expedienteService->sortearTodas(
            encargada: $request->user(),
            ipOrigen: $request->ip(),
        );

        $resumen = array_map(function (array $resultado) {
            return [
                'expediente_id' => $resultado['expediente']->id,
                'nurej_code' => $resultado['expediente']->nurej_code,
                'via' => $resultado['expediente']->via,
                'ganador' => [
                    'id' => $resultado['ganador']->id,
                    'nombres' => $resultado['ganador']->nombres,
                    'apellidos' => $resultado['ganador']->apellidos,
                ],
            ];
        }, $resultados);

        return response()->json([
            'message' => count($resultados).' causa(s) sorteada(s) correctamente.',
            'total' => count($resultados),
            'resultados' => $resumen,
        ]);
    }

    /**
     * E9-S1 (RN-10): la Encargada deriva un NUREJ Hijo a partir de un
     * expediente padre. El hijo hereda los metadatos informativos (via,
     * reglamento, resumen de hechos, partes) pero nace en PENDIENTE_SORTEO
     * con su línea de tiempo en cero: sin actuados, plazos ni asignaciones
     * del padre. El padre conserva su estado actual.
     */
    public function derivarNurejHijo(DerivarNurejHijoRequest $request, Expediente $expediente): JsonResponse
    {
        $hijo = $this->nurejHijoService->crearHijo(
            padre: $expediente,
            encargada: $request->user(),
            motivo: $request->input('motivo'),
            ipOrigen: $request->ip(),
        );

        return (new ExpedienteResource($hijo->load([
            'reglamento',
            'estadoActual',
            'creador',
            'partesVigentes',
        ])))->response()->setStatusCode(201);
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
