<?php

namespace App\Http\Controllers;

use App\Http\Resources\HistorialAsignacionResource;
use App\Http\Resources\HistorialOperativoDetalleResource;
use App\Models\Expediente;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class HistorialOperativoController extends Controller
{
    /**
     * Historial de solo lectura de todos los expedientes por los que pasó el
     * operador, incluidas las asignaciones que ya no están activas.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('historialAsignaciones', Expediente::class);

        $filtros = $request->validate([
            'busqueda' => ['nullable', 'string', 'max:120'],
            'estado' => ['nullable', 'string', 'max:30', 'exists:catalogo_estados,codigo'],
        ]);

        $usuarioId = $request->user()->id;

        $expedientes = Expediente::query()
            ->whereHas('asignaciones', fn ($consulta) => $consulta->where('usuario_id', $usuarioId))
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
            ->with([
                'reglamento',
                'estadoActual',
                'asignaciones' => fn ($consulta) => $consulta
                    ->where('usuario_id', $usuarioId)
                    ->with('rol')
                    ->orderByDesc('fecha_asignacion'),
            ])
            ->orderByDesc('fecha_ingreso')
            ->paginate(15);

        return HistorialAsignacionResource::collection($expedientes);
    }

    /**
     * Detalle de consulta: mismo expediente, sin permisos de escritura ni
     * descarga de adjuntos.
     */
    public function show(Expediente $expediente): JsonResource
    {
        $this->authorize('verDetalleHistorialAsignado', $expediente);

        return new HistorialOperativoDetalleResource($expediente->load([
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
        ]));
    }
}
