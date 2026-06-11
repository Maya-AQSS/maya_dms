<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\DTOs\TemplateBlocks\BulkUpdateTemplateBlocksDto;
use App\Http\Concerns\AuthorizesTemplateForBlocks;
use App\Http\Controllers\Controller;
use App\Http\Requests\TemplateBlocks\BulkUpdateTemplateBlockRequest;
use App\Http\Requests\TemplateBlocks\ReorderTemplateBlocksRequest;
use App\Http\Resources\TemplateBlockResource;
use App\Models\Template;
use App\Services\Contracts\TemplateBlockServiceInterface;
use App\Services\Contracts\TemplateServiceInterface;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * Operaciones bulk sobre bloques de plantilla: reordenar y actualización masiva.
 * Separado de TemplateBlockController (CRUD) para cumplir B9 (~5 métodos/controller).
 */
class TemplateBlockBulkController extends Controller
{
    use AuthorizesTemplateForBlocks;

    public function __construct(
        private readonly TemplateBlockServiceInterface $blockService,
        private readonly TemplateServiceInterface $templateService,
    ) {}

    /**
     * PATCH /api/v1/templates/{template}/blocks/reorder
     *
     * Reordena todos los bloques de una plantilla. Recibe { block_ids: [...] }.
     */
    public function reorder(ReorderTemplateBlocksRequest $request, string $template): Response
    {
        $templateModel = $this->findTemplateOrFail($this->templateService, $template);
        $this->authorize('updateTemplateBlock', $templateModel);
        $this->assertOptionalProcessContextMatches((string) $templateModel->process_id);

        $blockIds = $request->validated('block_ids');
        $this->blockService->reorderForTemplate($template, $blockIds, (string) Auth::id());

        return response()->noContent();
    }

    /**
     * PUT /api/v1/blocks/bulk
     *
     * Actualiza múltiples bloques de una o más plantillas. Verifica autorización
     * para cada plantilla afectada antes de mutar ningún bloque.
     */
    public function bulkUpdate(BulkUpdateTemplateBlockRequest $request): AnonymousResourceCollection
    {
        $validated = $request->validated();
        $blocks = $this->blockService->findBlocksByIdsOrFail($validated['ids']);
        $templateIds = array_values(array_unique(array_map(
            static fn ($dto): string => $dto->templateId,
            $blocks,
        )));

        $templates = $this->templateService->findManyByIds($templateIds);

        /** @var array<int, Template> $resolvedTemplates */
        $resolvedTemplates = [];
        foreach ($templateIds as $templateId) {
            $templateModel = $templates->get($templateId);

            if ($templateModel === null) {
                abort(403, 'No tienes acceso a uno o más bloques solicitados.');
            }

            $this->authorize('updateTemplateBlock', $templateModel);
            $resolvedTemplates[] = $templateModel;
        }

        $givenProcessId = $request->query('process_id');
        if ($givenProcessId !== null && $givenProcessId !== '') {
            foreach ($resolvedTemplates as $templateModel) {
                $this->assertOptionalProcessContextMatches((string) $templateModel->process_id);
            }
        }

        $dto = new BulkUpdateTemplateBlocksDto(
            ids: $validated['ids'],
            blockState: $validated['block_state'] ?? null,
            setBlockState: $request->has('block_state'),
        );

        $blocks = $this->blockService->bulkUpdate(
            dto: $dto,
            userId: (string) Auth::id(),
        );

        return TemplateBlockResource::collection($blocks);
    }
}
