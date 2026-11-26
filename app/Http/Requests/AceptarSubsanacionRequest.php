<?php

namespace App\Http\Requests;

use App\Models\Expediente;
use Illuminate\Foundation\Http\FormRequest;

class AceptarSubsanacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $expediente = $this->expedienteDeRuta();

        return $expediente !== null && $this->user()->can('aceptarSubsanacion', $expediente);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'documentos' => ['required', 'array', 'min:1'],
            'documentos.*.requisito_id' => ['required', 'integer', 'distinct', 'exists:catalogo_requisitos,id,activo,1'],
            'documentos.*.archivo' => ['required', 'file', 'mimes:pdf', 'max:10240'],
            'descripcion' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'documentos.required' => 'Debe cargar al menos el PDF de la subsanación presentada por el ciudadano.',
            'documentos.*.archivo.mimes' => 'Cada subsanación debe estar en formato PDF.',
            'documentos.*.archivo.max' => 'Cada PDF no puede superar los 10 MB.',
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
