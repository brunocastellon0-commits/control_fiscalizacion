<?php

namespace App\Services;

use App\Models\Actuado;
use App\Models\Asignacion;
use App\Models\Expediente;
use App\Models\Plazo;
use App\Models\Transferencia;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Consultas de respaldo para la pantalla y las exportaciones de los 9 reportes
 * normativos del SRS (RF-R01 a RF-R09).
 *
 * El contrato CTR-08 pertenece a Bruno (`ReportesQueryService` + `/api/reportes`).
 * Mientras ese backend no esté disponible, este servicio entrega exactamente la
 * misma forma de filas para que la pantalla y las descargas sean operativas.
 * Cuando CTR-08 se integre, la pantalla lo consume primero y este servicio queda
 * como respaldo de exportación.
 */
class ReporteDatosService
{
    /**
     * Definiciones institucionales de los 9 reportes.
     *
     * @return array<string, array{codigo: string, titulo: string, descripcion: string, columnas: array<int, array{clave: string, titulo: string}>}>
     */
    public static function definiciones(): array
    {
        return [
            'R01' => [
                'codigo' => 'R01',
                'titulo' => 'Carga laboral por usuario',
                'descripcion' => 'Casos pendientes segmentados por perfil (Técnico, Jurídico, Financiero).',
                'columnas' => [
                    ['clave' => 'usuario', 'titulo' => 'Usuario'],
                    ['clave' => 'rol', 'titulo' => 'Perfil'],
                    ['clave' => 'causas_activas', 'titulo' => 'Causas activas'],
                    ['clave' => 'vias', 'titulo' => 'Vías'],
                    ['clave' => 'ultima_asignacion', 'titulo' => 'Última asignación'],
                ],
            ],
            'R02' => [
                'codigo' => 'R02',
                'titulo' => 'Solicitudes registradas',
                'descripcion' => 'Libro matriz digital de ingresos de causas.',
                'columnas' => [
                    ['clave' => 'nurej', 'titulo' => 'NUREJ'],
                    ['clave' => 'fecha_ingreso', 'titulo' => 'Fecha de ingreso'],
                    ['clave' => 'via', 'titulo' => 'Vía'],
                    ['clave' => 'reglamento', 'titulo' => 'Reglamento'],
                    ['clave' => 'estado', 'titulo' => 'Estado'],
                    ['clave' => 'creado_por', 'titulo' => 'Registrado por'],
                ],
            ],
            'R03' => [
                'codigo' => 'R03',
                'titulo' => 'Recepción inter-unidad',
                'descripcion' => 'Auditoría y trazabilidad de trámites derivados y aceptados.',
                'columnas' => [
                    ['clave' => 'nurej', 'titulo' => 'NUREJ'],
                    ['clave' => 'unidad_destino', 'titulo' => 'Unidad destino'],
                    ['clave' => 'estado_transferencia', 'titulo' => 'Estado'],
                    ['clave' => 'fecha_remision', 'titulo' => 'Fecha de remisión'],
                    ['clave' => 'fecha_recepcion', 'titulo' => 'Fecha de recepción'],
                    ['clave' => 'remitido_por', 'titulo' => 'Remitido por'],
                    ['clave' => 'recibido_por', 'titulo' => 'Recibido por'],
                ],
            ],
            'R04' => [
                'codigo' => 'R04',
                'titulo' => 'Estado de evaluación',
                'descripcion' => 'Balance de casos admitidos, observados y rechazados.',
                'columnas' => [
                    ['clave' => 'estado', 'titulo' => 'Resultado'],
                    ['clave' => 'total', 'titulo' => 'Casos'],
                    ['clave' => 'porcentaje', 'titulo' => 'Porcentaje'],
                ],
            ],
            'R05' => [
                'codigo' => 'R05',
                'titulo' => 'Notificaciones pendientes',
                'descripcion' => 'Seguimiento de expedientes en plazo de subsanación.',
                'columnas' => [
                    ['clave' => 'nurej', 'titulo' => 'NUREJ'],
                    ['clave' => 'fecha_inicio', 'titulo' => 'Inicio'],
                    ['clave' => 'fecha_limite', 'titulo' => 'Fecha límite'],
                    ['clave' => 'dias_restantes', 'titulo' => 'Días restantes'],
                    ['clave' => 'responsable', 'titulo' => 'Responsable'],
                    ['clave' => 'estado', 'titulo' => 'Estado del trámite'],
                ],
            ],
            'R06' => [
                'codigo' => 'R06',
                'titulo' => 'Resoluciones finales',
                'descripcion' => 'Salidas institucionales: demanda disciplinaria, sumariante o archivo.',
                'columnas' => [
                    ['clave' => 'nurej', 'titulo' => 'NUREJ'],
                    ['clave' => 'via', 'titulo' => 'Vía'],
                    ['clave' => 'salida', 'titulo' => 'Salida institucional'],
                    ['clave' => 'estado', 'titulo' => 'Estado'],
                    ['clave' => 'fecha_resolucion', 'titulo' => 'Fecha de resolución'],
                    ['clave' => 'responsable', 'titulo' => 'Responsable'],
                ],
            ],
            'R07' => [
                'codigo' => 'R07',
                'titulo' => 'Estadístico por vía',
                'descripcion' => 'Indicadores de rendimiento y distribución de denuncias.',
                'columnas' => [
                    ['clave' => 'via', 'titulo' => 'Vía'],
                    ['clave' => 'total', 'titulo' => 'Total'],
                    ['clave' => 'admitidos', 'titulo' => 'Admitidos'],
                    ['clave' => 'rechazados', 'titulo' => 'Rechazados'],
                    ['clave' => 'en_tramite', 'titulo' => 'En trámite'],
                    ['clave' => 'porcentaje', 'titulo' => 'Participación'],
                ],
            ],
            'R08' => [
                'codigo' => 'R08',
                'titulo' => 'Carátula oficial',
                'descripcion' => 'Generación automática de la foja 0 del expediente en PDF.',
                'columnas' => [
                    ['clave' => 'nurej', 'titulo' => 'NUREJ'],
                    ['clave' => 'fecha_ingreso', 'titulo' => 'Fecha de ingreso'],
                    ['clave' => 'via', 'titulo' => 'Vía'],
                    ['clave' => 'estado', 'titulo' => 'Estado'],
                    ['clave' => 'partes', 'titulo' => 'Partes vigentes'],
                    ['clave' => 'nurej_padre', 'titulo' => 'NUREJ padre'],
                ],
            ],
            'R09' => [
                'codigo' => 'R09',
                'titulo' => 'Trazabilidad padre-hijo',
                'descripcion' => 'Vínculo procesal entre el expediente raíz y sus derivados.',
                'columnas' => [
                    ['clave' => 'nurej_padre', 'titulo' => 'NUREJ padre'],
                    ['clave' => 'nurej_hijo', 'titulo' => 'NUREJ hijo'],
                    ['clave' => 'fecha_ingreso', 'titulo' => 'Fecha del hijo'],
                    ['clave' => 'estado_padre', 'titulo' => 'Estado padre'],
                    ['clave' => 'estado_hijo', 'titulo' => 'Estado hijo'],
                    ['clave' => 'creado_por', 'titulo' => 'Registrado por'],
                ],
            ],
        ];
    }

