<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogoEstado extends Model
{
    use HasFactory;

    protected $table = 'catalogo_estados';

    public $timestamps = false;

    protected $fillable = [
        'codigo',
        'nombre',
        'estado_padre_id',
        'es_final',
    ];

    protected $casts = [
        'es_final' => 'boolean',
    ];

    public function padre(): BelongsTo
    {
        return $this->belongsTo(CatalogoEstado::class, 'estado_padre_id');
    }

    public function subestados(): HasMany
    {
        return $this->hasMany(CatalogoEstado::class, 'estado_padre_id');
    }

    public function expedientes(): HasMany
    {
        return $this->hasMany(Expediente::class, 'estado_actual_id');
    }
}
