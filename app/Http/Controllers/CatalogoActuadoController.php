<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexCatalogoActuadosRequest;
use App\Http\Resources\CatalogoActuadoResource;
use App\Models\CatalogoActuado;
use App\Models\Expediente;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

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
            ->with(['estadoOrigen', 'estadoDestino'])
            ->orderBy('fase')
            ->orderBy('nombre');

        $rolId = $request->user()->rol_id;

        $subquery = DB::table('catalogo_actuado_roles')
            ->select('catalogo_actuado_id')
            ->where('rol_id', $rolId);

        if ($request->filled('expediente_id')) {
            $expediente = Expediente::find((int) $request->input('expediente_id'));

            if ($expediente !== null) {
                $subquery->where(fn ($q) => $q->whereNull('reglamento_id')->orWhere('reglamento_id', $expediente->reglamento_id));
            }
        }

        // Los actuados sin filas en la tabla pivote conservan el filtro legacy
        // por rol_id (datos de catálogo preexistentes).
        $query->where(function ($q) use ($rolId, $subquery) {
            $q->whereIn('id', $subquery)
                ->orWhere(function ($q2) use ($rolId) {
                    $q2->whereDoesntHave('roles')
                        ->where('rol_id', $rolId);
                });
        });

        if ($request->filled('estado_origen_id')) {
            $query->where('estado_origen_id', (int) $request->input('estado_origen_id'));
        }

        return CatalogoActuadoResource::collection($query->get());
    }
}
