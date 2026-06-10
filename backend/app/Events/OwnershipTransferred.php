<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Maya\Messaging\Contracts\AuditableEvent;

/**
 * Hecho de negocio: se ha cedido la propiedad de una plantilla (`created_by`)
 * o de un documento (`owner_id`) a otro usuario. El wildcard del package
 * publica al exchange `maya.audit` tras commit.
 */
class OwnershipTransferred implements AuditableEvent
{
    use Dispatchable;

    public function __construct(
        public readonly string $entityType,
        public readonly string $entityId,
        public readonly string $previousOwnerId,
        public readonly string $newOwnerId,
        public readonly string $actorId,
        public readonly ?string $ipAddress = null,
        public readonly ?string $userAgent = null,
    ) {}

    public function toAuditPayload(): array
    {
        return [
            'applicationSlug' => 'maya-dms',
            'entityType' => $this->entityType,
            'entityId' => $this->entityId,
            'action' => 'ownership_transferred',
            'userId' => $this->actorId,
            'previousValue' => ['owner_id' => $this->previousOwnerId],
            'newValue' => ['owner_id' => $this->newOwnerId],
            'ipAddress' => $this->ipAddress,
            'userAgent' => $this->userAgent,
        ];
    }
}
