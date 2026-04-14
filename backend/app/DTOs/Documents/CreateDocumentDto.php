<?php

namespace App\DTOs\Documents;

/**
 * Alta de documento anclado a una versión publicada de plantilla.
 */
readonly class CreateDocumentDto
{
    public function __construct(
        public string $templateId,
        public string $title,
        public string $organizationId,
        public string $createdBy,
        public string $ownerId,
        public ?string $studyId = null,
        public ?string $moduleId = null,
        public ?string $templateVersionId = null,
    ) {}
}
