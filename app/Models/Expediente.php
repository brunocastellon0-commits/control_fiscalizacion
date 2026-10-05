<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Expediente extends Model
{
    protected $table = 'expedientes';

    public $timestamps = false;

    protected $fillable = [
        'nurej_code',
        'nurej_padre_id',
        'via',
        'reglamento_id',
        'estado_actual_id',
        'resumen_hechos',
        'fecha_ingreso',
        'creado_por',
    ];

    protected $casts = [
        'fecha_ingreso' => 'datetime',
        'created_at' => 'datetime',
    ];

    /**
     * RN-06 (inmutabilidad normativa): el reglamento se fija al crear la
     * causa y no puede modificarse durante su vida. Bloquea cualquier
     * actualización de `reglamento_id` (update(), forceFill()->save(),
     * asignación directa + save()) sin afectar la creación de expedientes
     * ni la de hijos mediante Expediente::create().
     */
    protected static function booted(): void
    {
        static::updating(function (Expediente $expediente): void {
            if ($expediente->isDirty('reglamento_id')) {
                throw new DomainException(
                    'RN-06: el reglamento de una causa no puede modificarse después de su creación (inmutabilidad normativa).'
                );
            }
        });
    }

    public function padre(): BelongsTo
    {
        return $this->belongsTo(Expediente::class, 'nurej_padre_id');
    }

    public function hijos(): HasMany
    {
        return $this->hasMany(Expediente::class, 'nurej_padre_id');
    }

    public function reglamento(): BelongsTo
    {
        return $this->belongsTo(Reglamento::class, 'reglamento_id');
    }

    public function estadoActual(): BelongsTo
    {
        return $this->belongsTo(CatalogoEstado::class, 'estado_actual_id');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'creado_por');
    }

    public function partes(): HasMany
    {
        return $this->hasMany(Parte::class, 'expediente_id');
    }

    public function partesVigentes(): HasMany
    {
        return $this->hasMany(Parte::class, 'expediente_id')->where('es_version_actual', true);
    }

    public function actuados(): HasMany
    {
        return $this->hasMany(Actuado::class, 'expediente_id')->orderBy('fecha_hora', 'asc');
    }

    public function asignaciones(): HasMany
    {
        return $this->hasMany(Asignacion::class, 'expediente_id');
    }

    public function asignacionActiva(): HasOne
    {
        return $this->hasOne(Asignacion::class, 'expediente_id')->where('activa', true);
    }

    public function plazos(): HasMany
    {
        return $this->hasMany(Plazo::class, 'expediente_id');
    }

    public function evaluacionesAdmisibilidad(): HasMany
    {
        return $this->hasMany(EvaluacionAdmisibilidad::class, 'expediente_id');
    }

    public function impugnaciones(): HasMany
    {
        return $this->hasMany(Impugnacion::class, 'expediente_id');
    }

    public function transferencias(): HasMany
    {
        return $this->hasMany(Transferencia::class, 'expediente_id');
    }
}
