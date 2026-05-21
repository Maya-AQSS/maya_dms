<?php

declare(strict_types=1);

namespace App\Http\Requests\Processes;

use App\DTOs\Processes\UpdateProcessDto;
use App\Models\Process;
use App\Services\Contracts\ProcessServiceInterface;
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
        );
    }

    public function resolveProcess(): Process
    {
        $id = (string) $this->route('process');

        return app(ProcessServiceInterface::class)->findModelOrFail($id);
    }
}
