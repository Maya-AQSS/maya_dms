<?php

declare(strict_types=1);

namespace App\DTOs\Themes;

/**
 * Vista pública de un Theme (la que devuelve el Service al Controller).
 *
 * @phpstan-type PaletteShape array{
 *     primary: string,
 *     secondary: string,
 *     text: string,
 *     background: string,
 *     accent?: string,
 * }
 * @phpstan-type TypographyShape array{
 *     heading_font: string,
 *     body_font: string,
 *     base_size_pt: int,
 *     line_height: float,
 * }
 */
readonly class ThemeDto
{
    /**
     * @param  array<string, string>  $palette
     * @param  array<string, mixed>  $typography
     * @param  array<string, mixed>  $layout
     * @param  array<string, ?string|string>  $accessibility
     */
    public function __construct(
        public string $id,
        public string $name,
        public ?string $description,
        public string $status,
        public string $createdBy,
        public ?string $teamId,
        public array $palette,
        public array $typography,
        public array $layout,
        public array $accessibility,
        public ?string $clonedFromId,
        public string $createdAt,
        public string $updatedAt,
        public bool $isSystem = false,
    ) {}
}
