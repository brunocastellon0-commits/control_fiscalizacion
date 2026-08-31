<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogoActuado extends Model
{
    protected $table = 'catalogo_actuados';

    public $timestamps = false;

    protected $fillable = [
        'codigo',
        'nombre',
        'fase',
        'rol_id',
        'reglamento_id',
        'estado_origen_id',
        'estado_destino_id',
        'es_automatico',
        'requiere_adjunto',
        'descripcion',
    ];

    protected $casts = [
        'es_automatico' => 'boolean',
        'requiere_adjunto' => 'boolean',
    ];

    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class, 'rol_id');
    }

    public function reglamento(): BelongsTo
    {
        return $this->belongsTo(Reglamento::class, 'reglamento_id');
    }

    public function estadoOrigen(): BelongsTo
    {
        return $this->belongsTo(CatalogoEstado::class, 'estado_origen_id');
    }

    public function estadoDestino(): BelongsTo
    {
        return $this->belongsTo(CatalogoEstado::class, 'estado_destino_id');
    }

    public function actuados(): HasMany
    {
        return $this->hasMany(Actuado::class, 'catalogo_actuado_id');
    }
}
