<?php

namespace App\Http\Requests;

use App\Models\CatalogoRequisito;
use App\Models\Expediente;
use Illuminate\Foundation\Http\FormRequest;

class StoreEvaluacionAdmisibilidadRequest extends FormRequest
{
    public function authorize(): bool
    {
        $expediente = $this->expedienteDeRuta();

        if ($expediente === null) {
            return false;
        }

        return $this->user()->can('evaluarAdmisibilidad', $expediente);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'requisitos' => ['required', 'array', 'min:1'],
            'requisitos.*.requisito_id' => ['required', 'integer', 'distinct', 'exists:catalogo_requisitos,id,activo,1'],
            'requisitos.*.cumple' => ['required', 'boolean'],
        ];
    }

    /**
     * El checklist enviado debe cubrir exactamente el conjunto de requisitos
     * activos del reglamento del expediente, para poder inferir sin ambigüedad
     * si "todos" se cumplen o cuáles faltan (admisión/observación/rechazo).
     */
    public function after(): array
    {
        $expediente = $this->expedienteDeRuta();

        if ($expediente === null) {
            return [];
        }

        $enviados = collect($this->input('requisitos'))
            ->pluck('requisito_id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values();

        $activos = CatalogoRequisito::where('reglamento_id', $expediente->reglamento_id)
            ->where('activo', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values();

        if ($enviados->count() !== $activos->count() || $enviados->sort()->values()->all() !== $activos->all()) {
            return [
                function ($validator) {
                    $validator->errors()->add(
                        'requisitos',
                        'Debe evaluar todos los requisitos activos del reglamento del expediente.',
                    );
                },
            ];
        }

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
