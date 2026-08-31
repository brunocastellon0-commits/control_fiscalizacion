<?php

namespace App\Http\Requests;

use App\Models\CatalogoActuado;
use App\Models\Expediente;
use Illuminate\Foundation\Http\FormRequest;

class StoreActuadoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $expediente = $this->expedienteDeRuta();
        $catalogoActuado = CatalogoActuado::find((int) $this->input('catalogo_actuado_id'));

        if ($expediente === null || $catalogoActuado === null) {
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
            'catalogo_actuado_id' => ['required', 'integer', 'exists:catalogo_actuados,id'],
            'descripcion' => ['required', 'string', 'min:5', 'max:10000'],
            'usuario_destino_id' => ['nullable', 'integer', 'exists:usuarios,id'],
            'adjunto' => ['nullable', 'file', 'mimes:pdf', 'max:20480'],
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
