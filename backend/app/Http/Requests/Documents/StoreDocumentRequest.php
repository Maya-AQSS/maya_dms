<?php

namespace App\Http\Requests\Documents;

use App\DTOs\Documents\CreateDocumentDto;
use App\Models\Template;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentRequest extends FormRequest
{
    /**
     * Verifica si el usuario puede crear un documento.
     */
    public function authorize(): bool
    {
        if (! $this->filled('template_id')) {
            return true;
        }

        $template = Template::query()->find($this->input('template_id'));

        return $template !== null && $this->user()->can('view', $template);
    }

    /**
     * Reglas de validación para la creación de un documento.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'template_id' => ['required', 'uuid', 'exists:templates,id'],
            'title' => ['required', 'string', 'max:255'],
            'organization_id' => ['required', 'string', 'max:255'],
            'study_type_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'study_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'module_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'template_version_id' => [
                'sometimes',
                'nullable',
                'uuid',
                Rule::exists('template_versions', 'id')->where(
                    fn ($q) => $q->where('template_id', $this->input('template_id')),
                ),
            ],
        ];
    }

    /**
     * Convierte los datos de la solicitud en un DTO.
     */
    public function toDto(string $createdBy, string $ownerId): CreateDocumentDto
    {
        return new CreateDocumentDto(
            templateId: $this->validated('template_id'),
            title: $this->validated('title'),
            organizationId: $this->validated('organization_id'),
            createdBy: $createdBy,
            ownerId: $ownerId,
            studyTypeId: $this->validated('study_type_id') ?? null,
            studyId: $this->validated('study_id') ?? null,
            moduleId: $this->validated('module_id') ?? null,
            templateVersionId: $this->validated('template_version_id') ?? null,
        );
    }
}
