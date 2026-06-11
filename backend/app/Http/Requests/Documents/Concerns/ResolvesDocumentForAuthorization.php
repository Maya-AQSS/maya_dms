<?php

declare(strict_types=1);

namespace App\Http\Requests\Documents\Concerns;

use App\Models\Document;
use App\Services\Contracts\DocumentServiceInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;

trait ResolvesDocumentForAuthorization
{
    private ?Document $resolvedDocument = null;

    private ?bool $resolvedDirectAccess = null;

    public function resolveDocument(): Document
    {
        if ($this->resolvedDocument !== null) {
            return $this->resolvedDocument;
        }

        [$document, $directAccess] = $this->resolveDocumentWithAccessContext();
        $this->resolvedDocument = $document;
        $this->resolvedDirectAccess = $directAccess;

        return $document;
    }

    public function hasDirectDocumentAccess(): bool
    {
        $this->resolveDocument();

        return $this->resolvedDirectAccess ?? true;
    }

    /**
     * @return array{0: Document, 1: bool}
     */
    private function resolveDocumentWithAccessContext(): array
    {
        $id = (string) $this->route('document');
        $service = app(DocumentServiceInterface::class);

        try {
            return [$service->findModelOrFail($id), true];
        } catch (ModelNotFoundException) {
            $document = $service->findModelOrFailWithoutUserAccess($id);
            if (! $service->hasPublishedSnapshot($document->id)) {
                abort(404);
            }

            return [$document, false];
        }
    }
}
