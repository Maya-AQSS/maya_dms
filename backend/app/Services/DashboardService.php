<?php

namespace App\Services;

use App\Repositories\Contracts\TemplateRepositoryInterface;
use App\Services\Contracts\DashboardServiceInterface;

class DashboardService implements DashboardServiceInterface
{
    public function __construct(
        private readonly TemplateRepositoryInterface $templateRepository,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function buildForUser(string $userId): array
    {
        $templateReviewInbox = $this->templateRepository->listPendingReviewInboxForUser($userId);

        return [
            'stats' => [],
            'recent_documents' => [],
            'template_review_inbox' => $templateReviewInbox->all(),
        ];
    }
}
