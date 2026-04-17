<?php

namespace App\DTOs\Templates;

/**
 * Filtros del listado de plantillas (query string).
 */
readonly class FilterTemplatesDto
{
    public function __construct(
        public ?string $visibilityLevel = null,
        public ?string $status = null,
        public ?string $studyTypeId = null,
        public ?string $studyId = null,
        public ?string $moduleId = null,
        public ?string $teamId = null,
    ) {}
}
