<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ParametroPlazo extends Model
{
    protected $table = 'parametros_plazo';

    public $timestamps = false;

    protected $fillable = [
        'reglamento_id',
        'tipo_plazo',
        'subtipo',
        'dias_habiles',
        'base_legal',
        'activo',
    ];

    protected $casts = [
        'dias_habiles' => 'integer',
        'activo' => 'boolean',
    ];

    public function reglamento(): BelongsTo
    {
        return $this->belongsTo(Reglamento::class, 'reglamento_id');
    }

    public function plazos(): HasMany
    {
        return $this->hasMany(Plazo::class, 'parametro_plazo_id');
    }
}