    /**
     * @param  array{fecha_desde?: string|null, fecha_hasta?: string|null, usuario_id?: int|string|null, estado?: string|null}  $filtros
     * @return array<string, mixed>
     */
    public function obtener(string $codigo, array $filtros = []): array
    {
        $codigo = $this->normalizarCodigo($codigo);
        $definicion = self::definiciones()[$codigo];

        $filas = match ($codigo) {
            'R01' => $this->reporteCargaLaboral($filtros),
            'R02' => $this->reporteSolicitudesRegistradas($filtros),
            'R03' => $this->reporteRecepcionInterUnidad($filtros),
            'R04' => $this->reporteEstadoEvaluacion($filtros),
            'R05' => $this->reporteNotificacionesPendientes($filtros),
            'R06' => $this->reporteResolucionesFinales($filtros),
            'R07' => $this->reporteEstadisticoPorVia($filtros),
            'R08' => $this->reporteCaratulaOficial($filtros),
            'R09' => $this->reporteTrazabilidadPadreHijo($filtros),
        };

        return [
            'codigo' => $codigo,
            'titulo' => $definicion['titulo'],
            'descripcion' => $definicion['descripcion'],
            'columnas' => $definicion['columnas'],
            'filtros_aplicados' => $this->filtrosAplicados($filtros),
            'meta' => [
                'total' => count($filas),
                'generado_en' => now()->toIso8601String(),
            ],
            'data' => array_values($filas),
        ];
    }

