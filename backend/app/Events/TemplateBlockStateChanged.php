<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Maya\Messaging\Contracts\AuditableEvent;

/**
 * Hecho de negocio: cambia el `block_state` de un bloque de plantilla
 * (editable ↔ readonly, mandatory, etc.).
 */
class TemplateBlockStateChanged implements AuditableEvent
{
    use Dispatchable;

    public function __construct(
        public readonly string $templateId,
        public readonly string $blockId,
        public readonly string $previousState,
        public readonly string $newState,
        public readonly string $actorId,
    ) {}

    public function toAuditPayload(): array
    {
        return [
            'applicationSlug' => 'maya-dms',
            'entityType' => 'template',
            'entityId' => $this->templateId,
            'action' => 'block_state_changed',
            'userId' => $this->actorId,
            'blockId' => $this->blockId,
            'previousValue' => ['block_state' => $this->previousState],
            'newValue' => ['block_state' => $this->newState],
        ];
    }
}
