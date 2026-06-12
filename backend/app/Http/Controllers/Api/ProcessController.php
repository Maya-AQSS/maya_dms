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
use Illuminate\Http\Response;
use Maya\Http\Concerns\RespondsWithEnvelope;

class ProcessController extends Controller
{
    use RespondsWithEnvelope;

    public function __construct(
        private readonly ProcessServiceInterface $processService,
    ) {}

    /**
     * Listado paginado de procesos con el envelope plano estándar (ADR-C),
     * igual que DocumentController/TemplateController. Cambio observable
     * documentado en changes.md (F4-B1): antes anidaba la paginación bajo
     * `meta`; el frontend normaliza ambos formatos.
     */
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

        $page = $this->processService->paginate($filters, $request->getPerPage());

        return $this->paginated($page, ProcessResource::class, $request);
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
