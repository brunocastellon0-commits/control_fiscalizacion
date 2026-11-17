<?php

namespace App\Http\Requests;

use App\Models\Expediente;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RemitirUnidadRequest extends FormRequest
{
    /**
     * RF-05 / CTR-04 (B2.1): solo la Encargada activa remite una causa no
     * final y aún no remitida (política remitirUnidad).
     */
    public function authorize(): bool
    {
        $expediente = $this->expedienteDeRuta();

        return $expediente !== null && $this->user()?->can('remitirUnidad', $expediente) === true;
    }

    /**
     * La remisión exige una unidad externa activa y un motivo (mín. 20
     * caracteres, misma exigencia que la nota de remisión a Transparencia).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'unidad_id' => [
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
            'unidad_id.required' => 'La unidad de destino es obligatoria.',
            'unidad_id.exists' => 'La unidad de destino no existe o no está activa.',
            'motivo.required' => 'El motivo de la remisión es obligatorio.',
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
