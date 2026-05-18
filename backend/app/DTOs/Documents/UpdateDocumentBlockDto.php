<?php

declare(strict_types=1);

namespace App\DTOs\Documents;

/**
 * Actualización de contenido de un bloque de documento.
 */
readonly class UpdateDocumentBlockDto
{
    public function __construct(
        public string $documentId,
        public string $documentBlockId,
        public mixed $content,
        public string $actorId,
    ) {}
}
