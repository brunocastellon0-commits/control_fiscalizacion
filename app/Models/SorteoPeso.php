<?php

namespace App\Models;

use Database\Factories\SorteoPesoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SorteoPeso extends Model
{
    /** @use HasFactory<SorteoPesoFactory> */
    use HasFactory;

    protected $table = 'sorteo_pesos';

    protected $fillable = [
        'usuario_id',
        'reglamento_id',
        'peso',
    ];

    protected $casts = [
        'peso' => 'integer',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function reglamento(): BelongsTo
    {
        return $this->belongsTo(Reglamento::class, 'reglamento_id');
    }
}
