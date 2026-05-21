<?php

declare(strict_types=1);

namespace App\DTOs\Themes;

readonly class CloneThemeDto
{
    /**
     * @param  array<string, mixed>|null  $paletteOverrides
     * @param  array<string, mixed>|null  $typographyOverrides
     * @param  array<string, mixed>|null  $layoutOverrides
     * @param  array<string, mixed>|null  $assetsOverrides
     * @param  array<string, mixed>|null  $accessibilityOverrides
     */
    public function __construct(
        public ?string $name = null,
        public ?array $paletteOverrides = null,
        public ?array $typographyOverrides = null,
        public ?array $layoutOverrides = null,
        public ?array $assetsOverrides = null,
        public ?array $accessibilityOverrides = null,
    ) {}
}
