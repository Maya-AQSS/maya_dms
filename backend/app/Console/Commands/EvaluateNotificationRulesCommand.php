<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\NotificationRule;
use App\Models\NotificationRuleRun;
use App\Notifications\Rules\PendingValidationsThresholdRule;
use App\Notifications\Rules\ScheduledNotificationRule;
use App\Notifications\Rules\ValidationDeadlineApproachingRule;
use Cron\CronExpression;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Maya\Messaging\Publishers\NotificationPublisher;
use Throwable;

/**
 * Level B: reads this service's active scheduled rules from the dashboard
 * (via the notification_rules FDW view), computes cron due-ness against the
 * local run-state, and dispatches each due rule to its registered evaluator
 * with the admin-configured params/severity.
 */
final class EvaluateNotificationRulesCommand extends Command
{
    protected $signature = 'notifications:evaluate-rules';

    protected $description = 'Evalúa las reglas programadas configuradas en el dashboard (FDW) y publica notificaciones';

    /**
     * Maps evaluator_key → evaluator class (the only code-side registration).
     *
     * @var array<string, class-string<ScheduledNotificationRule>>
     */
    private const EVALUATORS = [
        'dms.validation_deadline_approaching' => ValidationDeadlineApproachingRule::class,
        'dms.pending_validations_threshold' => PendingValidationsThresholdRule::class,
    ];

    public function handle(NotificationPublisher $publisher): int
    {
        $app = (string) config('messaging.app');
        $now = now();
        $total = 0;

        $rules = NotificationRule::query()->forApp($app)->get();

        foreach ($rules as $rule) {
            try {
                if (! isset(self::EVALUATORS[$rule->evaluator_key])) {
                    continue; // rule with no code-side evaluator in this service
                }

                if (! $this->isDue($rule, $now)) {
                    continue;
                }

                /** @var ScheduledNotificationRule $evaluator */
                $evaluator = app(self::EVALUATORS[$rule->evaluator_key]);
                $count = $evaluator->evaluate(
                    $publisher,
                    is_array($rule->params) ? $rule->params : [],
                    (string) ($rule->severity ?? 'info'),
                );

                $this->stampRun((int) $rule->id, $now);
                $total += $count;

                $this->line("✓ rule #{$rule->id} {$rule->evaluator_key}: {$count}");
            } catch (Throwable $e) {
                Log::error('notifications.rule_execution_failed', [
                    'rule_id' => $rule->id ?? null,
                    'evaluator_key' => $rule->evaluator_key ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Total notificaciones publicadas: {$total}");

        return self::SUCCESS;
    }

    private function isDue(NotificationRule $rule, Carbon $now): bool
    {
        $cron = (string) $rule->schedule_cron;
        if (! CronExpression::isValidExpression($cron)) {
            return false;
        }

        $lastRun = NotificationRuleRun::query()->whereKey($rule->id)->value('last_run_at');
        $since = $lastRun !== null ? Carbon::parse($lastRun) : $now->copy()->subYear();

        return (new CronExpression($cron))->getNextRunDate($since->toDateTimeString()) <= $now;
    }

    private function stampRun(int $ruleId, Carbon $now): void
    {
        NotificationRuleRun::query()->updateOrCreate(
            ['rule_id' => $ruleId],
            ['last_run_at' => $now],
        );
    }
}
