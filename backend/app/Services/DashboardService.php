<?php

namespace App\Services;

use App\Repositories\Contracts\DocumentRepositoryInterface;
use App\Repositories\Contracts\TemplateRepositoryInterface;
use App\Services\Contracts\DashboardServiceInterface;

class DashboardService implements DashboardServiceInterface
{
    public function __construct(
        private readonly TemplateRepositoryInterface $templateRepository,
        private readonly DocumentRepositoryInterface $documentRepository,
    ) {}

    /**
     * Construye el dashboard para un usuario.
     * 
     * @param string $userId
     * @return array
     */
    public function buildForUser(string $userId): array
    {
        $templateReviewInbox = $this->templateRepository->listPendingReviewInboxForUser($userId);
        $documentReviewInbox = $this->documentRepository->listPendingDocumentReviewInboxForUser($userId);

        return [
            'stats' => [],
            'recent_documents' => [],
            'template_review_inbox' => $templateReviewInbox->all(),
            'document_review_inbox' => $documentReviewInbox->all(),
        ];
    }
}
