<?php

namespace App\Http\Requests;

use App\Models\Expediente;
use Illuminate\Foundation\Http\FormRequest;

class RemitirTransparenciaRequest extends FormRequest
{
    /**
     * E5-S5: solo la Encargada activa remite a Transparencia un expediente en
     * PENDIENTE_REMISION_TRANSPARENCIA (politica remitirTransparencia).
     */
    public function authorize(): bool
    {
        $expediente = $this->expedienteDeRuta();

        return $expediente !== null && $this->user()->can('remitirTransparencia', $expediente);
    }

    /**
     * La nota de remisión de salida es obligatoria (mín. 20 caracteres).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nota_remision' => ['required', 'string', 'min:20', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'nota_remision.required' => 'La nota de remisión es obligatoria.',
            'nota_remision.min' => 'La nota de remisión debe tener al menos :min caracteres.',
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
