<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\DocumentBlock;
use App\Models\TemplateBlock;
use App\Repositories\Contracts\DocumentBlockRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class DocumentBlockRepository implements DocumentBlockRepositoryInterface
{
    public function findInDocumentOrFail(string $blockId, string $documentId): DocumentBlock
    {
        return DocumentBlock::query()
            ->with('templateBlock')
            ->where('id', $blockId)
            ->where('document_id', $documentId)
            ->firstOrFail();
    }

    public function listByDocumentKeyedByTemplateBlock(string $documentId): Collection
    {
        return DocumentBlock::query()
            ->where('document_id', $documentId)
            ->get()
            ->keyBy('template_block_id');
    }

    public function create(array $attributes): DocumentBlock
    {
        return DocumentBlock::query()->create($attributes);
    }

    public function insertDocumentBlock(array $attributes): DocumentBlock
    {
        $documentId = (string) $attributes['document_id'];
        $templateBlockId = (string) $attributes['template_block_id'];

        // La unique (document_id, template_block_id) NO excluye soft-deletes: si existe una
        // fila (incluso borrada) para ese par, la restauramos/reutilizamos en vez de insertar.
        $block = DocumentBlock::withTrashed()
            ->where('document_id', $documentId)
            ->where('template_block_id', $templateBlockId)
            ->first();

        if ($block === null) {
            $block = new DocumentBlock;
            $block->setAttribute('id', (string) Str::uuid());
            $block->document_id = $documentId;
            $block->template_block_id = $templateBlockId;
        } elseif ($block->trashed()) {
            $block->restore();
        }

        $block->content = $attributes['content'] ?? null; // cast 'array' → JSON
        $block->sort_order = (int) ($attributes['sort_order'] ?? 0);
        $block->is_filled = (bool) ($attributes['is_filled'] ?? false);
        $block->last_edited_by = $attributes['last_edited_by'] ?? null;
        $block->save();

        return $block;
    }

    public function deleteAllForDocument(string $documentId): int
    {
        return DocumentBlock::query()->where('document_id', $documentId)->delete();
    }

    public function deleteForDocumentExceptTemplateBlocks(string $documentId, array $keepTemplateBlockIds): int
    {
        return DocumentBlock::query()
            ->where('document_id', $documentId)
            ->whereNotIn('template_block_id', $keepTemplateBlockIds)
            ->delete();
    }

    public function findTemplateBlockIdsInUseByTemplate(string $templateId): array
    {
        return DocumentBlock::query()
            ->whereIn('template_block_id', function ($query) use ($templateId): void {
                $query->select('id')
                    ->from((new TemplateBlock)->getTable())
                    ->where('template_id', $templateId);
            })
            ->pluck('template_block_id')
            ->map(static fn ($id): string => (string) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Obtiene los bloques de un documento con sus relaciones.
     *
     * @return Collection<string, DocumentBlock>
     */
    public function findBlocksForDocumentWithRelations(string $documentId): Collection
    {
        return DocumentBlock::query()
            ->where('document_id', $documentId)
            ->with('templateBlock')
            ->get()
            ->keyBy('template_block_id');
    }

    /**
     * Actualiza el contenido y estado de un bloque del documento.
     *
     * @param  DocumentBlock  $block  El bloque a actualizar (se modifica en-place).
     * @param  mixed  $content  Nuevo contenido.
     * @param  bool  $isFilled  Indicador de relleno.
     * @param  string  $lastEditedBy  ID del actor que editó.
     */
    public function updateBlock(DocumentBlock $block, mixed $content, bool $isFilled, string $lastEditedBy): void
    {
        $block->content = $content;
        $block->is_filled = $isFilled;
        $block->last_edited_by = $lastEditedBy;
        $block->save();
    }

    /**
     * Elimina un bloque del documento.
     */
    public function deleteBlock(DocumentBlock $block): void
    {
        $block->delete();
    }
}
