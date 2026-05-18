<?php

declare(strict_types=1);

namespace App\Events;

use App\Listeners\RecordSegregationOfDutiesDenial;
use Illuminate\Auth\Access\Events\GateEvaluated;
use Illuminate\Foundation\Events\Dispatchable;
use Maya\Messaging\Contracts\AuditableEvent;

/**
 * Hecho de negocio: una política de SoD (Segregation of Duties) ha denegado
 * explícitamente una ability (`review`, `submit`) sobre un Document o Template.
 * Disparado por el listener {@see RecordSegregationOfDutiesDenial}
 * desde el evento marco {@see GateEvaluated};
 * el wildcard del package publica al exchange `maya.audit` tras commit.
 */
class SodViolationDetected implements AuditableEvent
{
    use Dispatchable;

    public function __construct(
        public readonly string $entityType,
        public readonly string $entityId,
        public readonly string $userId,
        public readonly string $ability,
        public readonly ?string $ipAddress = null,
        public readonly ?string $userAgent = null,
    ) {}

    public function toAuditPayload(): array
    {
        return [
            'applicationSlug' => 'maya-dms',
            'entityType' => $this->entityType,
            'entityId' => $this->entityId,
            'action' => 'sod_violation',
            'userId' => $this->userId,
            'newValue' => [
                'ability' => $this->ability,
                'level' => 'WARNING',
                'reason' => 'segregation_of_duties',
            ],
            'ipAddress' => $this->ipAddress,
            'userAgent' => $this->userAgent,
        ];
    }
}
