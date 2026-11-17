<?php

namespace App\Http\Controllers\Administrador;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class RequisitosController extends Controller
{
    public function index(): View
    {
        return view('administrador.requisitos');
    }
}
