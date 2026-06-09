<?php

declare(strict_types=1);

namespace App\DTOs\Processes;

use App\Models\Process;

final readonly class ProcessDto
{
    public function __construct(
        public string $id,
        public string $code,
        public string $name,
        public string $alias,
        public ?string $icon,
        public ?string $color,
        public ?string $description,
        public ?string $processParentId,
    ) {}

    public static function fromModel(Process $m): self
    {
        return new self(
            id: (string) $m->id,
            code: (string) $m->code,
            name: (string) $m->name,
            alias: (string) $m->alias,
            icon: $m->icon,
            color: $m->color,
            description: $m->description,
            processParentId: $m->process_parent_id,
        );
    }
}
