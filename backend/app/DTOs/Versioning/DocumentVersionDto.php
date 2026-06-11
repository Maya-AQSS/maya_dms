<?php

declare(strict_types=1);

namespace App\DTOs\Versioning;

/**
 * Metadatos mínimos de una versión de documento (sin snapshot).
 * Devuelto por DocumentVersionService::findDocumentVersionOrFail().
 */
final readonly class DocumentVersionDto
{
    public function __construct(
        public string $id,
        public string $documentId,
        public int $versionNumber,
    ) {}
}
