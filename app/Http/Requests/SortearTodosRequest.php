<?php

namespace App\Http\Requests;

use App\Models\Rol;
use Illuminate\Foundation\Http\FormRequest;

class SortearTodosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->rol?->codigo === Rol::CODIGO_ENCARGADA;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
