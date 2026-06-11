<?php

declare(strict_types=1);

namespace App\DTOs\Documents;

use JsonSerializable;

/**
 * Una opción de creación de documento disponible para un módulo.
 * Devuelto por DocumentService::creationOptionsForModule() como list<self>.
 */
final readonly class CreationOptionDto implements JsonSerializable
{
    public function __construct(
        public string $templateId,
        public string $templateVersionId,
        public string $processId,
        public string $name,
        public ?string $description,
        public string $visibilityLevel,
        public ?string $teamId,
        public ?string $teamName,
    ) {}

    /**
     * @return array{
     *   template_id: string,
     *   template_version_id: string,
     *   process_id: string,
     *   name: string,
     *   description: string|null,
     *   visibility_level: string,
     *   team_id: string|null,
     *   team_name: string|null,
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'template_id' => $this->templateId,
            'template_version_id' => $this->templateVersionId,
            'process_id' => $this->processId,
            'name' => $this->name,
            'description' => $this->description,
            'visibility_level' => $this->visibilityLevel,
            'team_id' => $this->teamId,
            'team_name' => $this->teamName,
        ];
    }
}
