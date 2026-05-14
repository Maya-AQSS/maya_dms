<?php

declare(strict_types=1);

namespace App\Http\Concerns;

use App\Models\Document;
use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Shared `can_clone` attachment for Document/Template controllers. Resolves the
 * policy gate per item once so Resources can read `can_clone` without re-running
 * Gate for each serialization.
 */
trait AttachesCanCloneMeta
{
    /**
     * @param  Document|Template|Collection<int, Document|Template>  $resources
     */
    protected function attachCanCloneMeta(Document|Template|Collection $resources, Request $request): void
    {
        $user = $request->user();
        if ($user === null) {
            return;
        }

        $attach = static function (Document|Template $resource) use ($user): void {
            $resource->setAttribute('can_clone', Gate::forUser($user)->allows('clone', $resource));
        };

        if ($resources instanceof Document || $resources instanceof Template) {
            $attach($resources);
            return;
        }

        foreach ($resources as $resource) {
            $attach($resource);
        }
    }
}
