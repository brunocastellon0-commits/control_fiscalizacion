<?php

namespace App\Http\Resources;

use App\Models\Expediente;
use App\Services\PasosExpedienteService;
use App\Services\SemaforoPlazoService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HistorialOperativoDetalleResource extends JsonResource
{
    /**
     * Detalle de consulta del historial operativo. No expone URLs de descarga
     * de adjuntos ni habilita acciones de escritura.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Expediente $expediente */
        $expediente = $this->resource;

        return [
            'id' => $expediente->id,
            'nurej_code' => $expediente->nurej_code,
            'via' => $expediente->via,
            'naturaleza' => $expediente->naturaleza,
            'fecha_ingreso' => $expediente->fecha_ingreso?->format('Y-m-d H:i:s'),
            'resumen_hechos' => $expediente->resumen_hechos,
            'es_final' => (bool) $expediente->estadoActual?->es_final,
            'reglamento' => $this->whenLoaded('reglamento', fn () => $expediente->reglamento ? [
                'id' => $expediente->reglamento->id,
                'codigo' => $expediente->reglamento->codigo,
                'nombre' => $expediente->reglamento->nombre,
            ] : null),
            'estado_actual' => $this->whenLoaded('estadoActual', fn () => $expediente->estadoActual ? [
                'id' => $expediente->estadoActual->id,
                'codigo' => $expediente->estadoActual->codigo,
                'nombre' => $expediente->estadoActual->nombre,
                'es_final' => (bool) $expediente->estadoActual->es_final,
            ] : null),
            'creador' => $this->whenLoaded('creador', fn () => $expediente->creador ? [
                'id' => $expediente->creador->id,
                'nombres' => $expediente->creador->nombres,
                'apellidos' => $expediente->creador->apellidos,
            ] : null),
            'asignacion_activa' => $this->whenLoaded('asignacionActiva', fn () => $expediente->asignacionActiva ? [
                'id' => $expediente->asignacionActiva->id,
                'usuario' => $expediente->asignacionActiva->usuario ? [
                    'id' => $expediente->asignacionActiva->usuario->id,
                    'nombres' => $expediente->asignacionActiva->usuario->nombres,
                    'apellidos' => $expediente->asignacionActiva->usuario->apellidos,
                ] : null,
                'rol' => $expediente->asignacionActiva->rol ? [
                    'id' => $expediente->asignacionActiva->rol->id,
                    'codigo' => $expediente->asignacionActiva->rol->codigo,
                    'nombre' => $expediente->asignacionActiva->rol->nombre,
                ] : null,
            ] : null),
            'partes_vigentes' => ParteResource::collection($this->whenLoaded('partesVigentes')),
            'plazos' => PlazoResource::collection($this->whenLoaded('plazos')),
            'actuados' => ActuadoResource::collection($this->whenLoaded('actuados')),
            'pasos' => $this->whenLoaded('actuados', fn () => app(PasosExpedienteService::class)->calcular($expediente)),
            'sem_plazo' => $this->semPlazoMasUrgente($expediente),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function semPlazoMasUrgente(?Expediente $expediente): ?array
    {
        $plazoUrgente = $expediente?->plazos
            ?->where('estado', 'VIGENTE')
            ->sortBy('fecha_limite')
            ->first();

        if ($plazoUrgente === null) {
            return null;
        }

        return app(SemaforoPlazoService::class)->evaluarPlazo($plazoUrgente);
    }
}
