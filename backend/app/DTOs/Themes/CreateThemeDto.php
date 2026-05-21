<?php

declare(strict_types=1);

namespace App\DTOs\Themes;

readonly class CreateThemeDto
{
    /**
     * @param  array<string, mixed>  $palette
     * @param  array<string, mixed>  $typography
     * @param  array<string, mixed>  $layout
     * @param  array<string, mixed>  $assets
     * @param  array<string, mixed>  $accessibility
     */
    public function __construct(
        public string $name,
        public ?string $description,
        public ?string $teamId,
        public array $palette,
        public array $typography,
        public array $layout,
        public array $assets,
        public array $accessibility,
    ) {}
}
