<?php

namespace App\Http\Controllers\Administrador;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFeriadoRequest;
use App\Http\Requests\UpdateFeriadoRequest;
use App\Models\Feriado;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminFeriadosController extends Controller
{
    /**
     * Listar feriados.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('gestionar', Feriado::class);

        $query = Feriado::query()
            ->orderBy('fecha');

        /*
         * Búsqueda por descripción o ámbito.
         */
        if ($request->filled('buscar')) {
            $texto = trim($request->input('buscar'));

            $query->where(function ($q) use ($texto) {
                $q->where('descripcion', 'like', "%{$texto}%")
                    ->orWhere('ambito', 'like', "%{$texto}%");
            });
        }

        /*
         * Filtro por ámbito.
         */
        if ($request->filled('ambito')) {
            $query->where('ambito', $request->input('ambito'));
        }

        /*
         * Filtro por año.
         */
        if ($request->filled('anio')) {
            $query->whereYear('fecha', (int) $request->input('anio'));
        }

        return response()->json([
            'data' => $query->get(),
            'ambitos' => [
                'NACIONAL',
                'DEPARTAMENTAL',
                'INSTITUCIONAL',
            ],
        ]);
    }

    /**
     * Registrar un nuevo feriado.
     */
    public function store(StoreFeriadoRequest $request): JsonResponse
    {
        $this->authorize('gestionar', Feriado::class);

        $feriado = Feriado::create(
            $request->validated()
        );

        return response()->json([
            'message' => 'Feriado registrado correctamente.',
            'data' => $feriado,
        ], 201);
    }

    /**
     * Editar un feriado.
     */
    public function update(
        UpdateFeriadoRequest $request,
        Feriado $feriado
    ): JsonResponse {
        $this->authorize('gestionar', Feriado::class);

        $feriado->update(
            $request->validated()
        );

        return response()->json([
            'message' => 'Feriado actualizado correctamente.',
            'data' => $feriado->fresh(),
        ]);
    }

    /**
     * Eliminar un feriado.
     */
    public function destroy(Feriado $feriado): JsonResponse
    {
        $this->authorize('gestionar', Feriado::class);

        $feriado->delete();

        return response()->json([
            'message' => 'Feriado eliminado correctamente.',
        ]);
    }
}