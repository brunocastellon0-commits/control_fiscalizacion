<?php

namespace App\Http\Requests;

use App\Models\Expediente;
use Illuminate\Foundation\Http\FormRequest;

class RecibirDescargosRequest extends FormRequest
{
    /**
     * E7-S* (RN-09): solo el Auditor Financiero activo con la bandeja del
     * expediente puede recibir los descargos (politica recibirDescargos).
     */
    public function authorize(): bool
    {
        $expediente = $this->expedienteDeRuta();

        return $expediente !== null && $this->user()->can('recibirDescargos', $expediente);
    }

    /**
     * El escrito de descargos es obligatorio: la fase exige respaldo
     * documental estricto (AC055).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'descripcion' => ['required', 'string', 'min:5', 'max:10000'],
            'adjunto' => ['required', 'file', 'mimes:pdf', 'max:20480'],
        ];
    }

    public function messages(): array
    {
        return [
            'descripcion.required' => 'La descripción de la recepción es obligatoria.',
            'descripcion.min' => 'La descripción debe tener al menos :min caracteres.',
            'adjunto.required' => 'Debe adjuntar el escrito de descargos presentado por los auditados.',
            'adjunto.mimes' => 'El adjunto debe ser un archivo PDF.',
            'adjunto.max' => 'El adjunto no puede superar los 20 MB.',
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
