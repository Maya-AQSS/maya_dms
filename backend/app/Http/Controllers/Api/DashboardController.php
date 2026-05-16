<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Contracts\DashboardServiceInterface;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardServiceInterface $dashboardService,
    ) {}

    /**
     * GET /api/v1/dashboard
     *
     * Muestra las métricas y documentos recientes.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->dashboardService->buildForUser(
                (string) request()->user()->getAuthIdentifier(),
            ),
        ]);
    }
}
