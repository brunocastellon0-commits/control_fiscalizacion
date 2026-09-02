<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexCatalogoEstadosRequest;
use App\Http\Resources\CatalogoEstadoResource;
use App\Models\CatalogoEstado;
use App\Models\Expediente;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CatalogoEstadoController extends Controller
{
    /**
     * Catalogo de estados del workflow. Lectura para filtros de bandejas y detalle.
     */
    public function index(IndexCatalogoEstadosRequest $request): AnonymousResourceCollection
    {
        $this->authorize('verCatalogoEstados', Expediente::class);

        $query = CatalogoEstado::query()
            ->with('padre')
            ->orderBy('codigo');

        if ($request->filled('es_final')) {
            $query->where('es_final', $request->boolean('es_final'));
        }

        if ($request->filled('estado_padre_id')) {
            $query->where('estado_padre_id', (int) $request->input('estado_padre_id'));
        }

        return CatalogoEstadoResource::collection($query->get());
    }
}
