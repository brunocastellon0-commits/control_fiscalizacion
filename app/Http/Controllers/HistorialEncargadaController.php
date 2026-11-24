<?php

namespace App\Http\Controllers;

use App\Http\Resources\HistorialEncargadaResource;
use App\Models\Expediente;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class HistorialEncargadaController extends Controller
{
    /**
     * Historial general de solo lectura: todas las causas, sin filtro por
     * asignación, incluidas las finalizadas y remitidas a unidad externa.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('historialGeneralEncargada', Expediente::class);

        $filtros = $request->validate([
            'busqueda' => ['nullable', 'string', 'max:120'],
            'estado' => ['nullable', 'string', 'max:30', 'exists:catalogo_estados,codigo'],
        ]);

        $expedientes = Expediente::query()
            ->when($filtros['busqueda'] ?? null, function ($consulta, string $busqueda): void {
                $consulta->where(function ($consultaFiltro) use ($busqueda): void {
                    $consultaFiltro->where('nurej_code', 'like', '%'.$busqueda.'%')
                        ->orWhere('resumen_hechos', 'like', '%'.$busqueda.'%');
                });
            })
            ->when($filtros['estado'] ?? null, fn ($consulta, string $estado) => $consulta->whereHas(
                'estadoActual',
                fn ($consultaEstado) => $consultaEstado->where('codigo', $estado),
            ))
            ->with($this->relacionesHistorial())
            ->orderBy('fecha_ingreso', 'desc')
            ->paginate(15);

        return HistorialEncargadaResource::collection($expedientes);
    }

    /**
     * Detalle reducido de consulta: información general y estado, sin adjuntos
     * ni acciones de escritura.
     */
    public function show(Expediente $expediente): JsonResource
    {
        $this->authorize('verDetalleHistorialEncargada', $expediente);

        return new HistorialEncargadaResource($expediente->load(array_merge(
            $this->relacionesHistorial(),
            ['creador', 'plazos'],
        )));
    }

    /**
     * @return array<int, string>
     */
    protected function relacionesHistorial(): array
    {
        return [
            'reglamento',
            'estadoActual',
            'asignacionActiva.usuario',
            'asignacionActiva.rol',
        ];
    }
}
