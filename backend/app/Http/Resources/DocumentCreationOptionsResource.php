<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wrapper resource for document creation options response.
 * Represents available templates that can be used to create a document.
 *
 * @property array{
 *   can_create: bool,
 *   mode: string,
 *   message: string|null,
 *   options: array<int, array{
 *     template_id: string,
 *     template_version_id: string,
 *     process_id: string,
 *     name: string,
 *     description: string|null,
 *     visibility_level: string,
 *     team_id: string|null,
 *     team_name: string|null
 *   }>
 * } $resource
 */
class DocumentCreationOptionsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
