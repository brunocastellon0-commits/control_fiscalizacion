<?php

namespace App\Http\Controllers\Encargada;

use App\Http\Controllers\Controller;
use App\Http\Requests\MarcarRevisadoRequest;
use App\Models\Actuado;
use App\Models\Expediente;
use App\Services\EvaluacionAdmisibilidadService;
use App\Services\ImpugnacionService;
use App\Services\PlanificacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EncargadaSupervisionController extends Controller
{
    /**
     * Actuados informativos que la Encargada puede revisar. Ninguno requiere
     * su aprobación: el operador ya continuó con el trámite.
     *
     * @var array<string, string>
     */
    private const CATEGORIAS = [
        PlanificacionService::CODIGO_ACT_CRONOGRAMA => 'cronogramas',
        EvaluacionAdmisibilidadService::CODIGO_ACT_OBSERVACION => 'observacion',
        EvaluacionAdmisibilidadService::CODIGO_ACT_RECHAZO => 'rechazo',
        EvaluacionAdmisibilidadService::CODIGO_ACT_ACEPTACION_SUBSANACION => 'subsanacion',
        ImpugnacionService::CODIGO_ACT_REMITIR => 'impugnacion',
        ImpugnacionService::CODIGO_ACT_RATIFICA => 'impugnacion',
        ImpugnacionService::CODIGO_ACT_REVOCA => 'impugnacion',
    ];

    /**
     * Bandeja de supervisión: documentos informativos que la Encargada puede
     * marcar como revisados sin bloquear al operador.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('bandejaSorteo', Expediente::class);

        $categoria = $request->query('categoria');
        if ($categoria === 'planificacion') {
            $categoria = 'cronogramas';
        }

        if ($categoria !== null && $categoria !== '' && ! in_array($categoria, array_values(self::CATEGORIAS), true)) {
            return $this->respuestaVacia();
        }

        $query = Actuado::whereHas('tipoActuado', function ($query) {
            $query->whereIn('codigo', array_keys(self::CATEGORIAS));
        });

        if ($categoria !== null && $categoria !== '') {
            $codigos = array_keys(self::CATEGORIAS, $categoria, true);
            $query->whereHas('tipoActuado', fn ($q) => $q->whereIn('codigo', $codigos));
        }

        $actuados = $query
            ->with([
                'expediente.partesVigentes',
                'expediente.asignacionActiva.usuario',
                'usuario',
                'revisadoPor',
                'adjuntos',
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

            $codigo = $actuado->tipoActuado?->codigo ?? '';
            $categoria = self::CATEGORIAS[$codigo] ?? 'informacion';

            return [
                'id' => $actuado->id,
                'expediente_id' => $actuado->expediente_id,
                'actuado_id' => $actuado->id,
                'nurej' => $expediente?->nurej_code,
                'nurej_code' => $expediente?->nurej_code,
                'partes' => filled($partes) ? $partes : null,
                'descripcion' => $contenido['descripcion'] ?? $expediente?->resumen_hechos,
                'accion_pendiente' => $this->etiquetaAccion($categoria),
                'categoria' => $categoria,
                'requiere_accion' => false,
                'asignado_a' => $usuarioAsignado
                    ? trim($usuarioAsignado->nombres.' '.$usuarioAsignado->apellidos)
                    : null,
                'fecha_hora' => optional($actuado->fecha_hora)->toIso8601String(),
                'adjuntos' => $actuado->adjuntos->map(fn ($adjunto) => [
                    'id' => $adjunto->id,
                    'nombre' => $adjunto->nombre_original,
                    'url' => '/api/adjuntos/'.$adjunto->id.'/descargar',
                ])->values()->all(),
                'revisado' => $actuado->revisado_en !== null,
                'revisado_por' => $actuado->revisadoPor
                    ? trim($actuado->revisadoPor->nombres.' '.$actuado->revisadoPor->apellidos)
                    : null,
                'revisado_en' => optional($actuado->revisado_en)->toIso8601String(),
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

    /**
     * Registra el visto de revisado. No cambia estados ni desbloquea nada.
     */
    public function marcarRevisado(MarcarRevisadoRequest $request): JsonResponse
    {
        $actuado = Actuado::with('tipoActuado')->findOrFail($request->validated('actuado_id'));

        $codigo = $actuado->tipoActuado?->codigo ?? '';
        if (! array_key_exists($codigo, self::CATEGORIAS)) {
            abort(404, 'El actuado no corresponde a la bandeja de supervisión.');
        }

        $actuado->update([
            'revisado_por' => $request->user()->id,
            'revisado_en' => now(),
        ]);

        return response()->json([
            'actuado_id' => $actuado->id,
            'revisado' => true,
            'revisado_por' => trim($request->user()->nombres.' '.$request->user()->apellidos),
            'revisado_en' => optional($actuado->revisado_en)->toIso8601String(),
        ]);
    }

    private function etiquetaAccion(string $categoria): string
    {
        return match ($categoria) {
            'cronogramas' => 'Cronograma cargado (informativo)',
            'observacion' => 'Aviso de observación (informativo)',
            'rechazo' => 'Aviso de rechazo (informativo)',
            'subsanacion' => 'Subsanación aceptada (informativa)',
            'impugnacion' => 'Impugnación resuelta (informativa)',
            default => 'Documento para revisado',
        };
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
