<?php

namespace App\Http\Requests\Templates;

use App\DTOs\Templates\FilterTemplatesDto;
use App\Enums\TemplateVisibilityLevel;
use App\Models\Template;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Template::class);
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('Se requiere permiso.');
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
            'status'           => ['sometimes', 'nullable', 'string', 'in:draft,in_review,published,archived'],
            'usable_for_documents' => ['sometimes', 'boolean'],
            'study_type_id'    => ['sometimes', 'nullable', 'string', 'max:255'],
            'study_id'         => ['sometimes', 'nullable', 'string', 'max:255'],
            'module_id'        => ['sometimes', 'nullable', 'string', 'max:255'],
            'team_id'          => ['sometimes', 'nullable', 'uuid', 'exists:teams,id'],
            'author_name'      => ['sometimes', 'nullable', 'string', 'max:255'],
            'delivery_deadline' => ['sometimes', 'nullable', 'date'],
            'process_id'       => ['sometimes', 'nullable', 'uuid', 'exists:processes,id'],
        ];
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
            usableForDocuments: (bool) ($v['usable_for_documents'] ?? false),
            studyTypeId: $v['study_type_id'] ?? null,
            studyId: $v['study_id'] ?? null,
            moduleId: $v['module_id'] ?? null,
            teamId: $v['team_id'] ?? null,
            authorName: $v['author_name'] ?? null,
            deliveryDeadline: $v['delivery_deadline'] ?? null,
            processId: $v['process_id'] ?? null,
        );
    }
}
