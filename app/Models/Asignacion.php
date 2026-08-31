<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Asignacion extends Model
{
    protected $table = 'asignaciones';

    public $timestamps = false;

    protected $fillable = [
        'expediente_id',
        'usuario_id',
        'rol_id',
        'actuado_origen_id',
        'fecha_asignacion',
        'activa',
    ];

    protected $casts = [
        'fecha_asignacion' => 'datetime',
        'activa' => 'boolean',
    ];

    public function expediente(): BelongsTo
    {
        return $this->belongsTo(Expediente::class, 'expediente_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class, 'rol_id');
    }

    public function actuadoOrigen(): BelongsTo
    {
        return $this->belongsTo(Actuado::class, 'actuado_origen_id');
    }
}
