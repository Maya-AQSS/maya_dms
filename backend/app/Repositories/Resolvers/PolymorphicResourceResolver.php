<?php

declare(strict_types=1);

namespace App\Repositories\Resolvers;

use App\Exceptions\ResourceNotFoundException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Resolves polymorphic resources given a resource type key and resource ID.
 *
 * Maintains a RESOURCE_MAP allow-list to prevent IDOR attacks by restricting
 * which model classes can be resolved via string keys passed through URLs.
 *
 * Throws domain exceptions (ResourceNotFoundException, ModelNotFoundException).
 * HTTP mapping (404 responses) is the responsibility of the Controller/middleware.
 */
final class PolymorphicResourceResolver
{
    /**
     * Whitelist of polymorphic targets. Extend deliberately — passing an
     * arbitrary class name through the URL would be an IDOR vector.
     *
     * @var array<string, class-string>
     */
    private const RESOURCE_MAP = [
        'template' => \App\Models\Template::class,
        'document' => \App\Models\Document::class,
    ];

    /**
     * Resolve a resource from its type key and ID.
     *
     * @param  string  $resourceType  The resource type key (e.g., 'document', 'template')
     * @param  string  $resourceId    The resource ID (UUID)
     * @return Model                  The resolved Eloquent model
     * @throws ResourceNotFoundException  If the resource type is not allowed
     * @throws ModelNotFoundException     If the resource is not found (domain exception)
     */
    public function resolve(string $resourceType, string $resourceId): Model
    {
        $class = self::RESOURCE_MAP[$resourceType] ?? null;
        if ($class === null) {
            throw ResourceNotFoundException::forUnknownType($resourceType);
        }

        /** @var Model $resource */
        $resource = $class::findOrFail($resourceId);

        return $resource;
    }
}
