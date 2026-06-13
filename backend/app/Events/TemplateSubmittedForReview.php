<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Maya\Messaging\Contracts\AuditableEvent;
use Maya\Messaging\Support\MessagingConfig;

/**
 * Hecho de negocio: el autor envía una plantilla a validación, indicando los
 * revisores materializados (id, nombre y etapa), el tipo de validación
 * (review_mode) y los datos de la plantilla. El wildcard del package publica al
 * exchange `maya.audit` tras commit.
 */
class TemplateSubmittedForReview implements AuditableEvent
{
    use Dispatchable;

    /**
     * @param  list<array{id: string, name: ?string, stage: int}>  $reviewers
     */
    public function __construct(
        public readonly string $templateId,
        public readonly string $actorId,
        public readonly string $reviewMode,
        public readonly array $reviewers,
        public readonly ?string $name = null,
        public readonly ?string $visibilityLevel = null,
        public readonly ?string $studyTypeId = null,
        public readonly ?string $studyId = null,
        public readonly ?string $moduleId = null,
        public readonly ?string $changelog = null,
    ) {}

    public function toAuditPayload(): array
    {
        return [
            'applicationSlug' => MessagingConfig::appSlug(),
            'entityType' => 'template',
            'entityId' => $this->templateId,
            'action' => 'submitted_for_review',
            'userId' => $this->actorId,
            'newValue' => [
                'status' => 'in_review',
                'review_mode' => $this->reviewMode,
                'reviewers' => $this->reviewers,
                'template' => array_filter([
                    'name' => $this->name,
                    'visibility_level' => $this->visibilityLevel,
                    'study_type_id' => $this->studyTypeId,
                    'study_id' => $this->studyId,
                    'module_id' => $this->moduleId,
                ], static fn ($v) => $v !== null && $v !== ''),
                'changelog' => $this->changelog,
            ],
        ];
    }
}
