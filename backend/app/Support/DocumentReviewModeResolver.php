<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Document;
use App\Models\Template;
use App\Repositories\Contracts\EntityVersionRepositoryInterface;

/**
 * Modo de revisión de documentos: `document_review_mode` en plantilla (parallel | sequential),
 * con fallback a `review_mode` para plantillas legacy.
 * Si el documento está en revisión, usa el `review_mode` congelado en su cabecera al enviar.
 */
final class DocumentReviewModeResolver
{
    public function __construct(
        private readonly EntityVersionRepositoryInterface $entityVersionRepository,
    ) {}

    public function resolve(Document $document): string
    {
        $document->loadMissing('headVersion', 'template.headVersion');

        $frozen = $this->resolveFrozenFromDocumentHead($document);
        if ($frozen !== null) {
            return $frozen;
        }

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

    private function resolveFrozenFromDocumentHead(Document $document): ?string
    {
        $documentFields = data_get(
            $document->headVersion?->snapshot_data,
            DocumentHeadSnapshot::JSON_DOCUMENT_KEY,
        );

        if (! is_array($documentFields)) {
            return null;
        }

        $status = $documentFields['status'] ?? null;
        $mode = $documentFields['review_mode'] ?? null;

        if ($status !== 'in_review' || ! is_string($mode) || ! in_array($mode, ['sequential', 'parallel'], true)) {
            return null;
        }

        return $mode;
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
