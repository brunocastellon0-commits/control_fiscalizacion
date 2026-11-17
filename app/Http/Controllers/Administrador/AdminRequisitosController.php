<?php

namespace App\Http\Controllers\Administrador;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCatalogoRequisitoRequest;
use App\Http\Requests\UpdateCatalogoRequisitoRequest;
use App\Models\CatalogoRequisito;
use App\Models\Reglamento;
use App\Models\Rol;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminRequisitosController extends Controller
{
    /**
     * Listar el catálogo de requisitos de admisibilidad.
     */
    public function index(Request $request): JsonResponse
    {
        $this->autorizarAdmin($request);

        $query = CatalogoRequisito::query()
            ->with('reglamento')
            ->orderBy('reglamento_id')
            ->orderBy('orden')
            ->orderBy('id');

        if ($request->filled('reglamento_id')) {
            $query->where('reglamento_id', (int) $request->input('reglamento_id'));
        }

        if ($request->filled('buscar')) {
            $texto = trim((string) $request->input('buscar'));
            $query->where('descripcion', 'like', "%{$texto}%");
        }

        if ($request->has('activo') && $request->input('activo') !== '') {
            $query->where('activo', filter_var($request->input('activo'), FILTER_VALIDATE_BOOL));
        }

        if ($request->has('es_critico') && $request->input('es_critico') !== '') {
            $query->where('es_critico', filter_var($request->input('es_critico'), FILTER_VALIDATE_BOOL));
        }

        return response()->json([
            'data' => $query->get()->map(fn (CatalogoRequisito $requisito): array => $this->formatear($requisito)),
            'reglamentos' => Reglamento::query()
                ->orderBy('codigo')
                ->get()
                ->map(fn (Reglamento $reglamento): array => [
                    'id' => $reglamento->id,
                    'codigo' => $reglamento->codigo,
                    'nombre' => $reglamento->nombre,
                    'activo' => (bool) $reglamento->activo,
                ]),
        ]);
    }

    /**
     * Registrar un requisito de admisibilidad.
     */
    public function store(StoreCatalogoRequisitoRequest $request): JsonResponse
    {
        $this->autorizarAdmin($request);

        $datos = $request->validated();
        $datos['activo'] = $datos['activo'] ?? true;
        $datos['es_critico'] = $datos['es_critico'] ?? false;
        $datos['orden'] = $datos['orden'] ?? 0;

        $requisito = CatalogoRequisito::create($datos)->load('reglamento');

        return response()->json([
            'message' => 'Requisito registrado correctamente.',
            'data' => $this->formatear($requisito),
        ], 201);
    }

    /**
     * Editar un requisito del catálogo.
     */
    public function update(UpdateCatalogoRequisitoRequest $request, CatalogoRequisito $requisito): JsonResponse
    {
        $this->autorizarAdmin($request);

        $requisito->update($request->validated());

        return response()->json([
            'message' => 'Requisito actualizado correctamente.',
            'data' => $this->formatear($requisito->fresh('reglamento')),
        ]);
    }

    /**
     * Desactivar un requisito sin borrar su historial de evaluaciones.
     *
     * El catálogo es referenciado por evaluaciones inmutables; por integridad
     * se inactiva lógicamente en lugar de eliminar la fila.
     */
    public function destroy(Request $request, CatalogoRequisito $requisito): JsonResponse
    {
        $this->autorizarAdmin($request);

        $requisito->update(['activo' => false]);

        return response()->json([
            'message' => 'Requisito desactivado correctamente.',
            'data' => $this->formatear($requisito->fresh('reglamento')),
        ]);
    }

    private function autorizarAdmin(Request $request): void
    {
        $usuario = $request->user();

        abort_unless(
            $usuario !== null
            && $usuario->activo
            && ($usuario->rol?->codigo ?? null) === Rol::CODIGO_ADMIN,
            403,
            'No tiene permisos para gestionar el catálogo de requisitos.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function formatear(CatalogoRequisito $requisito): array
    {
        return [
            'id' => $requisito->id,
            'descripcion' => $requisito->descripcion,
            'orden' => $requisito->orden,
            'activo' => (bool) $requisito->activo,
            'es_critico' => (bool) $requisito->es_critico,
            'reglamento' => $requisito->reglamento ? [
                'id' => $requisito->reglamento->id,
                'codigo' => $requisito->reglamento->codigo,
                'nombre' => $requisito->reglamento->nombre,
            ] : null,
        ];
    }
}
