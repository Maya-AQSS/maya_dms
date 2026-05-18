<?php

declare(strict_types=1);

namespace App\Http\Requests\Documents;

use App\DTOs\Documents\UpdateDocumentDto;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'filled', 'string', 'max:255'],
            'delivery_deadline' => ['sometimes', 'date', 'after_or_equal:today'],
            'study_type_id' => ['sometimes', 'nullable', 'string'],
            'study_id' => ['sometimes', 'nullable', 'string'],
            'module_id' => ['sometimes', 'nullable', 'string'],
        ];
    }

    public function toDto(): UpdateDocumentDto
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return new UpdateDocumentDto(
            title: $this->stringOrNull($validated, 'title'),
            deliveryDeadline: $this->stringOrNull($validated, 'delivery_deadline'),
            studyTypeId: $this->stringOrNull($validated, 'study_type_id'),
            studyId: $this->stringOrNull($validated, 'study_id'),
            moduleId: $this->stringOrNull($validated, 'module_id'),
            changedFields: array_keys($validated),
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function stringOrNull(array $validated, string $key): ?string
    {
        if (! array_key_exists($key, $validated)) {
            return null;
        }
        $value = $validated[$key];

        return is_string($value) ? $value : null;
    }
}
