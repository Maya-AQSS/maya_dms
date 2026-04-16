<?php

namespace App\Http\Requests\Templates;

use App\DTOs\Templates\FilterTemplatesDto;
use App\Enums\TemplateVisibilityLevel;
use App\Models\Template;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Template::class);
    }

    /**
     * Reglas de validación para el listado de plantillas.
     * 
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'visibility_level' => ['sometimes', 'nullable', Rule::enum(TemplateVisibilityLevel::class)],
            'status'           => ['sometimes', 'nullable', 'string', 'in:draft,published,archived'],
            'study_type_id'    => ['sometimes', 'nullable', 'string', 'max:255'],
            'study_id'         => ['sometimes', 'nullable', 'string', 'max:255'],
            'module_id'        => ['sometimes', 'nullable', 'string', 'max:255'],
            'group_id'         => ['sometimes', 'nullable', 'uuid', 'exists:teams,id'],
            'per_page'         => ['sometimes', 'integer', 'min:1', 'max:20'],
        ];
    }

    /**
     * Obtiene el número máximo de plantillas por página.
     */
    public function perPage(): int
    {
        return min(max((int) $this->query('per_page', 20), 1), 20);
    }

    /**
     * Convierte los datos validados en un DTO de filtros.
     */
    public function toFilterDto(): FilterTemplatesDto
    {
        $v = $this->validated();

        return new FilterTemplatesDto(
            visibilityLevel: $v['visibility_level'] ?? null,
            status: $v['status'] ?? null,
            studyTypeId: $v['study_type_id'] ?? null,
            studyId: $v['study_id'] ?? null,
            moduleId: $v['module_id'] ?? null,
            groupId: $v['group_id'] ?? null,
        );
    }
}
