<?php

namespace App\Http\Controllers\Api;

use App\DTOs\TemplateBlocks\BulkUpdateTemplateBlocksDto;
use App\DTOs\TemplateBlocks\UpdateTemplateBlockDto;
use App\Http\Concerns\ValidatesOptionalProcessContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\TemplateBlocks\BulkUpdateTemplateBlockRequest;
use App\Http\Requests\TemplateBlocks\ReorderTemplateBlocksRequest;
use App\Http\Requests\TemplateBlocks\StoreTemplateBlockRequest;
use App\Http\Requests\TemplateBlocks\UpdateTemplateBlockRequest;
use App\Http\Resources\TemplateBlockResource;
use App\Models\Template;
use App\Services\Contracts\TemplateBlockServiceInterface;
use App\Services\Contracts\TemplateServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class TemplateBlockController extends Controller
{
    use ValidatesOptionalProcessContext;

    public function __construct(
        private readonly TemplateBlockServiceInterface $blockService,
        private readonly TemplateServiceInterface $templateService,
    ) {}

    /** 
     * Lista todos los bloques de una plantilla.
     * 
     * GET /api/v1/templates/{template}/blocks
     * 
     */
    public function index(string $template): AnonymousResourceCollection
    {
        $this->authorizeAndValidateTemplateContext($this->findTemplateOrFail($template), 'view');

        $blocks = $this->blockService->listForTemplate($template);

        return TemplateBlockResource::collection($blocks);
    }

    /**
     * Crea un nuevo bloque para una plantilla.
     * 
     * POST /api/v1/templates/{template}/blocks
     */
    public function store(StoreTemplateBlockRequest $request, string $template): JsonResponse
    {
        $this->authorizeAndValidateTemplateContext($this->findTemplateOrFail($template), 'update');

        $block = $this->blockService->create(
            templateId: $template,
            attributes: $request->validated(),
            userId:     (string) Auth::id(),
        );

        return (new TemplateBlockResource($block))->response()->setStatusCode(201);
    }

    /**
     * Muestra un bloque de una plantilla.
     * 
     * GET /api/v1/blocks/{block}
     */
    public function show(string $block): TemplateBlockResource
    {
        $blockModel = $this->blockService->findOrFail($block);
        $this->authorizeAndValidateTemplateContext($this->findTemplateOrFail((string) $blockModel->template_id), 'view');

        return new TemplateBlockResource($blockModel);
    }

    /**
     * Actualiza un bloque de una plantilla.
     * 
     * PUT /api/v1/blocks/{block}
     */
    public function update(UpdateTemplateBlockRequest $request, string $block): TemplateBlockResource
    {
        $validated = $request->validated();
        $blockModel = $this->blockService->findOrFail($block);
        $this->authorizeAndValidateTemplateContext($this->findTemplateOrFail((string) $blockModel->template_id), 'update');

        $dto = new UpdateTemplateBlockDto(
            title:           $validated['title'] ?? null,
            set_title:       $request->has('title'),
            default_content: $validated['default_content'] ?? null,
            set_default_content: $request->has('default_content'),
            sort_order:      $validated['sort_order'] ?? null,
            set_sort_order:  $request->has('sort_order'),
            block_state:     $validated['block_state'] ?? null,
            set_block_state: $request->has('block_state'),
            description:     $validated['description'] ?? null,
            set_description: $request->has('description'),
        );

        $updated = $this->blockService->update(
            blockId: $block,
            dto:     $dto,
            userId:  (string) Auth::id(),
        );

        return new TemplateBlockResource($updated);
    }

    /**
     * Elimina un bloque de una plantilla.
     * 
     * DELETE /api/v1/blocks/{block}
     */
    public function destroy(string $block): Response
    {
        $blockModel = $this->blockService->findOrFail($block);
        $this->authorizeAndValidateTemplateContext($this->findTemplateOrFail((string) $blockModel->template_id), 'update');

        $this->blockService->delete($block, (string) Auth::id());

        return response()->noContent();
    }

    /**
     * PATCH /api/v1/templates/{template}/blocks/reorder
     * 
     * Reordena todos los bloques de una plantilla. Recibe { block_ids: [...] } en el nuevo orden.
     * 
     * @param  ReorderTemplateBlocksRequest  $request
     * @param  string  $template
     * @return \Illuminate\Http\Response
     */
    public function reorder(ReorderTemplateBlocksRequest $request, string $template): \Illuminate\Http\Response
    {
        $this->authorizeAndValidateTemplateContext($this->findTemplateOrFail($template), 'update');

        $blockIds = $request->validated('block_ids');
        $this->blockService->reorderForTemplate($template, $blockIds, (string) Auth::id());

        return response()->noContent();
    }

    /**
     * Actualiza múltiples bloques de una plantilla.
     * 
     * PUT /api/v1/blocks/bulk
     * 
     * @param  BulkUpdateTemplateBlockRequest  $request
     * @return AnonymousResourceCollection
     */
    public function bulkUpdate(BulkUpdateTemplateBlockRequest $request): AnonymousResourceCollection
    {
        $validated = $request->validated();
        $blocks = $this->blockService->findBlocksByIdsOrFail($validated['ids']);
        $templateIds = $blocks->pluck('template_id')->map(static fn ($id): string => (string) $id)->unique()->values()->all();

        // findManyByIds aplica el global scope: solo devuelve plantillas visibles para el usuario.
        $templates = $this->templateService->findManyByIds($templateIds);

        /** @var array<int, Template> $resolvedTemplates */
        $resolvedTemplates = [];
        foreach ($templateIds as $templateId) {
            $templateModel = $templates->get($templateId);

            // Si la plantilla no está en el scope del usuario, abortamos antes de tocar ningún bloque.
            if ($templateModel === null) {
                abort(403, 'No tienes acceso a uno o más bloques solicitados.');
            }

            $this->authorizeAndValidateTemplateContext($templateModel, 'update', false);
            $resolvedTemplates[] = $templateModel;
        }

        $givenProcessId = $request->query('process_id');
        if ($givenProcessId !== null && $givenProcessId !== '') {
            foreach ($resolvedTemplates as $templateModel) {
                $this->assertOptionalProcessContextMatches((string) $templateModel->process_id);
            }
        }

        $dto = new BulkUpdateTemplateBlocksDto(
            ids:             $validated['ids'],
            block_state:     $validated['block_state'] ?? null,
            set_block_state: $request->has('block_state'),
        );

        $blocks = $this->blockService->bulkUpdate(
            dto:    $dto,
            userId: (string) Auth::id(),
        );

        return TemplateBlockResource::collection($blocks);
    }

    private function findTemplateOrFail(string $templateId): Template
    {
        return $this->templateService->findOrFail($templateId);
    }

    private function authorizeAndValidateTemplateContext(
        Template $template,
        string $ability,
        bool $checkProcessContext = true,
    ): void {
        $this->authorize($ability, $template);

        if ($checkProcessContext) {
            $this->assertOptionalProcessContextMatches((string) $template->process_id);
        }
    }
}
