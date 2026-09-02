<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexCatalogoActuadosRequest;
use App\Http\Resources\CatalogoActuadoResource;
use App\Models\CatalogoActuado;
use App\Models\Expediente;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CatalogoActuadoController extends Controller
{
    /**
     * Catalogo de actuados habilitados para el rol del usuario autenticado.
     * Solo se exponen actuados no automaticos y cuyo rol_id coincide con el
     * del usuario, evitando ofrecer acciones que provocarian un 403.
     *
     * @param  Request  $request
     */
    public function index(IndexCatalogoActuadosRequest $request): AnonymousResourceCollection
    {
        $this->authorize('verCatalogoActuados', Expediente::class);

        $query = CatalogoActuado::query()
            ->where('es_automatico', false)
            ->where('rol_id', $request->user()->rol_id)
            ->with(['estadoOrigen', 'estadoDestino'])
            ->orderBy('fase')
            ->orderBy('nombre');

        if ($request->filled('estado_origen_id')) {
            $query->where('estado_origen_id', (int) $request->input('estado_origen_id'));
        }

        return CatalogoActuadoResource::collection($query->get());
    }
}
