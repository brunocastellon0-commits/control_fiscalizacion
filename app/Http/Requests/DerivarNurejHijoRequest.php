<?php

namespace App\Http\Requests;

use App\Models\Expediente;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DerivarNurejHijoRequest extends FormRequest
{
    /**
     * E9-S1: solo la Encargada activa puede derivar un NUREJ Hijo.
     */
    public function authorize(): bool
    {
        $expediente = $this->expedienteDeRuta();

        return $expediente !== null && $this->user()->can('derivarNurejHijo', $expediente);
    }

    /**
     * Sintaxis (R1 de la matriz): la semántica de la combinación
     * origen/destino la valida NurejHijoService contra
     * MATRIZ_DERIVACIONES (D-P1/D-P2).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'motivo' => ['required', 'string', 'min:10', 'max:5000'],
            'via_destino' => ['required', 'string', Rule::in(['TECNICO', 'JURIDICO', 'FINANCIERO'])],
        ];
    }

    public function messages(): array
    {
        return [
            'motivo.required' => 'El motivo de la derivación es obligatorio.',
            'motivo.min' => 'El motivo debe tener al menos :min caracteres.',
            'via_destino.required' => 'La especialidad de destino es obligatoria.',
            'via_destino.in' => 'La especialidad de destino debe ser TECNICO, JURIDICO o FINANCIERO.',
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
