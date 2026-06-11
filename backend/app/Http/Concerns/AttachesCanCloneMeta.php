<?php

declare(strict_types=1);

namespace App\Http\Concerns;

use App\Models\Document;
use App\Models\Template;
use App\Services\Contracts\DocumentServiceInterface;
use App\Services\Contracts\TemplateServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Adjunta capacidades de UI derivadas de policy (`can_clone`, `can_view_history`,
 * `can_create_new_version`) antes de serializar Resources.
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

        $attach = function (Document|Template $resource) use ($user): void {
            $gate = Gate::forUser($user);
            $resource->setAttribute('can_clone', $gate->allows('clone', $resource));
            $resource->setAttribute('can_view_history', $gate->allows('viewHistory', $resource));
            $resource->setAttribute(
                'can_create_new_version',
                $this->resourceHasPublishedSnapshot($resource)
                    && $gate->allows('attemptStartRevision', $resource)
                    && ! $gate->allows('discard', $resource),
            );
        };

        if ($resources instanceof Document || $resources instanceof Template) {
            $attach($resources);

            return;
        }

        foreach ($resources as $resource) {
            $attach($resource);
        }
    }

    private function resourceHasPublishedSnapshot(Document|Template $resource): bool
    {
        $publishedId = $resource->getAttribute('latest_published_version_id');
        if (is_string($publishedId) && $publishedId !== '') {
            return true;
        }

        $id = (string) $resource->getKey();
        if ($id === '') {
            return false;
        }

        if ($resource instanceof Template) {
            return app(TemplateServiceInterface::class)->hasPublishedSnapshot($id);
        }

        return app(DocumentServiceInterface::class)->hasPublishedSnapshot($id);
    }
}
