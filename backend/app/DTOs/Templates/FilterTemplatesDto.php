<?php

declare(strict_types=1);

namespace App\DTOs\Templates;

/**
 * Filtros del listado de plantillas (query string).
 */
readonly class FilterTemplatesDto
{
    public function __construct(
        public ?string $visibilityLevel = null,
        public ?string $status = null,
        public bool $usableForDocuments = false,
        public ?string $studyTypeId = null,
        public ?string $studyId = null,
        public ?string $moduleId = null,
        public ?string $teamId = null,
        public ?string $authorName = null,
        /** Fecha calendario (Y-m-d): cabezal con `delivery_deadline` en esa fecha o anterior (inclusive). */
        public ?string $deliveryDeadline = null,
        /** Fecha calendario (Y-m-d): última publicación (`max(published_at)`) en ese día o posterior. */
        public ?string $publishedOn = null,
        public ?string $processId = null,
    ) {}
}
