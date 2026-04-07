<?php

namespace App\Services;

use App\Events\DocumentStateChanged;
use App\Models\Document;
use App\Repositories\Contracts\DocumentRepositoryInterface;
use App\Services\Contracts\DocumentServiceInterface;

class DocumentService implements DocumentServiceInterface
{
    public function __construct(
        private readonly DocumentRepositoryInterface $documentRepository,
    ) {}

    /**
     * Transiciona el documento a un nuevo estado y emite DocumentStateChanged.
     */
    public function transition(string $documentId, string $newStatus, string $actorId): Document
    {
        $document  = $this->documentRepository->findOrFail($documentId);
        $oldStatus = $document->status;

        $document->update(['status' => $newStatus]);

        event(new DocumentStateChanged(
            document:  $document,
            oldStatus: $oldStatus,
            newStatus: $newStatus,
            actorId:   $actorId,
        ));

        return $document;
    }
}
