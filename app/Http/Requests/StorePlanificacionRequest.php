<?php

namespace App\Http\Requests;

use App\Models\Expediente;
use App\Services\PlanificacionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlanificacionRequest extends FormRequest
{
    /**
     * US-2.4: autoriza la carga de planificación para el rol operativo del
     * reglamento, con bandeja activa y estado EN_PLANIFICACION.
     */
    public function authorize(): bool
    {
        $expediente = $this->expedienteDeRuta();

        return $expediente !== null && $this->user()->can('cargarPlanificacion', $expediente);
    }

    /**
     * Reglas de validación de la carga de Cronograma/MPA.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $expediente = $this->expedienteDeRuta();

        $esMpa = $expediente?->reglamento?->codigo !== PlanificacionService::REGLAMENTO_AC022;

        return [
            'descripcion' => ['required', 'string', 'min:10', 'max:10000'],
            'fecha_limite_propuesta' => [
                Rule::requiredIf($esMpa),
                'nullable',
                'date_format:Y-m-d',
                'after:today',
            ],
            'adjunto' => ['required', 'file', 'mimes:pdf', 'max:20480'],
        ];
    }

    public function messages(): array
    {
        return [
            'descripcion.required' => 'La descripción de la planificación es obligatoria.',
            'descripcion.min' => 'La descripción debe tener al menos :min caracteres.',
            'fecha_limite_propuesta.required' => 'El MPA exige una fecha límite propuesta.',
            'fecha_limite_propuesta.after' => 'La fecha límite propuesta debe ser mayor a la fecha de hoy.',
            'adjunto.required' => 'Debe adjuntarse el documento de la planificación.',
            'adjunto.mimes' => 'El documento adjunto debe ser un PDF.',
            'adjunto.max' => 'El documento adjunto no puede superar los 20 MB.',
        ];
    }

    private function expedienteDeRuta(): ?Expediente
    {
        $expediente = $this->route('expediente');

        if ($expediente instanceof Expediente) {
            return $expediente;
        }

        return is_numeric($expediente) ? Expediente::find((int) $expediente) : null;
    }
}
