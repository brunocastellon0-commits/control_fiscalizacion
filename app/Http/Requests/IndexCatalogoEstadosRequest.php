<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IndexCatalogoEstadosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'es_final' => ['nullable', 'boolean'],
            'estado_padre_id' => ['nullable', 'integer', 'exists:catalogo_estados,id'],
        ];
    }
}
