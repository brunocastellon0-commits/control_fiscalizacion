<?php

namespace App\Http\Requests;

use App\Models\Expediente;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SolicitarIncompetenciaRequest extends FormRequest
{
    /**
     * CTR-10 / B2.2: cualquier operador operativo con asignación activa
     * solicita incompetencia; nunca una causa final ni ya remitida
     * (política solicitarIncompetencia).
     */
    public function authorize(): bool
    {
        $expediente = $this->expedienteDeRuta();

        return $expediente !== null && $this->user()?->can('solicitarIncompetencia', $expediente) === true;
    }

    /**
     * Contrato CTR-10: `{unidad_destino_id, motivo, adjunto}`. La unidad
     * debe existir y estar activa; el motivo exige el mismo mínimo de 20
     * caracteres que el resto de motivos del sistema; el respaldo PDF es
     * obligatorio.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'unidad_destino_id' => [
                'required',
                'integer',
                Rule::exists('unidades', 'id')->where(fn ($query) => $query->where('activa', true)),
            ],
            'motivo' => ['required', 'string', 'min:20', 'max:5000'],
            'adjunto' => ['required', 'file', 'mimes:pdf', 'max:20480'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'unidad_destino_id.required' => 'La unidad de destino es obligatoria.',
            'unidad_destino_id.exists' => 'La unidad de destino no existe o no está activa.',
            'motivo.required' => 'El motivo de la solicitud es obligatorio.',
            'motivo.min' => 'El motivo debe tener al menos :min caracteres.',
            'adjunto.required' => 'El documento de respaldo en PDF es obligatorio.',
            'adjunto.mimes' => 'El documento de respaldo debe ser un archivo PDF.',
            'adjunto.max' => 'El documento de respaldo no puede superar los 20 MB.',
        ];
    }

    private function expedienteDeRuta(): ?Expediente
    {
        $expediente = $this->route('expediente');

        if ($expediente instanceof Expediente) {
            return $expediente;
        }

        return is_numeric($expediente) ? Expediente::find((int) $expediente) : null;
    }
}
