<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Documents\DocumentMigrationPayloadDto;
use App\Models\Document;
use App\Models\DocumentBlock;
use App\Models\Template;
use App\Repositories\Contracts\DocumentRepositoryInterface;
use App\Repositories\Contracts\EntityVersionRepositoryInterface;
use Illuminate\Validation\ValidationException;

/**
 * Construye el {@see DocumentMigrationPayloadDto} para el paso de migración del
 * wizard: resuelve los bloques de la versión anclada al documento origen y los
 * de la última versión publicada, y los compara con el contenido real del
 * origen vía {@see DocumentMigrationBlockDiffer}.
 */
final class DocumentMigrationPayloadResolver
{
    public function __construct(
        private readonly DocumentRepositoryInterface $documentRepository,
        private readonly EntityVersionRepositoryInterface $entityVersionRepository,
        private readonly DocumentBlockService $documentBlockService,
        private readonly DocumentMigrationBlockDiffer $blockDiffer,
    ) {}

    public function resolve(string $sourceDocumentId): DocumentMigrationPayloadDto
    {
        $source = $this->documentRepository->findWithBlocksAndThemeOrFail($sourceDocumentId);
        $templateId = (string) $source->template_id;
        $sourceVersionId = is_string($source->template_version_id) ? $source->template_version_id : '';

        $sourceVersion = $sourceVersionId !== ''
            ? $this->entityVersionRepository->findPublishedByIdForVersionable($sourceVersionId, Template::class, $templateId)
            : null;
        $latestVersion = $this->entityVersionRepository->findLatestPublishedForEntity(Template::class, $templateId);

        if ($sourceVersion === null || $latestVersion === null) {
            throw ValidationException::withMessages([
                'document' => ['El documento origen no está anclado a una versión publicada de plantilla.'],
            ]);
        }

        if ((int) $latestVersion->version_number <= (int) $sourceVersion->version_number) {
            throw ValidationException::withMessages([
                'document' => ['No existe una versión de plantilla más reciente que la del documento origen.'],
            ]);
        }

        $sourceBlocks = $this->documentBlockService->templatePublicationDefinitionRowsFromEntityVersion((string) $sourceVersion->id);
        $targetBlocks = $this->documentBlockService->templatePublicationDefinitionRowsFromEntityVersion((string) $latestVersion->id);

        $blocks = $this->blockDiffer->diff(
            $sourceBlocks,
            $targetBlocks,
            $this->oldContentByTemplateBlock($source),
        );

        return new DocumentMigrationPayloadDto(
            sourceDocumentId: (string) $source->id,
            sourceTemplateVersionId: (string) $sourceVersion->id,
            sourceVersionNumber: (int) $sourceVersion->version_number,
            targetTemplateVersionId: (string) $latestVersion->id,
            targetVersionNumber: (int) $latestVersion->version_number,
            blocks: $blocks,
        );
    }

    /**
     * Contenido real del documento origen indexado por template_block_id.
     *
     * No Eloquent query is issued here: `$source` is loaded via
     * `DocumentRepositoryInterface::findWithBlocksAndThemeOrFail` which eager-loads
     * `blocks` with `orderBy('sort_order')`. Iterating `$source->blocks` reads the
     * in-memory Collection — no lazy-load occurs. This is acceptable per the
     * architecture rule (Eloquent access in Services forbidden as a new query;
     * reading pre-loaded relations does not constitute a new query).
     *
     * @return array<string, mixed>
     */
    private function oldContentByTemplateBlock(Document $source): array
    {
        $content = [];
        foreach ($source->blocks as $block) {
            /** @var DocumentBlock $block */
            $content[(string) $block->template_block_id] = $block->content;
        }

        return $content;
    }
}
