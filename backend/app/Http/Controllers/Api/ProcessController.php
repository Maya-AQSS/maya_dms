<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProcessResource;
use App\Services\Contracts\ProcessServiceInterface;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProcessController extends Controller
{
    public function __construct(
        private readonly ProcessServiceInterface $processService,
    ) {}

    /**
     * Lista de procesos disponibles.
     */
    public function index(): AnonymousResourceCollection
    {
        return ProcessResource::collection($this->processService->list());
    }
}
