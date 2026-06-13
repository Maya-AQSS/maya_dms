<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Maya\Messaging\Contracts\AuditableEvent;
use Maya\Messaging\Support\MessagingConfig;

/**
 * Hecho de negocio: el titular envía un documento a validación, indicando los
 * validadores materializados (id, nombre y etapa), el tipo de validación
 * (review_mode) y los datos del documento. El wildcard del package publica al
 * exchange `maya.audit` tras commit.
 */
class DocumentSubmittedForReview implements AuditableEvent
{
    use Dispatchable;

    /**
     * @param  list<array{id: string, name: ?string, stage: int}>  $reviewers
     */
    public function __construct(
        public readonly string $documentId,
        public readonly string $actorId,
        public readonly string $reviewMode,
        public readonly array $reviewers,
        public readonly ?string $title = null,
        public readonly ?string $studyTypeId = null,
        public readonly ?string $studyId = null,
        public readonly ?string $moduleId = null,
        public readonly ?string $changelog = null,
    ) {}

    public function toAuditPayload(): array
    {
        return [
            'applicationSlug' => MessagingConfig::appSlug(),
            'entityType' => 'document',
            'entityId' => $this->documentId,
            'action' => 'submitted_for_review',
            'userId' => $this->actorId,
            'newValue' => [
                'status' => 'in_review',
                'review_mode' => $this->reviewMode,
                'reviewers' => $this->reviewers,
                'document' => array_filter([
                    'title' => $this->title,
                    'study_type_id' => $this->studyTypeId,
                    'study_id' => $this->studyId,
                    'module_id' => $this->moduleId,
                ], static fn ($v) => $v !== null && $v !== ''),
                'changelog' => $this->changelog,
            ],
        ];
    }
}