    public function normalizarCodigo(string $codigo): string
    {
        $codigo = strtoupper(trim($codigo));
        $codigo = str_replace(['RF-', 'RF_', '-'], '', $codigo);

        abort_unless(array_key_exists($codigo, self::definiciones()), 404, 'Reporte no reconocido.');

        return $codigo;
    }

    /**
     * @param  array{fecha_desde?: string|null, fecha_hasta?: string|null, usuario_id?: int|string|null, estado?: string|null}  $filtros
     * @return array<int, array<string, mixed>>
     */
    private function reporteCargaLaboral(array $filtros): array
    {
        $asignaciones = Asignacion::query()
            ->with(['usuario', 'rol', 'expediente.estadoActual'])
            ->where('activa', true)
            ->when($this->fechaDesde($filtros), fn (Builder $q, string $fecha) => $q->whereDate('fecha_asignacion', '>=', $fecha))
            ->when($this->fechaHasta($filtros), fn (Builder $q, string $fecha) => $q->whereDate('fecha_asignacion', '<=', $fecha))
            ->when($this->usuarioId($filtros), fn (Builder $q, int $usuario) => $q->where('usuario_id', $usuario))
            ->when($this->estado($filtros), fn (Builder $q, string $estado) => $q->whereHas(
                'expediente.estadoActual',
                fn (Builder $sub) => $sub->where('codigo', $estado),
            ))
            ->get();

        return $asignaciones
            ->groupBy('usuario_id')
            ->map(function ($grupo): array {
                /** @var Asignacion $primera */
                $primera = $grupo->first();

                return [
                    'usuario' => $this->nombreUsuario($primera->usuario),
                    'rol' => $primera->rol?->nombre ?? '',
                    'causas_activas' => $grupo->count(),
                    'vias' => $grupo->pluck('expediente.via')->filter()->unique()->sort()->implode(', '),
                    'ultima_asignacion' => $this->formatearFecha($grupo->max('fecha_asignacion'), true),
                ];
            })
            ->sortByDesc('causas_activas')
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<int, array<string, mixed>>
     */
    private function reporteSolicitudesRegistradas(array $filtros): array
    {
        return $this->consultaExpedientes($filtros)
            ->with(['estadoActual', 'reglamento', 'creador'])
            ->orderByDesc('fecha_ingreso')
            ->limit(5000)
            ->get()
            ->map(fn (Expediente $expediente): array => [
                'nurej' => $expediente->nurej_code,
                'fecha_ingreso' => $this->formatearFecha($expediente->fecha_ingreso, true),
                'via' => $expediente->via,
                'reglamento' => $expediente->reglamento?->codigo ?? '',
                'estado' => $expediente->estadoActual?->nombre ?? '',
                'creado_por' => $this->nombreUsuario($expediente->creador),
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<int, array<string, mixed>>
     */
    private function reporteRecepcionInterUnidad(array $filtros): array
    {
        return Transferencia::query()
            ->with(['expediente.estadoActual', 'actuadoRemision.usuario', 'actuadoRecepcion.usuario'])
            ->when($this->fechaDesde($filtros), fn (Builder $q, string $fecha) => $q->whereHas(
                'actuadoRemision',
                fn (Builder $sub) => $sub->whereDate('fecha_hora', '>=', $fecha),
            ))
            ->when($this->fechaHasta($filtros), fn (Builder $q, string $fecha) => $q->whereHas(
                'actuadoRemision',
                fn (Builder $sub) => $sub->whereDate('fecha_hora', '<=', $fecha),
            ))
            ->when($this->usuarioId($filtros), fn (Builder $q, int $usuario) => $q->whereHas(
                'actuadoRemision',
                fn (Builder $sub) => $sub->where('usuario_id', $usuario),
            ))
            ->when($this->estado($filtros), fn (Builder $q, string $estado) => $q->whereHas(
                'expediente.estadoActual',
                fn (Builder $sub) => $sub->where('codigo', $estado),
            ))
            ->orderByDesc('id')
            ->limit(5000)
            ->get()
            ->map(fn (Transferencia $transferencia): array => [
                'nurej' => $transferencia->expediente?->nurej_code ?? '',
                'unidad_destino' => $transferencia->unidad_destino,
                'estado_transferencia' => $transferencia->estado,
                'fecha_remision' => $this->formatearFecha($transferencia->actuadoRemision?->fecha_hora, true),
                'fecha_recepcion' => $this->formatearFecha($transferencia->actuadoRecepcion?->fecha_hora, true),
                'remitido_por' => $this->nombreUsuario($transferencia->actuadoRemision?->usuario),
                'recibido_por' => $this->nombreUsuario($transferencia->actuadoRecepcion?->usuario),
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<int, array<string, mixed>>
     */
    private function reporteEstadoEvaluacion(array $filtros): array
    {
        $estados = ['ADMITIDO', 'OBSERVADO', 'RECHAZADO'];

        $expedientes = $this->consultaExpedientes($filtros)
            ->whereHas('estadoActual', fn (Builder $q) => $q->whereIn('codigo', $estados))
            ->with('estadoActual')
            ->get(['id', 'estado_actual_id']);

        $total = max($expedientes->count(), 1);

        return collect($estados)
            ->map(function (string $codigo) use ($expedientes, $total): array {
                $cantidad = $expedientes->filter(
                    fn (Expediente $expediente): bool => $expediente->estadoActual?->codigo === $codigo,
                )->count();

                return [
                    'estado' => $codigo,
                    'total' => $cantidad,
                    'porcentaje' => number_format(($cantidad / $total) * 100, 1).'%',
                ];
            })
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<int, array<string, mixed>>
     */
    private function reporteNotificacionesPendientes(array $filtros): array
    {
        return Plazo::query()
            ->with(['expediente.estadoActual', 'expediente.asignacionActiva.usuario'])
            ->where('tipo_plazo', 'SUBSANACION')
            ->where('estado', 'VIGENTE')
            ->when($this->fechaDesde($filtros), fn (Builder $q, string $fecha) => $q->whereDate('fecha_inicio', '>=', $fecha))
            ->when($this->fechaHasta($filtros), fn (Builder $q, string $fecha) => $q->whereDate('fecha_limite', '<=', $fecha))
            ->when($this->usuarioId($filtros), fn (Builder $q, int $usuario) => $q->whereHas(
                'expediente.asignacionActiva',
                fn (Builder $sub) => $sub->where('usuario_id', $usuario),
            ))
            ->when($this->estado($filtros), fn (Builder $q, string $estado) => $q->whereHas(
                'expediente.estadoActual',
                fn (Builder $sub) => $sub->where('codigo', $estado),
            ))
            ->orderBy('fecha_limite')
            ->get()
            ->map(function (Plazo $plazo): array {
                $diasRestantes = (int) now()->startOfDay()->diffInDays($plazo->fecha_limite->copy()->startOfDay(), false);

                return [
                    'nurej' => $plazo->expediente?->nurej_code ?? '',
                    'fecha_inicio' => $this->formatearFecha($plazo->fecha_inicio),
                    'fecha_limite' => $this->formatearFecha($plazo->fecha_limite),
                    'dias_restantes' => $diasRestantes,
                    'responsable' => $this->nombreUsuario($plazo->expediente?->asignacionActiva?->usuario),
                    'estado' => $plazo->expediente?->estadoActual?->nombre ?? '',
                ];
            })
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<int, array<string, mixed>>
     */
    private function reporteResolucionesFinales(array $filtros): array
    {
        $actuadosSalida = [
            'ACT_REPARTO_INSTITUCIONAL',
            'ACT_RESOLUCION_RATIFICA_RECHAZO',
            'ACT_RESOLUCION_REVOCA_RECHAZO',
            'ACT_ARCHIVO_POR_ABANDONO',
            'ACT_REMISION_TRANSPARENCIA',
        ];

        return Actuado::query()
            ->with(['expediente.estadoActual', 'tipoActuado', 'usuario'])
            ->whereHas('tipoActuado', fn (Builder $q) => $q->whereIn('codigo', $actuadosSalida))
            ->when($this->fechaDesde($filtros), fn (Builder $q, string $fecha) => $q->whereDate('fecha_hora', '>=', $fecha))
            ->when($this->fechaHasta($filtros), fn (Builder $q, string $fecha) => $q->whereDate('fecha_hora', '<=', $fecha))
            ->when($this->usuarioId($filtros), fn (Builder $q, int $usuario) => $q->where('usuario_id', $usuario))
            ->when($this->estado($filtros), fn (Builder $q, string $estado) => $q->whereHas(
                'expediente.estadoActual',
                fn (Builder $sub) => $sub->where('codigo', $estado),
            ))
            ->orderByDesc('fecha_hora')
            ->get()
            ->unique('expediente_id')
            ->map(fn (Actuado $actuado): array => [
                'nurej' => $actuado->expediente?->nurej_code ?? '',
                'via' => $actuado->expediente?->via ?? '',
                'salida' => $actuado->tipoActuado?->nombre ?? '',
                'estado' => $actuado->expediente?->estadoActual?->nombre ?? '',
                'fecha_resolucion' => $this->formatearFecha($actuado->fecha_hora, true),
                'responsable' => $this->nombreUsuario($actuado->usuario),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<int, array<string, mixed>>
     */
    private function reporteEstadisticoPorVia(array $filtros): array
    {
        $expedientes = $this->consultaExpedientes($filtros)
            ->with('estadoActual')
            ->get(['id', 'via', 'estado_actual_id']);

        $total = max($expedientes->count(), 1);

        return $expedientes
            ->groupBy('via')
            ->map(function ($grupo, string $via) use ($total): array {
                $admitidos = $grupo->filter(fn (Expediente $e): bool => $e->estadoActual?->codigo === 'ADMITIDO')->count();
                $rechazados = $grupo->filter(fn (Expediente $e): bool => $e->estadoActual?->codigo === 'RECHAZADO')->count();

                return [
                    'via' => $via,
                    'total' => $grupo->count(),
                    'admitidos' => $admitidos,
                    'rechazados' => $rechazados,
                    'en_tramite' => $grupo->count() - $admitidos - $rechazados,
                    'porcentaje' => number_format(($grupo->count() / $total) * 100, 1).'%',
                ];
            })
            ->sortByDesc('total')
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<int, array<string, mixed>>
     */
    private function reporteCaratulaOficial(array $filtros): array
    {
        return $this->consultaExpedientes($filtros)
            ->with(['estadoActual', 'padre'])
            ->withCount('partesVigentes')
            ->orderByDesc('fecha_ingreso')
            ->limit(5000)
            ->get()
            ->map(fn (Expediente $expediente): array => [
                'expediente_id' => $expediente->id,
                'nurej' => $expediente->nurej_code,
                'fecha_ingreso' => $this->formatearFecha($expediente->fecha_ingreso, true),
                'via' => $expediente->via,
                'estado' => $expediente->estadoActual?->nombre ?? '',
                'partes' => $expediente->partes_vigentes_count ?? 0,
                'nurej_padre' => $expediente->padre?->nurej_code ?? '',
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<int, array<string, mixed>>
     */
    private function reporteTrazabilidadPadreHijo(array $filtros): array
    {
        return $this->consultaExpedientes($filtros)
            ->whereNotNull('nurej_padre_id')
            ->with(['padre.estadoActual', 'estadoActual', 'creador'])
            ->orderByDesc('fecha_ingreso')
            ->limit(5000)
            ->get()
            ->map(fn (Expediente $expediente): array => [
                'nurej_padre' => $expediente->padre?->nurej_code ?? '',
                'nurej_hijo' => $expediente->nurej_code,
                'fecha_ingreso' => $this->formatearFecha($expediente->fecha_ingreso, true),
                'estado_padre' => $expediente->padre?->estadoActual?->nombre ?? '',
                'estado_hijo' => $expediente->estadoActual?->nombre ?? '',
                'creado_por' => $this->nombreUsuario($expediente->creador),
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return Builder<Expediente>
     */
    private function consultaExpedientes(array $filtros): Builder
    {
        return Expediente::query()
            ->when($this->fechaDesde($filtros), fn (Builder $q, string $fecha) => $q->whereDate('fecha_ingreso', '>=', $fecha))
            ->when($this->fechaHasta($filtros), fn (Builder $q, string $fecha) => $q->whereDate('fecha_ingreso', '<=', $fecha))
            ->when($this->usuarioId($filtros), fn (Builder $q, int $usuario) => $q->whereHas(
                'asignacionActiva',
                fn (Builder $sub) => $sub->where('usuario_id', $usuario),
            ))
            ->when($this->estado($filtros), fn (Builder $q, string $estado) => $q->whereHas(
                'estadoActual',
                fn (Builder $sub) => $sub->where('codigo', $estado),
            ));
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function fechaDesde(array $filtros): ?string
    {
        return $this->fechaValida($filtros['fecha_desde'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function fechaHasta(array $filtros): ?string
    {
        return $this->fechaValida($filtros['fecha_hasta'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function usuarioId(array $filtros): ?int
    {
        $usuario = $filtros['usuario_id'] ?? null;

        return is_numeric($usuario) && (int) $usuario > 0 ? (int) $usuario : null;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function estado(array $filtros): ?string
    {
        $estado = trim((string) ($filtros['estado'] ?? ''));

        return $estado !== '' ? $estado : null;
    }

    private function fechaValida(mixed $fecha): ?string
    {
        if (! is_string($fecha) || trim($fecha) === '') {
            return null;
        }

        try {
            return Carbon::parse($fecha)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, string>
     */
    private function filtrosAplicados(array $filtros): array
    {
        return collect([
            'fecha_desde' => $this->fechaDesde($filtros),
            'fecha_hasta' => $this->fechaHasta($filtros),
            'usuario_id' => $this->usuarioId($filtros),
            'estado' => $this->estado($filtros),
        ])->filter(fn ($valor) => $valor !== null && $valor !== '')->all();
    }

    private function nombreUsuario(?Usuario $usuario): string
    {
        if (! $usuario) {
            return '';
        }

        return trim($usuario->nombres.' '.$usuario->apellidos);
    }

    private function formatearFecha(mixed $fecha, bool $conHora = false): string
    {
        if ($fecha === null || $fecha === '') {
            return '';
        }

        try {
            $carbon = $fecha instanceof \DateTimeInterface
                ? Carbon::instance($fecha)
                : Carbon::parse((string) $fecha);
        } catch (\Throwable) {
            return '';
        }

        return $carbon->format($conHora ? 'd/m/Y H:i' : 'd/m/Y');
    }
}
