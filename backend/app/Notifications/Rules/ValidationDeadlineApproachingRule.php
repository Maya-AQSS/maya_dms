<?php

declare(strict_types=1);

namespace App\Notifications\Rules;

use App\Repositories\Contracts\DocumentRepositoryInterface;
use Illuminate\Support\Facades\Log;
use Maya\Messaging\Publishers\NotificationPublisher;

/**
 * Notifica a propietarios sobre documentos cuya fecha de validación
 * vence en menos de 7 días y aún no están validados/publicados.
 */
final class ValidationDeadlineApproachingRule implements ScheduledNotificationRule
{
    private const DAYS_THRESHOLD = 7;

    public function __construct(
        private readonly DocumentRepositoryInterface $documentRepository,
    ) {}

    public function evaluate(NotificationPublisher $publisher, array $params, string $severity): int
    {
        $count = 0;
        $days = (int) ($params['days'] ?? self::DAYS_THRESHOLD);

        try {
            $documents = $this->documentRepository->findApproachingDeadline($days);

            foreach ($documents as $doc) {
                try {
                    $publisher->send(
                        type: 'dms.validation_deadline_approaching',
                        recipientId: $doc->ownerId,
                        severity: $severity,
                        titleKey: 'notifications.dms.validation_deadline_approaching.title',
                        bodyKey: 'notifications.dms.validation_deadline_approaching.body',
                        params: [
                            'document_id' => $doc->documentId,
                            'document_title' => $doc->title,
                            'deadline' => $doc->deadline,
                        ],
                        scope: 'user',
                        channels: ['app'],
                        app: 'dms',
                    );

                    $count++;
                } catch (\Throwable $e) {
                    Log::warning('notifications.validation_deadline.document_processing_failed', [
                        'document_id' => $doc->documentId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('notifications.validation_deadline.rule_failed', [
                'error' => $e->getMessage(),
            ]);
        }

        return $count;
    }
}
