<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Contracts\AcademicHierarchyServiceInterface;
use Illuminate\Http\JsonResponse;

class AcademicHierarchyController extends Controller
{
    public function __construct(
        private readonly AcademicHierarchyServiceInterface $hierarchyService,
    ) {}

    /**
     * Árbol de jerarquía académica, con caché Redis.
     */
    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->hierarchyService->getCachedTree()]);
    }
}
