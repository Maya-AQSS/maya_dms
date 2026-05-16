<?php
declare(strict_types=1);

namespace App\DTOs\Documents;

/**
 * Parámetros para crear un snapshot append-only en document_versions.
 */
readonly class CreateDocumentSnapshotDto
{
    public function __construct(
        public string $documentId,
        public string $triggerEvent,
        public string $triggeredBy,
        public string $notes,
    ) {}
}
