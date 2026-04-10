<?php

namespace App\DTOs\Groups;

/**
 * name: null = no actualizar.
 * description: solo se escribe si setDescription es true (permite null explícito).
 */
readonly class UpdateGroupDto
{
    public function __construct(
        public ?string $name = null,
        public ?string $description = null,
        public bool $setDescription = false,
    ) {}
}
