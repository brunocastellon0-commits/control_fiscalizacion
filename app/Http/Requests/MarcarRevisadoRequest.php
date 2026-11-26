<?php

namespace App\Http\Requests;

use App\Models\Expediente;
use Illuminate\Foundation\Http\FormRequest;

class MarcarRevisadoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('bandejaSorteo', Expediente::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'actuado_id' => ['required', 'integer', 'exists:actuados,id'],
        ];
    }
}
