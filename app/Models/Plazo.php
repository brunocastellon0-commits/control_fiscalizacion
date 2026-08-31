<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Plazo extends Model
{
    protected $table = 'plazos';

    public $timestamps = false;

    protected $fillable =
        [
            'expediente_id',
            'tipo_plazo',
            'parametro_plazo_id',
            'dias_habiles_otorgados',
            'fecha_inicio',
            'fecha_limite',
            'estado',
            'fecha_pausa',
            'fecha_reanudacion',
            'fuera_de_plazo',
            'actuado_disparador_id',
            'actuado_cierre_id',
        ];

    protected $casts = [
        'dias_habiles_otorgados' => 'integer',
        'fecha_inicio' => 'date',
        'fecha_limite' => 'date',
        'fecha_pausa' => 'date',
        'fecha_reanudacion' => 'date',
        'fuera_de_plazo' => 'boolean',
    ];

    public function expediente(): BelongsTo
    {
        return $this->belongsTo(Expediente::class, 'expediente_id');
    }

    public function parametroPlazo(): BelongsTo
    {
        return $this->belongsTo(ParametroPlazo::class, 'parametro_plazo_id');
    }

    public function actuadoDisparador(): BelongsTo
    {
        return $this->belongsTo(Actuado::class, 'actuado_disparador_id');
    }

    public function actuadoCierre(): BelongsTo
    {
        return $this->belongsTo(Actuado::class, 'actuado_cierre_id');
    }
}
