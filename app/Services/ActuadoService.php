<?php

namespace App\Services;

use App\Models\Actuado;
use App\Models\Asignacion;
use App\Models\CatalogoActuado;
use App\Models\Expediente;
use App\Models\ParametroPlazo;
use App\Models\Plazo;
use App\Models\Usuario;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ActuadoService
{
    public function __construct(
        protected PlazoCalculatorService $calculadoraPlazo,
        protected AdjuntoService $adjuntoService,
    ) {}

    /**
     * Mapea cada código de actuado del catálogo con el tipo de plazo que abre.
     * Fuera de este mapa, el actuado no dispara ningún plazo.
     */
    protected const MAPA_TIPO_PLAZO = [
        'ACT_SORTEO_INICIAL' => 'EVALUACION',
        'ACT_OBSERVACION' => 'SUBSANACION',
        'ACT_ADMISION' => 'EJECUCION',
    ];

    /**
     * Registra un actuado de forma inmutable (append-only), transiciona el
     * estado del expediente, actualiza la bandeja y abre plazos si aplica.
     *
     * El `hash_anterior` y `hash_actuado` los calcula el trigger de MySQL.
     */
    public function registerActuado(
        Expediente $expediente,
        CatalogoActuado $catalogoActuado,
        Usuario $emisor,
        string $descripcion,
        ?int $usuarioDestinoId = null,
        array $metadatos = [],
        ?string $ipOrigen = null,
        ?UploadedFile $adjunto = null,
    ): Actuado {
        return DB::transaction(function () use (
            $expediente,
            $catalogoActuado,
            $emisor,
            $descripcion,
            $usuarioDestinoId,
            $metadatos,
            $ipOrigen,
            $adjunto,
        ) {
            if ($catalogoActuado->requiere_adjunto && $adjunto === null) {
                throw ValidationException::withMessages([
                    'adjunto' => 'Este actuado exige adjuntar un documento.',
                ]);
            }

            $estadoAnteriorId = $expediente->estado_actual_id;
            $estadoNuevoId = $catalogoActuado->estado_destino_id;

            $contenido = array_merge(
                ['descripcion' => $descripcion],
                $metadatos,
            );

            if ($usuarioDestinoId !== null) {
                $contenido['usuario_destino_id'] = $usuarioDestinoId;
            }

            $actuado = Actuado::create([
                'expediente_id' => $expediente->id,
                'catalogo_actuado_id' => $catalogoActuado->id,
                'usuario_id' => $emisor->id,
                'estado_anterior_id' => $estadoAnteriorId,
                'estado_nuevo_id' => $estadoNuevoId,
                'contenido' => $contenido,
                'actuado_referencia_id' => null,
                'ip_origen' => $ipOrigen,
            ]);

            // La cadena de custodia (hash_anterior/hash_actuado) la resuelve el
            // trigger BEFORE INSERT de MySQL; refresh() trae esos valores a la
            // instancia para que queden disponibles sin recargar el registro.
            $actuado->refresh();

            if ($estadoNuevoId !== null) {
                $expediente->update([
                    'estado_actual_id' => $estadoNuevoId,
                ]);
            }

            if ($usuarioDestinoId !== null) {
                $this->reasignarBandeja($expediente, $usuarioDestinoId, $actuado);
            }

            $this->abrirPlazoSiAplica($expediente, $catalogoActuado, $actuado);

            if ($adjunto !== null) {
                $this->adjuntoService->guardarParaActuado($actuado, $adjunto, $emisor);
            }

            return $actuado;
        });
    }

    /**
     * Cierra la bandeja activa previa y crea una nueva asignación para el
     * usuario destino, tomando su rol.
     */
    protected function reasignarBandeja(Expediente $expediente, int $usuarioDestinoId, Actuado $actuado): void
    {
        Asignacion::where('expediente_id', $expediente->id)
            ->where('activa', true)
            ->update(['activa' => false]);

        $usuarioDestino = Usuario::findOrFail($usuarioDestinoId);

        Asignacion::create([
            'expediente_id' => $expediente->id,
            'usuario_id' => $usuarioDestinoId,
            'rol_id' => $usuarioDestino->rol_id,
            'actuado_origen_id' => $actuado->id,
            'fecha_asignacion' => now(),
            'activa' => true,
        ]);
    }

    /**
     * Abre un plazo cuando el actuado tiene un tipo de plazo asociado.
     */
    protected function abrirPlazoSiAplica(Expediente $expediente, CatalogoActuado $catalogoActuado, Actuado $actuado): void
    {
        $tipoPlazo = $this->resolveTipoPlazo($catalogoActuado);

        if ($tipoPlazo === null) {
            return;
        }

        $subtipo = $tipoPlazo === 'EJECUCION'
            ? $this->resolveSubtipoEjecucion($expediente)
            : null;

        $parametro = ParametroPlazo::where('reglamento_id', $expediente->reglamento_id)
            ->where('tipo_plazo', $tipoPlazo)
            ->where('subtipo', $subtipo)
            ->first();

        if ($parametro === null && $subtipo !== null) {
            $parametro = ParametroPlazo::where('reglamento_id', $expediente->reglamento_id)
                ->where('tipo_plazo', $tipoPlazo)
                ->whereNull('subtipo')
                ->first();
        }

        if ($parametro === null) {
            return;
        }

        $fechaLimite = $this->calculadoraPlazo->calculateDueDate(now(), $parametro->dias_habiles);

        Plazo::create([
            'expediente_id' => $expediente->id,
            'tipo_plazo' => $tipoPlazo,
            'parametro_plazo_id' => $parametro->id,
            'dias_habiles_otorgados' => $parametro->dias_habiles,
            'fecha_inicio' => now(),
            'fecha_limite' => $fechaLimite,
            'estado' => 'VIGENTE',
            'actuado_disparador_id' => $actuado->id,
        ]);
    }

    /**
     * Devuelve el tipo de plazo que abre un actuado, o null si ninguno.
     */
    protected function resolveTipoPlazo(CatalogoActuado $catalogoActuado): ?string
    {
        return static::MAPA_TIPO_PLAZO[$catalogoActuado->codigo] ?? null;
    }

    /**
     * Resuelve el subtipo del plazo de ejecución según la vía del expediente:
     * JURIDICO → JURISDICCIONAL, el resto → ADMINISTRATIVA.
     */
    protected function resolveSubtipoEjecucion(Expediente $expediente): string
    {
        return $expediente->via === 'JURIDICO'
            ? 'JURISDICCIONAL'
            : 'ADMINISTRATIVA';
    }
}
