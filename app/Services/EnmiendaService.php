<?php

namespace App\Services;

use App\Models\Actuado;
use App\Models\CatalogoActuado;
use App\Models\Expediente;
use App\Models\Parte;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * RF-02 / CTR-09 (B1.7): Actuado de Enmienda con preservación de datos.
 *
 * Corrige datos caratulados de la causa sin ediciones silenciosas: emite el
 * actuado inmutable `ACT_ENMIENDA` con `datos_anteriores` y `datos_nuevos` en
 * su payload JSON (cadena de custodia resuelta por el trigger de MySQL) y
 * aplica el cambio en la misma transacción — update directo para
 * `expedientes.resumen_hechos`, versionado (`es_version_actual` +
 * `actuado_origen_id`) para las columnas de `partes`.
 *
 * Whitelist única de `campo_modificado` (contrato interno B1.7, a coordinar
 * con B4.2):
 *  - `resumen_hechos`
 *  - `parte.DENUNCIANTE.{nombre_completo,documento_identidad,cargo_institucion}`
 *  - `parte.DENUNCIADO.{nombre_completo,documento_identidad,cargo_institucion}`
 *
 * Excluidos por decisión: `via`, `nurej_code`, `reglamento_id`,
 * `fecha_ingreso` y estado procesal (afectan plazos, reglamento o
 * identificadores de la causa).
 */
class EnmiendaService
{
    /** Código del actuado de enmienda en el catálogo. */
    public const CODIGO_ACTUADO = 'ACT_ENMIENDA';

    /**
     * Tipos de parte enmendables (RF-02: denunciante/denunciado).
     *
     * @var array<int, string>
     */
    private const TIPOS_PARTE = ['DENUNCIANTE', 'DENUNCIADO'];

    /**
     * Columnas de `partes` enmendables y su longitud máxima (varchar).
     *
     * @var array<string, int>
     */
    private const COLUMNAS_PARTE = [
        'nombre_completo' => 200,
        'documento_identidad' => 30,
        'cargo_institucion' => 150,
    ];

    /** Longitud máxima de `expedientes.resumen_hechos` (TEXT utf8mb4). */
    private const MAX_RESUMEN_HECHOS = 16000;

    public function __construct(
        protected ActuadoService $actuadoService,
    ) {}

    /**
     * Whitelist única de `campo_modificado` aceptada por CTR-09.
     *
     * @return array<int, string>
     */
    public static function camposPermitidos(): array
    {
        $campos = ['resumen_hechos'];

        foreach (self::TIPOS_PARTE as $tipo) {
            foreach (array_keys(self::COLUMNAS_PARTE) as $columna) {
                $campos[] = "parte.{$tipo}.{$columna}";
            }
        }

        return $campos;
    }

    /**
     * Longitud máxima en caracteres del valor nuevo para un campo dado;
     * devuelve el máximo general si el campo no está en la whitelist (la
     * pertenencia al whitelist la valida el FormRequest con `Rule::in`).
     */
    public static function maximoPorCampo(?string $campo): int
    {
        if ($campo === 'resumen_hechos') {
            return self::MAX_RESUMEN_HECHOS;
        }

        foreach (self::TIPOS_PARTE as $tipo) {
            foreach (self::COLUMNAS_PARTE as $columna => $maximo) {
                if ($campo === "parte.{$tipo}.{$columna}") {
                    return $maximo;
                }
            }
        }

        return self::MAX_RESUMEN_HECHOS;
    }

