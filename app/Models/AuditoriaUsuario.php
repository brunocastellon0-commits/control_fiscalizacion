<?php

namespace App\Models;

use Database\Factories\AuditoriaUsuarioFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditoriaUsuario extends Model
{
    public const ACCION_INACTIVACION = 'INACTIVACION';

    public const ACCION_ACTIVACION = 'ACTIVACION';

    /** @use HasFactory<AuditoriaUsuarioFactory> */
    use HasFactory;

    protected $table = 'auditoria_usuarios';

    public $timestamps = false;

    protected $fillable = [
        'admin_id',
        'usuario_objetivo_id',
        'accion',
        'ip_origen',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'admin_id');
    }

    public function usuarioObjetivo(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_objetivo_id');
    }
}
