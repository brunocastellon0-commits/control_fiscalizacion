<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SuspensionPlazo extends Model
{
    protected $table = 'suspensiones_plazo';

    public $timestamps = false;

    protected $fillable = [
        'fecha_inicio',
        'fecha_fin',
        'motivo',
        'creado_por',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
    ];

    public function creador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'creado_por');
    }
}
