<?php

namespace App\Http\Requests;

use App\Models\CatalogoActuado;
use App\Models\Expediente;
use App\Services\EnmiendaService;
use Illuminate\Foundation\Http\FormRequest;

class StoreEnmiendaActuadoRequest extends FormRequest
{
    /**
     * Enmienda de un actuado (Fase B): misma ability que CTR-09 —
     * ExpedientePolicy::crearActuado sobre ACT_ENMIENDA resuelve rol+reglamento,
     * asignación activa y bloquea expedientes en REMITIDO_UNIDAD_EXTERNA.
     */
    public function authorize(): bool
    {
        $expediente = $this->expedienteDeRuta();

        if ($expediente === null) {
            return false;
        }

        $catalogoActuado = CatalogoActuado::where('codigo', EnmiendaService::CODIGO_ACTUADO)->first();

        if ($catalogoActuado === null) {
            return false;
        }

        return $this->user()->can('crearActuado', [$expediente, $catalogoActuado]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'descripcion' => ['required', 'string', 'min:5', 'max:10000'],
            'motivo' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'descripcion.required' => 'La descripción corregida es obligatoria.',
            'descripcion.min' => 'La descripción corregida debe tener al menos :min caracteres.',
            'descripcion.max' => 'La descripción corregida no puede exceder :max caracteres.',
            'motivo.required' => 'El motivo de la enmienda es obligatorio.',
            'motivo.min' => 'El motivo debe tener al menos :min caracteres.',
            'motivo.max' => 'El motivo no puede exceder :max caracteres.',
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
