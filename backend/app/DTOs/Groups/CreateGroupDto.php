<?php

namespace App\DTOs\Groups;

readonly class CreateGroupDto
{
    public function __construct(
        public string $name,
        public ?string $description = null,
    ) {}
}
