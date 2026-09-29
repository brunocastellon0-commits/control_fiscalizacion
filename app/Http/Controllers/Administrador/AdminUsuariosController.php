<?php

namespace App\Http\Controllers\Administrador;

use App\Http\Controllers\Controller;
use App\Http\Requests\ActivarUsuarioRequest;
use App\Http\Requests\IndexUsuariosAdminRequest;
use App\Http\Requests\StoreUsuarioRequest;
use App\Http\Requests\UpdateUsuarioRequest;
use App\Http\Resources\UsuarioResource;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\SeguridadSesionesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Hash;

class AdminUsuariosController extends Controller
{
    public function __construct(
        protected SeguridadSesionesService $seguridadSesiones,
    ) {}

    /**
     * Listado completo de usuarios para el módulo de administración: a
     * diferencia de UsuarioController@indexOperativos (que sirve al sorteo
     * de la Encargada y por eso fuerza activo=true y solo roles operativos),
     * este endpoint no aplica ningún filtro fijo: el ADMIN debe poder ver
     * TODOS los roles y TODOS los estados, filtrando solo lo que él pida.
     */
    public function index(IndexUsuariosAdminRequest $request): AnonymousResourceCollection
    {
        $this->authorize('gestionar', Usuario::class);

        $query = Usuario::with('rol')
            ->orderBy('apellidos')
            ->orderBy('nombres');

        if ($request->filled('rol_id')) {
            $query->where('rol_id', (int) $request->input('rol_id'));
        }

        // 'activo' es un filtro EXPLÍCITO: si no viene en la petición se
        // devuelven activos e inactivos por igual. En la BD 1 = activo,
        // 0 = inactivo; el modelo lo castea a boolean, así que se compara
        // con boolean() y no con el string crudo.
        if ($request->filled('activo')) {
            $query->where('activo', $request->boolean('activo'));
        }

        if ($request->filled('buscar')) {
            $texto = trim($request->input('buscar'));
            $query->where(function ($q) use ($texto) {
                $q->where('ci', 'like', "%{$texto}%")
                    ->orWhere('username', 'like', "%{$texto}%")
                    ->orWhere('nombres', 'like', "%{$texto}%")
                    ->orWhere('apellidos', 'like', "%{$texto}%");
            });
        }

        return UsuarioResource::collection($query->get())
            ->additional([
                'roles' => Rol::orderBy('nombre')->get(['id', 'codigo', 'nombre']),
            ]);
    }

    /**
     * Creación de un usuario nuevo (rol ADMIN). Se guarda 'activo' = true
     * por defecto (columna con default en BD) y la contraseña se hashea
     * antes de persistir; nunca se recibe/almacena en texto plano.
     */
    public function store(StoreUsuarioRequest $request): JsonResponse
    {
        $this->authorize('gestionar', Usuario::class);

        $datos = $request->validated();

        $usuario = Usuario::create([
            'ci' => $datos['ci'],
            'nombres' => $datos['nombres'],
            'apellidos' => $datos['apellidos'],
            'cargo' => $datos['cargo'] ?? null,
            'username' => $datos['username'],
            'password_hash' => Hash::make($datos['password']),
            'rol_id' => $datos['rol_id'],
            'activo' => true,
        ]);

        return (new UsuarioResource($usuario->load('rol')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Edición de datos de un usuario existente. No modifica 'activo': el
     * cambio de estado tiene su propio flujo auditado (activar/inactivar).
     * La contraseña solo se actualiza si se envía un valor nuevo.
     */
    public function update(UpdateUsuarioRequest $request, Usuario $usuario): JsonResponse
    {
        $this->authorize('gestionar', Usuario::class);

        $datos = $request->validated();

        $usuario->ci = $datos['ci'];
        $usuario->nombres = $datos['nombres'];
        $usuario->apellidos = $datos['apellidos'];
        $usuario->cargo = $datos['cargo'] ?? null;
        $usuario->username = $datos['username'];
        $usuario->rol_id = $datos['rol_id'];

        if (! empty($datos['password'])) {
            $usuario->password_hash = Hash::make($datos['password']);
        }

        $usuario->save();

        return (new UsuarioResource($usuario->load('rol')))->response();
    }

    /**
     * Reactivación de un usuario previamente inactivado (RF Administrador).
     * Complementa a UsuarioController@inactivar; delega en el mismo
     * servicio de seguridad para dejar registro auditable.
     */
    public function activar(ActivarUsuarioRequest $request, Usuario $usuario): JsonResponse
    {
        $this->authorize('activar', $usuario);

        $this->seguridadSesiones->reactivar(
            admin: $request->user(),
            objetivo: $usuario,
            ipOrigen: $request->ip(),
        );

        return response()->json([
            'message' => 'Usuario reactivado correctamente.',
        ]);
    }
}
