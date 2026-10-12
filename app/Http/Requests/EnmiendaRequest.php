<?php

namespace App\Http\Requests;

use App\Models\CatalogoActuado;
use App\Models\Expediente;
use App\Services\EnmiendaService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EnmiendaRequest extends FormRequest
{
    /**
     * CTR-09 (RF-02): operador con asignación activa del expediente o la
     * Encargada; la habilitación por rol/reglamento y la asignación activa
     * las resuelve ExpedientePolicy::crearActuado sobre ACT_ENMIENDA.
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
        $campoModificado = $this->string('campo_modificado')->toString();

        return [
            'campo_modificado' => ['required', 'string', Rule::in(EnmiendaService::camposPermitidos())],
            'valor_nuevo' => ['required', 'string', 'max:'.EnmiendaService::maximoPorCampo($campoModificado)],
            'motivo' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'campo_modificado.required' => 'El campo a enmendar es obligatorio.',
            'campo_modificado.in' => 'El campo indicado no está habilitado para enmienda.',
            'valor_nuevo.required' => 'El valor nuevo es obligatorio.',
            'valor_nuevo.max' => 'El valor nuevo excede la longitud máxima permitida para el campo.',
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
