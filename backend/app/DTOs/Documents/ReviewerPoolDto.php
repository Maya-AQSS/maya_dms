<?php

declare(strict_types=1);

namespace App\DTOs\Documents;

use App\Http\Resources\ReviewerPoolResource;

/**
 * Pool de validadores efectivo del documento para mostrar en el wizard.
 * Devuelto por DocumentService::getDocumentReviewerPool() y serializado por
 * {@see ReviewerPoolResource}.
 */
final readonly class ReviewerPoolDto
{
    /**
     * @param  'document'|'template_fallback'|'none'  $kind
     * @param  list<ReviewerCandidateDto>  $reviewers
     */
    public function __construct(
        public string $kind,
        public string $reviewMode,
        public array $reviewers,
    ) {}
}
