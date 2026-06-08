<?php

declare(strict_types=1);

namespace App\DTOs\Themes;

/**
 * Resolved theme data for template rendering.
 * Contains flattened theme configuration with defaults applied.
 *
 * @phpstan-type PaletteShape array<string, string>
 * @phpstan-type TypographyShape array<string, mixed>
 */
readonly class ThemeResolvedDto
{
    /**
     * @param  array<string, string>  $palette
     * @param  array<string, mixed>  $typography
     * @param  array<string, mixed>  $layout
     * @param  array<string, ?string|string>  $accessibility
     */
    public function __construct(
        public array $palette,
        public array $typography,
        public array $layout,
        public array $accessibility,
        public string $brandName,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'palette' => $this->palette,
            'typography' => $this->typography,
            'layout' => $this->layout,
            'accessibility' => $this->accessibility,
            'brand_name' => $this->brandName,
        ];
    }
}
