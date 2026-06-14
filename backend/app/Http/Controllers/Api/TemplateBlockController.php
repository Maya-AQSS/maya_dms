<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Concerns\AuthorizesTemplateForBlocks;
use App\Http\Controllers\Controller;
use App\Http\Requests\TemplateBlocks\StoreTemplateBlockRequest;
use App\Http\Requests\TemplateBlocks\UpdateTemplateBlockRequest;
use App\Http\Resources\TemplateBlockResource;
use App\Services\Contracts\TemplateBlockServiceInterface;
use App\Services\Contracts\TemplateServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * CRUD de bloques de plantilla.
 * Las operaciones bulk (reorder, bulkUpdate) viven en TemplateBlockBulkController.
 */
class TemplateBlockController extends Controller
{
    use AuthorizesTemplateForBlocks;

    public function __construct(
        private readonly TemplateBlockServiceInterface $blockService,
        private readonly TemplateServiceInterface $templateService,
    ) {}

    /**
     * GET /api/v1/templates/{template}/blocks
     */
    public function index(string $template): AnonymousResourceCollection
    {
        $templateModel = $this->findTemplateOrFail($this->templateService, $template);
        $this->authorize('listTemplateBlocks', $templateModel);
        $this->assertOptionalProcessContextMatches((string) $templateModel->process_id);

        $blocks = $this->blockService->listForTemplate($template);

        return TemplateBlockResource::collection($blocks);
    }

    /**
     * POST /api/v1/templates/{template}/blocks
     */
    public function store(StoreTemplateBlockRequest $request, string $template): JsonResponse
    {
        $templateModel = $this->findTemplateOrFail($this->templateService, $template);
        $this->authorize('createTemplateBlock', $templateModel);
        $this->assertOptionalProcessContextMatches((string) $templateModel->process_id);

        $block = $this->blockService->create(
            templateId: $template,
            attributes: $request->validated(),
            userId: (string) Auth::id(),
        );

        return (new TemplateBlockResource($block))->response()->setStatusCode(201);
    }

    /**
     * GET /api/v1/blocks/{block}
     */
    public function show(string $block): TemplateBlockResource
    {
        $blockDto = $this->blockService->findOrFail($block);
        $templateModel = $this->findTemplateOrFail($this->templateService, $blockDto->templateId);
        $this->authorize('showTemplateBlock', $templateModel);
        $this->assertOptionalProcessContextMatches((string) $templateModel->process_id);

        return new TemplateBlockResource($blockDto);
    }

    /**
     * PUT /api/v1/blocks/{block}
     */
    public function update(UpdateTemplateBlockRequest $request, string $block): TemplateBlockResource
    {
        $blockDto = $this->blockService->findOrFail($block);
        $templateModel = $this->findTemplateOrFail($this->templateService, $blockDto->templateId);
        $this->authorize('updateTemplateBlock', $templateModel);
        $this->assertOptionalProcessContextMatches((string) $templateModel->process_id);

        $updated = $this->blockService->update(
            blockId: $block,
            dto: $request->toDto(),
            userId: (string) Auth::id(),
        );

        return new TemplateBlockResource($updated);
    }

    /**
     * DELETE /api/v1/blocks/{block}
     */
    public function destroy(string $block): Response
    {
        // El gate `deleteTemplateBlock` autoriza sobre la plantilla padre; el
        // bloque solo aporta su template_id (DTO, sin cruzar el Model).
        $blockDto = $this->blockService->findOrFail($block);
        $template = $this->findTemplateOrFail($this->templateService, $blockDto->templateId);
        $this->authorize('deleteTemplateBlock', $template);
        $this->assertOptionalProcessContextMatches((string) $template->process_id);

        $this->blockService->delete($block, (string) Auth::id());

        return response()->noContent();
    }
}
