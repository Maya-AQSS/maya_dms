<?php

declare(strict_types=1);

namespace App\Http\Concerns;

use App\Models\Document;
use App\Services\DocumentReviewService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Adjunta atributos derivados (`can_clone`, `review_mode`) a uno o varios
 * Documents antes de presentarlos al cliente. Reutiliza {@see AttachesCanCloneMeta}
 * para `can_clone` y añade `review_mode` desde el snapshot anclado.
 *
 * El consumidor debe inyectar `DocumentReviewService` y exponerlo como
 * `protected readonly DocumentReviewService $documentReviewService`.
 */
trait AttachesDocumentCanCloneMeta
{
    use AttachesCanCloneMeta {
        attachCanCloneMeta as protected attachCanCloneAttribute;
    }

    /**
     * @param  Document|Collection<int, Document>  $documents
     */
    protected function attachCanCloneMeta(Document|Collection $documents, Request $request): void
    {
        $this->attachCanCloneAttribute($documents, $request);

        $attachReviewMode = function (Document $document): void {
            $document->setAttribute('review_mode', $this->documentReviewService->resolveReviewMode($document));
        };

        if ($documents instanceof Document) {
            $attachReviewMode($documents);

            return;
        }

        foreach ($documents as $document) {
            $attachReviewMode($document);
        }
    }
}
