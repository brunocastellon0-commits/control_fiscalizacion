<?php

namespace App\Http\Resources;

use App\Models\Plazo;
use App\Services\SemaforoPlazoService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlazoResource extends JsonResource
{
    /**
     * Transforma un plazo en su representacion para el workstation,
     * incluyendo el semaforo calculado en tiempo real por el servicio.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Plazo $plazo */
        $plazo = $this->resource;

        $semaforo = app(SemaforoPlazoService::class)->evaluarPlazo($plazo);

        return [
            'id' => $plazo->id,
            'tipo_plazo' => $plazo->tipo_plazo,
            'estado' => $plazo->estado,
            'dias_habiles_otorgados' => $plazo->dias_habiles_otorgados,
            'fecha_inicio' => $plazo->fecha_inicio?->format('Y-m-d'),
            'fecha_limite' => $plazo->fecha_limite?->format('Y-m-d'),
            'sem_plazo' => $semaforo,
        ];
    }
}
