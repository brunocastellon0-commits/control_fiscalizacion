<?php

namespace App\Http\Requests;

use App\Models\Expediente;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class VistoBuenoIncompetenciaRequest extends FormRequest
{
    /**
     * CTR-10 / B2.2: solo la Encargada activa resuelve la solicitud mientras
     * la causa espera en EN_EVALUACION (política vistoBuenoIncompetencia).
     */
    public function authorize(): bool
    {
        $expediente = $this->expedienteDeRuta();

        return $expediente !== null && $this->user()?->can('vistoBuenoIncompetencia', $expediente) === true;
    }

    /**
     * Contrato CTR-10: `{aprobado: bool, observaciones?}`. Las observaciones
     * son opcionales (se envían sobre todo en el rechazo); si se envían
     * exigen el mismo mínimo de 20 caracteres que el resto de motivos.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'aprobado' => ['required', 'boolean'],
            'observaciones' => ['nullable', 'string', 'min:20', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'aprobado.required' => 'Debe indicar si aprueba o rechaza la solicitud.',
            'aprobado.boolean' => 'El campo aprobado debe ser verdadero o falso.',
            'observaciones.min' => 'Las observaciones deben tener al menos :min caracteres.',
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
