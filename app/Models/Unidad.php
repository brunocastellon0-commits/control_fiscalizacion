<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unidad extends Model
{
    /**
     * La pluralización en español no la infiere Laravel (unidad -> unidades).
     *
     * @var string
     */
    protected $table = 'unidades';

    /**
     * Catálogo maestro de unidades organizacionales externas (RF-05).
     *
     * @var list<string>
     */
    protected $fillable = [
        'codigo',
        'nombre',
        'activa',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'activa' => 'boolean',
    ];

    /**
     * Transferencias inter-unidad (remisión/recepción) con esta unidad como
     * destino (REMISION) u origen (RECEPCION).
     *
     * @return HasMany<TransferenciaUnidad, $this>
     */
    public function transferencias(): HasMany
    {
        return $this->hasMany(TransferenciaUnidad::class, 'unidad_id');
    }
}
