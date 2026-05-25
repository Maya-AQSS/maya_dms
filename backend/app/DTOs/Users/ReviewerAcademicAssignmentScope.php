<?php

declare(strict_types=1);

namespace App\DTOs\Users;

/**
 * Ámbito académico resuelto para filtrar validadores: un candidato es elegible si
 * tiene asignación en cualquiera de los IDs listados (OR), sin inclusión hacia abajo.
 */
final readonly class ReviewerAcademicAssignmentScope
{
    /**
     * @param  list<string>  $moduleIds
     * @param  list<string>  $studyIds
     * @param  list<string>  $studyTypeIds
     * @param  list<string>  $teamIds
     */
    public function __construct(
        public array $moduleIds = [],
        public array $studyIds = [],
        public array $studyTypeIds = [],
        public array $teamIds = [],
    ) {}

    public function matchesNothing(): bool
    {
        return $this->moduleIds === []
            && $this->studyIds === []
            && $this->studyTypeIds === []
            && $this->teamIds === [];
    }
}
