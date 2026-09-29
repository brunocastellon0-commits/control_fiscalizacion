<?php

namespace App\Services;

use App\Models\Asignacion;
use App\Models\CatalogoEstado;
use App\Models\Expediente;
use App\Models\Feriado;

class EncargadaDashboardService
{
    public function __construct(
        protected SemaforoPlazoService $semaforoPlazo,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function obtenerResumen(): array
    {
        $expedientesTotales = Expediente::count();
        $estadoPendienteId = CatalogoEstado::where('codigo', 'PENDIENTE_SORTEO')->value('id');

        $pendientesSorteo = $estadoPendienteId === null
            ? 0
            : Expediente::where('estado_actual_id', $estadoPendienteId)->count();

        $expedientesPorEstado = CatalogoEstado::withCount('expedientes')
            ->having('expedientes_count', '>', 0)
            ->orderByDesc('expedientes_count')
            ->get()
            ->map(fn (CatalogoEstado $estado): array => [
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
            ->map(fn (Expediente $expediente): array => [
                'via' => $expediente->via,
                'total' => (int) $expediente->getAttribute('total'),
            ])
            ->values();

        $ultimosExpedientes = Expediente::query()
            ->select([
                'id',
                'nurej_code',
                'via',
                'fecha_ingreso',
                'estado_actual_id',
                'creado_por',
            ])
            ->with([
                'estadoActual:id,codigo,nombre',
                'creador:id,nombres,apellidos',
                'asignacionActiva' => fn ($query) => $query->select([
                    'id',
                    'expediente_id',
                    'usuario_id',
                    'activa',
                ]),
                'asignacionActiva.usuario:id,nombres,apellidos',
            ])
            ->latest('fecha_ingreso')
            ->limit(5)
            ->get()
            ->map(fn (Expediente $expediente): array => [
                'id' => $expediente->id,
                'nurej_code' => $expediente->nurej_code,
                'via' => $expediente->via,
                'estado' => $expediente->estadoActual?->nombre,
                'fecha_ingreso' => $expediente->fecha_ingreso?->format('Y-m-d H:i'),
                'creador' => $expediente->creador
                    ? trim($expediente->creador->nombres.' '.$expediente->creador->apellidos)
                    : null,
                'asignado_a' => $expediente->asignacionActiva?->usuario
                    ? trim($expediente->asignacionActiva->usuario->nombres.' '.$expediente->asignacionActiva->usuario->apellidos)
                    : null,
            ])
            ->values();

        $expedientesConPlazos = Expediente::query()
            ->select(['id', 'nurej_code', 'estado_actual_id'])
            ->whereHas('plazos', fn ($query) => $query->where('estado', 'VIGENTE'))
            ->with([
                'estadoActual:id,codigo,nombre',
                'plazos' => fn ($query) => $query
                    ->select([
                        'id',
                        'expediente_id',
                        'tipo_plazo',
                        'dias_habiles_otorgados',
                        'fecha_inicio',
                        'fecha_limite',
                        'estado',
                    ])
                    ->where('estado', 'VIGENTE')
                    ->orderBy('fecha_limite'),
                'asignacionActiva' => fn ($query) => $query->select([
                    'id',
                    'expediente_id',
                    'usuario_id',
                    'activa',
                ]),
                'asignacionActiva.usuario:id,nombres,apellidos',
            ])
            ->get();

        $vencimientos = $expedientesConPlazos
            ->map(function (Expediente $expediente): ?array {
                $plazo = $expediente->plazos->first();

                if ($plazo === null) {
                    return null;
                }

                $semaforo = $this->semaforoPlazo->evaluarPlazo($plazo);

                return [
                    'id' => $plazo->id,
                    'expediente_id' => $expediente->id,
                    'nurej_code' => $expediente->nurej_code,
                    'tipo_plazo' => $plazo->tipo_plazo,
                    'fecha_limite' => $semaforo['fecha_limite'],
                    'codigo_color' => $semaforo['codigo_color'],
                    'dias_restantes' => $semaforo['dias_restantes'],
                    'estado_expediente' => $expediente->estadoActual?->nombre,
                    'asignado_a' => $expediente->asignacionActiva?->usuario
                        ? trim($expediente->asignacionActiva->usuario->nombres.' '.$expediente->asignacionActiva->usuario->apellidos)
                        : 'Sin asignar',
                ];
            })
            ->filter()
            ->sortBy('fecha_limite')
            ->values();

        $cargaOperadores = Asignacion::query()
            ->select(['usuario_id', 'rol_id'])
            ->selectRaw('COUNT(*) as total')
            ->where('activa', true)
            ->with([
                'usuario:id,nombres,apellidos,rol_id',
                'rol:id,codigo,nombre',
            ])
            ->groupBy('usuario_id', 'rol_id')
            ->orderByDesc('total')
            ->get()
            ->map(fn (Asignacion $asignacion): array => [
                'usuario_id' => $asignacion->usuario_id,
                'nombre' => $asignacion->usuario
                    ? trim($asignacion->usuario->nombres.' '.$asignacion->usuario->apellidos)
                    : 'Usuario no disponible',
                'rol' => $asignacion->rol?->nombre,
                'total' => (int) $asignacion->getAttribute('total'),
            ])
            ->values();

        $feriadosProximos = Feriado::where('fecha', '>=', now()->toDateString())
            ->orderBy('fecha')
            ->limit(3)
            ->get()
            ->map(fn (Feriado $feriado): array => [
                'fecha' => $feriado->fecha->format('Y-m-d'),
                'descripcion' => $feriado->descripcion,
            ])
            ->values();

        return [
            'expedientes' => [
                'total' => $expedientesTotales,
                'pendientes_sorteo' => $pendientesSorteo,
                'sin_asignar' => Expediente::whereDoesntHave('asignacionActiva')->count(),
                'por_estado' => $expedientesPorEstado,
                'por_via' => $expedientesPorVia,
                'ultimos' => $ultimosExpedientes,
            ],
            'asignaciones' => [
                'activas' => Asignacion::where('activa', true)->count(),
                'carga_operadores' => $cargaOperadores,
            ],
            'semaforo' => $this->semaforoPlazo->resumenBandeja($expedientesConPlazos),
            'vencimientos' => [
                'fuera_de_plazo' => $vencimientos
                    ->where('codigo_color', 'FUERA_DE_PLAZO')
                    ->take(6)
                    ->values(),
                'proximos' => $vencimientos
                    ->where('codigo_color', '!=', 'FUERA_DE_PLAZO')
                    ->take(6)
                    ->values(),
            ],
            'feriados_proximos' => $feriadosProximos,
        ];
    }
}
