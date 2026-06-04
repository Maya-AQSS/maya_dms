<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\Documents\BlockDisplayDto;
use App\DTOs\Documents\BlockUpdateDto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use InvalidArgumentException;

/**
 * Serializa bloques de documento desde DTOs de dominio.
 *
 * - {@see BlockDisplayDto}: listados y payloads compuestos (documento + blocks).
 * - {@see BlockUpdateDto}: respuesta de PUT /documents/{id}/blocks/{block}.
 */
class DocumentBlockResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $resource = $this->resource;

        if ($resource instanceof BlockDisplayDto) {
            return $this->fromDisplayDto($resource);
        }

        if ($resource instanceof BlockUpdateDto) {
            return $this->fromUpdateDto($resource);
        }

        throw new InvalidArgumentException(
            'DocumentBlockResource expects BlockDisplayDto or BlockUpdateDto, got '.get_debug_type($resource),
        );
    }

    /**
     * Serializa bloques para respuestas compuestas (p. ej. documento + blocks en store/show).
     *
     * @param  list<BlockDisplayDto>  $blocks
     * @return list<array<string, mixed>>
     */
    public static function resolveDisplayList(Request $request, array $blocks): array
    {
        return array_map(
            fn (BlockDisplayDto $dto): array => (new self($dto))->toArray($request),
            $blocks,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function fromDisplayDto(BlockDisplayDto $dto): array
    {
        return [
            'document_block_id' => $dto->document_block_id,
            'template_block_id' => $dto->template_block_id,
            'type' => $dto->type,
            'title' => $dto->title,
            'description' => $dto->description,
            'default_content' => $dto->default_content,
            'block_state' => $dto->block_state,
            'mandatory' => $dto->mandatory,
            'sort_order' => $dto->sort_order,
            'content' => $dto->content,
            'is_filled' => $dto->is_filled,
            'is_deleted' => $dto->is_deleted,
            'last_edited_by' => null,
            'created_at' => null,
            'updated_at' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fromUpdateDto(BlockUpdateDto $dto): array
    {
        return [
            'document_block_id' => $dto->document_block_id,
            'template_block_id' => $dto->template_block_id,
            'content' => $dto->content,
            'is_filled' => $dto->is_filled,
            'last_edited_by' => $dto->last_edited_by,
            'updated_at' => $dto->updated_at,
        ];
    }
}
