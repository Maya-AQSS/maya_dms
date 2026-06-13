<?php

declare(strict_types=1);

namespace App\Services\Concerns;

use Illuminate\Support\Facades\Log;
use Maya\Messaging\Events\BroadcastNotificationCreated;
use Maya\Messaging\Publishers\NotificationPublisher;
use Maya\Messaging\Support\MessagingConfig;

/**
 * Encapsula el patrón duplicado de notificación de dominio: publicar la
 * notificación persistente (vía {@see NotificationPublisher}) y emitir el
 * broadcast en tiempo real ({@see BroadcastNotificationCreated}), cada uno con
 * su propio try/catch que degrada a un Log::warning sin propagar el fallo.
 *
 * El consumidor debe exponer una propiedad `$notificationPublisher`
 * (inyectada por constructor) — todos los Services que usan este trait ya la
 * declaran.
 */
trait NotifiesOwner
{
    /**
     * Publica una notificación de dominio (persistente + broadcast) para un
     * destinatario. El fallo de cualquiera de las dos vías se registra como
     * warning sin interrumpir la transacción que la origina.
     *
     * @param  array<string, mixed>  $params  Parámetros de interpolación i18n.
     * @param  array<string, mixed>  $metadata  Metadatos compartidos por persistencia y broadcast.
     */
    private function notifyOwner(
        string $recipientId,
        string $type,
        string $title,
        string $body,
        ?string $titleKey = null,
        ?string $bodyKey = null,
        array $params = [],
        string $severity = 'info',
        array $metadata = [],
    ): void {
        /** @var NotificationPublisher $publisher */
        $publisher = $this->notificationPublisher;

        try {
            $publisher->send(
                type: $type,
                recipientId: $recipientId,
                title: $title,
                body: $body,
                titleKey: $titleKey,
                bodyKey: $bodyKey,
                params: $params,
                severity: $severity,
                channels: ['app'],
                metadata: $metadata,
            );
        } catch (\Throwable $e) {
            Log::warning('notification.publish_failed', [
                'error' => $e->getMessage(),
                'type' => $type,
                'recipient_id' => $recipientId,
            ]);
        }

        try {
            BroadcastNotificationCreated::dispatch(
                recipientId: $recipientId,
                app: MessagingConfig::appSlug(),
                type: $type,
                title: $title,
                body: $body,
                metadata: $metadata,
            );
        } catch (\Throwable $e) {
            Log::warning('broadcast.dispatch_failed', [
                'error' => $e->getMessage(),
                'type' => $type,
                'recipient_id' => $recipientId,
            ]);
        }
    }
}
