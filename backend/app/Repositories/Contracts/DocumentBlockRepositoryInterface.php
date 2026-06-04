<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\DocumentBlock;
use Illuminate\Support\Collection;

interface DocumentBlockRepositoryInterface
{
    /**
     * Devuelve un bloque concreto del documento, con relaciones
     * `templateBlock` y `document` eager-loaded. Lanza
     * `ModelNotFoundException` si el par (block, document) no existe.
     */
    public function findInDocumentOrFail(string $blockId, string $documentId): DocumentBlock;

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

    /**
     * Obtiene los bloques de un documento con sus relaciones.
     *
     * @return Collection<string, DocumentBlock>
     */
    public function findBlocksForDocumentWithRelations(string $documentId): Collection;

    /**
     * Actualiza el contenido y estado de un bloque del documento.
     *
     * @param  DocumentBlock  $block  El bloque a actualizar (se modifica en-place).
     * @param  mixed  $content  Nuevo contenido.
     * @param  bool  $isFilled  Indicador de relleno.
     * @param  string  $lastEditedBy  ID del actor que editó.
     */
    public function updateBlock(DocumentBlock $block, mixed $content, bool $isFilled, string $lastEditedBy): void;

    /**
     * Elimina un bloque del documento.
     */
    public function deleteBlock(DocumentBlock $block): void;
}
