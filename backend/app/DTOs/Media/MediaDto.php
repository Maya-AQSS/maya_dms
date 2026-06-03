<?php

declare(strict_types=1);

namespace App\DTOs\Media;

final readonly class MediaDto
{
    public function __construct(
        public string $url,
    ) {}
}
