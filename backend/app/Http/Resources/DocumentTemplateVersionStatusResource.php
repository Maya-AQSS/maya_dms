<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\Documents\TemplateVersionStatusDto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wrapper resource for template version status response.
 * Indicates if a newer published template version is available.
 *
 * @property TemplateVersionStatusDto $resource
 */
class DocumentTemplateVersionStatusResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $resource = $this->resource;

        if ($resource instanceof TemplateVersionStatusDto) {
            return $resource->jsonSerialize();
        }

        /** @var array<string, mixed> $resource */
        return $resource;
    }
}
