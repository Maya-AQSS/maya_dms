<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Constants\DocumentConstants;
use App\DTOs\Themes\CreateThemeDto;
use App\DTOs\Themes\ThemeDto;
use App\DTOs\Themes\ThemeResolvedDto;
use App\DTOs\Themes\UpdateThemeDto;
use App\Models\Theme;
use App\Repositories\Contracts\ThemeRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as ConcretePaginator;
use Illuminate\Support\Str;

class ThemeRepository implements ThemeRepositoryInterface
{
    /**
     * @param  array{status?: string, search?: string, team_id?: string}  $filters
     */
    public function paginate(array $filters, int $perPage = 15, string $sortBy = 'updated_at', string $sortDir = 'desc'): LengthAwarePaginator
    {
        $query = Theme::query();

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['team_id'])) {
            $query->where('team_id', $filters['team_id']);
        }

        if (! empty($filters['search'])) {
            $needle = '%'.$filters['search'].'%';
            $query->where(function ($w) use ($needle) {
                $w->where('name', 'ilike', $needle)
                    ->orWhere('description', 'ilike', $needle);
            });
        }

        // Validar sort contra whitelist (seguridad contra inyección)
        $whitelist = ['name', 'created_at', 'updated_at'];
        $safeSortBy = in_array($sortBy, $whitelist, true) ? $sortBy : 'updated_at';
        $safeSortDir = in_array($sortDir, ['asc', 'desc'], true) ? $sortDir : 'desc';

        $query->orderBy($safeSortBy, $safeSortDir);

        /** @var LengthAwarePaginator<int, Theme> $page */
        $page = $query->paginate($perPage);

        $items = $page->getCollection()->map(fn (Theme $t) => $this->toDto($t))->values()->all();

        return new ConcretePaginator(
            items: $items,
            total: $page->total(),
            perPage: $page->perPage(),
            currentPage: $page->currentPage(),
            options: [
                'path' => $page->path(),
                'pageName' => $page->getPageName(),
            ],
        );
    }

    public function findById(string $id): ?ThemeDto
    {
        $model = Theme::query()->find($id);

        return $model ? $this->toDto($model) : null;
    }

    public function findModelOrFail(string $id): Theme
    {
        return Theme::query()->findOrFail($id);
    }

    public function create(CreateThemeDto $dto, string $createdBy): ThemeDto
    {
        $theme = new Theme;
        $theme->id = (string) Str::uuid();
        $theme->name = $dto->name;
        $theme->description = $dto->description;
        $theme->status = 'draft';
        $theme->created_by = $createdBy;
        $theme->team_id = $dto->teamId;
        $theme->palette = $dto->palette;
        $theme->typography = $dto->typography;
        $theme->layout = $dto->layout;
        $theme->accessibility = $dto->accessibility;
        $theme->save();

        return $this->toDto($theme);
    }

    public function update(string $id, UpdateThemeDto $dto): ThemeDto
    {
        /** @var Theme $theme */
        $theme = Theme::query()->findOrFail($id);

        if ($dto->name !== null) {
            $theme->name = $dto->name;
        }
        if ($dto->description !== null) {
            $theme->description = $dto->description;
        }
        // El estado NO se muta aquí: las transiciones pasan por
        // ThemeRepository::updateStatus() vía ThemeStateTransitions.
        if ($dto->palette !== null) {
            $theme->palette = $dto->palette;
        }
        if ($dto->typography !== null) {
            $theme->typography = $dto->typography;
        }
        if ($dto->layout !== null) {
            $theme->layout = $dto->layout;
        }
        if ($dto->accessibility !== null) {
            // El autor es inmutable (es el usuario creador): se preserva el
            // valor existente sea cual sea el que llegue en el payload.
            $existingAuthor = ((array) $theme->accessibility)['author'] ?? 'CEEDCV';
            $theme->accessibility = array_replace($dto->accessibility, [
                'author' => $existingAuthor,
            ]);
        }
        $theme->save();

        return $this->toDto($theme);
    }

    public function updateStatus(string $id, string $status): ThemeDto
    {
        /** @var Theme $theme */
        $theme = Theme::query()->findOrFail($id);
        $theme->status = $status;
        $theme->save();

        return $this->toDto($theme);
    }

    public function delete(string $id): void
    {
        Theme::query()->where('id', $id)->delete();
    }

    public function clone(
        string $parentId,
        string $createdBy,
        ?string $name,
        ?array $paletteOverrides,
        ?array $typographyOverrides,
        ?array $layoutOverrides,
        ?array $accessibilityOverrides,
    ): ThemeDto {
        /** @var Theme $parent */
        $parent = Theme::query()->findOrFail($parentId);

        $clone = new Theme;
        $clone->id = (string) Str::uuid();
        $clone->name = $name ?? ($parent->name.' (copia)');
        $clone->description = $parent->description;
        $clone->status = 'draft';
        $clone->created_by = $createdBy;
        $clone->team_id = $parent->team_id;
        $clone->cloned_from_id = $parent->id;

        $clone->palette = $paletteOverrides
            ? array_replace($parent->palette, $paletteOverrides)
            : $parent->palette;
        $clone->typography = $typographyOverrides
            ? array_replace($parent->typography, $typographyOverrides)
            : $parent->typography;
        $clone->layout = $layoutOverrides
            ? array_replace($parent->layout, $layoutOverrides)
            : $parent->layout;
        $clone->accessibility = $accessibilityOverrides
            ? array_replace($parent->accessibility, $accessibilityOverrides)
            : $parent->accessibility;

        $clone->save();

        return $this->toDto($clone);
    }

    private function toDto(Theme $theme): ThemeDto
    {
        return new ThemeDto(
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

    /**
     * Fetch resolved theme data for rendering by theme ID.
     * Returns theme assets and styling configuration as DTO.
     * Returns null if theme not found.
     */
    public function findThemeResolvedById(string $id): ?ThemeResolvedDto
    {
        $model = Theme::query()->find($id);

        if ($model === null) {
            return null;
        }

        return new ThemeResolvedDto(
            palette: (array) ($model->palette ?? []),
            typography: (array) ($model->typography ?? []),
            layout: (array) ($model->layout ?? []),
            accessibility: (array) ($model->accessibility ?? []),
            brandName: (string) ($model->name ?? 'CEEDCV'),
        );
    }

    public function findScopedThemesByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return Theme::query()
            ->whereIn('id', $ids)
            ->get()
            ->map(fn (Theme $t) => [
                'id' => (string) $t->id,
                'palette' => (array) ($t->palette ?? DocumentConstants::DEFAULT_THEME['palette']),
                'typography' => (array) ($t->typography ?? DocumentConstants::DEFAULT_THEME['typography']),
            ])
            ->all();
    }
}
