<?php

declare(strict_types=1);

namespace App\DTOs\Documents;

/**
 * Actualización in-situ de un documento (en ciclo de nueva versión) a una versión
 * de plantilla más reciente: re-ancla a {@see $targetTemplateVersionId} y reconcilia
 * los bloques con el contenido ya resuelto en cliente.
 */
readonly class ApplyTemplateMigrationDto
{
    /**
     * @param  array<string, mixed>  $migratedBlockContent  Contenido final por template_block_id
     *                                                       (replace/append ya resuelto en cliente).
     *                                                       Los bloques `locked` ignoran este override.
     * @param  array<string, string>  $removedBlockActions   Acción por template_block_id eliminado
     *                                                        en la versión nueva: 'delete' | 'keep'.
     */
    public function __construct(
        public string $documentId,
        public string $actorId,
        public string $targetTemplateVersionId,
        public array $migratedBlockContent = [],
        public array $removedBlockActions = [],
    ) {}
}
