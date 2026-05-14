<?php

declare(strict_types=1);

namespace App\DTOs\Refs;

final readonly class UserRefDto
{
    public function __construct(
        public string $id,
        public ?string $name,
    ) {}
}
