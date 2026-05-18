<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\TemplateBlock;
use Illuminate\Foundation\Events\Dispatchable;
use Maya\Messaging\Contracts\AuditableEvent;

/**
 * Hecho de negocio: un bloque ha sido creado dentro de una plantilla.
 */
class TemplateBlockCreated implements AuditableEvent
{
    use Dispatchable;

    public function __construct(
        public readonly string $templateId,
        public readonly TemplateBlock $block,
        public readonly string $actorId,
    ) {}

    public function toAuditPayload(): array
    {
        return [
            'applicationSlug' => 'maya-dms',
            'entityType' => 'template',
            'entityId' => $this->templateId,
            'action' => 'block_created',
            'userId' => $this->actorId,
            'blockId' => (string) $this->block->getKey(),
            'newValue' => ['block_state' => $this->block->block_state],
        ];
    }
}
