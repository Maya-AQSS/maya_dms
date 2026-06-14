<?php

declare(strict_types=1);

namespace App\Notifications\Rules;

use App\Repositories\Contracts\DocumentRepositoryInterface;
use Illuminate\Support\Facades\Log;
use Maya\Messaging\Publishers\NotificationPublisher;

/**
 * Notifica a revisores con más de N documentos pendientes de validar.
 * N es configurable vía `DMS_PENDING_VALIDATIONS_THRESHOLD` (por defecto 10).
 */
final class PendingValidationsThresholdRule implements ScheduledNotificationRule
{
    public function __construct(
        private readonly DocumentRepositoryInterface $documentRepository,
    ) {}

    public function evaluate(NotificationPublisher $publisher, array $params, string $severity): int
    {
        $count = 0;
        $threshold = (int) ($params['threshold'] ?? config('dms.pending_validations_threshold', 10));

        try {
            $reviewers = $this->documentRepository->reviewersWithPendingReviewsAbove($threshold);

            foreach ($reviewers as $reviewer) {
                try {
                    $publisher->send(
                        type: 'dms.pending_validations_threshold',
                        recipientId: $reviewer->reviewerId,
                        severity: $severity,
                        titleKey: 'notifications.dms.pending_validations_threshold.title',
                        bodyKey: 'notifications.dms.pending_validations_threshold.body',
                        params: [
                            'count' => $reviewer->pendingCount,
                        ],
                        scope: 'user',
                        channels: ['app'],
                        app: 'dms',
                    );

                    $count++;
                } catch (\Throwable $e) {
                    Log::warning('notifications.pending_validations.reviewer_processing_failed', [
                        'reviewer_id' => $reviewer->reviewerId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('notifications.pending_validations.rule_failed', [
                'error' => $e->getMessage(),
            ]);
        }

        return $count;
    }
}
