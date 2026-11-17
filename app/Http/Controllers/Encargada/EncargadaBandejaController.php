<?php

namespace App\Http\Controllers\Encargada;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class EncargadaBandejaController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();
        abort_unless($user && ($user->rol?->codigo === 'ENCARGADA' || $user->esEncargada()), 403);

        return view('encargada.bandeja-supervision');
    }
}
