<?php

declare(strict_types=1);

namespace App\Http\Requests\Processes;

use App\DTOs\Processes\UpdateProcessDto;
use App\Models\Process;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProcessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->resolveProcess());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $processId = (string) $this->route('process');

        return [
            'code' => ['required', 'string', 'max:100', Rule::unique('processes', 'code')->ignore($processId)],
            'name' => ['required', 'string', 'max:255'],
            'alias' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'process_parent_id' => ['nullable', 'uuid', 'exists:processes,id', Rule::notIn([$processId])],
            'color' => ['nullable', 'string', 'max:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'icon' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function toDto(): UpdateProcessDto
    {
        $v = $this->validated();

        return new UpdateProcessDto(
            code: $v['code'],
            name: $v['name'],
            alias: $v['alias'],
            description: $v['description'] ?? null,
            processParentId: $v['process_parent_id'] ?? null,
            color: $v['color'] ?? null,
            icon: $v['icon'] ?? null,
        );
    }

    public function resolveProcess(): Process
    {
        $id = (string) $this->route('process');

        return Process::findOrFail($id);
    }
}
