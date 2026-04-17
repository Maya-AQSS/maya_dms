<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
class DashboardController extends Controller
{
    /**
     * GET /api/v1/dashboard
     * 
     * Muestra las métricas y documentos recientes.
     * 
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => [
                'stats'             => [],
                'recent_documents'  => [],
            ],
        ]);
    }
}
