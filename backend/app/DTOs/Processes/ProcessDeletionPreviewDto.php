<?php

declare(strict_types=1);

namespace App\DTOs\Processes;

/**
 * Conteo de dependientes afectados por el borrado de un proceso.
 * Vista de valor que devuelve el Service al Controller para confirmar la acción.
 */
final class ProcessDeletionPreviewDto
{
    public function __construct(
        public readonly int $templatesCount,
        public readonly int $documentsCount,
        public readonly int $subprocessCount,
    ) {}

    /**
     * @param  array{templates_count: int, documents_count: int, subprocess_count: int}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            templatesCount: (int) ($data['templates_count'] ?? 0),
            documentsCount: (int) ($data['documents_count'] ?? 0),
            subprocessCount: (int) ($data['subprocess_count'] ?? 0),
        );
    }

    /**
     * @return array{templates_count: int, documents_count: int, subprocess_count: int}
     */
    public function toArray(): array
    {
        return [
            'templates_count' => $this->templatesCount,
            'documents_count' => $this->documentsCount,
            'subprocess_count' => $this->subprocessCount,
        ];
    }
}
