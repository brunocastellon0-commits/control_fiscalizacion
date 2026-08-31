<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Parte extends Model
{
    protected $table = 'partes';

    public $timestamps = false;

    protected $fillable = [
        'expediente_id',
        'tipo',
        'nombre_completo',
        'documento_identidad',
        'cargo_institucion',
        'actuado_origen_id',
        'vigente_desde',
        'vigente_hasta',
        'es_version_actual',
    ];

    protected $casts = [
        'vigente_desde' => 'datetime',
        'vigente_hasta' => 'datetime',
        'es_version_actual' => 'boolean',
    ];

    public function expediente(): BelongsTo
    {
        return $this->belongsTo(Expediente::class, 'expediente_id');
    }

    public function actuadoOrigen(): BelongsTo
    {
        return $this->belongsTo(Actuado::class, 'actuado_origen_id');
    }
}
