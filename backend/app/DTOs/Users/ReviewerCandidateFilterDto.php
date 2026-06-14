<?php

declare(strict_types=1);

namespace App\DTOs\Users;

/**
 * Contexto académico opcional para acotar candidatos a validador en el directorio.
 */
final readonly class ReviewerCandidateFilterDto
{
    public function __construct(
        public ?string $visibilityLevel,
        public ?string $studyTypeId,
        public ?string $studyId,
        public ?string $moduleId,
        public ?string $teamId,
    ) {}

    /**
     * Construye el filtro a partir de los datos ya validados por el FormRequest.
     *
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            visibilityLevel: self::nullableTrimmed($validated['visibility_level'] ?? null),
            studyTypeId: self::nullableTrimmed($validated['study_type_id'] ?? null),
            studyId: self::nullableTrimmed($validated['study_id'] ?? null),
            moduleId: self::nullableTrimmed($validated['module_id'] ?? null),
            teamId: self::nullableTrimmed($validated['team_id'] ?? null),
        );
    }

    private static function nullableTrimmed(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
