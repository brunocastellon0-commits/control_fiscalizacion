<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFeriadoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $feriadoId = $this->route('feriado')?->id;

        return [
            'fecha' => [
                'required',
                'date',
                Rule::unique('feriados', 'fecha')->ignore($feriadoId),
            ],

            'descripcion' => [
                'required',
                'string',
                'max:255',
            ],

            'ambito' => [
                'required',
                'string',
                'max:30',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'fecha.required' => 'La fecha es obligatoria.',
            'fecha.date' => 'La fecha no tiene un formato válido.',
            'fecha.unique' => 'Ya existe otro feriado registrado para esta fecha.',

            'descripcion.required' => 'La descripción es obligatoria.',
            'descripcion.max' => 'La descripción no puede superar los 255 caracteres.',

            'ambito.required' => 'El ámbito es obligatorio.',
        ];
    }
}