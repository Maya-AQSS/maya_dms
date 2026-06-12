<?php

declare(strict_types=1);

namespace App\DTOs\Documents;

/**
 * Candidato a revisor/validador dentro del pool efectivo de un documento.
 * Item de {@see ReviewerPoolDto::$reviewers}.
 */
final readonly class ReviewerCandidateDto
{
    public function __construct(
        public string $id,
        public ?string $name,
        public ?int $stage,
    ) {}
}
