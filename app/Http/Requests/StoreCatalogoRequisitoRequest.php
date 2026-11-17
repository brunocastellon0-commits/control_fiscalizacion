<?php

namespace App\Http\Requests;

use App\Models\Rol;
use Illuminate\Foundation\Http\FormRequest;

class StoreCatalogoRequisitoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $usuario = $this->user();

        return $usuario !== null
            && $usuario->activo
            && ($usuario->rol?->codigo ?? null) === Rol::CODIGO_ADMIN;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reglamento_id' => ['required', 'integer', 'exists:reglamentos,id'],
            'descripcion' => ['required', 'string', 'max:300'],
            'orden' => ['nullable', 'integer', 'min:0', 'max:32767'],
            'activo' => ['sometimes', 'boolean'],
            'es_critico' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reglamento_id.required' => 'Seleccione el reglamento al que pertenece el requisito.',
            'reglamento_id.exists' => 'El reglamento seleccionado no existe.',
            'descripcion.required' => 'La descripción del requisito es obligatoria.',
            'descripcion.max' => 'La descripción no puede superar los 300 caracteres.',
            'orden.integer' => 'El orden debe ser un número entero.',
            'orden.min' => 'El orden no puede ser negativo.',
            'es_critico.boolean' => 'El indicador de requisito crítico no es válido.',
            'activo.boolean' => 'El estado activo no es válido.',
        ];
    }
}
