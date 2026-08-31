<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transferencia extends Model
{
    protected $table = 'transferencias';
    public $timestamps = false;

    protected $fillable = [
        'expediente_id',
        'unidad_destino',
        'actuado_remision_id',
        'actuado_recepcion_id',
        'estado',
    ];

    protected $casts = [
        'actuado_recepcion_id' => 'integer',
    ];

    public function expediente(): BelongsTo
    {
        return $this->belongsTo(Expediente::class, 'expediente_id');
    }

    public function actuadoRemision(): BelongsTo
    {
        return $this->belongsTo(Actuado::class, 'actuado_remision_id');
    }

    public function actuadoRecepcion(): BelongsTo
    {
        return $this->belongsTo(Actuado::class, 'actuado_recepcion_id');
    }
}
