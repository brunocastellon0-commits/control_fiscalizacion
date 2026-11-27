<?php

namespace App\Http\Controllers;

use App\Models\Unidad;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UnidadController extends Controller
{
    /**
     * Lista las unidades organizacionales activas, con filtro opcional por tipo
     * (p. ej. ?tipo=TRANSPARENCIA para derivación por incompetencia Ley 974).
     */
    public function index(Request $request): JsonResponse
    {
        $tipo = $request->query('tipo');

        $unidades = Unidad::query()
            ->where('activa', true)
            ->when(is_string($tipo) && $tipo !== '', fn ($q) => $q->where('tipo', $tipo))
            ->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre', 'tipo']);

        return response()->json([
            'data' => $unidades,
        ]);
    }
}
