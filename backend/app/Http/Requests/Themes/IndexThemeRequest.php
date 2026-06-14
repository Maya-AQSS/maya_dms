<?php

declare(strict_types=1);

namespace App\Http\Requests\Themes;

use App\Models\Theme;
use Illuminate\Foundation\Http\FormRequest;

class IndexThemeRequest extends FormRequest
{
    /**
     * Catálogo de gestión → theme.index. Selector en plantilla (solo publicados) → dms.login.
     */
    public function authorize(): bool
    {
        if (($this->input('status') ?? '') === 'published') {
            return $this->user()->can('viewPublishedForTemplate', Theme::class);
        }

        return $this->user()->can('viewAny', Theme::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', 'in:draft,published,archived'],
            'team_id' => ['nullable', 'uuid'],
            'search' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort_by' => ['nullable', 'string', 'in:name,created_at,updated_at'],
            'sort_dir' => ['nullable', 'string', 'in:asc,desc'],
        ];
    }

    /**
     * @return array{status?: string, search?: string, team_id?: string}
     */
    public function filters(): array
    {
        $v = $this->validated();
        $filters = [];
        foreach (['status', 'search', 'team_id'] as $key) {
            if (! empty($v[$key])) {
                $filters[$key] = (string) $v[$key];
            }
        }

        /** @var array{status?: string, search?: string, team_id?: string} $filters */
        return $filters;
    }

    public function getPage(): int
    {
        $page = (int) ($this->validated()['page'] ?? 1);

        return $page > 0 ? $page : 1;
    }

    public function perPage(): int
    {
        return (int) ($this->validated()['per_page'] ?? 15);
    }

    public function getSortBy(): string
    {
        $sortBy = $this->validated()['sort_by'] ?? 'updated_at';
        $whitelist = ['name', 'created_at', 'updated_at'];

        return in_array($sortBy, $whitelist, true) ? $sortBy : 'updated_at';
    }

    public function getSortDir(): string
    {
        $dir = $this->validated()['sort_dir'] ?? 'desc';

        return in_array($dir, ['asc', 'desc'], true) ? $dir : 'desc';
    }
}
