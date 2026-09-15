<?php

namespace App\Http\Requests;

use App\Models\CatalogoActuado;
use App\Models\Expediente;
use App\Services\DescargoFinancieroService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
        $catalogoActuado = CatalogoActuado::find((int) $this->input('catalogo_actuado_id'));

        return [
            'catalogo_actuado_id' => ['required', 'integer', 'exists:catalogo_actuados,id'],
            'descripcion' => ['required', 'string', 'min:5', 'max:10000'],
            'usuario_destino_id' => ['nullable', 'integer', 'exists:usuarios,id'],
            'adjunto' => [
                Rule::requiredIf($catalogoActuado?->requiere_adjunto ?? false),
                'file',
                'mimes:pdf',
                'max:20480',
            ],
        ];
    }

    /**
     * Bloqueo de Salida (RN-09): la emisión de un Informe Final de auditoría
     * financiera (AC055) a través del endpoint genérico exige que el NUREJ haya
     * registrado previamente la recepción de descargos. Fallar aquí devuelve
     * 422 (ValidationException), no 403, para no confundir una restricción de
     * negocio con una de autorización.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $expediente = $this->expedienteDeRuta();
            $catalogo = CatalogoActuado::find((int) $this->input('catalogo_actuado_id'));

            if ($expediente !== null
                && $catalogo !== null
                && app(DescargoFinancieroService::class)->esInformeFinanciero($catalogo->codigo)) {
                app(DescargoFinancieroService::class)->validarFaseDescargosPrevia($expediente);
            }
        });
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
