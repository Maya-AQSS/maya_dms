<?php

declare(strict_types=1);

namespace App\Http\Requests\Documents\Concerns;

use App\Models\Document;
use App\Services\Contracts\DocumentServiceInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;

trait ResolvesDocumentForReview
{
    public function resolveDocument(): Document
    {
        $id = (string) $this->route('document');
        $service = app(DocumentServiceInterface::class);

        try {
            return $service->findModelOrFail($id);
        } catch (ModelNotFoundException) {
            $document = $service->findModelOrFailWithoutUserAccess($id);
            if (! $service->hasPublishedSnapshot($document->id)) {
                abort(404);
            }

            return $document;
        }
    }
}
