<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvaluacionAdmisibilidad extends Model
{
    protected $table = 'evaluaciones_admisibilidad';

    public $timestamps = false;

    protected $fillable = [
        'expediente_id',
        'requisito_id',
        'cumple',
        'actuado_id',
        'fecha',
    ];

    protected $casts = [
        'cumple' => 'boolean',
        'fecha' => 'datetime',
    ];

    public function expediente(): BelongsTo
    {
        return $this->belongsTo(Expediente::class, 'expediente_id');
    }

    public function requisito(): BelongsTo
    {
        return $this->belongsTo(CatalogoRequisito::class, 'requisito_id');
    }

    public function actuado(): BelongsTo
    {
        return $this->belongsTo(Actuado::class, 'actuado_id');
    }
}
