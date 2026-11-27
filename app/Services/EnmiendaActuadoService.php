<?php

namespace App\Services;

use App\Models\Actuado;
use App\Models\CatalogoActuado;
use App\Models\Expediente;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Enmienda de un actuado (Fase B): corrige la descripción de un actuado ya
 * emitido sin alterarlo — el original es inmutable por los triggers de MySQL
 * (append-only) y la corrección se registra como un nuevo actuado
 * ACT_ENMIENDA que lo referencia vía actuado_referencia_id.
 *
 * A diferencia de CTR-09 (EnmiendaService), aquí NO se transiciona estado,
 * NO se abren ni cierran relojes y NO se toca la bandeja: el actuado se crea
 * directamente con estado_anterior = estado_nuevo = estado actual (no-op).
 */
class EnmiendaActuadoService
{
    /**
     * @param  string  $descripcion  Descripción corregida del actuado original.
     * @param  string  $motivo  Motivo de la enmienda (obligatorio, min 10).
     * @return Actuado El actuado de enmienda emitido (cadena de custodia por trigger).
     *
     * @throws ValidationException El actuado ya fue enmendado (doble enmienda)
     *                             o el destino es él mismo una enmienda.
     */
    public function registrar(
        Expediente $expediente,
        Actuado $actuado,
        Usuario $emisor,
        string $descripcion,
        string $motivo,
        ?string $ipOrigen = null,
    ): Actuado {
        return DB::transaction(function () use (
            $expediente,
            $actuado,
            $emisor,
            $descripcion,
            $motivo,
            $ipOrigen,
        ): Actuado {
            // Lock del expediente (patrón EnmiendaService): serializa las
            // enmiendas concurrentes del mismo caso para que la verificación
            // de doble enmienda sea atómica.
            $expediente = Expediente::query()->lockForUpdate()->findOrFail($expediente->id);

            $catalogoActuado = CatalogoActuado::where('codigo', EnmiendaService::CODIGO_ACTUADO)->firstOrFail();

            $yaEnmendado = Actuado::query()
                ->where('actuado_referencia_id', $actuado->id)
                ->exists();

            if ($yaEnmendado) {
                throw ValidationException::withMessages([
                    'actuado' => 'El actuado ya fue enmendado: no admite una segunda enmienda.',
                ]);
            }

            if ($actuado->actuado_referencia_id !== null) {
                throw ValidationException::withMessages([
                    'actuado' => 'No se puede enmendar un actuado que ya es una enmienda.',
                ]);
            }

            $enmienda = Actuado::create([
                'expediente_id' => $expediente->id,
                'catalogo_actuado_id' => $catalogoActuado->id,
                'usuario_id' => $emisor->id,
                'estado_anterior_id' => $expediente->estado_actual_id,
                'estado_nuevo_id' => $expediente->estado_actual_id,
                'contenido' => [
                    'descripcion' => $descripcion,
                    'motivo' => $motivo,
                ],
                'actuado_referencia_id' => $actuado->id,
                'ip_origen' => $ipOrigen,
            ]);

            // El trigger BEFORE INSERT resuelve hash_anterior/hash_actuado.
            $enmienda->refresh();

            return $enmienda;
        }, 3);
    }
}
