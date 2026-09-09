<?php

namespace App\Http\Requests;

use App\Models\Expediente;
use Illuminate\Foundation\Http\FormRequest;

class VistoBuenoPlanificacionRequest extends FormRequest
{
    /**
     * US-2.4: solo la Encargada activa aprueba la planificación de un
     * expediente en PENDIENTE_VISTO_BUENO.
     */
    public function authorize(): bool
    {
        $expediente = $this->expedienteDeRuta();

        return $expediente !== null && $this->user()->can('aprobarPlanificacion', $expediente);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'descripcion' => ['required', 'string', 'min:5', 'max:10000'],
        ];
    }

    public function messages(): array
    {
        return [
            'descripcion.required' => 'La resolución del Visto Bueno es obligatoria.',
            'descripcion.min' => 'La resolución debe tener al menos :min caracteres.',
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
