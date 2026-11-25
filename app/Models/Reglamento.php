<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reglamento extends Model
{
    use HasFactory;

    public const CODIGO_AC_022 = 'AC_022_2018';

    public const CODIGO_AC_054 = 'AC_054_2018';

    public const CODIGO_AC_055 = 'AC_055_2018';

    public const CODIGOS = [
        self::CODIGO_AC_022,
        self::CODIGO_AC_054,
        self::CODIGO_AC_055,
    ];

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
