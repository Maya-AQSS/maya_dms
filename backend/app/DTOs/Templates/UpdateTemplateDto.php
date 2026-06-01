<?php

declare(strict_types=1);

namespace App\DTOs\Templates;

/**
 * Actualización parcial: cada *set** indica si el campo viene en el body.
 */
readonly class UpdateTemplateDto
{
    public function __construct(
        public ?string $name = null,
        public bool $setName = false,
        public ?string $description = null,
        public bool $setDescription = false,
        public ?string $visibilityLevel = null,
        public bool $setVisibilityLevel = false,
        public ?string $deliveryDeadline = null,
        public bool $setDeliveryDeadline = false,
        public ?string $studyTypeId = null,
        public bool $setStudyTypeId = false,
        public ?string $studyId = null,
        public bool $setStudyId = false,
        public ?string $moduleId = null,
        public bool $setModuleId = false,
        public ?string $teamId = null,
        public bool $setTeamId = false,
        public ?int $reviewStages = null,
        public bool $setReviewStages = false,
        public ?string $reviewMode = null,
        public bool $setReviewMode = false,
        public ?string $documentReviewMode = null,
        public bool $setDocumentReviewMode = false,
        public ?string $themeId = null,
        public bool $setThemeId = false,
        public ?string $createdBy = null,
        public bool $setCreatedBy = false,
    ) {}
}
