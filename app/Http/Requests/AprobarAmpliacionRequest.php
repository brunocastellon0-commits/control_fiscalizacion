<?php

namespace App\Http\Requests;

use App\Models\Expediente;
use Illuminate\Foundation\Http\FormRequest;

class AprobarAmpliacionRequest extends FormRequest
{
    /**
     * US-2.6: solo la Encargada activa aprueba la ampliación de un expediente
     * en PENDIENTE_APROBACION_AMPLIACION.
     */
    public function authorize(): bool
    {
        $expediente = $this->expedienteDeRuta();

        return $expediente !== null && $this->user()->can('aprobarAmpliacion', $expediente);
    }

    /**
     * La aprobación no recibe cuerpo: la resolución y los límites los calcula
     * el servicio a partir del plazo original.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
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
