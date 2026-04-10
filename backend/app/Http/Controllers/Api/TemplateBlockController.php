<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TemplateBlocks\BulkUpdateTemplateBlockRequest;
use App\Http\Requests\TemplateBlocks\StoreTemplateBlockRequest;
use App\Http\Requests\TemplateBlocks\UpdateTemplateBlockRequest;
use App\Http\Resources\TemplateBlockResource;
use App\Services\Contracts\TemplateBlockServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;

class TemplateBlockController extends Controller
{
    public function __construct(
        private readonly TemplateBlockServiceInterface $blockService,
    ) {}

    /**
     * GET /api/v1/templates/{template}/blocks
     */
    public function index(string $template): AnonymousResourceCollection
    {
        $blocks = $this->blockService->listForTemplate($template);

        return TemplateBlockResource::collection($blocks);
    }

    /**
     * POST /api/v1/templates/{template}/blocks
     */
    public function store(StoreTemplateBlockRequest $request, string $template): JsonResponse
    {
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
        return new TemplateBlockResource($this->blockService->findOrFail($block));
    }

    /**
     * PUT /api/v1/blocks/{block}
     */
    public function update(UpdateTemplateBlockRequest $request, string $block): TemplateBlockResource
    {
        $updated = $this->blockService->update(
            blockId:    $block,
            attributes: $request->validated(),
            userId:     (string) Auth::id(),
        );

        return new TemplateBlockResource($updated);
    }

    /**
     * DELETE /api/v1/blocks/{block}
     */
    public function destroy(string $block): \Illuminate\Http\Response
    {
        $this->blockService->delete($block, (string) Auth::id());

        return response()->noContent();
    }

    /**
     * PUT /api/v1/blocks/bulk
     * Actualización masiva de block_state (y opcionalmente mandatory) para múltiples bloques.
     */
    public function bulkUpdate(BulkUpdateTemplateBlockRequest $request): AnonymousResourceCollection
    {
        $validated = $request->validated();
        $attributes = array_filter([
            'block_state' => $validated['block_state'],
            'mandatory'   => $validated['mandatory'] ?? null,
        ], fn ($v) => $v !== null);

        $blocks = $this->blockService->bulkUpdate(
            ids:        $validated['ids'],
            attributes: $attributes,
            userId:     (string) Auth::id(),
        );

        return TemplateBlockResource::collection($blocks);
    }
}
