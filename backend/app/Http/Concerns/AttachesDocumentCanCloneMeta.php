<?php

declare(strict_types=1);

namespace App\Http\Concerns;

use App\Models\Document;
use App\Services\DocumentReviewService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Adjunta atributos derivados (`can_clone`, `review_mode`) a uno o varios
 * Documents antes de presentarlos al cliente. Evita pasar Gate y queries
 * dentro de DocumentResource. `review_mode` se resuelve desde el snapshot
 * de la versión anclada para que coincida con el modo que aplica el
 * backend al aprobar/rechazar, no el de la plantilla live.
 *
 * El consumidor debe inyectar `DocumentReviewService` y exponerlo como
 * `protected readonly DocumentReviewService $documentReviewService`
 * (vía constructor en cada controller que use el trait).
 */
trait AttachesDocumentCanCloneMeta
{
    /**
     * @param  Document|Collection<int, Document>  $documents
     */
    protected function attachCanCloneMeta(Document|Collection $documents, Request $request): void
    {
        $user = $request->user();
        if ($user === null) {
            return;
        }

        $attach = function (Document $document) use ($user): void {
            $document->setAttribute('can_clone', Gate::forUser($user)->allows('clone', $document));
            $document->setAttribute('review_mode', $this->documentReviewService->resolveReviewMode($document));
        };

        if ($documents instanceof Document) {
            $attach($documents);

            return;
        }

        foreach ($documents as $document) {
            $attach($document);
        }
    }
}
