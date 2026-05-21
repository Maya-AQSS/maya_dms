<?php

declare(strict_types=1);

namespace App\Http\Requests\Processes;

use App\DTOs\Processes\CreateProcessDto;
use App\Models\Process;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProcessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Process::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:100', 'unique:processes,code'],
            'name' => ['required', 'string', 'max:255'],
            'alias' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'process_parent_id' => ['nullable', 'uuid', 'exists:processes,id'],
        ];
    }

    public function toDto(): CreateProcessDto
    {
        $v = $this->validated();

        return new CreateProcessDto(
            code: $v['code'],
            name: $v['name'],
            alias: $v['alias'],
            description: $v['description'] ?? null,
            processParentId: $v['process_parent_id'] ?? null,
        );
    }
}
