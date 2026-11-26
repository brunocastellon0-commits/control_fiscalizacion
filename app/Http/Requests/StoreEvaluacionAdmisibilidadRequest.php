<?php

namespace App\Http\Requests;

use App\Models\CatalogoRequisito;
use App\Models\Expediente;
use App\Services\AdjuntoService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'documentos' => ['nullable', 'array'],
            'documentos.*.requisito_id' => ['required', 'integer', 'distinct', 'exists:catalogo_requisitos,id,activo,1'],
            'documentos.*.finalidad' => ['required', Rule::in([
                AdjuntoService::FINALIDAD_SUBSANACION,
                AdjuntoService::FINALIDAD_IMPUGNACION,
            ])],
            'documentos.*.archivo' => ['required', 'file', 'mimes:pdf', 'max:10240'],
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

        return [
            function ($validator) use ($expediente) {
                $cumplidos = collect($this->input('requisitos'))
                    ->mapWithKeys(fn ($item) => [(int) $item['requisito_id'] => (bool) $item['cumple']]);

                $criticos = CatalogoRequisito::where('reglamento_id', $expediente->reglamento_id)
                    ->where('activo', true)
                    ->get()
                    ->mapWithKeys(fn ($req) => [(int) $req->id => (bool) $req->es_critico]);

                $documentos = collect($this->input('documentos'))
                    ->mapWithKeys(fn ($item) => [(int) $item['requisito_id'] => $item]);

                foreach ($cumplidos as $requisitoId => $cumple) {
                    $esperado = $criticos->get($requisitoId) === true
                        ? 'IMPUGNACION'
                        : 'SUBSANACION';

                    if (! $cumple && ! $documentos->has($requisitoId)) {
                        $validator->errors()->add(
                            'documentos',
                            "Debe cargar el PDF de {$esperado} para el requisito {$requisitoId} no cumplido.",
                        );

                        continue;
                    }

                    if ($cumple && $documentos->has($requisitoId)) {
                        $validator->errors()->add(
                            'documentos',
                            "No se puede adjuntar un PDF en el requisito {$requisitoId} marcado como cumplido.",
                        );

                        continue;
                    }

                    if (! $cumple && ($documentos->get($requisitoId)['finalidad'] ?? null) !== $esperado) {
                        $validator->errors()->add(
                            'documentos',
                            "La finalidad del PDF para el requisito {$requisitoId} debe ser {$esperado}.",
                        );
                    }
                }
            },
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
