<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\SodViolationDetected;
use App\Models\Document;
use App\Models\Template;
use Illuminate\Auth\Access\Events\GateEvaluated;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Log;

/**
 * Detecta denegaciones de SoD desde el evento marco
 * {@see GateEvaluated} y dispara el
 * domain event {@see SodViolationDetected} (implementa `AuditableEvent`)
 * para que el wildcard del package publique en `maya.audit`. El listener
 * NO publica directamente — cumple E5.
 */
class RecordSegregationOfDutiesDenial
{
    private const ABILITIES = ['review', 'submit'];

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
            default => null,
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
            'ability' => $event->ability,
            'entity_type' => $entityType,
            'entity_id' => (string) $subject->getKey(),
            'user_id' => $userId,
        ]);

        SodViolationDetected::dispatch(
            $entityType,
            (string) $subject->getKey(),
            (string) $userId,
            (string) $event->ability,
            $request?->ip(),
            $request?->userAgent(),
        );
    }
}
