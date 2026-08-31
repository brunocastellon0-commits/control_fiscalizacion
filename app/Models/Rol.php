<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rol extends Model
{
    use HasFactory;

    public const CODIGO_ENCARGADA = 'ENCARGADA';

    public const CODIGO_TECNICO = 'TECNICO';

    public const CODIGO_AUD_JURIDICO = 'AUD_JURIDICO';

    public const CODIGO_AUD_FINANCIERO = 'AUD_FINANCIERO';

    public const CODIGO_ADMIN = 'ADMIN';

    protected $table = 'roles';

    public $timestamps = false;

    protected $fillable = [
        'codigo',
        'nombre',
        'descripcion',
    ];

    public function usuarios(): HasMany
    {
        return $this->hasMany(Usuario::class, 'rol_id');
    }

    public function catalogoActuados(): HasMany
    {
        return $this->hasMany(CatalogoActuado::class, 'rol_id');
    }

    public function asignaciones(): HasMany
    {
        return $this->hasMany(Asignacion::class, 'rol_id');
    }
}
