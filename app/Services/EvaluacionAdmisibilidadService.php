<?php

namespace App\Services;

use App\Models\Actuado;
use App\Models\Adjunto;
use App\Models\CatalogoActuado;
use App\Models\CatalogoEstado;
use App\Models\CatalogoRequisito;
use App\Models\EvaluacionAdmisibilidad;
use App\Models\Expediente;
use App\Models\Plazo;
use App\Models\Rol;
use App\Models\Usuario;
use App\Notifications\AvisoParaRevision;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EvaluacionAdmisibilidadService
{
    public const CODIGO_ACT_ADMISION = 'ACT_ADMISION';

    public const CODIGO_ACT_OBSERVACION = 'ACT_OBSERVACION';

    public const CODIGO_ACT_RECHAZO = 'ACT_RECHAZO';

    public const CODIGO_ACT_ACEPTACION_SUBSANACION = 'ACT_SUBSANACION_ACEPTADA';

    private const ESTADO_EN_EVALUACION = 'EN_EVALUACION';

    private const ESTADO_EN_SUBSANACION = 'EN_SUBSANACION';

    public function __construct(
        protected ActuadoService $actuadoService,
        protected AdjuntoService $adjuntoService,
        protected DocumentoPdfService $documentoPdfService,
    ) {}

    /**
     * Evalúa el checklist de admisibilidad, persiste los resultados
     * inmutables, emite el actuado correspondiente, guarda los PDF
     * presentados y genera el aviso al ciudadano.
     *
     * @param  array<int, array{requisito_id: int, cumple: bool}>  $requisitos
     * @param  array<int, array{requisito_id: int, finalidad: string, archivo: UploadedFile}>  $documentos
     * @return array{actuado: Actuado, evaluaciones: Collection, resumen: array}
     */
    public function evaluar(
        Expediente $expediente,
        Usuario $operador,
        array $requisitos,
        ?string $ipOrigen = null,
        array $documentos = [],
    ): array {
        return DB::transaction(function () use ($expediente, $operador, $requisitos, $ipOrigen, $documentos) {
            $expediente = Expediente::query()->lockForUpdate()->findOrFail($expediente->id);

            $this->verificarEstadoEnEvaluacion($expediente);

            $enviados = $this->indexarRequisitosEnviados($requisitos);

            $requisitosActivos = CatalogoRequisito::where('reglamento_id', $expediente->reglamento_id)
                ->where('activo', true)
                ->orderBy('orden')
                ->get();

            $this->validarCompletitud($enviados, $requisitosActivos);

            $faltantes = $requisitosActivos->filter(fn ($req) => ! ($enviados[$req->id] ?? false));

            $catalogoCodigo = $this->resolverCatalogo($faltantes);
            $catalogoActuado = CatalogoActuado::where('codigo', $catalogoCodigo)->firstOrFail();

            // Cerrar primero los relojes heredados: hacerlo después del actuado
            // cerraría también el plazo de 1 día que ACT_RECHAZO acaba de abrir.
            $this->desactivarRelojesSiRechazo($catalogoCodigo, $expediente);

            $actuado = $this->actuadoService->registerActuado(
                expediente: $expediente,
                catalogoActuado: $catalogoActuado,
                emisor: $operador,
                descripcion: $this->resolverDescripcion($catalogoCodigo, $faltantes),
                metadatos: ['tipo' => 'EVALUACION_ADMISIBILIDAD'],
                ipOrigen: $ipOrigen,
            );

            $this->guardarDocumentosEvaluacion($actuado, $operador, $documentos);
            $this->generarAvisoAlCiudadano($expediente, $actuado, $operador, $catalogoCodigo, $faltantes);

            $evaluaciones = $this->persistirChecklist(
                expediente: $expediente,
                operador: $operador,
                actuado: $actuado,
                requisitosActivos: $requisitosActivos,
                enviados: $enviados,
            );

            return [
                'actuado' => $actuado,
                'evaluaciones' => $evaluaciones,
                'resumen' => [
                    'resultado' => $catalogoCodigo,
                    'requisitos_cumplidos' => $evaluaciones->where('cumple', true)->count(),
                    'requisitos_faltantes' => $faltantes->count(),
                    'faltantes_criticos' => $faltantes->where('es_critico', true)->pluck('id')->values(),
                ],
            ];
        }, 3);
    }

    /**
     * El operador asignado acepta la(s) subsanación(es) presentadas por el
     * ciudadano. Cada PDF queda ligado a su requisito y el expediente pasa a
     * EN_PLANIFICACION con el reloj de 2 días.
     *
     * @param  array<int, array{requisito_id: int, archivo: UploadedFile}>  $documentos
     */
    public function aceptarSubsanacion(
        Expediente $expediente,
        Usuario $operador,
        array $documentos,
        ?string $descripcion = null,
        ?string $ipOrigen = null,
    ): Actuado {
        return DB::transaction(function () use ($expediente, $operador, $documentos, $descripcion, $ipOrigen) {
            $expediente = Expediente::query()->lockForUpdate()->findOrFail($expediente->id);

            $estado = CatalogoEstado::where('codigo', static::ESTADO_EN_SUBSANACION)->firstOrFail();
            if ($expediente->estado_actual_id !== $estado->id) {
                throw ValidationException::withMessages([
                    'expediente' => 'El expediente no está en fase de subsanación.',
                ]);
            }

            $catalogoActuado = CatalogoActuado::where('codigo', static::CODIGO_ACT_ACEPTACION_SUBSANACION)->firstOrFail();

            $actuado = $this->actuadoService->registerActuado(
                expediente: $expediente,
                catalogoActuado: $catalogoActuado,
                emisor: $operador,
                descripcion: $descripcion ?: 'Subsanación presentada por el ciudadano, aceptada por el operador asignado.',
                metadatos: [
                    'tipo' => 'SUBSANACION_ACEPTADA',
                    'requisitos' => collect($documentos)->pluck('requisito_id')->map(fn ($id) => (int) $id)->values()->all(),
                ],
                ipOrigen: $ipOrigen,
            );

            $this->guardarDocumentosEvaluacion(
                actuado: $actuado,
                operador: $operador,
                documentos: collect($documentos)
                    ->map(fn ($item) => [
                        'requisito_id' => (int) $item['requisito_id'],
                        'archivo' => $item['archivo'],
                        'finalidad' => AdjuntoService::FINALIDAD_SUBSANACION,
                    ])->all(),
            );

            /** @var Adjunto|null $adjunto */
            $adjunto = $actuado->adjuntos()->latest('id')->first();

            $this->notificarEncargada(
                expediente: $expediente,
                actuado: $actuado,
                titulo: 'Subsanación aceptada',
                detalle: sprintf(
                    'El expediente %s subsanó los requisitos observados y pasó a planificación. Los PDF quedan disponibles para su revisión; no requiere su aprobación.',
                    $expediente->nurej_code,
                ),
                categoria: 'subsanacion',
                adjunto: $adjunto,
            );

            return $actuado;
        }, 3);
    }

    private function verificarEstadoEnEvaluacion(Expediente $expediente): void
    {
        $estadoEvaluacion = CatalogoEstado::where('codigo', static::ESTADO_EN_EVALUACION)->firstOrFail();

        if ($expediente->estado_actual_id !== $estadoEvaluacion->id) {
            throw ValidationException::withMessages([
                'expediente' => 'El expediente no está en fase de evaluación de admisibilidad.',
            ]);
        }
    }

    private function indexarRequisitosEnviados(array $requisitos): array
    {
        $index = [];

        foreach ($requisitos as $item) {
            $index[(int) $item['requisito_id']] = (bool) $item['cumple'];
        }

        return $index;
    }

    private function validarCompletitud(array $enviados, $requisitosActivos): void
    {
        $activosIds = $requisitosActivos->pluck('id')->map(fn ($id) => (int) $id)->sort()->values();
        $enviadosIds = collect(array_keys($enviados))->sort()->values();

        if ($activosIds->diff($enviadosIds)->isNotEmpty() || $enviadosIds->diff($activosIds)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'requisitos' => 'Debe evaluar exactamente los requisitos activos del reglamento del expediente.',
            ]);
        }
    }

    private function resolverCatalogo($faltantes): string
    {
        if ($faltantes->isEmpty()) {
            return static::CODIGO_ACT_ADMISION;
        }

        $tieneCriticoFaltante = $faltantes->contains(fn ($req) => (bool) $req->es_critico);

        return $tieneCriticoFaltante
            ? static::CODIGO_ACT_RECHAZO
            : static::CODIGO_ACT_OBSERVACION;
    }

    private function resolverDescripcion(string $catalogoCodigo, $faltantes): string
    {
        $countFaltantes = $faltantes->count();

        return match ($catalogoCodigo) {
            static::CODIGO_ACT_ADMISION => 'Admisión: todos los requisitos de admisibilidad cumplidos.',
            static::CODIGO_ACT_OBSERVACION => "Observación: {$countFaltantes} requisito(s) no crítico(s) sin cumplir.",
            static::CODIGO_ACT_RECHAZO => "Rechazo: {$countFaltantes} requisito(s) crítico(s) ausente(s).",
            default => 'Evaluación de admisibilidad.',
        };
    }

    private function desactivarRelojesSiRechazo(string $catalogoCodigo, Expediente $expediente): void
    {
        if ($catalogoCodigo !== static::CODIGO_ACT_RECHAZO) {
            return;
        }

        Plazo::where('expediente_id', $expediente->id)
            ->whereIn('estado', ['VIGENTE', 'SUSPENDIDO'])
            ->update(['estado' => 'CERRADO']);
    }

    private function persistirChecklist(
        Expediente $expediente,
        Usuario $operador,
        $actuado,
        $requisitosActivos,
        array $enviados,
    ): Collection {
        foreach ($requisitosActivos as $requisito) {
            EvaluacionAdmisibilidad::create([
                'expediente_id' => $expediente->id,
                'requisito_id' => $requisito->id,
                'cumple' => $enviados[$requisito->id] ?? false,
                'operador_id' => $operador->id,
                'actuado_id' => $actuado->id,
            ]);
        }

        return EvaluacionAdmisibilidad::query()
            ->with(['requisito', 'operador'])
            ->where('actuado_id', $actuado->id)
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  array<int, array{requisito_id: int, finalidad: string, archivo: UploadedFile}>  $documentos
     */
    private function guardarDocumentosEvaluacion(Actuado $actuado, Usuario $operador, array $documentos): void
    {
        foreach ($documentos as $documento) {
            $adjunto = $this->adjuntoService->guardarParaActuado($actuado, $documento['archivo'], $operador);

            $adjunto->update([
                'requisito_id' => (int) $documento['requisito_id'],
                'finalidad' => $documento['finalidad'],
            ]);
        }
    }

    /**
     * Genera el aviso PDF al ciudadano y lo deja como adjunto del actuado
     * de observación o rechazo. La Encargada recibe el mismo aviso para su
     * visto de revisado, sin bloquear al técnico.
     *
     * @param  Collection<int, CatalogoRequisito>  $faltantes
     */
    private function generarAvisoAlCiudadano(
        Expediente $expediente,
        Actuado $actuado,
        Usuario $operador,
        string $catalogoCodigo,
        $faltantes,
    ): void {
        if (! in_array($catalogoCodigo, [static::CODIGO_ACT_OBSERVACION, static::CODIGO_ACT_RECHAZO], true)) {
            return;
        }

        $esRechazo = $catalogoCodigo === static::CODIGO_ACT_RECHAZO;
        $titulo = $esRechazo ? 'Aviso de rechazo de admisibilidad' : 'Aviso de observación de admisibilidad';
        $detalle = $esRechazo
            ? 'La documentación presentada no cumple requisitos de carácter crítico. Se emite el presente aviso y podrá interponer impugnación dentro del plazo de 1 día hábil desde la notificación.'
            : 'La documentación presentada requiere subsanación de requisitos no críticos. Se emite el presente aviso y dispone de 3 días hábiles para presentar la subsanación.';

        $plazo = Plazo::where('expediente_id', $expediente->id)
            ->where('actuado_disparador_id', $actuado->id)
            ->first();

        $contenidoPdf = $this->documentoPdfService->renderizar('expedientes.aviso-admisibilidad-pdf', [
            'expediente' => $expediente,
            'actuado' => $actuado,
            'titulo' => $titulo,
            'detalle' => $detalle,
            'requisitos' => $faltantes->pluck('descripcion')->filter()->values()->all(),
            'estado' => $esRechazo ? 'RECHAZADO' : 'OBSERVADO',
            'fechaLimite' => $plazo?->fecha_limite?->format('d/m/Y H:i'),
            'fechaEmision' => $actuado->fecha_hora?->format('d/m/Y H:i'),
        ]);

        $adjunto = $this->adjuntoService->guardarContenidoParaActuado(
            actuado: $actuado,
            contenido: $contenidoPdf,
            nombreOriginal: $esRechazo ? 'aviso-rechazo.pdf' : 'aviso-observacion.pdf',
            subidoPor: $operador,
        );

        $this->notificarEncargada(
            expediente: $expediente,
            actuado: $actuado,
            titulo: $titulo,
            detalle: sprintf(
                'El expediente %s tiene un aviso generado para el ciudadano. Solo se notifica a la Encargada para su visto de revisado; no requiere su aprobación.',
                $expediente->nurej_code,
            ),
            categoria: $esRechazo ? 'rechazo' : 'observacion',
            adjunto: $adjunto,
        );
    }

    private function notificarEncargada(
        Expediente $expediente,
        Actuado $actuado,
        string $titulo,
        string $detalle,
        string $categoria,
        ?Adjunto $adjunto = null,
    ): void {
        $encargada = Usuario::query()
            ->where('activo', true)
            ->whereHas('rol', fn ($query) => $query->where('codigo', Rol::CODIGO_ENCARGADA))
            ->orderBy('id')
            ->first();

        if ($encargada === null) {
            return;
        }

        $encargada->notify(new AvisoParaRevision(
            expediente: $expediente,
            actuado: $actuado,
            titulo: $titulo,
            detalle: $detalle,
            categoria: $categoria,
            adjunto: $adjunto,
        ));
    }
}
