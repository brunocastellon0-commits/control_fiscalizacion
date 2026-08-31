<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SesionAcceso extends Model
{
    protected $table = 'sesiones_acceso';

    public $timestamps = false;

    protected $fillable = [
        'usuario_id',
        'ip_origen',
        'login_at',
        'logout_at',
        'exitoso',
    ];

    protected $casts = [
        'login_at' => 'datetime',
        'logout_at' => 'datetime',
        'exitoso' => 'boolean',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
