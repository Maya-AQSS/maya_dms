<?php

declare(strict_types=1);

namespace App\Http\Requests\Documents;

use App\DTOs\Documents\CreateDocumentDto;
use App\Models\Document;
use App\Models\JwtUser;
use App\Models\Template;
use App\Repositories\Contracts\TemplateRepositoryInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreDocumentRequest extends FormRequest
{
    /** @var Template|null|false Cache: null = sin plantilla, false = no inicializado */
    private Template|false|null $resolvedTemplate = false;

    /**
     * Verifica si el usuario puede crear un documento.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user instanceof JwtUser || ! $user->can('create', Document::class)) {
            return false;
        }

        if (! $this->filled('template_id') && ! $this->filled('template_version_id')) {
            return true;
        }

        $template = $this->resolveTemplate();

        return $template !== null && $user->can('view', $template);
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('Se requiere permiso document.create para crear documentos.');
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
            'team_id' => ['sometimes', 'nullable', 'uuid', 'exists:teams,id'],
            'delivery_deadline' => ['required', 'date', 'after_or_equal:today'],
            'process_id' => ['required', 'uuid', 'exists:processes,id'],
            'template_version_id' => [
                'required_without:template_id',
                'uuid',
                Rule::exists('entity_versions', 'id')->where(
                    static fn ($q) => $q->where('versionable_type', Template::class)->where('status', 'published'),
                ),
            ],
            // Paso de migración del wizard: contenido a precargar por template_block_id.
            'migrated_blocks' => ['sometimes', 'nullable', 'array'],
            'migrated_blocks.*' => ['array'],
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

            $template = $this->resolveTemplate();

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
     * Resuelve la plantilla referenciada (por template_id o template_version_id) exactamente
     * una vez por request. Devuelve null cuando no hay referencia de plantilla en la petición
     * o cuando el ID no existe en base de datos.
     */
    private function resolveTemplate(): ?Template
    {
        if ($this->resolvedTemplate !== false) {
            return $this->resolvedTemplate;
        }

        $repo = app(TemplateRepositoryInterface::class);

        if ($this->filled('template_id')) {
            try {
                $this->resolvedTemplate = $repo->findOrFailWithoutCatalogScope((string) $this->input('template_id'));
            } catch (ModelNotFoundException) {
                $this->resolvedTemplate = null;
            }
        } elseif ($this->filled('template_version_id')) {
            try {
                $this->resolvedTemplate = $repo->findOrFailByVersionId((string) $this->input('template_version_id'));
            } catch (ModelNotFoundException) {
                $this->resolvedTemplate = null;
            }
        } else {
            $this->resolvedTemplate = null;
        }

        return $this->resolvedTemplate;
    }

    /**
     * Convierte los datos de la solicitud en un DTO.
     */
    public function toDto(string $createdBy, string $ownerId): CreateDocumentDto
    {
        $templateVersionId = $this->validated('template_version_id');
        $templateId = $this->validated('template_id');

        if (! is_string($templateId) && is_string($templateVersionId)) {
            $templateId = (string) ($this->resolveTemplate()?->id ?? '');
        }

        $migratedBlocks = $this->validated('migrated_blocks');

        return new CreateDocumentDto(
            templateId: (string) $templateId,
            title: $this->validated('title'),
            createdBy: $createdBy,
            ownerId: $ownerId,
            processId: $this->validated('process_id'),
            studyTypeId: $this->validated('study_type_id') ?? null,
            studyId: $this->validated('study_id') ?? null,
            moduleId: $this->validated('module_id') ?? null,
            teamId: $this->validated('team_id') ?? null,
            deliveryDeadline: $this->validated('delivery_deadline'),
            templateVersionId: $templateVersionId ?? null,
            migratedBlockContent: is_array($migratedBlocks) && $migratedBlocks !== [] ? $migratedBlocks : null,
        );
    }
}
