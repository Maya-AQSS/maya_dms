<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Local run-state for scheduled rules (cron due-ness), avoiding cross-DB writes.
 */
class NotificationRuleRun extends Model
{
    protected $table = 'notification_rule_runs';

    protected $primaryKey = 'rule_id';

    public $incrementing = false;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = ['rule_id', 'last_run_at'];

    protected function casts(): array
    {
        return ['last_run_at' => 'datetime'];
    }
}
