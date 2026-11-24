<?php

namespace App\Http\Controllers\Encargada;

use App\Http\Controllers\Controller;
use App\Models\Actuado;
use App\Models\Expediente;
use App\Services\PlanificacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EncargadaSupervisionController extends Controller
{
    /**
     * Bandeja de supervisión de la Encargada.
     *
     * Por ahora publica únicamente los cronogramas de trabajo cargados, como
     * avisos informativos: no requieren una acción de la Encargada.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('bandejaSorteo', Expediente::class);

        $categoria = $request->query('categoria');
        if ($categoria !== null && $categoria !== '' && ! in_array($categoria, ['cronogramas', 'planificacion'], true)) {
            return $this->respuestaVacia();
        }

        $actuados = Actuado::whereHas('tipoActuado', function ($query) {
            $query->where('codigo', PlanificacionService::CODIGO_ACT_CRONOGRAMA);
        })
            ->with([
                'expediente.partesVigentes',
                'expediente.asignacionActiva.usuario',
                'usuario',
            ])
            ->orderByDesc('fecha_hora')
            ->paginate(15);

        $datos = $actuados->getCollection()->map(function (Actuado $actuado) {
            $expediente = $actuado->expediente;
            $usuarioAsignado = $expediente?->asignacionActiva?->usuario;
            $contenido = is_array($actuado->contenido) ? $actuado->contenido : [];
            $partes = $expediente?->partesVigentes
                ->pluck('nombre_completo')
                ->filter()
                ->implode(', ');

            return [
                'id' => $actuado->id,
                'expediente_id' => $actuado->expediente_id,
                'actuado_id' => $actuado->id,
                'nurej' => $expediente?->nurej_code,
                'nurej_code' => $expediente?->nurej_code,
                'partes' => filled($partes) ? $partes : null,
                'descripcion' => $contenido['descripcion'] ?? $expediente?->resumen_hechos,
                'accion_pendiente' => 'Cronograma cargado (informativo)',
                'categoria' => 'cronogramas',
                'requiere_accion' => false,
                'asignado_a' => $usuarioAsignado
                    ? trim($usuarioAsignado->nombres.' '.$usuarioAsignado->apellidos)
                    : null,
                'fecha_hora' => optional($actuado->fecha_hora)->toIso8601String(),
            ];
        })->values()->all();

        return response()->json([
            'data' => $datos,
            'meta' => [
                'total' => $actuados->total(),
                'per_page' => $actuados->perPage(),
                'current_page' => $actuados->currentPage(),
                'last_page' => $actuados->lastPage(),
            ],
            'links' => [
                'first' => $actuados->url(1),
                'last' => $actuados->url($actuados->lastPage()),
                'prev' => $actuados->previousPageUrl(),
                'next' => $actuados->nextPageUrl(),
            ],
        ]);
    }

    private function respuestaVacia(): JsonResponse
    {
        return response()->json([
            'data' => [],
            'meta' => [
                'total' => 0,
                'per_page' => 15,
                'current_page' => 1,
                'last_page' => 1,
            ],
            'links' => [
                'first' => null,
                'last' => null,
                'prev' => null,
                'next' => null,
            ],
        ]);
    }
}
