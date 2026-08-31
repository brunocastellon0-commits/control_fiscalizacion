<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Actuado extends Model
{
    protected $table = 'actuados';

    public $timestamps = false;

    // hash_actuado y hash_anterior los resuelve el trigger de MySQL

    protected $fillable = [
        'expediente_id',
        'catalogo_actuado_id',
        'usuario_id',
        'fecha_hora',
        'estado_anterior_id',
        'estado_nuevo_id',
        'contenido',
        'actuado_referencia_id',
        'ip_origen',
    ];

    protected $casts = [
        'fecha_hora' => 'datetime',
        'contenido' => 'array',
    ];

    public function expediente(): BelongsTo
    {
        return $this->belongsTo(Expediente::class, 'expediente_id');
    }

    public function tipoActuado(): BelongsTo
    {
        return $this->belongsTo(CatalogoActuado::class, 'catalogo_actuado_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function estadoAnterior(): BelongsTo
    {
        return $this->belongsTo(CatalogoEstado::class, 'estado_anterior_id');
    }

    public function estadoNuevo(): BelongsTo
    {
        return $this->belongsTo(CatalogoEstado::class, 'estado_nuevo_id');
    }

    public function actuadoReferencia(): BelongsTo
    {
        return $this->belongsTo(Actuado::class, 'actuado_referencia_id');
    }

    public function adjuntos(): HasMany
    {
        return $this->hasMany(Adjunto::class, 'actuado_id');
    }
}
