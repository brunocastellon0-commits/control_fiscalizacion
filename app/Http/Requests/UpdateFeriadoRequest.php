<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFeriadoRequest extends FormRequest
{
    /**
     * Valores de ámbito admitidos: los expuestos por el formulario
     * (NACIONAL, DEPARTAMENTAL, INSTITUCIONAL) más LOCAL, que ya existe en
     * los datos sembrados.
     *
     * @var list<string>
     */
    private const AMBITOS = ['NACIONAL', 'DEPARTAMENTAL', 'LOCAL', 'INSTITUCIONAL'];

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
                'max:150',
            ],

            'ambito' => [
                'required',
                'string',
                Rule::in(self::AMBITOS),
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
            'descripcion.max' => 'La descripción no puede superar los 150 caracteres.',

            'ambito.required' => 'El ámbito es obligatorio.',
            'ambito.in' => 'El ámbito debe ser NACIONAL, DEPARTAMENTAL, LOCAL o INSTITUCIONAL.',
        ];
    }
}
