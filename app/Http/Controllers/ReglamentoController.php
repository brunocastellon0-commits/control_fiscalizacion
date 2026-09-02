<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexReglamentosRequest;
use App\Http\Resources\ReglamentoResource;
use App\Models\Expediente;
use App\Models\Reglamento;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ReglamentoController extends Controller
{
    /**
     * Reglamentos activos y vigentes a la fecha para el select de apertura
     * de causa.
     */
    public function index(IndexReglamentosRequest $request): AnonymousResourceCollection
    {
        $this->authorize('verCatalogoReglamentos', Expediente::class);

        $hoy = now()->toDateString();

        $reglamentos = Reglamento::where('activo', true)
            ->whereDate('vigente_desde', '<=', $hoy)
            ->where(function ($query) use ($hoy) {
                $query->whereNull('vigente_hasta')
                    ->orWhereDate('vigente_hasta', '>=', $hoy);
            })
            ->select('id', 'codigo', 'nombre', 'version')
            ->orderBy('codigo')
            ->get();

        return ReglamentoResource::collection($reglamentos);
    }
}
