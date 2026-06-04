<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

/**
 * Thrown when a resource cannot be resolved by type and ID.
 * This is a domain exception, not an HTTP concern.
 * Controllers should catch this and map it to an HTTP 404 response.
 */
final class ResourceNotFoundException extends Exception
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function forUnknownType(string $resourceType): self
    {
        return new self("Resource type '{$resourceType}' is not supported.");
    }

    public static function forMissingResource(string $resourceType, string $resourceId): self
    {
        return new self("Resource of type '{$resourceType}' with ID '{$resourceId}' not found.");
    }
}
