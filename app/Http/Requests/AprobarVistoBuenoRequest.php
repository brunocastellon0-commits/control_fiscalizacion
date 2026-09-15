<?php

namespace App\Http\Requests;

use App\Models\Expediente;
use Illuminate\Foundation\Http\FormRequest;

class AprobarVistoBuenoRequest extends FormRequest
{
    /**
     * E10-S1: solo la Encargada activa aprueba el Visto Bueno Final de un
     * expediente en PENDIENTE_VISTO_BUENO_FINAL.
     */
    public function authorize(): bool
    {
        $expediente = $this->expedienteDeRuta();

        return $expediente !== null && $this->user()->can('aprobarVistoBuenoFinal', $expediente);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'descripcion' => ['required', 'string', 'min:10', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'descripcion.required' => 'La referencia de la aprobación es obligatoria.',
            'descripcion.min' => 'La referencia debe tener al menos :min caracteres.',
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
