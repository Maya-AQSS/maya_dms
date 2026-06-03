<?php

declare(strict_types=1);

namespace App\DTOs\Templates;

/**
 * Template data required for HTML rendering (preview/export).
 * Contains blocks and minimal template metadata without full DTO overhead.
 */
readonly class TemplateRenderDto
{
    /**
     * @param  list<array{id: string, title: ?string, default_content: array|string|null}>  $blocks
     */
    public function __construct(
        public string $id,
        public ?string $name,
        public ?string $description,
        public ?string $themeId,
        public array $blocks,
    ) {}
}
