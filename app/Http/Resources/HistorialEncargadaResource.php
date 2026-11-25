<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\IncluyeDocumentosExpediente;
use App\Models\Expediente;
use App\Services\SemaforoPlazoService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HistorialEncargadaResource extends JsonResource
{
    use IncluyeDocumentosExpediente;

    /**
     * Representación de solo lectura del historial general de la Encargada.
     * No expone adjuntos, actuados ni acciones operativas.
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
            'numero_hoja_ruta' => $expediente->numero_hoja_ruta,
            'naturaleza' => $expediente->naturaleza,
            'fecha_ingreso' => $expediente->fecha_ingreso?->format('Y-m-d H:i:s'),
            'resumen_hechos' => $expediente->resumen_hechos,
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
            'responsable' => $this->whenLoaded('asignacionActiva', fn () => $expediente->asignacionActiva ? [
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
            'creador' => $this->whenLoaded('creador', fn () => $expediente->creador ? [
                'id' => $expediente->creador->id,
                'nombres' => $expediente->creador->nombres,
                'apellidos' => $expediente->creador->apellidos,
            ] : null),
            'boleta_apertura' => $this->documentoBoletaApertura($expediente),
            'ficha_sorteo' => $this->documentoFichaSorteo($expediente),
            'puede_sortear' => $expediente->estadoActual?->codigo === 'PENDIENTE_SORTEO',
            'sem_plazo' => $this->whenLoaded('plazos', fn () => $this->semPlazoMasUrgente($expediente)),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function semPlazoMasUrgente(Expediente $expediente): ?array
    {
        $plazoUrgente = $expediente->plazos
            ->where('estado', 'VIGENTE')
            ->sortBy('fecha_limite')
            ->first();

        if ($plazoUrgente === null) {
            return null;
        }

        return app(SemaforoPlazoService::class)->evaluarPlazo($plazoUrgente);
    }
}
