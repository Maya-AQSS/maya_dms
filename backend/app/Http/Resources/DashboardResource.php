<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transforma el payload del dashboard devuelto por DashboardService::buildForUser().
 *
 * El recurso recibe un array con claves: stats, recent_documents,
 * template_review_inbox, document_review_inbox.
 *
 * @property-read array{
 *     stats: array{
 *         documents_critical: int,
 *         documents_high: int,
 *         templates_critical: int,
 *         templates_high: int,
 *     },
 *     recent_documents: list<mixed>,
 *     template_review_inbox: list<array<string, mixed>>,
 *     document_review_inbox: list<array<string, mixed>>,
 * } $resource
 */
class DashboardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'stats' => $this->resource['stats'] ?? [],
            'recent_documents' => $this->resource['recent_documents'] ?? [],
            'template_review_inbox' => $this->resource['template_review_inbox'] ?? [],
            'document_review_inbox' => $this->resource['document_review_inbox'] ?? [],
        ];
    }
}
