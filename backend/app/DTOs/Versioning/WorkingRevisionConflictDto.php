<?php

declare(strict_types=1);

namespace App\DTOs\Versioning;

final readonly class WorkingRevisionConflictDto
{
    public function __construct(
        public bool $inProgress,
        public ?string $editorName = null,
        public ?string $startedAt = null,
    ) {}

    public static function none(): self
    {
        return new self(false);
    }
}
