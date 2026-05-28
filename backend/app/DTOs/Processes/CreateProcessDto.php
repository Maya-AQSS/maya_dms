<?php

declare(strict_types=1);

namespace App\DTOs\Processes;

readonly class CreateProcessDto
{
    public function __construct(
        public string $code,
        public string $name,
        public string $alias,
        public ?string $description,
        public ?string $processParentId,
        public ?string $color = null,
        public ?string $icon = null,
    ) {}
}
