<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Processes\DestroyProcessRequest;
use App\Http\Requests\Processes\IndexProcessRequest;
use App\Http\Requests\Processes\ShowProcessRequest;
use App\Http\Requests\Processes\StoreProcessRequest;
use App\Http\Requests\Processes\UpdateProcessRequest;
use App\Http\Resources\ProcessDeletionPreviewResource;
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

    public function index(IndexProcessRequest $request): JsonResponse
    {
        $filters = [
            'search' => $request->input('search'),
            'parent_id' => $request->input('parent_id'),
        ];

        $sortBy = $request->getSortBy();
        $sortDir = $request->getSortDir();
        if ($sortBy) {
            $filters['sort_by'] = $sortBy;
            $filters['sort_dir'] = $sortDir;
        }

        $paginated = $this->processService->paginate($filters, $request->getPerPage());
        $page = $request->getPage();

        return response()->json([
            'data' => ProcessResource::collection($paginated->items())->resolve(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
            ],
        ]);
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

    public function deletionPreview(DestroyProcessRequest $request, string $process): ProcessDeletionPreviewResource
    {
        return new ProcessDeletionPreviewResource($this->processService->deletionPreview($process));
    }

    public function destroy(DestroyProcessRequest $request, string $process): Response
    {
        $this->processService->delete($process);

        return response()->noContent();
    }
}
