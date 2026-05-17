<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Services\DocumentBlockService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializa un bloque tal y como lo expone
 * {@see DocumentBlockService::blocksForDisplay()}.
 *
 * El service ya devuelve un array preparado para la vista (mezclando
 * definición del template + estado del documento); este Resource fija
 * el contrato de salida y aporta un único punto donde añadir/renombrar
 * campos públicos.
 */
class DocumentBlockResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $row */
        $row = $this->resource;

        return [
            'document_block_id' => $row['document_block_id'] ?? null,
            'template_block_id' => $row['template_block_id'] ?? null,
            'type' => $row['type'] ?? '',
            'title' => $row['title'] ?? null,
            'description' => $row['description'] ?? null,
            'default_content' => $row['default_content'] ?? null,
            'block_state' => $row['block_state'] ?? null,
            'mandatory' => (bool) ($row['mandatory'] ?? false),
            'content' => $row['content'] ?? null,
            'sort_order' => $row['sort_order'] ?? null,
            'is_filled' => array_key_exists('is_filled', $row) ? (bool) $row['is_filled'] : null,
            'last_edited_by' => $row['last_edited_by'] ?? null,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }
}
