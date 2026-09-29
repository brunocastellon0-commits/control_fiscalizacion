<?php

namespace App\Http\Controllers\Administrador;

use App\Http\Controllers\Controller;
use App\Models\Asignacion;
use App\Models\Expediente;
use App\Models\Rol;
use App\Services\SemaforoPlazoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminMonitoreoController extends Controller
{
    public function __construct(
        protected SemaforoPlazoService $semaforoPlazo
    ) {}

    /**
     * Monitoreo general de todos los expedientes.
     */
    public function index(Request $request): JsonResponse
    {
        $usuario = $request->user();

        if (
            ! $usuario ||
            ! $usuario->activo ||
            ($usuario->rol?->codigo ?? null) !== Rol::CODIGO_ADMIN
        ) {
            abort(403, 'No tiene permisos para acceder al monitoreo.');
        }

        $query = Expediente::query()
            ->with([
                'reglamento',
                'estadoActual',
                'asignacionActiva.usuario',
                'asignacionActiva.rol',
                'asignacionActiva.actuadoOrigen.usuario',
                'plazos',
            ]);

        /*
         * BUSCAR
         * NUREJ o resumen/descripción.
         */
        if ($request->filled('buscar')) {

            $buscar = trim($request->input('buscar'));

            $query->where(function ($q) use ($buscar) {

                $q->where('nurej_code', 'like', "%{$buscar}%")
                    ->orWhere('resumen_hechos', 'like', "%{$buscar}%");

            });
        }

        /*
         * FILTRO POR RESPONSABLE
         *
         * TECNICO:
         * AUDITOR:
         *   - AUD_JURIDICO
         *   - AUD_FINANCIERO
         */
        if ($request->filled('responsable')) {

            $responsable = $request->input('responsable');

            if ($responsable === 'TECNICO') {

                $query->whereHas(
                    'asignacionActiva.rol',
                    fn ($q) => $q->where('codigo', Rol::CODIGO_TECNICO)
                );

            } elseif ($responsable === 'AUDITOR') {

                $query->whereHas(
                    'asignacionActiva.rol',
                    fn ($q) => $q->whereIn('codigo', [
                        Rol::CODIGO_AUD_JURIDICO,
                        Rol::CODIGO_AUD_FINANCIERO,
                    ])
                );
            }
        }

        /*
         * ORDENAMIENTO BASE
         */
        $orden = $request->input('orden', 'plazo');

        if ($orden === 'reciente') {

            $query->orderBy('fecha_ingreso', 'desc');

        } elseif ($orden === 'antiguo') {

            $query->orderBy('fecha_ingreso', 'asc');

        } else {

            /*
             * Para "plazo" primero cargamos y ordenamos después
             * según el semáforo real.
             */
            $query->orderBy('fecha_ingreso', 'desc');
        }

        $expedientes = $query->get();

        /*
         * Transformar los expedientes para el administrador.
         */
        $resultado = $expedientes->map(function (Expediente $expediente) {

            $asignacion = $expediente->asignacionActiva;

            /*
             * Responsable.
             */
            $responsable = null;

            if ($asignacion && $asignacion->usuario) {

                $codigoRol = $asignacion->rol?->codigo;

                $tipoResponsable = in_array($codigoRol, [
                    Rol::CODIGO_AUD_JURIDICO,
                    Rol::CODIGO_AUD_FINANCIERO,
                ], true)
                    ? 'AUDITOR'
                    : 'TECNICO';

                $responsable = [
                    'id' => $asignacion->usuario->id,
                    'nombre' => trim(
                        $asignacion->usuario->nombres .
                        ' ' .
                        $asignacion->usuario->apellidos
                    ),
                    'rol' => $tipoResponsable,
                    'rol_nombre' => $asignacion->rol?->nombre,
                ];
            }

            /*
             * Persona que realizó la designación.
             *
             * Normalmente viene desde el actuado que originó
             * la asignación/sorteo.
             */
            $designacion = null;

            if ($asignacion) {

                $designacion = [
                    'fecha' => $asignacion->fecha_asignacion?->format('d/m/Y H:i'),
                    'por' => $asignacion->actuadoOrigen?->usuario
                        ? trim(
                            $asignacion->actuadoOrigen->usuario->nombres .
                            ' ' .
                            $asignacion->actuadoOrigen->usuario->apellidos
                        )
                        : null,
                ];
            }

            /*
             * Plazo vigente más urgente.
             */
            $plazo = $expediente->plazos
                ->where('estado', 'VIGENTE')
                ->sortBy('fecha_limite')
                ->first();

            $datosPlazo = null;
            $estadoMonitoreo = 'EN_TRAMITE';

            /*
             * Si no existe asignación activa:
             * SIN_ASIGNAR.
             */
            if (! $asignacion) {

                $estadoMonitoreo = 'SIN_ASIGNAR';

            } elseif ($plazo) {

                $sem = $this->semaforoPlazo->evaluarPlazo($plazo);

                $diasRestantes = $sem['dias_restantes'];

                $datosPlazo = [
                    'id' => $plazo->id,
                    'dias_otorgados' => $plazo->dias_habiles_otorgados,
                    'transcurridos' => max(
                        $plazo->dias_habiles_otorgados - $diasRestantes,
                        0
                    ),
                    'dias_restantes' => $diasRestantes,
                    'porcentaje' => round(
                        $sem['porcentaje_consumido'] * 100,
                        1
                    ),
                    'fecha_limite' => $sem['fecha_limite'],
                    'codigo_color' => $sem['codigo_color'],
                    'fuera_de_plazo' => $sem['es_fuera_de_plazo'],
                ];

                if ($sem['codigo_color'] === 'FUERA_DE_PLAZO') {

                    $estadoMonitoreo = 'FUERA_DE_PLAZO';

                } elseif (in_array($sem['codigo_color'], [
                    'ROJO',
                    'AMARILLO',
                ], true)) {

                    $estadoMonitoreo = 'POR_VENCER';

                } else {

                    $estadoMonitoreo = 'EN_TRAMITE';
                }
            }

            return [
                'id' => $expediente->id,

                'nurej' => $expediente->nurej_code,

                'descripcion' => $expediente->resumen_hechos,

                'fecha_ingreso' => $expediente->fecha_ingreso
                    ?->format('d/m/Y H:i'),

                'via' => $expediente->via,

                'reglamento' => $expediente->reglamento
                    ? [
                        'id' => $expediente->reglamento->id,
                        'codigo' => $expediente->reglamento->codigo,
                        'nombre' => $expediente->reglamento->nombre,
                    ]
                    : null,

                'estado_actual' => $expediente->estadoActual
                    ? [
                        'id' => $expediente->estadoActual->id,
                        'codigo' => $expediente->estadoActual->codigo,
                        'nombre' => $expediente->estadoActual->nombre,
                    ]
                    : null,

                'responsable' => $responsable,

                'designacion' => $designacion,

                'plazo' => $datosPlazo,

                'estado' => $estadoMonitoreo,
            ];
        });

        /*
         * Ordenar por situación del plazo.
         *
         * Prioridad:
         * FUERA DE PLAZO
         * POR VENCER
         * SIN ASIGNAR
         * EN TRÁMITE
         */
        if ($orden === 'plazo') {

            $prioridades = [
                'FUERA_DE_PLAZO' => 1,
                'POR_VENCER' => 2,
                'SIN_ASIGNAR' => 3,
                'EN_TRAMITE' => 4,
            ];

            $resultado = $resultado
                ->sortBy(function ($expediente) use ($prioridades) {

                    return $prioridades[$expediente['estado']] ?? 99;
                })
                ->values();
        }

        /*
         * Filtro por estado de monitoreo.
         *
         * Se hace después de calcular el semáforo porque
         * POR_VENCER y FUERA_DE_PLAZO no son estados almacenados
         * directamente en expedientes.
         */
        if ($request->filled('estado')) {

            $estado = $request->input('estado');

            $resultado = $resultado
                ->filter(
                    fn ($expediente) =>
                        $expediente['estado'] === $estado
                )
                ->values();
        }

        /*
         * Resumen.
         */
        $resumen = [
            'total' => $resultado->count(),

            'sin_asignar' => $resultado
                ->where('estado', 'SIN_ASIGNAR')
                ->count(),

            'en_tramite' => $resultado
                ->where('estado', 'EN_TRAMITE')
                ->count(),

            'por_vencer' => $resultado
                ->where('estado', 'POR_VENCER')
                ->count(),

            'fuera_plazo' => $resultado
                ->where('estado', 'FUERA_DE_PLAZO')
                ->count(),
        ];

        return response()->json([
            'data' => $resultado->values(),
            'resumen' => $resumen,
        ]);
    }
}