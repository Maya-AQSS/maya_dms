<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Document;
use App\Models\Template;
use App\Repositories\Contracts\EntityVersionRepositoryInterface;

/**
 * Modo de revisión de documentos: un único `review_mode` en plantilla (parallel | sequential).
 * Prioriza la plantilla live (config actual) frente al snapshot de la versión publicada anclada.
 */
final class DocumentReviewModeResolver
{
    public function __construct(
        private readonly EntityVersionRepositoryInterface $entityVersionRepository,
    ) {}

    public function resolve(Document $document): string
    {
        $document->loadMissing('template');

        $live = $document->template?->review_mode;
        if (is_string($live) && in_array($live, ['sequential', 'parallel'], true)) {
            return $live;
        }

        $anchored = $this->resolveFromAnchoredTemplateVersion($document);
        if ($anchored !== null) {
            return $anchored;
        }

        return 'parallel';
    }

    private function resolveFromAnchoredTemplateVersion(Document $document): ?string
    {
        $versionId = is_string($document->template_version_id) ? trim($document->template_version_id) : '';
        $templateId = is_string($document->template_id) ? trim($document->template_id) : '';

        if ($versionId === '' || $templateId === '') {
            return null;
        }

        $anchor = $this->entityVersionRepository->findPublishedByIdForVersionable(
            $versionId,
            Template::class,
            $templateId,
        );

        $mode = is_array($anchor?->snapshot_data)
            ? data_get($anchor->snapshot_data, 'template.review_mode')
            : null;

        if (is_string($mode) && in_array($mode, ['sequential', 'parallel'], true)) {
            return $mode;
        }

        return null;
    }
}
