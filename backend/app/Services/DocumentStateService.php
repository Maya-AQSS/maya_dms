<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\DocumentStateChanged;
use App\Models\Document;
use App\Repositories\Contracts\DocumentRepositoryInterface;

class DocumentStateService
{
    public function __construct(
        private readonly DocumentRepositoryInterface $documentRepository,
    ) {}

    /**
     * @param  array<string, mixed>  $extraAttributes
     */
    public function transition(
        string $documentId,
        string $newStatus,
        string $actorId,
        array $extraAttributes = [],
        ?int $reviewerStage = null,
        ?string $reviewerName = null,
        ?string $rejectionReason = null,
    ): Document {
        $document = $this->documentRepository->findOrFailForRefreshAfterMutation($documentId);
        $oldStatus = $document->status;

        $this->documentRepository->mergeHeadWorkingCopy(
            $document,
            array_merge(['status' => $newStatus], $extraAttributes),
        );

        $fresh = $this->documentRepository->findOrFailForRefreshAfterMutation($documentId);

        event(new DocumentStateChanged(
            document: $fresh,
            oldStatus: $oldStatus,
            newStatus: $newStatus,
            actorId: $actorId,
            reviewerStage: $reviewerStage,
            reviewerName: $reviewerName,
            rejectionReason: $rejectionReason,
        ));

        return $fresh;
    }
}
