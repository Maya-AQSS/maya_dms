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
}
