<?php

declare(strict_types=1);

namespace App\DTOs\Themes;

use App\Models\Theme;

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

    /**
     * Mapper canónico Model -> DTO (R3), homogéneo con el resto de DTOs de
     * salida del proyecto. La capa Repository sigue siendo la única que accede
     * a Eloquent: invoca este mapper sobre el modelo ya cargado.
     */
    public static function fromModel(Theme $theme): self
    {
        return new self(
            id: (string) $theme->id,
            name: (string) $theme->name,
            description: $theme->description,
            status: (string) $theme->status,
            createdBy: (string) $theme->created_by,
            teamId: $theme->team_id,
            palette: (array) $theme->palette,
            typography: (array) $theme->typography,
            layout: (array) $theme->layout,
            accessibility: (array) $theme->accessibility,
            clonedFromId: $theme->cloned_from_id,
            createdAt: (string) $theme->created_at,
            updatedAt: (string) $theme->updated_at,
            isSystem: (bool) $theme->is_system,
        );
    }
}
