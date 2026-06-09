<?php

declare(strict_types=1);

namespace App\DTOs\Documents;

/**
 * DTO for document rendering data (blocks + theme).
 * Extracted from Eloquent models for safe passage through service boundaries.
 */
final class DocumentRenderDataDto
{
    /**
     * @param  list<array<string, mixed>>  $blockHtmlParts  HTML-rendered block content
     * @param  array<string, mixed>  $theme  Theme configuration array
     */
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly ?string $templateDescription,
        public readonly array $blockHtmlParts,
        public readonly array $theme,
    ) {}
}
