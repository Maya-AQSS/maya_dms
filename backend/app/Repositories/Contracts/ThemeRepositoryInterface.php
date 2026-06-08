<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\DTOs\Themes\CreateThemeDto;
use App\DTOs\Themes\ThemeDto;
use App\DTOs\Themes\UpdateThemeDto;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ThemeRepositoryInterface
{
    /**
     * Listado paginado de themes accesibles al usuario actual.
     * Filtros opcionales: status, search (sobre name/description).
     *
     * @param  array{status?: string, search?: string, team_id?: string}  $filters
     * @return LengthAwarePaginator<int, ThemeDto>
     */
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator;

    public function findById(string $id): ?ThemeDto;

    public function create(CreateThemeDto $dto, string $createdBy): ThemeDto;

    public function update(string $id, UpdateThemeDto $dto): ThemeDto;

    /**
     * Cambia únicamente el estado del theme. La validez de la transición la
     * gobierna {@see \App\Services\ThemeStateTransitions}, no este método.
     */
    public function updateStatus(string $id, string $status): ThemeDto;

    public function delete(string $id): void;

    /**
     * Persiste un theme nuevo derivado del padre `$parentId` aplicando merges
     * profundos en cada bucket JSONB sobre los valores del padre.
     *
     * @param  array<string, mixed>|null  $paletteOverrides
     * @param  array<string, mixed>|null  $typographyOverrides
     * @param  array<string, mixed>|null  $layoutOverrides
     * @param  array<string, mixed>|null  $assetsOverrides
     * @param  array<string, mixed>|null  $accessibilityOverrides
     */
    public function clone(
        string $parentId,
        string $createdBy,
        ?string $name,
        ?array $paletteOverrides,
        ?array $typographyOverrides,
        ?array $layoutOverrides,
        ?array $assetsOverrides,
        ?array $accessibilityOverrides,
    ): ThemeDto;

    /**
     * Fetch resolved theme data for rendering by theme ID.
     * Returns theme assets and styling configuration as DTO.
     * Returns null if theme not found.
     *
     * @return \App\DTOs\Themes\ThemeResolvedDto|null
     */
    public function findThemeResolvedById(string $id): ?\App\DTOs\Themes\ThemeResolvedDto;

    /**
     * Fetch raw assets array for a theme by ID for mutation.
     * Used internally by asset upload service.
     * Returns null if theme not found.
     *
     * @return array<string, ?string>|null
     */
    public function findThemeAssetsById(string $id): ?array;
}
