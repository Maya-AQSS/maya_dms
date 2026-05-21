<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Themes\CloneThemeDto;
use App\DTOs\Themes\CreateThemeDto;
use App\DTOs\Themes\ThemeDto;
use App\DTOs\Themes\UpdateThemeDto;
use App\Repositories\Contracts\ThemeRepositoryInterface;
use App\Services\Contracts\ThemeServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ThemeService implements ThemeServiceInterface
{
    public function __construct(
        private readonly ThemeRepositoryInterface $repository,
    ) {}

    /**
     * @param  array{status?: string, search?: string, team_id?: string}  $filters
     * @return LengthAwarePaginator<int, ThemeDto>
     */
    public function list(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->paginate($filters, $perPage);
    }

    public function get(string $id): ThemeDto
    {
        $theme = $this->repository->findById($id);
        if ($theme === null) {
            throw new NotFoundHttpException('Theme no encontrado.');
        }

        return $theme;
    }

    public function create(CreateThemeDto $dto, string $userId): ThemeDto
    {
        return $this->repository->create($dto, $userId);
    }

    public function update(string $id, UpdateThemeDto $dto): ThemeDto
    {
        return $this->repository->update($id, $dto);
    }

    public function delete(string $id): void
    {
        $this->repository->delete($id);
    }

    public function clone(string $parentId, string $userId, CloneThemeDto $dto): ThemeDto
    {
        return $this->repository->clone(
            parentId: $parentId,
            createdBy: $userId,
            name: $dto->name,
            paletteOverrides: $dto->paletteOverrides,
            typographyOverrides: $dto->typographyOverrides,
            layoutOverrides: $dto->layoutOverrides,
            assetsOverrides: $dto->assetsOverrides,
            accessibilityOverrides: $dto->accessibilityOverrides,
        );
    }
}
