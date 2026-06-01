<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Document;
use App\Models\Template;
use App\Repositories\Contracts\EntityVersionRepositoryInterface;

/**
 * Modo de revisión de documentos: `document_review_mode` en plantilla (parallel | sequential),
 * con fallback a `review_mode` para plantillas legacy.
 * Prioriza la plantilla live frente al snapshot de la versión publicada anclada.
 */
final class DocumentReviewModeResolver
{
    public function __construct(
        private readonly EntityVersionRepositoryInterface $entityVersionRepository,
    ) {}

    public function resolve(Document $document): string
    {
        $document->loadMissing('template.headVersion');

        $liveFields = data_get(
            $document->template?->headVersion?->snapshot_data,
            TemplateHeadSnapshot::JSON_TEMPLATE_KEY,
        );

        if (is_array($liveFields)) {
            return TemplateHeadSnapshot::resolveDocumentReviewMode($liveFields);
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

        $templateFields = is_array($anchor?->snapshot_data)
            ? data_get($anchor->snapshot_data, 'template')
            : null;

        if (! is_array($templateFields)) {
            return null;
        }

        return TemplateHeadSnapshot::resolveDocumentReviewMode($templateFields);
    }
}
