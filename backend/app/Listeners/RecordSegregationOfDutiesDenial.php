<?php

namespace App\Listeners;

use App\Models\Document;
use App\Models\Template;
use Illuminate\Auth\Access\Events\GateEvaluated;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Log;
use Maya\Messaging\Publishers\AuditPublisher;
use Maya\Messaging\Publishers\LogPublisher;
use Throwable;

/**
 * Denegación de autorización sobre flujos sensibles (abilities `review` / `submit` en
 * {@see Document} / {@see Template}): trazado en maya_audit y notificación en maya.logs
 * (severidad alta) para monitorizar intentos de violar políticas. Monolog (`Log::warning`)
 * solo en el `catch` si falla la publicación a la cola.
 */
class RecordSegregationOfDutiesDenial
{
    /**
     * Abilities de política que quieres auditar / enviar a maya.logs cuando el gate devuelve false.
     * Amplía esta lista solo si necesitas registrar otras (p. ej. `publish`) y aceptas el volumen.
     */
    private const ABILITIES = ['review', 'submit'];

    public function __construct(
        private readonly AuditPublisher $auditPublisher,
        private readonly LogPublisher $logPublisher,
    ) {}

    /**
     * Punto de extensión: aquí se decide qué denegaciones cuentan (result false, ability, tipo de modelo).
     * No hay “huecos” que rellenar salvo que cambies criterios de negocio:
     * - `app` / `applicationSlug` deben coincidir con el slug registrado en maya_logs (p. ej. maya-dms).
     * - severity / errorCode en `publish()` si tu panel clasifica distinto.
     */
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

        try {
            $this->logPublisher->publish(
                severity: 'high',
                message: sprintf('Política denegada: %s sobre %s %s', $event->ability, $entityType, $subject->getKey()),
                errorCode: 'DMS_SOD_POLICY_DENIED',
                file: null,
                line: null,
                metadata: [
                    'ability' => $event->ability,
                    'entity_type' => $entityType,
                    'entity_id' => (string) $subject->getKey(),
                    'user_id' => (string) $userId,
                ],
                app: 'maya-dms',
            );
        } catch (Throwable $e) {
            Log::warning('maya.logs.publish_failed', [
                'context' => 'sod_denial',
                'error' => $e->getMessage(),
            ]);
        }

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
