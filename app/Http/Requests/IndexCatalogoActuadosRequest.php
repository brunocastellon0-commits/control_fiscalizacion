<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IndexCatalogoActuadosRequest extends FormRequest
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
            'expediente_id' => ['nullable', 'integer', 'exists:expedientes,id'],
            'estado_origen_id' => ['nullable', 'integer', 'exists:catalogo_estados,id'],
        ];
    }
}
