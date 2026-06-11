<?php

declare(strict_types=1);

namespace App\DTOs\Versioning;

/**
 * Metadatos de versión para el listado (sin snapshot completo).
 * Devuelto por DocumentVersionService::listDocumentVersions().
 * Incluye los campos notes/changelog y author/reviewer names para la UI.
 *
 * @phpstan-type VersionSummaryArray array{
 *   id: string,
 *   document_id: string,
 *   version_number: int,
 *   trigger_event: string|null,
 *   triggered_by: string|null,
 *   published_by_name: string|null,
 *   author_name: string|null,
 *   reviewer_names: list<string>,
 *   changelog: string|null,
 *   notes: string|null,
 *   created_at: string|null,
 * }
 */
final readonly class DocumentVersionSummaryDto
{
    /**
     * @param  list<string>  $reviewerNames
     */
    public function __construct(
        public string $id,
        public string $documentId,
        public int $versionNumber,
        public ?string $triggerEvent,
        public ?string $triggeredBy,
        public ?string $publishedByName,
        public ?string $authorName,
        public array $reviewerNames,
        public ?string $changelog,
        public ?string $notes,
        public ?string $createdAt,
    ) {}
}
