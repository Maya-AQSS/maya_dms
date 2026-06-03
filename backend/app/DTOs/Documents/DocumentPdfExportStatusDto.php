<?php

declare(strict_types=1);

namespace App\DTOs\Documents;

/**
 * DTO for PDF export job tracking state.
 * Represents the status of a document PDF export operation.
 */
final class DocumentPdfExportStatusDto
{
    public function __construct(
        public readonly string $state, // queued | processing | ready | failed | none
        public readonly string $documentId,
        public readonly ?string $path = null,
        public readonly ?string $queuedAt = null,
        public readonly ?string $completedAt = null,
        public readonly ?string $error = null,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            state: (string) ($data['state'] ?? 'none'),
            documentId: (string) ($data['document_id'] ?? ''),
            path: isset($data['path']) ? (string) $data['path'] : null,
            queuedAt: isset($data['queued_at']) ? (string) $data['queued_at'] : null,
            completedAt: isset($data['completed_at']) ? (string) $data['completed_at'] : null,
            error: isset($data['error']) ? (string) $data['error'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'state' => $this->state,
            'document_id' => $this->documentId,
            'path' => $this->path,
            'queued_at' => $this->queuedAt,
            'completed_at' => $this->completedAt,
            'error' => $this->error,
        ];
    }
}
