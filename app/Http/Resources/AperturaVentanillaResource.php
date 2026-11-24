<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\IncluyeDocumentosExpediente;
use App\Models\Expediente;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AperturaVentanillaResource extends JsonResource
{
    use IncluyeDocumentosExpediente;

    /**
     * Representación de solo lectura de una causa aperturada por Ventanilla.
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
            'reglamento' => $this->whenLoaded('reglamento', fn () => $expediente->reglamento ? [
                'id' => $expediente->reglamento->id,
                'codigo' => $expediente->reglamento->codigo,
                'nombre' => $expediente->reglamento->nombre,
            ] : null),
            'estado_actual' => $this->whenLoaded('estadoActual', fn () => $expediente->estadoActual ? [
                'id' => $expediente->estadoActual->id,
                'codigo' => $expediente->estadoActual->codigo,
                'nombre' => $expediente->estadoActual->nombre,
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
            'partes' => ParteResource::collection($this->whenLoaded('partesVigentes')),
            'boleta_apertura' => $this->documentoBoletaApertura($expediente),
        ];
    }
}
