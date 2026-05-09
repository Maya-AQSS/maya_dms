<?php

namespace App\Models\Concerns;

trait HasCommentingStatus
{
    private const string COMMENTING_CLOSED_STATUS = 'published';

    // Requires $this->status to be resolvable — callers must eager-load headVersion.
    public function isCommentingOpen(): bool
    {
        return ($this->status ?? '') !== self::COMMENTING_CLOSED_STATUS;
    }
}
