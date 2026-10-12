<?php

namespace App\Http\Requests;

use App\Models\Expediente;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegistrarRecepcionRequest extends FormRequest
{
    /**
     * RF-05 / CTR-05 (B2.1): solo la Encargada activa registra la recepción
     * de una causa que está en REMITIDO_UNIDAD_EXTERNA (política
     * registrarRecepcion). La coincidencia con la unidad de la remisión
     * abierta la resuelve TransferenciaUnidadService en transacción.
     */
    public function authorize(): bool
    {
        $expediente = $this->expedienteDeRuta();

        return $expediente !== null && $this->user()?->can('registrarRecepcion', $expediente) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'unidad_origen_id' => [
                'required',
                'integer',
                Rule::exists('unidades', 'id')->where(fn ($query) => $query->where('activa', true)),
            ],
            'motivo' => ['required', 'string', 'min:20', 'max:5000'],
            'adjunto' => ['nullable', 'file', 'mimes:pdf', 'max:20480'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'unidad_origen_id.required' => 'La unidad de origen es obligatoria.',
            'unidad_origen_id.exists' => 'La unidad de origen no existe o no está activa.',
            'motivo.required' => 'El motivo de la recepción es obligatorio.',
            'motivo.min' => 'El motivo debe tener al menos :min caracteres.',
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
