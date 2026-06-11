<?php

declare(strict_types=1);

namespace App\DTOs\Documents;

use JsonSerializable;

/**
 * Pool de validadores efectivo del documento para mostrar en el wizard.
 * Devuelto por DocumentService::getDocumentReviewerPool().
 */
final readonly class ReviewerPoolDto implements JsonSerializable
{
    /**
     * @param  'document'|'template_fallback'|'none'  $kind
     * @param  list<array{id: string, name: ?string, stage: ?int}>  $reviewers
     */
    public function __construct(
        public string $kind,
        public string $reviewMode,
        public array $reviewers,
    ) {}

    /**
     * @return array{
     *   kind: 'document'|'template_fallback'|'none',
     *   review_mode: string,
     *   reviewers: list<array{id: string, name: ?string, stage: ?int}>
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'kind' => $this->kind,
            'review_mode' => $this->reviewMode,
            'reviewers' => $this->reviewers,
        ];
    }
}
