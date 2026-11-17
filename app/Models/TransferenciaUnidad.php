<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransferenciaUnidad extends Model
{
    /**
     * La pluralización en español no la infiere Laravel
     * (transferencia_unidad -> transferencias_unidad).
     *
     * @var string
     */
    protected $table = 'transferencias_unidad';

    /**
     * Par Remisión/Recepción de una transferencia inter-unidad (RF-05).
     *
     * @var list<string>
     */
    protected $fillable = [
        'expediente_id',
        'unidad_id',
        'tipo',
        'actuado_id',
        'motivo',
        'fecha',
        'estado_previo_id',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'fecha' => 'datetime',
    ];

    /**
     * @return BelongsTo<Expediente, $this>
     */
    public function expediente(): BelongsTo
    {
        return $this->belongsTo(Expediente::class, 'expediente_id');
    }

    /**
     * Unidad destino en la remisión, unidad origen en la recepción.
     *
     * @return BelongsTo<Unidad, $this>
     */
    public function unidad(): BelongsTo
    {
        return $this->belongsTo(Unidad::class, 'unidad_id');
    }

    /**
     * Actuado del par remisión/recepción que ampara este movimiento.
     *
     * @return BelongsTo<Actuado, $this>
     */
    public function actuado(): BelongsTo
    {
        return $this->belongsTo(Actuado::class, 'actuado_id');
    }

    /**
     * Fase procesal exacta de la causa al momento de remitir (solo REMISION).
     *
     * @return BelongsTo<CatalogoEstado, $this>
     */
    public function estadoPrevio(): BelongsTo
    {
        return $this->belongsTo(CatalogoEstado::class, 'estado_previo_id');
    }
}
