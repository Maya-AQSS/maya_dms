<?php

declare(strict_types=1);

namespace App\DTOs\Themes;

readonly class UpdateThemeDto
{
    /**
     * Solo los campos presentes serán actualizados. Cada campo opcional
     * usa `null` para "no tocar". Para vaciar una colección JSONB, enviar
     * un array vacío explícito (los FormRequests lo permiten).
     *
     * @param  array<string, mixed>|null  $palette
     * @param  array<string, mixed>|null  $typography
     * @param  array<string, mixed>|null  $layout
     * @param  array<string, mixed>|null  $assets
     * @param  array<string, mixed>|null  $accessibility
     */
    public function __construct(
        public ?string $name = null,
        public ?string $description = null,
        public ?string $status = null,
        public ?array $palette = null,
        public ?array $typography = null,
        public ?array $layout = null,
        public ?array $assets = null,
        public ?array $accessibility = null,
    ) {}
}
