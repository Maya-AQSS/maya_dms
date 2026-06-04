<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\Documents\BlockDisplayDto;
use App\DTOs\Documents\DocumentDto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wrapper resource for createFromModule endpoint response.
 * Combines DocumentResource data with blocks array.
 *
 * @property array{
 *   document: DocumentDto,
 *   blocks: list<BlockDisplayDto>
 * } $resource
 */
class DocumentCreateFromModuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var list<BlockDisplayDto> $blocks */
        $blocks = $this->resource['blocks'];

        return array_merge(
            (new DocumentResource($this->resource['document']))->toArray($request),
            ['blocks' => DocumentBlockResource::resolveDisplayList($request, $blocks)],
        );
    }
}
