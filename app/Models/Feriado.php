<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Feriado extends Model
{
    protected $table = 'feriados';

    public $timestamps = false;

    protected $fillable = [
        'fecha',
        'descripcion',
        'ambito',
    ];

    protected $casts = [
        'fecha' => 'date',
    ];
}
