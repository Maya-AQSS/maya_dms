<?php

namespace App\DTOs\Templates;

readonly class CreateTemplateDto
{
    public function __construct(
        public string $name,
        public ?string $description,
        public string $visibilityLevel,
        public ?string $deliveryDeadline,
        public ?string $studyTypeId,
        public ?string $studyId,
        public ?string $moduleId,
        public ?string $groupId,
        public ?string $organizationId,
        public int $reviewStages,
        public string $reviewMode,
    ) {}
}
