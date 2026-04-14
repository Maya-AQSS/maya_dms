<?php

namespace App\Listeners;

use App\Models\Document;
use App\Models\Template;
use App\Services\Contracts\AuditLogServiceInterface;
use Illuminate\Auth\Access\Events\GateEvaluated;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Log;

/**
 * Registra en audit_log (y deja traza en log de aplicación) cuando una política SoD
 * deniega explícitamente {@see DocumentPolicy} / {@see TemplatePolicy}.
 */
class RecordSegregationOfDutiesDenial
{
    private const ABILITIES = ['review', 'submit'];

    public function __construct(
        private readonly AuditLogServiceInterface $auditLogService,
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
            $subject instanceof Template   => 'template',
            default                        => null,
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
            'ability'      => $event->ability,
            'entity_type'  => $entityType,
            'entity_id'    => (string) $subject->getKey(),
            'user_id'      => $userId,
        ]);

        try {
            $this->auditLogService->record(
                entityType:    $entityType,
                entityId:      (string) $subject->getKey(),
                action:        'sod_violation',
                userId:        (string) $userId,
                blockId:       null,
                previousValue: null,
                newValue:      [
                    'ability' => $event->ability,
                    'level'   => 'WARNING',
                    'reason'  => 'segregation_of_duties',
                ],
                ipAddress:     $request?->ip(),
                userAgent:     $request?->userAgent(),
            );
        } catch (\Throwable $e) {
            Log::error('Failed to persist SoD audit entry', [
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
