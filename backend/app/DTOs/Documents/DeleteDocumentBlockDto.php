<?php

namespace App\DTOs\Documents;

readonly class DeleteDocumentBlockDto
{
    public function __construct(
        public string $documentId,
        public string $documentBlockId,
        public string $actorId,
    ) {}
}
