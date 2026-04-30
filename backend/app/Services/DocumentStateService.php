<?php

namespace App\Services;

use App\Models\Document;
use App\Events\DocumentStateChanged;
use App\Repositories\Contracts\DocumentRepositoryInterface;

class DocumentStateService
{
    public function __construct(
        private readonly DocumentRepositoryInterface $documentRepository,
    ) {}

    /**
     * @param  array<string, mixed>  $extraAttributes
     */
    public function transition(string $documentId, string $newStatus, string $actorId, array $extraAttributes = []): Document
    {
        $document = $this->documentRepository->findOrFail($documentId);
        $oldStatus = $document->status;

        $document->update(array_merge(['status' => $newStatus], $extraAttributes));

        event(new DocumentStateChanged(
            document: $document->fresh(),
            oldStatus: $oldStatus,
            newStatus: $newStatus,
            actorId: $actorId,
        ));

        return $document->fresh();
    }
}
