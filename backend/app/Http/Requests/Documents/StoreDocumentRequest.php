<?php

namespace App\Http\Requests\Documents;

use App\DTOs\Documents\CreateDocumentDto;
use App\Models\EntityVersion;
use App\Models\JwtUser;
use App\Models\Template;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            $ev = EntityVersion::query()
                ->whereKey($this->input('template_version_id'))
                ->where('versionable_type', Template::class)
                ->first();
            if ($ev !== null) {
                $template = Template::query()->find($ev->versionable_id);
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
            'delivery_deadline' => ['required', 'date', 'after_or_equal:today'],
            'process_id' => ['required', 'uuid', 'exists:processes,id'],
            'template_version_id' => [
                'required_without:template_id',
                'uuid',
                Rule::exists('entity_versions', 'id')->where(
                    static fn ($q) => $q->where('versionable_type', Template::class)->where('status', 'published'),
                ),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            $processId = $this->input('process_id');
            if (! is_string($processId)) {
                return;
            }

            $template = null;
            if ($this->filled('template_id')) {
                $template = Template::query()->find($this->input('template_id'));
            } elseif ($this->filled('template_version_id')) {
                $ev = EntityVersion::query()
                    ->whereKey($this->input('template_version_id'))
                    ->where('versionable_type', Template::class)
                    ->first();
                if ($ev !== null) {
                    $template = Template::query()->find($ev->versionable_id);
                }
            }

            if ($template === null) {
                return;
            }

            if ((string) $template->process_id !== $processId) {
                $v->errors()->add(
                    'process_id',
                    'El proceso no corresponde a la plantilla seleccionada.',
                );
            }
        });
    }

    /**
     * Convierte los datos de la solicitud en un DTO.
     */
    public function toDto(string $createdBy, string $ownerId): CreateDocumentDto
    {
        $templateVersionId = $this->validated('template_version_id');
        $templateId = $this->validated('template_id');

        if (! is_string($templateId) && is_string($templateVersionId)) {
            $templateId = (string) (EntityVersion::query()
                ->whereKey($templateVersionId)
                ->where('versionable_type', Template::class)
                ->value('versionable_id') ?? '');
        }

        return new CreateDocumentDto(
            templateId: (string) $templateId,
            title: $this->validated('title'),
            createdBy: $createdBy,
            ownerId: $ownerId,
            processId: $this->validated('process_id'),
            studyTypeId: $this->validated('study_type_id') ?? null,
            studyId: $this->validated('study_id') ?? null,
            moduleId: $this->validated('module_id') ?? null,
            deliveryDeadline: $this->validated('delivery_deadline'),
            templateVersionId: $templateVersionId ?? null,
        );
    }
}
