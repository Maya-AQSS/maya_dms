<?php

namespace App\Http\Requests\Templates;

use App\DTOs\Templates\UpdateTemplateDto;
use App\Enums\TemplateVisibilityLevel;
use App\Models\Template;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTemplateRequest extends FormRequest
{
    /**
     * Verifica si el usuario puede actualizar la plantilla.
     */
    public function authorize(): bool
    {
        $template = $this->resolveTemplate();

        if ($this->filled('visibility_level')) {
            return $this->user()->can('update', [$template, $this->input('visibility_level')]);
        }

        return $this->user()->can('update', $template);
    }

    /**
     * Reglas de validación para la actualización de una plantilla.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'visibility_level' => ['sometimes', Rule::enum(TemplateVisibilityLevel::class)],
            'delivery_deadline' => ['sometimes', 'nullable', 'date'],
            'study_type_id' => ['sometimes', 'nullable', 'string', 'max:255', 'required_if:visibility_level,study_type'],
            'study_id' => ['sometimes', 'nullable', 'string', 'max:255', 'required_if:visibility_level,study'],
            'module_id' => ['sometimes', 'nullable', 'string', 'max:255', 'required_if:visibility_level,module'],
            'group_id' => ['sometimes', 'nullable', 'uuid', 'exists:teams,id', 'required_if:visibility_level,group'],
            'organization_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'string', 'in:draft,in_review,archived'],
            'review_stages' => ['sometimes', 'integer', 'min:0'],
            'review_mode' => ['sometimes', 'string', 'in:sequential,parallel'],
        ];
    }

    /**
     * Convierte los datos validados en un DTO de actualización de plantilla.
     */
    public function toUpdateDto(): UpdateTemplateDto
    {
        return new UpdateTemplateDto(
            name: $this->input('name'),
            setName: $this->has('name'),
            description: $this->input('description'),
            setDescription: $this->has('description'),
            visibilityLevel: $this->input('visibility_level'),
            setVisibilityLevel: $this->has('visibility_level'),
            deliveryDeadline: $this->has('delivery_deadline')
                ? ($this->input('delivery_deadline') !== null ? (string) $this->input('delivery_deadline') : null)
                : null,
            setDeliveryDeadline: $this->has('delivery_deadline'),
            studyTypeId: $this->input('study_type_id'),
            setStudyTypeId: $this->has('study_type_id'),
            studyId: $this->input('study_id'),
            setStudyId: $this->has('study_id'),
            moduleId: $this->input('module_id'),
            setModuleId: $this->has('module_id'),
            groupId: $this->input('group_id'),
            setGroupId: $this->has('group_id'),
            organizationId: $this->input('organization_id'),
            setOrganizationId: $this->has('organization_id'),
            status: $this->input('status'),
            setStatus: $this->has('status'),
            reviewStages: $this->has('review_stages') ? (int) $this->input('review_stages') : null,
            setReviewStages: $this->has('review_stages'),
            reviewMode: $this->input('review_mode'),
            setReviewMode: $this->has('review_mode'),
        );
    }

    /**
     * Obtiene la plantilla a partir del UUID en la ruta.
     */
    private function resolveTemplate(): Template
    {
        $id = $this->route('template');

        return Template::findOrFail($id);
    }
}
