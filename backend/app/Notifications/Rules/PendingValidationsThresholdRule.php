<?php

declare(strict_types=1);

namespace App\Notifications\Rules;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maya\Messaging\Publishers\NotificationPublisher;

/**
 * Notifica a revisores con más de N documentos pendientes de validar.
 * N es configurable vía `DMS_PENDING_VALIDATIONS_THRESHOLD` (por defecto 10).
 */
final class PendingValidationsThresholdRule implements ScheduledNotificationRule
{
    public function evaluate(NotificationPublisher $publisher, array $params, string $severity): int
    {
        $count = 0;
        $threshold = (int) ($params['threshold'] ?? config('dms.pending_validations_threshold', 10));

        try {
            $reviewers = DB::table('document_reviews')
                ->select('reviewer_id')
                ->where('status', 'pending')
                ->groupBy('reviewer_id')
                ->havingRaw('COUNT(*) > ?', [$threshold])
                ->get();

            foreach ($reviewers as $row) {
                try {
                    $reviewerId = $row->reviewer_id;
                    $pendingCount = DB::table('document_reviews')
                        ->where('reviewer_id', $reviewerId)
                        ->where('status', 'pending')
                        ->count();

                    if ($pendingCount <= $threshold) {
                        continue;
                    }

                    $publisher->send(
                        type: 'dms.pending_validations_threshold',
                        recipientId: $reviewerId,
                        severity: $severity,
                        titleKey: 'notifications.dms.pending_validations_threshold.title',
                        bodyKey: 'notifications.dms.pending_validations_threshold.body',
                        params: [
                            'count' => $pendingCount,
                        ],
                        scope: 'user',
                        channels: ['app'],
                        app: 'dms',
                    );

                    $count++;
                } catch (\Throwable $e) {
                    Log::warning('notifications.pending_validations.reviewer_processing_failed', [
                        'reviewer_id' => $row->reviewer_id ?? 'unknown',
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
