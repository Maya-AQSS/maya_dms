<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Contracts\AcademicHierarchyServiceInterface;
use Illuminate\Http\JsonResponse;

class AcademicHierarchyController extends Controller
{
    private AcademicHierarchyServiceInterface $hierarchyService;

    public function __construct(AcademicHierarchyServiceInterface $hierarchyService)
    {
        $this->hierarchyService = $hierarchyService;
    }

    public function index(): JsonResponse
    {
        return response()->json($this->hierarchyService->getCachedTree());
    }
}
