<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Themes\CloneThemeDto;
use App\DTOs\Themes\CreateThemeDto;
use App\DTOs\Themes\ThemeDto;
use App\DTOs\Themes\UpdateThemeDto;
use App\Enums\ThemeStatus;
use App\Repositories\Contracts\ThemeRepositoryInterface;
use App\Services\Contracts\ThemeServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
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
        // El autor de los metadatos PDF es siempre el usuario creador; no es
        // editable y se ignora cualquier valor entrante de `accessibility.author`.
        $dto = new CreateThemeDto(
            name: $dto->name,
            description: $dto->description,
            teamId: $dto->teamId,
            palette: $dto->palette,
            typography: $dto->typography,
            layout: $dto->layout,
            accessibility: array_replace($dto->accessibility, [
                'author' => $this->resolveAuthorName($userId),
            ]),
        );

        return $this->repository->create($dto, $userId);
    }

    public function update(string $id, UpdateThemeDto $dto): ThemeDto
    {
        return $this->repository->update($id, $dto);
    }

    public function publish(string $id): ThemeDto
    {
        return $this->transition($id, ThemeStatus::Published);
    }

    public function archive(string $id): ThemeDto
    {
        return $this->transition($id, ThemeStatus::Archived);
    }

    /**
     * Aplica una transición de estado validada e idempotente.
     */
    private function transition(string $id, ThemeStatus $target): ThemeDto
    {
        $theme = $this->get($id);
        $current = ThemeStatus::from($theme->status);

        if ($current === $target) {
            return $theme; // idempotente: ya está en el estado destino.
        }

        ThemeStateTransitions::assert($current, $target);

        return $this->repository->updateStatus($id, $target->value);
    }

    public function delete(string $id): void
    {
        $this->repository->delete($id);
    }

    public function clone(string $parentId, string $userId, CloneThemeDto $dto): ThemeDto
    {
        // Al clonar, el autor pasa a ser el usuario que clona (nuevo creador).
        $accessibilityOverrides = array_replace($dto->accessibilityOverrides ?? [], [
            'author' => $this->resolveAuthorName($userId),
        ]);

        return $this->repository->clone(
            parentId: $parentId,
            createdBy: $userId,
            name: $dto->name,
            paletteOverrides: $dto->paletteOverrides,
            typographyOverrides: $dto->typographyOverrides,
            layoutOverrides: $dto->layoutOverrides,
            accessibilityOverrides: $accessibilityOverrides,
        );
    }

    /**
     * Resuelve el nombre legible del usuario (vista FDW `users`) para usar como
     * autor de los metadatos PDF. Cae a 'CEEDCV' si no se puede resolver.
     */
    private function resolveAuthorName(string $userId): string
    {
        $name = DB::table('users')->where('id', $userId)->value('name');

        return is_string($name) && trim($name) !== '' ? trim($name) : 'CEEDCV';
    }
}
