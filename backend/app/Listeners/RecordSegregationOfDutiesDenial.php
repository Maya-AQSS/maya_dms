<?php

namespace App\Listeners;

use App\Models\Document;
use App\Models\Template;
use Illuminate\Auth\Access\Events\GateEvaluated;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Log;
use Maya\Messaging\Publishers\AuditPublisher;

/**
 * Registra en el bus de auditoría cuando una política SoD deniega
 * explícitamente {@see DocumentPolicy} / {@see TemplatePolicy}.
 */
class RecordSegregationOfDutiesDenial
{
    private const ABILITIES = ['review', 'submit'];

    public function __construct(
        private readonly AuditPublisher $auditPublisher,
    ) {}

    public function handle(GateEvaluated $event): void
    {
        if ($event->result !== false) {
            return;
        }

        if (! in_array($event->ability, self::ABILITIES, true)) {
            return;
        }

        $user = $event->user;
        if (! $user instanceof Authenticatable) {
            return;
        }

        $subject = $event->arguments[0] ?? null;

        $entityType = match (true) {
            $subject instanceof Document => 'document',
            $subject instanceof Template => 'template',
            default                      => null,
        };

        if ($entityType === null || ! $subject->getKey()) {
            return;
        }

        $userId = $user->getAuthIdentifier();
        if ($userId === null || $userId === '') {
            return;
        }

        $request = request();

        Log::warning('SoD policy denied', [
            'ability'     => $event->ability,
            'entity_type' => $entityType,
            'entity_id'   => (string) $subject->getKey(),
            'user_id'     => $userId,
        ]);

        $this->auditPublisher->publish(
            applicationSlug: 'maya-dms',
            entityType:      $entityType,
            entityId:        (string) $subject->getKey(),
            action:          'sod_violation',
            userId:          (string) $userId,
            newValue:        [
                'ability' => $event->ability,
                'level'   => 'WARNING',
                'reason'  => 'segregation_of_duties',
            ],
            ipAddress:       $request?->ip(),
            userAgent:       $request?->userAgent(),
        );
    }
}
