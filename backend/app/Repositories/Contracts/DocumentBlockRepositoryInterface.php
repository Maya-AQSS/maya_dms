<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\DocumentBlock;
use Illuminate\Support\Collection;

interface DocumentBlockRepositoryInterface
{
    /**
     * Bloques existentes en el documento agrupados por template_block_id.
     *
     * @return Collection<string, DocumentBlock>
     */
    public function listByDocumentKeyedByTemplateBlock(string $documentId): Collection;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): DocumentBlock;

    /**
     * Elimina todos los bloques del documento (cambio destructivo, ver caller).
     */
    public function deleteAllForDocument(string $documentId): int;

    /**
     * Elimina bloques cuyo template_block_id no esté en la lista provista.
     *
     * @param  list<string>  $keepTemplateBlockIds
     */
    public function deleteForDocumentExceptTemplateBlocks(string $documentId, array $keepTemplateBlockIds): int;

    /**
     * IDs únicos de `template_blocks.id` que están en uso por cualquier
     * `document_blocks.template_block_id` para la plantilla dada. Se usa
     * para proteger bloques referenciados desde la limpieza de plantilla.
     *
     * @return list<string>
     */
    public function findTemplateBlockIdsInUseByTemplate(string $templateId): array;
}
