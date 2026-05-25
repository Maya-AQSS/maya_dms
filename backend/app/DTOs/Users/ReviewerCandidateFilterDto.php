<?php

declare(strict_types=1);

namespace App\DTOs\Users;

use Illuminate\Http\Request;

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

    public static function fromRequest(Request $request): self
    {
        return new self(
            visibilityLevel: self::nullableTrimmed($request->query('visibility_level')),
            studyTypeId: self::nullableTrimmed($request->query('study_type_id')),
            studyId: self::nullableTrimmed($request->query('study_id')),
            moduleId: self::nullableTrimmed($request->query('module_id')),
            teamId: self::nullableTrimmed($request->query('team_id')),
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
