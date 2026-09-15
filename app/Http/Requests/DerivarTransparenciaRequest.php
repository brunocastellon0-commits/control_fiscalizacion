<?php

namespace App\Http\Requests;

use App\Models\Expediente;
use Illuminate\Foundation\Http\FormRequest;

class DerivarTransparenciaRequest extends FormRequest
{
    /**
     * E5-S5: cualquier operador activo con asignación vigente sobre el
     * expediente puede derivar por incompetencia (politica derivarPorIncompetencia).
     */
    public function authorize(): bool
    {
        $expediente = $this->expedienteDeRuta();

        return $expediente !== null && $this->user()->can('derivarPorIncompetencia', $expediente);
    }

    /**
     * La justificación es obligatoria (mín. 20 caracteres) y el adjunto
     * probatorio de la incompetencia también: la vía penal exige respaldo.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'justificacion' => ['required', 'string', 'min:20', 'max:10000'],
            'adjunto' => ['required', 'file', 'mimes:pdf', 'max:20480'],
        ];
    }

    public function messages(): array
    {
        return [
            'justificacion.required' => 'La justificación de la derivación es obligatoria.',
            'justificacion.min' => 'La justificación debe tener al menos :min caracteres.',
            'adjunto.required' => 'Debe adjuntar el documento probatorio de la incompetencia.',
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
