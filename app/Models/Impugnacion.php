<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Impugnacion extends Model
{
    protected $table = 'impugnaciones';

    public $timestamps = false;

    protected $fillable = [
        'expediente_id',
        'actuado_rechazo_id',
        'fecha_presentacion',
        'fecha_limite_resolucion',
        'resultado',
        'actuado_resolucion_id',
    ];

    protected $casts = [
        'fecha_presentacion' => 'datetime',
        'fecha_limite_resolucion' => 'date',
    ];

    public function expediente(): BelongsTo
    {
        return $this->belongsTo(Expediente::class, 'expediente_id');
    }

    public function actuadoRechazo(): BelongsTo
    {
        return $this->belongsTo(Actuado::class, 'actuado_rechazo_id');
    }

    public function actuadoResolucion(): BelongsTo
    {
        return $this->belongsTo(Actuado::class, 'actuado_resolucion_id');
    }
}
