<?php

declare(strict_types=1);

namespace App\Http\Requests\Processes;

use App\Models\Process;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

class IndexProcessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Process::class);
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('Se requiere permiso process.index.');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'parent_id' => ['nullable', 'string'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
            'sort_by' => ['nullable', 'string', 'in:code,name,alias,created_at,updated_at'],
            'sort_dir' => ['nullable', 'string', 'in:asc,desc'],
        ];
    }

    public function getPage(): int
    {
        return (int) ($this->input('page') ?? 1);
    }

    public function getPerPage(): int
    {
        return (int) ($this->input('per_page') ?? 15);
    }

    public function getSortBy(): ?string
    {
        return $this->input('sort_by');
    }

    public function getSortDir(): string
    {
        return $this->input('sort_dir') === 'asc' ? 'asc' : 'desc';
    }
}
