<?php

declare(strict_types=1);

namespace App\DTOs\Dashboard;

/**
 * Payload BFF del dashboard para un usuario.
 *
 * Read-model agregado (sin entidad única): combina los contadores de severidad
 * con las bandejas de revisión de plantillas y documentos. Los items de bandeja
 * son filas estructuradas que produce la capa Repository.
 */
final readonly class DashboardDto
{
    /**
     * @param  list<mixed>  $recentDocuments
     * @param  list<array<string, mixed>>  $templateReviewInbox
     * @param  list<array<string, mixed>>  $documentReviewInbox
     */
    public function __construct(
        public DashboardStatsDto $stats,
        public array $recentDocuments,
        public array $templateReviewInbox,
        public array $documentReviewInbox,
    ) {}

    /**
     * @return array{
     *     stats: array<string, int>,
     *     recent_documents: list<mixed>,
     *     template_review_inbox: list<array<string, mixed>>,
     *     document_review_inbox: list<array<string, mixed>>,
     * }
     */
    public function toArray(): array
    {
        return [
            'stats' => $this->stats->toArray(),
            'recent_documents' => $this->recentDocuments,
            'template_review_inbox' => $this->templateReviewInbox,
            'document_review_inbox' => $this->documentReviewInbox,
        ];
    }
}
