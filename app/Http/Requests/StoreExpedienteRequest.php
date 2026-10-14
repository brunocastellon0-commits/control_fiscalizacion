<?php

namespace App\Http\Requests;

use App\Models\Expediente;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpedienteRequest extends FormRequest
{
    /**
     * B2.3: delega en la policy `aperturaCausa` (exige usuario activo y rol
     * TECNICO), en lugar de un check inline de rol sin validar `activo`.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('aperturaCausa', Expediente::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'via' => ['required', 'string', Rule::in(['TECNICO', 'JURIDICO', 'FINANCIERO'])],
            'reglamento_id' => ['required', 'integer', 'exists:reglamentos,id'],
            'resumen_hechos' => ['required', 'string', 'min:10', 'max:5000'],
            'partes' => ['required', 'array', 'min:1'],
            'partes.*.tipo' => ['required', 'string', Rule::in(['DENUNCIANTE', 'DENUNCIADO'])],
            'partes.*.nombre_completo' => ['required', 'string', 'max:200'],
            'partes.*.documento_identidad' => ['nullable', 'string', 'max:30'],
            'partes.*.cargo_institucion' => ['nullable', 'string', 'max:150'],
            'adjunto' => ['required', 'file', 'mimes:pdf', 'max:20480'],
        ];
    }
}
