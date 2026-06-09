<?php

declare(strict_types=1);

namespace App\DTOs\Documents;

/**
 * Alta de documento anclado a una versión publicada de plantilla.
 */
readonly class CreateDocumentDto
{
    /**
     * @param  array<string, mixed>|null  $migratedBlockContent  Contenido a precargar por template_block_id
     *                                                           (paso de migración del wizard). Los bloques
     *                                                           `locked` ignoran este override.
     */
    public function __construct(
        public string $templateId,
        public string $title,
        public string $createdBy,
        public string $ownerId,
        public string $processId,
        public ?string $studyTypeId = null,
        public ?string $studyId = null,
        public ?string $moduleId = null,
        public ?string $teamId = null,
        public ?string $deliveryDeadline = null,
        public ?string $templateVersionId = null,
        public ?array $migratedBlockContent = null,
    ) {}
}
