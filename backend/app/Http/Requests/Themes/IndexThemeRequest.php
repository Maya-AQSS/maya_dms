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
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
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

    public function perPage(): int
    {
        return (int) ($this->validated()['per_page'] ?? 15);
    }
}
