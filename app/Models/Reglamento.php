<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reglamento extends Model
{
    use HasFactory;

    protected $table = 'reglamentos';

    public $timestamps = false;

    protected $fillable = [
        'codigo',
        'nombre',
        'version',
        'vigente_desde',
        'vigente_hasta',
        'activo',
    ];

    protected $casts = [
        'vigente_desde' => 'date',
        'vigente_hasta' => 'date',
        'activo' => 'boolean',
    ];

    public function expedientes(): HasMany
    {
        return $this->hasMany(Expediente::class, 'reglamento_id');
    }

    public function catalogoActuados(): HasMany
    {
        return $this->hasMany(CatalogoActuado::class, 'reglamento_id');
    }

    public function catalogoRequisitos(): HasMany
    {
        return $this->hasMany(CatalogoRequisito::class, 'reglamento_id');
    }

    public function parametrosPlazo(): HasMany
    {
        return $this->hasMany(ParametroPlazo::class, 'reglamento_id');
    }
}
