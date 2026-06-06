<?php

declare(strict_types=1);

namespace App\DTOs\Documents;

/**
 * Payload para el paso de migración del wizard: bloques de la versión nueva de
 * plantilla comparados con la versión anclada al documento origen, más el
 * contenido real del origen para que el docente lo arrastre a la versión nueva.
 */
readonly class DocumentMigrationPayloadDto
{
    /**
     * @param  list<array<string, mixed>>  $blocks
     */
    public function __construct(
        public string $sourceDocumentId,
        public string $sourceTemplateVersionId,
        public int $sourceVersionNumber,
        public string $targetTemplateVersionId,
        public int $targetVersionNumber,
        public array $blocks,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'source_document_id' => $this->sourceDocumentId,
            'source_template_version_id' => $this->sourceTemplateVersionId,
            'source_version_number' => $this->sourceVersionNumber,
            'target_template_version_id' => $this->targetTemplateVersionId,
            'target_version_number' => $this->targetVersionNumber,
            'blocks' => $this->blocks,
        ];
    }
}
