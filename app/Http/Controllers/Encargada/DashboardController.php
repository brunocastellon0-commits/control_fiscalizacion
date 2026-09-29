<?php

namespace App\Http\Controllers\Encargada;

use App\Http\Controllers\Controller;
use App\Models\Expediente;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $this->authorize('bandejaSorteo', Expediente::class);

        return view('encargada.dashboard');
    }
}
