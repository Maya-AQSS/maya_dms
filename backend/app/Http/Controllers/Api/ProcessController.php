<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Processes\DestroyProcessRequest;
use App\Http\Requests\Processes\IndexProcessRequest;
use App\Http\Requests\Processes\ShowProcessRequest;
use App\Http\Requests\Processes\StoreProcessRequest;
use App\Http\Requests\Processes\UpdateProcessRequest;
use App\Http\Resources\ProcessResource;
use App\Services\Contracts\ProcessServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ProcessController extends Controller
{
    public function __construct(
        private readonly ProcessServiceInterface $processService,
    ) {}

    public function index(IndexProcessRequest $request): AnonymousResourceCollection
    {
        $filters = $request->only(['search', 'parent_id']);
        $perPage = (int) $request->input('per_page', 20);

        if ($request->anyFilled(['search', 'parent_id']) || $request->has('page')) {
            return ProcessResource::collection($this->processService->paginate($filters, $perPage));
        }

        return ProcessResource::collection($this->processService->list());
    }

    public function show(ShowProcessRequest $request, string $process): ProcessResource
    {
        return new ProcessResource($this->processService->findOrFail($process));
    }

    public function store(StoreProcessRequest $request): JsonResponse
    {
        $row = $this->processService->create($request->toDto());

        return (new ProcessResource($row))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateProcessRequest $request, string $process): ProcessResource
    {
        return new ProcessResource($this->processService->update($process, $request->toDto()));
    }

    public function destroy(DestroyProcessRequest $request, string $process): Response
    {
        $this->processService->delete($process);

        return response()->noContent();
    }
}
