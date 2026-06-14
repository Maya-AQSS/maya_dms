<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Dashboard\DashboardDto;
use App\DTOs\Dashboard\DashboardStatsDto;
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
     */
    public function buildForUser(string $userId): DashboardDto
    {
        $templateReviewInbox = $this->templateRepository->listPendingReviewInboxForUser($userId);
        $documentReviewInbox = $this->documentRepository->listPendingDocumentReviewInboxForUser($userId);

        return new DashboardDto(
            stats: new DashboardStatsDto(
                documentsCritical: $this->countCritical($documentReviewInbox->all()),
                documentsHigh: $this->countHigh($documentReviewInbox->all()),
                templatesCritical: $this->countCritical($templateReviewInbox->all()),
                templatesHigh: $this->countHigh($templateReviewInbox->all()),
            ),
            recentDocuments: [],
            templateReviewInbox: $templateReviewInbox->all(),
            documentReviewInbox: $documentReviewInbox->all(),
        );
    }

    /**
     * Cuenta las items con severidad crítica.
     *
     * @param  list<array<string, mixed>>  $items
     */
    private function countCritical(array $items): int
    {
        return count(array_filter($items, static function (array $item): bool {
            $days = $item['days_remaining'] ?? null;
            if (! is_numeric($days)) {
                return false;
            }

            return (float) $days <= 7.0;
        }));
    }

    /**
     * Cuenta las items con severidad alta.
     *
     * @param  list<array<string, mixed>>  $items
     */
    private function countHigh(array $items): int
    {
        return count(array_filter($items, static function (array $item): bool {
            $days = $item['days_remaining'] ?? null;
            if (! is_numeric($days)) {
                return false;
            }

            $value = (float) $days;

            return $value > 7.0 && $value <= 30.0;
        }));
    }
}
