<?php

declare(strict_types=1);

namespace App\DTOs\Versioning;

/**
 * Detalle completo de una versión de documento con snapshot.
 * Devuelto por DocumentVersionService::findDocumentVersionDetailOrFail().
 */
final readonly class DocumentVersionDetailDto
{
    /**
     * @param  array<string, mixed>  $snapshotData
     * @param  list<string>          $reviewerNames
     */
    public function __construct(
        public string $id,
        public string $documentId,
        public int $versionNumber,
        public string $triggerEvent,
        public string $triggeredBy,
        public ?string $publishedByName,
        public ?string $authorName,
        public ?string $ownerName,
        public array $reviewerNames,
        public ?string $changelog,
        public array $snapshotData,
        public ?string $createdAt,
    ) {}
}
