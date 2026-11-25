<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\IncluyeDocumentosExpediente;
use App\Models\Expediente;
use App\Services\PasosExpedienteService;
use App\Services\SemaforoPlazoService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpedienteResource extends JsonResource
{
    use IncluyeDocumentosExpediente;

    /**
     * Transforma un expediente a su representacion del workstation,
     * incluyendo el semaforo del plazo vigente mas urgente.
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
            'nurej_padre_id' => $expediente->nurej_padre_id,
            'via' => $expediente->via,
            'numero_hoja_ruta' => $expediente->numero_hoja_ruta,
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
            'resumen_hechos' => $expediente->resumen_hechos,
            'fecha_ingreso' => $expediente->fecha_ingreso?->format('Y-m-d H:i:s'),
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
            'boleta_apertura' => $this->documentoBoletaApertura($expediente),
            'ficha_sorteo' => $this->documentoFichaSorteo($expediente),
            'sem_plazo' => $this->semPlazoMasUrgente($expediente),
        ];
    }

    /**
     * Calcula el semaforo del plazo vigente mas urgente (fecha limite mas
     * proxima) del expediente. Retorna null si no hay plazos cargados.
     *
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
