<?php

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
        $this->authorizeAndValidateTemplateContext(
            $this->findTemplateOrFail($this->templateService, $template),
            'view',
        );

        $blocks = $this->blockService->listForTemplate($template);

        return TemplateBlockResource::collection($blocks);
    }

    /**
     * POST /api/v1/templates/{template}/blocks
     */
    public function store(StoreTemplateBlockRequest $request, string $template): JsonResponse
    {
        $this->authorizeAndValidateTemplateContext(
            $this->findTemplateOrFail($this->templateService, $template),
            'update',
        );

        $block = $this->blockService->create(
            templateId: $template,
            attributes: $request->validated(),
            userId:     (string) Auth::id(),
        );

        return (new TemplateBlockResource($block))->response()->setStatusCode(201);
    }

    /**
     * GET /api/v1/blocks/{block}
     */
    public function show(string $block): TemplateBlockResource
    {
        $blockDto = $this->blockService->findOrFail($block);
        $this->authorizeAndValidateTemplateContext(
            $this->findTemplateOrFail($this->templateService, $blockDto->templateId),
            'view',
        );

        return new TemplateBlockResource($blockDto);
    }

    /**
     * PUT /api/v1/blocks/{block}
     */
    public function update(UpdateTemplateBlockRequest $request, string $block): TemplateBlockResource
    {
        $blockDto = $this->blockService->findOrFail($block);
        $this->authorizeAndValidateTemplateContext(
            $this->findTemplateOrFail($this->templateService, $blockDto->templateId),
            'update',
        );

        $updated = $this->blockService->update(
            blockId: $block,
            dto:     $request->toDto(),
            userId:  (string) Auth::id(),
        );

        return new TemplateBlockResource($updated);
    }

    /**
     * DELETE /api/v1/blocks/{block}
     */
    public function destroy(string $block): Response
    {
        // findModelOrFail: la policy `authorize('delete', $model)` requiere el Model
        // Eloquent (no DTO). Excepción documentada al patrón canónico.
        $blockModel = $this->blockService->findModelOrFail($block);
        $template = $this->findTemplateOrFail($this->templateService, (string) $blockModel->template_id);
        $blockModel->setRelation('template', $template);
        $this->authorize('delete', $blockModel);
        $this->assertOptionalProcessContextMatches((string) $template->process_id);

        $this->blockService->delete($block, (string) Auth::id());

        return response()->noContent();
    }
}
