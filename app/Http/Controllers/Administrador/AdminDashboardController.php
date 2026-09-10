<?php

namespace App\Http\Controllers\Administrador;

use App\Http\Controllers\Controller;
use App\Models\Asignacion;
use App\Models\CatalogoEstado;
use App\Models\Expediente;
use App\Models\Feriado;
use App\Models\Rol;
use App\Models\SesionAcceso;
use App\Models\Usuario;
use App\Services\SemaforoPlazoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    public function __construct(
        protected SemaforoPlazoService $semaforoPlazo,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $usuario = $request->user();

        // Seguridad adicional: esta API solamente puede ser utilizada por ADMIN.
        if (($usuario->rol?->codigo ?? null) !== Rol::CODIGO_ADMIN) {
            abort(403, 'No tiene permisos para acceder al dashboard administrativo.');
        }

        /*
        |--------------------------------------------------------------------------
        | USUARIOS
        |--------------------------------------------------------------------------
        */
        $usuariosTotal = Usuario::count();
        $usuariosActivos = Usuario::where('activo', true)->count();
        $usuariosInactivos = Usuario::where('activo', false)->count();

        $usuariosPorRol = Rol::withCount([
            'usuarios as usuarios_activos_count' => fn ($q) => $q->where('activo', true),
            'usuarios as usuarios_total_count',
        ])
            ->get()
            ->map(fn ($rol) => [
                'codigo' => $rol->codigo,
                'nombre' => $rol->nombre,
                'activos' => $rol->usuarios_activos_count,
                'total' => $rol->usuarios_total_count,
            ])
            ->values();

        /*
        |--------------------------------------------------------------------------
        | EXPEDIENTES
        |--------------------------------------------------------------------------
        */
        $expedientesTotal = Expediente::count();

        $expedientesPorEstado = CatalogoEstado::withCount('expedientes')
            ->having('expedientes_count', '>', 0)
            ->orderByDesc('expedientes_count')
            ->get()
            ->map(fn ($estado) => [
                'codigo' => $estado->codigo,
                'nombre' => $estado->nombre,
                'total' => $estado->expedientes_count,
            ])
            ->values();

        $expedientesPorVia = Expediente::query()
            ->selectRaw('via, COUNT(*) as total')
            ->groupBy('via')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($fila) => [
                'via' => $fila->via ?? 'SIN_VIA',
                'total' => $fila->total,
            ])
            ->values();

        $expedientesSinAsignar = Expediente::whereDoesntHave('asignacionActiva')->count();

        /*
        |--------------------------------------------------------------------------
        | SEMÁFORO GLOBAL DE PLAZOS (RF-R01/RF-R05) + FUERA DE PLAZO
        |--------------------------------------------------------------------------
        */
        $expedientesConPlazos = Expediente::with(['plazos', 'asignacionActiva.usuario'])->get();

        $resumenSemaforo = $this->semaforoPlazo->resumenBandeja($expedientesConPlazos);

        $expedientesFueraDePlazo = $expedientesConPlazos
            ->map(function ($expediente) {
                $plazoUrgente = $expediente->plazos
                    ->where('estado', 'VIGENTE')
                    ->sortBy('fecha_limite')
                    ->first();

                if (! $plazoUrgente) {
                    return null;
                }

                $color = $this->semaforoPlazo->evaluarPlazo($plazoUrgente)['codigo_color'];

                if ($color !== 'FUERA_DE_PLAZO') {
                    return null;
                }

                return [
                    'id' => $expediente->id,
                    'nurej_code' => $expediente->nurej_code,
                    'asignado_a' => $expediente->asignacionActiva?->usuario
                        ? trim($expediente->asignacionActiva->usuario->nombres.' '.$expediente->asignacionActiva->usuario->apellidos)
                        : 'Sin asignar',
                ];
            })
            ->filter()
            ->take(6)
            ->values();

        /*
        |--------------------------------------------------------------------------
        | ÚLTIMOS EXPEDIENTES INGRESADOS
        |--------------------------------------------------------------------------
        */
        $ultimosExpedientes = Expediente::with(['estadoActual', 'creador'])
            ->orderByDesc('fecha_ingreso')
            ->take(5)
            ->get()
            ->map(fn ($expediente) => [
                'id' => $expediente->id,
                'nurej_code' => $expediente->nurej_code,
                'estado' => $expediente->estadoActual?->nombre,
                'fecha_ingreso' => $expediente->fecha_ingreso?->format('Y-m-d H:i'),
                'creador' => $expediente->creador
                    ? trim($expediente->creador->nombres.' '.$expediente->creador->apellidos)
                    : null,
            ]);

        /*
        |--------------------------------------------------------------------------
        | ASIGNACIONES
        |--------------------------------------------------------------------------
        */
        $asignacionesActivas = Asignacion::where('activa', true)->count();

        /*
        |--------------------------------------------------------------------------
        | SEGURIDAD: SESIONES RECIENTES
        |--------------------------------------------------------------------------
        */
        $sesionesRecientes = SesionAcceso::with('usuario')
            ->orderByDesc('login_at')
            ->take(6)
            ->get()
            ->map(fn ($sesion) => [
                'usuario' => $sesion->usuario
                    ? trim($sesion->usuario->nombres.' '.$sesion->usuario->apellidos)
                    : 'Desconocido',
                'ip_origen' => $sesion->ip_origen,
                'login_at' => $sesion->login_at?->format('Y-m-d H:i'),
                'exitoso' => $sesion->exitoso,
            ]);

        $intentosFallidos24h = SesionAcceso::where('exitoso', false)
            ->where('login_at', '>=', now()->subDay())
            ->count();

        /*
        |--------------------------------------------------------------------------
        | FERIADOS PRÓXIMOS
        |--------------------------------------------------------------------------
        */
        $feriadosProximos = Feriado::where('fecha', '>=', now()->toDateString())
            ->orderBy('fecha')
            ->take(3)
            ->get()
            ->map(fn ($feriado) => [
                'fecha' => $feriado->fecha->format('Y-m-d'),
                'descripcion' => $feriado->descripcion,
            ]);

        /*
        |--------------------------------------------------------------------------
        | RESPUESTA
        |--------------------------------------------------------------------------
        */
        return response()->json([
            'usuarios' => [
                'total' => $usuariosTotal,
                'activos' => $usuariosActivos,
                'inactivos' => $usuariosInactivos,
                'por_rol' => $usuariosPorRol,
            ],
            'expedientes' => [
                'total' => $expedientesTotal,
                'sin_asignar' => $expedientesSinAsignar,
                'por_estado' => $expedientesPorEstado,
                'por_via' => $expedientesPorVia,
                'ultimos' => $ultimosExpedientes,
            ],
            'asignaciones' => [
                'activas' => $asignacionesActivas,
            ],
            'semaforo' => $resumenSemaforo,
            'expedientes_fuera_de_plazo' => $expedientesFueraDePlazo,
            'seguridad' => [
                'sesiones_recientes' => $sesionesRecientes,
                'intentos_fallidos_24h' => $intentosFallidos24h,
            ],
            'feriados_proximos' => $feriadosProximos,
        ]);
    }
}