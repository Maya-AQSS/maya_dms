<?php

namespace App\DTOs\Groups;

/**
 * Campos omitidos (null) no se actualizan.
 */
readonly class UpdateGroupDto
{
    public function __construct(
        public ?string $name = null,
        public ?string $description = null,
    ) {}
}
