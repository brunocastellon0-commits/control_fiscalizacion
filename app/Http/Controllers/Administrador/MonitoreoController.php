<?php

namespace App\Http\Controllers\Administrador;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class MonitoreoController extends Controller
{
    public function index(Request $request)
    {
        return view('administrador.monitoreo');
    }
}