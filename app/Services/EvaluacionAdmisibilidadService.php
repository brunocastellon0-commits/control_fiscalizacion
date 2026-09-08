<?php

namespace App\Services;

use App\Models\CatalogoActuado;
use App\Models\CatalogoEstado;
use App\Models\CatalogoRequisito;
use App\Models\EvaluacionAdmisibilidad;
use App\Models\Expediente;
use App\Models\Plazo;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EvaluacionAdmisibilidadService
{
    private const CODIGO_ACT_ADMISION = 'ACT_ADMISION';

    private const CODIGO_ACT_OBSERVACION = 'ACT_OBSERVACION';

    private const CODIGO_ACT_RECHAZO = 'ACT_RECHAZO';

    private const ESTADO_EN_EVALUACION = 'EN_EVALUACION';

    private const ESTADO_EN_SUBSANACION = 'EN_SUBSANACION';

    public function __construct(
        protected ActuadoService $actuadoService,
    ) {}

    /**
     * Evalúa el checklist de admisibilidad, persiste los resultados
     * inmutables, emite el actuado correspondiente según las reglas de
     * negocio y desactiva relojes en caso de rechazo (ACT_RECHAZO).
     *
     * @param  array<int, array{requisito_id: int, cumple: bool}>  $requisitos
     * @return array{actuado: Actuado, evaluaciones: Collection, resumen: array}
     */
    public function evaluar(Expediente $expediente, Usuario $operador, array $requisitos, ?string $ipOrigen = null): array
    {
        return DB::transaction(function () use ($expediente, $operador, $requisitos, $ipOrigen) {
            $this->verificarEstadoEnEvaluacion($expediente);

            $enviados = $this->indexarRequisitosEnviados($requisitos);

            $requisitosActivos = CatalogoRequisito::where('reglamento_id', $expediente->reglamento_id)
                ->where('activo', true)
                ->orderBy('orden')
                ->get();

            $this->validarCompletitud($enviados, $requisitosActivos);

            $faltantes = $requisitosActivos->filter(fn ($req) => ! ($enviados[$req->id] ?? false));

            $catalogoCodigo = $this->resolverCatalogo($faltantes);
            $catalogoActuado = CatalogoActuado::where('codigo', $catalogoCodigo)->firstOrFail();

            $actuado = $this->actuadoService->registerActuado(
                expediente: $expediente,
                catalogoActuado: $catalogoActuado,
                emisor: $operador,
                descripcion: $this->resolverDescripcion($catalogoCodigo, $faltantes),
                metadatos: ['tipo' => 'EVALUACION_ADMISIBILIDAD'],
                ipOrigen: $ipOrigen,
            );

            $this->desactivarRelojesSiRechazo($catalogoCodigo, $expediente);

            $evaluaciones = $this->persistirChecklist(
                expediente: $expediente,
                operador: $operador,
                actuado: $actuado,
                requisitosActivos: $requisitosActivos,
                enviados: $enviados,
            );

            return [
                'actuado' => $actuado,
                'evaluaciones' => $evaluaciones,
                'resumen' => [
                    'resultado' => $catalogoCodigo,
                    'requisitos_cumplidos' => $evaluaciones->where('cumple', true)->count(),
                    'requisitos_faltantes' => $faltantes->count(),
                    'faltantes_criticos' => $faltantes->where('es_critico', true)->pluck('id')->values(),
                ],
            ];
        }, 3);
    }

    private function verificarEstadoEnEvaluacion(Expediente $expediente): void
    {
        $estadoEvaluacion = CatalogoEstado::where('codigo', static::ESTADO_EN_EVALUACION)->firstOrFail();

        if ($expediente->estado_actual_id !== $estadoEvaluacion->id) {
            throw ValidationException::withMessages([
                'expediente' => 'El expediente no está en fase de evaluación de admisibilidad.',
            ]);
        }
    }

    private function indexarRequisitosEnviados(array $requisitos): array
    {
        $index = [];

        foreach ($requisitos as $item) {
            $index[(int) $item['requisito_id']] = (bool) $item['cumple'];
        }

        return $index;
    }

    private function validarCompletitud(array $enviados, $requisitosActivos): void
    {
        $activosIds = $requisitosActivos->pluck('id')->map(fn ($id) => (int) $id)->sort()->values();
        $enviadosIds = collect(array_keys($enviados))->sort()->values();

        if ($activosIds->diff($enviadosIds)->isNotEmpty() || $enviadosIds->diff($activosIds)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'requisitos' => 'Debe evaluar exactamente los requisitos activos del reglamento del expediente.',
            ]);
        }
    }

    private function resolverCatalogo($faltantes): string
    {
        if ($faltantes->isEmpty()) {
            return static::CODIGO_ACT_ADMISION;
        }

        $tieneCriticoFaltante = $faltantes->contains(fn ($req) => (bool) $req->es_critico);

        return $tieneCriticoFaltante
            ? static::CODIGO_ACT_RECHAZO
            : static::CODIGO_ACT_OBSERVACION;
    }

    private function resolverDescripcion(string $catalogoCodigo, $faltantes): string
    {
        $countFaltantes = $faltantes->count();

        return match ($catalogoCodigo) {
            static::CODIGO_ACT_ADMISION => 'Admisión: todos los requisitos de admisibilidad cumplidos.',
            static::CODIGO_ACT_OBSERVACION => "Observación: {$countFaltantes} requisito(s) no crítico(s) sin cumplir.",
            static::CODIGO_ACT_RECHAZO => "Rechazo: {$countFaltantes} requisito(s) crítico(s) ausente(s).",
            default => 'Evaluación de admisibilidad.',
        };
    }

    private function desactivarRelojesSiRechazo(string $catalogoCodigo, Expediente $expediente): void
    {
        if ($catalogoCodigo !== static::CODIGO_ACT_RECHAZO) {
            return;
        }

        Plazo::where('expediente_id', $expediente->id)
            ->whereIn('estado', ['VIGENTE', 'SUSPENDIDO'])
            ->update(['estado' => 'CERRADO']);
    }

    private function persistirChecklist(
        Expediente $expediente,
        Usuario $operador,
        $actuado,
        $requisitosActivos,
        array $enviados,
    ): Collection {
        foreach ($requisitosActivos as $requisito) {
            EvaluacionAdmisibilidad::create([
                'expediente_id' => $expediente->id,
                'requisito_id' => $requisito->id,
                'cumple' => $enviados[$requisito->id] ?? false,
                'operador_id' => $operador->id,
                'actuado_id' => $actuado->id,
            ]);
        }

        return EvaluacionAdmisibilidad::query()
            ->with(['requisito', 'operador'])
            ->where('actuado_id', $actuado->id)
            ->orderBy('id')
            ->get();
    }
}
