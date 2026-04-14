<?php

namespace App\Http\Controllers\Api;

use App\DTOs\TemplateBlocks\BulkUpdateTemplateBlocksDto;
use App\DTOs\TemplateBlocks\UpdateTemplateBlockDto;
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
        $validated = $request->validated();
        $dto = new UpdateTemplateBlockDto(
            type:            $validated['type'] ?? null,
            set_type:        $request->has('type'),
            title:           $validated['title'] ?? null,
            set_title:       $request->has('title'),
            default_content: $validated['default_content'] ?? null,
            set_default_content: $request->has('default_content'),
            sort_order:      $validated['sort_order'] ?? null,
            set_sort_order:  $request->has('sort_order'),
            block_state:     $validated['block_state'] ?? null,
            set_block_state: $request->has('block_state'),
            mandatory:       $validated['mandatory'] ?? null,
            set_mandatory:   $request->has('mandatory'),
        );

        $updated = $this->blockService->update(
            blockId: $block,
            dto:     $dto,
            userId:  (string) Auth::id(),
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
        
        $dto = new BulkUpdateTemplateBlocksDto(
            ids:           $validated['ids'],
            block_state:   $validated['block_state'] ?? null,
            set_block_state: $request->has('block_state'),
            mandatory:     $validated['mandatory'] ?? null,
            set_mandatory: $request->has('mandatory'),
        );

        $blocks = $this->blockService->bulkUpdate(
            dto:    $dto,
            userId: (string) Auth::id(),
        );

        return TemplateBlockResource::collection($blocks);
    }
}
