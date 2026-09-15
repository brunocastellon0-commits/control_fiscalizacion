<?php

namespace App\Http\Requests;

use App\Models\Expediente;
use Illuminate\Foundation\Http\FormRequest;

class DevolverPlanificacionRequest extends FormRequest
{
    /**
     * US-2.5: solo la Encargada activa devuelve con observaciones la
     * planificación de un expediente en PENDIENTE_VISTO_BUENO.
     */
    public function authorize(): bool
    {
        $expediente = $this->expedienteDeRuta();

        return $expediente !== null && $this->user()->can('devolverPlanificacion', $expediente);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'justificacion' => ['required', 'string', 'min:10', 'max:10000'],
        ];
    }

    public function messages(): array
    {
        return [
            'justificacion.required' => 'La justificación de la devolución es obligatoria.',
            'justificacion.min' => 'La justificación debe tener al menos :min caracteres.',
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
