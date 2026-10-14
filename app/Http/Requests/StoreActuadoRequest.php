<?php

namespace App\Http\Requests;

use App\Models\CatalogoActuado;
use App\Models\Expediente;
use App\Services\DescargoFinancieroService;
use App\Services\EnmiendaService;
use App\Services\TransferenciaUnidadService;
use App\Services\TransparenciaService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreActuadoRequest extends FormRequest
{
    /**
     * Divergencia 2 (aprobada): los 5 tipos de flujo especializado solo se
     * emiten por sus endpoints dedicados (CTR-04, CTR-05, CTR-09, CTR-10);
     * el endpoint genérico los bloquea con 422 (segunda barrera) y
     * CatalogoActuadoController los excluye del catálogo CTR-03.
     */
    public const TIPOS_FLUJO_ESPECIALIZADO = [
        EnmiendaService::CODIGO_ACTUADO,
        TransferenciaUnidadService::CODIGO_ACT_REMISION_UNIDAD,
        TransferenciaUnidadService::CODIGO_ACT_RECEPCION_UNIDAD,
        TransparenciaService::CODIGO_ACT_SOLICITUD,
        TransparenciaService::CODIGO_ACT_VB,
    ];

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
     * Bloqueos de negocio del endpoint genérico (422, no 403, para no
     * confundir una restricción de negocio con una de autorización):
     *
     * 1. Bloqueo de Salida (RN-09): la emisión de un Informe Final de
     *    auditoría financiera (AC055) exige que el NUREJ haya registrado
     *    previamente la recepción de descargos.
     * 2. Divergencia 2 (aprobada): los 5 tipos de flujo especializado solo
     *    pueden emitirse por sus endpoints dedicados.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $expediente = $this->expedienteDeRuta();
            $catalogo = CatalogoActuado::find((int) $this->input('catalogo_actuado_id'));

            if ($catalogo !== null && in_array($catalogo->codigo, self::TIPOS_FLUJO_ESPECIALIZADO, true)) {
                $validator->errors()->add(
                    'catalogo_actuado_id',
                    'Este tipo de actuado debe emitirse mediante su endpoint especializado.'
                );
            }

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
