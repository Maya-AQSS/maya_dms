<?php

namespace App\Http\Requests\Documents;

use App\DTOs\Documents\CreateDocumentDto;
use App\Models\JwtUser;
use App\Models\Template;
use App\Models\TemplateVersion;
use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentRequest extends FormRequest
{
    /**
     * Verifica si el usuario puede crear un documento.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user instanceof JwtUser || ! $user->hasPermission('documents.create')) {
            return false;
        }

        if (! $this->filled('template_id') && ! $this->filled('template_version_id')) {
            return true;
        }

        $template = null;
        if ($this->filled('template_id')) {
            $template = Template::query()->find($this->input('template_id'));
        } elseif ($this->filled('template_version_id')) {
            $templateId = TemplateVersion::query()
                ->whereKey($this->input('template_version_id'))
                ->value('template_id');
            if (is_string($templateId)) {
                $template = Template::query()->find($templateId);
            }
        }

        return $template !== null && $user->can('view', $template);
    }

    /**
     * Reglas de validación para la creación de un documento.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'template_id' => ['sometimes', 'nullable', 'uuid', 'exists:templates,id'],
            'title' => ['required', 'string', 'max:255'],
            'study_type_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'study_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'module_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'template_version_id' => [
                'required_without:template_id',
                'uuid',
                'exists:template_versions,id',
            ],
        ];
    }

    /**
     * Convierte los datos de la solicitud en un DTO.
     */
    public function toDto(string $createdBy, string $ownerId): CreateDocumentDto
    {
        $templateVersionId = $this->validated('template_version_id');
        $templateId = $this->validated('template_id');

        if (! is_string($templateId) && is_string($templateVersionId)) {
            $templateId = (string) TemplateVersion::query()
                ->whereKey($templateVersionId)
                ->value('template_id');
        }

        return new CreateDocumentDto(
            templateId: (string) $templateId,
            title: $this->validated('title'),
            createdBy: $createdBy,
            ownerId: $ownerId,
            studyTypeId: $this->validated('study_type_id') ?? null,
            studyId: $this->validated('study_id') ?? null,
            moduleId: $this->validated('module_id') ?? null,
            templateVersionId: $templateVersionId ?? null,
        );
    }
}
