<?php

namespace App\Http\Requests;

use App\Models\Expediente;
use App\Models\Reglamento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpedienteRequest extends FormRequest
{
    /**
     * B2.3: delega en la policy `aperturaCausa` (exige usuario activo y rol
     * VENTANILLA), en lugar de un check inline de rol sin validar `activo`.
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
            'via' => ['required', 'string', Rule::in(Expediente::VIAS_INGRESO)],
            'numero_hoja_ruta' => [
                'nullable',
                'string',
                'max:50',
                Rule::requiredIf(fn () => $this->input('via') === Expediente::VIA_INGRESO_DISTRITAL),
            ],
            'naturaleza' => ['required', 'string', Rule::in([
                Expediente::NATURALEZA_JURISDICCIONAL,
                Expediente::NATURALEZA_ADMINISTRATIVA,
            ])],
            'reglamento_id' => [
                'required',
                'integer',
                Rule::exists((new Reglamento)->getTable(), 'id')
                    ->where(fn ($query) => $query->whereIn('codigo', Reglamento::CODIGOS)),
            ],
            'resumen_hechos' => ['required', 'string', 'min:10', 'max:5000'],
            'partes' => ['required', 'array', 'min:1'],
            'partes.*.tipo' => ['required', 'string', Rule::in(['DENUNCIANTE', 'DENUNCIADO'])],
            'partes.*.nombre_completo' => ['required', 'string', 'max:200'],
            'partes.*.documento_identidad' => ['nullable', 'string', 'max:30'],
            'partes.*.cargo_institucion' => ['nullable', 'string', 'max:150'],
            'adjunto' => ['required', 'file', 'mimes:pdf', 'max:20480'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'numero_hoja_ruta.required_if' => 'El número de hoja de ruta es obligatorio cuando el ingreso es desde distrital.',
            'reglamento_id.exists' => 'El reglamento seleccionado no es válido para la apertura de causas.',
        ];
    }
}
