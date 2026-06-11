<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Comment;

/**
 * Purges polymorphic Comment records when a block model is deleted.
 * Applied to DocumentBlock and TemplateBlock.
 */
trait PurgesBlockComments
{
    public static function bootPurgesBlockComments(): void
    {
        static::deleting(function (self $block): void {
            Comment::where('blockable_type', static::class)
                ->where('blockable_id', $block->id)
                ->delete();
        });
    }
}
