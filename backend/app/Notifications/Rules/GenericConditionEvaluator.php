<?php

declare(strict_types=1);

namespace App\Notifications\Rules;

use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Maya\Messaging\Publishers\NotificationPublisher;

/**
 * Generic scheduled rule evaluator driven entirely by the `conditions` JSONB
 * payload — no per-rule PHP code required.
 *
 * Recipient resolution (via `params.recipient_column`):
 *   - If the rule's `params` contain `recipient_column: "<col>"`, the evaluator
 *     reads that column from each matched row and sends one per-user notification.
 *   - If not set, it sends a single dashboard-scoped aggregate notification with
 *     `count` as the only param.
 *
 * Row cap: defaults to 500; override via `params.max_rows`.
 */
final class GenericConditionEvaluator implements ScheduledNotificationRule
{
    private const NOTIFICATION_TYPE = 'dms.generic_condition';

    private const DEFAULT_MAX_ROWS = 500;

    public function __construct(
        private readonly ConditionCompiler $compiler,
    ) {}

    public function evaluate(
        NotificationPublisher $publisher,
        array $params,
        string $severity,
        ?array $conditions = null,
    ): int {
        if ($conditions === null) {
            Log::warning('notifications.generic_condition.no_conditions', [
                'params' => $params,
            ]);

            return 0;
        }

        $ruleConditions = RuleConditions::fromArray($conditions);
        if ($ruleConditions === null) {
            return 0;
        }

        try {
            $query = $this->compiler->compile($ruleConditions);
        } catch (InvalidArgumentException $e) {
            Log::error('notifications.generic_condition.compile_failed', [
                'error' => $e->getMessage(),
            ]);

            return 0;
        }

        $maxRows        = max(1, (int) ($params['max_rows'] ?? self::DEFAULT_MAX_ROWS));
        $recipientCol   = isset($params['recipient_column']) ? (string) $params['recipient_column'] : null;

        $rows = $query->limit($maxRows)->get();

        if ($rows->isEmpty()) {
            return 0;
        }

        $count = 0;

        if ($recipientCol !== null) {
            foreach ($rows as $row) {
                $recipientId = $row->{$recipientCol} ?? null;
                if (! is_string($recipientId) || $recipientId === '') {
                    continue;
                }

                try {
                    $publisher->send(
                        type: self::NOTIFICATION_TYPE,
                        recipientId: $recipientId,
                        severity: $severity,
                        titleKey: 'notifications.'.self::NOTIFICATION_TYPE.'.title',
                        bodyKey: 'notifications.'.self::NOTIFICATION_TYPE.'.body',
                        params: ['count' => $rows->count()],
                        scope: 'user',
                        channels: ['app'],
                        app: 'dms',
                    );
                    $count++;
                } catch (\Throwable $e) {
                    Log::warning('notifications.generic_condition.publish_failed', [
                        'recipient_id' => $recipientId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } else {
            // Aggregate: single dashboard-scoped notification with the match count.
            try {
                $publisher->send(
                    type: self::NOTIFICATION_TYPE,
                    recipientId: '',
                    severity: $severity,
                    titleKey: 'notifications.'.self::NOTIFICATION_TYPE.'.title',
                    bodyKey: 'notifications.'.self::NOTIFICATION_TYPE.'.body',
                    params: ['count' => $rows->count()],
                    scope: 'dashboard',
                    channels: ['app'],
                    app: 'dms',
                );
                $count = 1;
            } catch (\Throwable $e) {
                Log::error('notifications.generic_condition.publish_aggregate_failed', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $count;
    }
}
