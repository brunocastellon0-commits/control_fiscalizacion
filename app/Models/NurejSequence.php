<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NurejSequence extends Model
{
    protected $table = 'nurej_sequences';

    protected $primaryKey = 'anio';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'anio',
        'correlativo',
    ];

    protected $casts = [
        'anio' => 'integer',
        'correlativo' => 'integer',
    ];
}
