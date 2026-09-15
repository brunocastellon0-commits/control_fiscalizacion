<?php

namespace App\Http\Requests;

use App\Models\Expediente;
use App\Services\CierreExpedienteService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RepartoInstitucionalRequest extends FormRequest
{
    /**
     * E10-S2: solo la Encargada activa ejecuta el reparto institucional de un
     * expediente en LISTO_PARA_REPARTO.
     */
    public function authorize(): bool
    {
        $expediente = $this->expedienteDeRuta();

        return $expediente !== null && $this->user()->can('ejecutarRepartoInstitucional', $expediente);
    }

    /**
     * El destino DEBE ser uno de los contemplados por la norma (RN-09/RN-12) y
     * la nota de remisión es obligatoria.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'destino' => ['required', 'string', Rule::in(CierreExpedienteService::DESTINOS_REPARTO)],
            'justificacion' => ['required', 'string', 'min:10', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'destino.required' => 'Debe indicar el destino institucional del reparto.',
            'destino.in' => 'El destino seleccionado no está contemplado en la norma (RN-09/RN-12).',
            'justificacion.required' => 'La nota de remisión es obligatoria.',
            'justificacion.min' => 'La nota de remisión debe tener al menos :min caracteres.',
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
