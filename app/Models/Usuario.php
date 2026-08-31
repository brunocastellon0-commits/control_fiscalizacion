<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
class Usuario extends Authenticatable
{
    use HasApiTokens, HasFactory;

    protected $table = 'usuarios';

    protected $fillable = [
        'ci',
        'nombres',
        'apellidos',
        'cargo',
        'username',
        'password_hash',
        'rol_id',
        'activo',
    ];

    protected $hidden = [
        'password_hash',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class, 'rol_id');
    }

    public function sesiones(): HasMany
    {
        return $this->hasMany(SesionAcceso::class, 'usuario_id');
    }

    public function expedientesCreados(): HasMany
    {
        return $this->hasMany(Expediente::class, 'creado_por');
    }

    public function actuados(): HasMany
    {
        return $this->hasMany(Actuado::class, 'usuario_id');
    }

    public function asignaciones(): HasMany
    {
        return $this->hasMany(Asignacion::class, 'usuario_id');
    }

    public function adjuntoSubidos(): HasMany
    {
        return $this->hasMany(Adjunto::class, 'subido_por');
    }
}
