<?php

declare(strict_types=1);

namespace App\Http\Requests\Documents;

use App\DTOs\Documents\ApplyTemplateMigrationDto;
use App\Http\Requests\Documents\Concerns\ResolvesDocumentForAuthorization;
use App\Models\Template;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApplyTemplateMigrationRequest extends FormRequest
{
    use ResolvesDocumentForAuthorization;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->resolveDocument());
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('No puedes migrar la plantilla de este documento.');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'target_template_version_id' => [
                'required',
                'uuid',
                Rule::exists('entity_versions', 'id')->where(
                    static fn ($q) => $q->where('versionable_type', Template::class)->where('status', 'published'),
                ),
            ],
            'migrated_blocks' => ['sometimes', 'nullable', 'array'],
            'migrated_blocks.*' => ['array'],
            'removed_block_actions' => ['sometimes', 'nullable', 'array'],
            'removed_block_actions.*' => ['string', 'in:delete,keep'],
        ];
    }

    public function toDto(): ApplyTemplateMigrationDto
    {
        $migrated = $this->validated('migrated_blocks');
        $removed = $this->validated('removed_block_actions');

        return new ApplyTemplateMigrationDto(
            documentId: (string) $this->route('document'),
            actorId: (string) $this->user()->getAuthIdentifier(),
            targetTemplateVersionId: (string) $this->validated('target_template_version_id'),
            migratedBlockContent: is_array($migrated) ? $migrated : [],
            removedBlockActions: is_array($removed) ? $removed : [],
        );
    }
}
