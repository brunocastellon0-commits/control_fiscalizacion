<?php

namespace App\Http\Requests;

use App\Models\Expediente;
use Illuminate\Foundation\Http\FormRequest;

class ResolverImpugnacionRequest extends FormRequest
{
    /**
     * RN-08: el operador asignado resuelve la impugnación de un expediente
     * en EN_IMPUGNACION y puede adjuntar el PDF presentado por el ciudadano.
     */
    public function authorize(): bool
    {
        $expediente = $this->expedienteDeRuta();

        return $expediente !== null && $this->user()->can('resolverImpugnacion', $expediente);
    }

    /**
     * Reglas de validación de la resolución (ratifica o revoca).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ratifica' => 'required|boolean',
            'justificacion' => 'required|string|min:10|max:5000',
            'archivo' => 'nullable|file|mimes:pdf|max:10240',
            'requisito_id' => 'nullable|integer|exists:catalogo_requisitos,id,activo,1',
        ];
    }

    public function messages(): array
    {
        return [
            'ratifica.required' => 'Debe indicar si la resolución ratifica o revoca el rechazo.',
            'ratifica.boolean' => 'El campo ratifica debe ser verdadero o falso.',
            'justificacion.required' => 'La justificación de la resolución es obligatoria.',
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
