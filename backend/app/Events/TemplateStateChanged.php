<?php

namespace App\Events;

use App\Models\Template;
use Illuminate\Foundation\Events\Dispatchable;

class TemplateStateChanged
{
    use Dispatchable;

    public function __construct(
        public readonly Template $template,
        public readonly string   $oldStatus,
        public readonly string   $newStatus,
        public readonly string   $actorId,
    ) {}
}