    /**
     * Emite el actuado de enmienda y aplica el cambio de valor.
     *
     * El estado procesal de la causa no cambia: el actuado se registra con
     * `estado_actual_id` como estado nuevo (no-op) y `ACT_ENMIENDA` no tiene
     * entradas en MAPA_TIPO_PLAZO/MAPA_CIERRA_PLAZO, por lo que no abre ni
     * cierra relojes.
     *
     * @throws ValidationException Campo fuera de whitelist, valor idéntico
     *                             al actual o parte vigente ausente/ambigua.
     */
    public function registrar(
        Expediente $expediente,
        Usuario $emisor,
        string $campoModificado,
        string $valorNuevo,
        string $motivo,
        ?string $ipOrigen = null,
    ): Actuado {
        if (! in_array($campoModificado, self::camposPermitidos(), true)) {
            throw ValidationException::withMessages([
                'campo_modificado' => 'El campo indicado no está habilitado para enmienda.',
            ]);
        }

        return DB::transaction(function () use (
            $expediente,
            $emisor,
            $campoModificado,
            $valorNuevo,
            $motivo,
            $ipOrigen,
        ) {
            $expediente = Expediente::query()->lockForUpdate()->findOrFail($expediente->id);

            $catalogoActuado = CatalogoActuado::where('codigo', self::CODIGO_ACTUADO)->firstOrFail();

            if ($campoModificado === 'resumen_hechos') {
                $anterior = $expediente->resumen_hechos;
                $this->verificarCambioReal($anterior, $valorNuevo, $campoModificado);

                $actuado = $this->emitirEnmienda(
                    expediente: $expediente,
                    emisor: $emisor,
                    catalogoActuado: $catalogoActuado,
                    campoModificado: $campoModificado,
                    anterior: $anterior,
                    valorNuevo: $valorNuevo,
                    motivo: $motivo,
                    ipOrigen: $ipOrigen,
                );

                $expediente->update(['resumen_hechos' => $valorNuevo]);

                return $actuado;
            }

            [, $tipo, $columna] = explode('.', $campoModificado);

            $vigentes = Parte::query()
                ->where('expediente_id', $expediente->id)
                ->where('tipo', $tipo)
                ->where('es_version_actual', true)
                ->lockForUpdate()
                ->get();

            if ($vigentes->count() !== 1) {
                throw ValidationException::withMessages([
                    'campo_modificado' => "La causa debe tener exactamente una versión vigente de {$tipo} para enmendar (hay {$vigentes->count()}).",
                ]);
            }

            $parteVigente = $vigentes->firstOrFail();
            $anterior = $parteVigente->{$columna};
            $this->verificarCambioReal($anterior, $valorNuevo, $campoModificado);

            $actuado = $this->emitirEnmienda(
                expediente: $expediente,
                emisor: $emisor,
                catalogoActuado: $catalogoActuado,
                campoModificado: $campoModificado,
                anterior: $anterior,
                valorNuevo: $valorNuevo,
                motivo: $motivo,
                ipOrigen: $ipOrigen,
            );

            $parteVigente->update([
                'es_version_actual' => false,
                'vigente_hasta' => now(),
            ]);

            $atributos = [
                'expediente_id' => $expediente->id,
                'tipo' => $parteVigente->tipo,
                'nombre_completo' => $parteVigente->nombre_completo,
                'documento_identidad' => $parteVigente->documento_identidad,
                'cargo_institucion' => $parteVigente->cargo_institucion,
            ];
            $atributos[$columna] = $valorNuevo;

            Parte::create($atributos + [
                'actuado_origen_id' => $actuado->id,
                'vigente_desde' => now(),
                'es_version_actual' => true,
            ]);

            return $actuado;
        }, 3);
    }

    /**
     * Emite `ACT_ENMIENDA` con la captura anterior/nueva en el payload.
     */
    private function emitirEnmienda(
        Expediente $expediente,
        Usuario $emisor,
        CatalogoActuado $catalogoActuado,
        string $campoModificado,
        mixed $anterior,
        string $valorNuevo,
        string $motivo,
        ?string $ipOrigen,
    ): Actuado {
        return $this->actuadoService->registerActuado(
            expediente: $expediente,
            catalogoActuado: $catalogoActuado,
            emisor: $emisor,
            descripcion: sprintf('Enmienda de %s (RF-02)', $campoModificado),
            usuarioDestinoId: null,
            metadatos: [
                'campo_modificado' => $campoModificado,
                'datos_anteriores' => $anterior,
                'datos_nuevos' => $valorNuevo,
                'motivo' => $motivo,
            ],
            ipOrigen: $ipOrigen,
            estadoNuevoIdExplicito: $expediente->estado_actual_id,
        );
    }

    /**
     * Una enmienda que no cambia el valor es ruido en la cadena de custodia:
     * se rechaza con 422 en lugar de emitir un actuado vacío.
     */
    private function verificarCambioReal(mixed $anterior, string $valorNuevo, string $campoModificado): void
    {
        if ((string) $anterior === $valorNuevo) {
            throw ValidationException::withMessages([
                'valor_nuevo' => "El valor de {$campoModificado} es idéntico al actual: no hay cambio que enmendar.",
            ]);
        }
    }
}
