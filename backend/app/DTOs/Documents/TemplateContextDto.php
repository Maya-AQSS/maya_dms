<?php

declare(strict_types=1);

namespace App\DTOs\Documents;

/**
 * Contexto académico/equipo resuelto para un nuevo documento a partir
 * de la visibilidad de la plantilla anclada.
 * Devuelto por TemplateContextResolver::resolve().
 */
final readonly class TemplateContextDto
{
    public function __construct(
        public ?string $studyTypeId,
        public ?string $studyId,
        public ?string $moduleId,
        public ?string $teamId,
    ) {}
}
