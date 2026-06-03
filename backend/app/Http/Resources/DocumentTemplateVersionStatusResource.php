<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wrapper resource for template version status response.
 * Indicates if a newer published template version is available.
 *
 * @property array{
 *   current_version: array{id: string, version_number: int}|null,
 *   latest_version: array{id: string, version_number: int, changelog: string}|null,
 *   has_update: bool,
 *   changelog: string|null
 * } $resource
 */
class DocumentTemplateVersionStatusResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
