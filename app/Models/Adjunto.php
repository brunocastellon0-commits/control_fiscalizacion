<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Adjunto extends Model
{
    protected $table = 'adjuntos';

    public $timestamps = false;

    protected $fillable = [
        'actuado_id',
        'nombre_original',
        'ruta_almacenamiento',
        'hash_sha256',
        'mime_type',
        'tamanio_bytes',
        'subido_por',
        'subido_at',
    ];

    protected $casts = [
        'tamanio_bytes' => 'integer',
        'subido_at' => 'datetime',
    ];

    public function actuado(): BelongsTo
    {
        return $this->belongsTo(Actuado::class, 'actuado_id');
    }

    public function subidoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'subido_por');
    }
}
