<?php

namespace App\Http\Requests;

use App\Models\Rol;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SortearExpedienteRequest extends FormRequest
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
        return [
            'usuario_destino_id' => [
                'required',
                'integer',
                Rule::exists('usuarios', 'id')->where(function ($query) {
                    $query->where('activo', true)
                        ->whereIn('rol_id', Rol::whereIn('codigo', [
                            Rol::CODIGO_TECNICO,
                            Rol::CODIGO_AUD_JURIDICO,
                            Rol::CODIGO_AUD_FINANCIERO,
                        ])->pluck('id'));
                }),
            ],
            'descripcion' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
