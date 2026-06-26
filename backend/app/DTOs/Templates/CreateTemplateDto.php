<?php

declare(strict_types=1);

namespace App\DTOs\Templates;

readonly class CreateTemplateDto
{
    public function __construct(
        public string $name,
        public ?string $description,
        public string $visibilityLevel,
        public ?string $deliveryDeadline,
        public ?string $documentDeliveryDeadline,
        public ?string $studyTypeId,
        public ?string $studyId,
        public ?string $moduleId,
        public ?string $teamId,
        public int $reviewStages,
        public string $reviewMode,
        public string $processId,
        public ?string $themeId = null,
        public ?string $documentReviewMode = null,
    ) {}
}
