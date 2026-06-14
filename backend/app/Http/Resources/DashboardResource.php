<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\Dashboard\DashboardDto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transforma el payload del dashboard devuelto por DashboardService::buildForUser().
 *
 * @property-read DashboardDto $resource
 */
class DashboardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'stats' => $this->resource->stats->toArray(),
            'recent_documents' => $this->resource->recentDocuments,
            'template_review_inbox' => $this->resource->templateReviewInbox,
            'document_review_inbox' => $this->resource->documentReviewInbox,
        ];
    }
}
