<?php

namespace App\Http\Requests;

use App\Models\Expediente;
use Illuminate\Foundation\Http\FormRequest;

class ImpugnacionRemitirRequest extends FormRequest
{
    /**
     * RN-08: solo el operador con asignación activa de un expediente en
     * RECHAZADO puede remitir la impugnación a la Encargada.
     */
    public function authorize(): bool
    {
        $expediente = $this->route('expediente');

        if (! $expediente instanceof Expediente) {
            $expediente = Expediente::findOrFail($expediente);
        }

        return $this->user()->can('remitirImpugnacion', $expediente);
    }

    /**
     * Reglas de validación del acto de remisión.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'descripcion' => 'required|string|min:10|max:5000',
        ];
    }

    public function messages(): array
    {
        return [
            'descripcion.required' => 'La descripción de la remisión es obligatoria.',
            'descripcion.min' => 'La descripción debe tener al menos :min caracteres.',
        ];
    }
}
