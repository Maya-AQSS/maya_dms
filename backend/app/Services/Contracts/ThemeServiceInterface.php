<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\DTOs\Themes\CloneThemeDto;
use App\DTOs\Themes\CreateThemeDto;
use App\DTOs\Themes\ThemeDto;
use App\DTOs\Themes\UpdateThemeDto;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ThemeServiceInterface
{
    /**
     * @param  array{status?: string, search?: string, team_id?: string}  $filters
     * @return LengthAwarePaginator<int, ThemeDto>
     */
    public function list(array $filters, int $perPage = 15): LengthAwarePaginator;

    public function get(string $id): ThemeDto;

    public function create(CreateThemeDto $dto, string $userId): ThemeDto;

    public function update(string $id, UpdateThemeDto $dto): ThemeDto;

    public function delete(string $id): void;

    public function clone(string $parentId, string $userId, CloneThemeDto $dto): ThemeDto;
}
