<?php

declare(strict_types=1);

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Log;
use Maya\Messaging\Publishers\NotificationPublisher;

/**
 * Encapsula el bucle de notificación "validación solicitada" compartido entre
 * TemplateReviewService y DocumentReviewService.
 *
 * El llamador suministra:
 *   - $recipients  → lista ya filtrada por ReviewValidationNotificationRecipients::filterForReviewMode()
 *   - $recipientKey → clave del array que contiene el user-ID ('user_id' para plantillas, 'reviewer_id' para documentos)
 *   - $buildArgs   → closure(string $recipientId): array<string, mixed> con los named-args para NotificationPublisher::send()
 *
 * La asimetría de broadcast (Document despacha BroadcastNotificationCreated, Template no)
 * es deliberada y permanece en cada ReviewService respectivo.
 */
final class ReviewValidationNotifier
{
    public function __construct(
        private readonly NotificationPublisher $notificationPublisher,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $recipients  Destinatarios ya filtrados para el review_mode activo.
     * @param  string  $recipientKey  Clave del user-ID en cada elemento de $recipients.
     * @param  Closure(string $recipientId): array<string, mixed>  $buildArgs  Factory de args para send().
     */
    public function notifyEach(array $recipients, string $recipientKey, Closure $buildArgs): void
    {
        foreach ($recipients as $row) {
            $recipientId = (string) ($row[$recipientKey] ?? '');
            if ($recipientId === '') {
                continue;
            }

            try {
                $args = $buildArgs($recipientId);
                $this->notificationPublisher->send(...$args);
            } catch (\Throwable $e) {
                $logContext = $row;
                $logContext['error'] = $e->getMessage();
                Log::warning('notification.publish_failed', $logContext);
            }
        }
    }
}
