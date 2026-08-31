<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogoRequisito extends Model
{
    protected $table = 'catalogo_requisitos';

    public $timestamps = false;

    protected $fillable = [
        'reglamento_id',
        'descripcion',
        'orden',
        'activo',
    ];

    protected $casts = [
        'orden' => 'integer',
        'activo' => 'boolean',
    ];

    public function reglamento(): BelongsTo
    {
        return $this->belongsTo(Reglamento::class, 'reglamento_id');
    }

    public function evaluaciones(): HasMany
    {
        return $this->hasMany(EvaluacionAdmisibilidad::class, 'requisito_id');
    }
}
