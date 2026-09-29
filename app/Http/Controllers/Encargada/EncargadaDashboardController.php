<?php

namespace App\Http\Controllers\Encargada;

use App\Http\Controllers\Controller;
use App\Models\Expediente;
use App\Services\EncargadaDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EncargadaDashboardController extends Controller
{
    public function __construct(
        protected EncargadaDashboardService $dashboardService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('bandejaSorteo', Expediente::class);

        return response()->json($this->dashboardService->obtenerResumen());
    }
}
