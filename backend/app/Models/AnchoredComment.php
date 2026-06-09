<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

final class AnchoredComment extends Model
{
    use HasUuids;

    protected $fillable = [
        'comment_id',
        'resource_type',
        'resource_id',
        'anchor_from',
        'anchor_to',
        'anchor_text_snapshot',
        'anchor_is_valid',
        'anchor_last_synced_at',
    ];

    protected $casts = [
        'anchor_from' => 'integer',
        'anchor_to' => 'integer',
        'anchor_is_valid' => 'boolean',
        'anchor_last_synced_at' => 'datetime',
    ];

    public function comment(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'comment_id');
    }

    /**
     * Polymorphic owner — typically `Template` or `Document`. The
     * controller uses `morph_map` to resolve string keys to model FQNs.
     */
    public function resource(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'resource_type', 'resource_id');
    }
}
