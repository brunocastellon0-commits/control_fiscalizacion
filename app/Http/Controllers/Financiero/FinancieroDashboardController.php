<?php

namespace App\Http\Controllers\Financiero;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class FinancieroDashboardController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();
        abort_unless($user && ($user->rol?->codigo === 'AUD_FINANCIERO' || $user->esAuditorFinanciero()), 403);

        return view('financiero.dashboard');
    }
}
