<?php

namespace App\Events;

use App\Models\Document;
use Illuminate\Foundation\Events\Dispatchable;

class DocumentStateChanged
{
    use Dispatchable;

    public function __construct(
        public readonly Document $document,
        public readonly string   $oldStatus,
        public readonly string   $newStatus,
        public readonly string   $actorId,
    ) {}
}
