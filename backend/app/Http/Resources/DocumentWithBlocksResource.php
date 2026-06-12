<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\Documents\BlockDisplayDto;
use App\DTOs\Documents\DocumentDto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Documento + bloques inline para los flujos de creación/clonado/show del
 * wizard (el editor necesita los bloques al instante). Encapsula el
 * `array_merge(DocumentResource, ['blocks' => …])` que se repetía en los
 * controllers; el JSON emitido es idéntico.
 *
 * @property DocumentDto $resource
 */
class DocumentWithBlocksResource extends JsonResource
{
    /**
     * @param  list<BlockDisplayDto>  $blocks
     */
    public function __construct(DocumentDto $document, private readonly array $blocks)
    {
        parent::__construct($document);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return array_merge(
            (new DocumentResource($this->resource))->toArray($request),
            ['blocks' => DocumentBlockResource::resolveDisplayList($request, $this->blocks)],
        );
    }
}
