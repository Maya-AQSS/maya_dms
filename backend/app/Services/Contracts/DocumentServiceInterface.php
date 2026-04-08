<?php

namespace App\Services\Contracts;

use App\Models\Document;
use App\Models\DocumentReview;
use Illuminate\Support\Collection;

interface DocumentServiceInterface
{
    /**
     * Transiciona el documento a un nuevo estado y emite el evento de dominio DocumentStateChanged.
     *
     * @param  array<string, mixed>  $extraAttributes
     */
    public function transition(string $documentId, string $newStatus, string $actorId, array $extraAttributes = []): Document;

    public function submitToReview(string $documentId, string $actorId): Document;

    public function publishDocument(string $documentId, string $actorId): Document;

    public function rejectDocument(string $documentId, string $actorId): Document;

    public function delegateOwner(string $documentId, string $newOwnerId, string $actorId): Document;

    /**
     * @return Collection<int, DocumentReview>
     */
    public function listReviews(string $documentId): Collection;

    public function approveReview(string $documentId, string $reviewId, string $actorId): Document;

    public function rejectReview(string $documentId, string $reviewId, string $actorId, ?string $reason = null): Document;
}
