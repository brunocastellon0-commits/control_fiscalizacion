<?php

namespace App\Http\Requests;

use App\Models\Expediente;
use Illuminate\Foundation\Http\FormRequest;

class SolicitarAmpliacionRequest extends FormRequest
{
    /**
     * US-2.6: solo el Técnico con bandeja activa de un expediente en
     * EN_EJECUCION (AC022) puede solicitar la ampliación de plazo.
     */
    public function authorize(): bool
    {
        $expediente = $this->expedienteDeRuta();

        return $expediente !== null && $this->user()->can('solicitarAmpliacion', $expediente);
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
            'justificacion.required' => 'La justificación de la ampliación es obligatoria.',
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
