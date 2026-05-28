<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DashboardResource;
use App\Services\Contracts\DashboardServiceInterface;
use Illuminate\Http\Request;

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
    public function index(Request $request): DashboardResource
    {
        $data = $this->dashboardService->buildForUser(
            (string) $request->user()->getAuthIdentifier(),
        );

        return new DashboardResource($data);
    }
}
