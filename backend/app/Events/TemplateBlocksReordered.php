<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Maya\Messaging\Contracts\AuditableEvent;

/**
 * Hecho de negocio: los bloques de una plantilla han sido reordenados.
 */
class TemplateBlocksReordered implements AuditableEvent
{
    use Dispatchable;

    /**
     * @param  list<string>  $orderedBlockIds
     */
    public function __construct(
        public readonly string $templateId,
        public readonly array $orderedBlockIds,
        public readonly string $actorId,
    ) {}

    public function toAuditPayload(): array
    {
        return [
            'applicationSlug' => 'maya-dms',
            'entityType'      => 'template',
            'entityId'        => $this->templateId,
            'action'          => 'blocks_reordered',
            'userId'          => $this->actorId,
            'newValue'        => ['block_ids' => $this->orderedBlockIds],
        ];
    }
}
