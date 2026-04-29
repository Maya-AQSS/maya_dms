<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Contracts\ProcessServiceInterface;
use Illuminate\Http\JsonResponse;

class ProcessController extends Controller
{
    public function __construct(
        private readonly ProcessServiceInterface $processService,
    ) {}

    /**
     * Lista de procesos disponibles.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->processService->list(),
        ]);
    }
}
