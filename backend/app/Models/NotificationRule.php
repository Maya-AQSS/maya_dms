<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-only projection of the dashboard's active scheduled rules, exposed
 * locally via the `notification_rules` FDW view. Never written from here.
 */
class NotificationRule extends Model
{
    protected $table = 'notification_rules';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'params' => 'array',
            'audience' => 'array',
        ];
    }

    public function scopeForApp(Builder $query, string $app): Builder
    {
        return $query->where('source_app', $app);
    }
}
