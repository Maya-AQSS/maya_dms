<?php

declare(strict_types=1);

namespace App\DTOs\Templates;

use Maya\Http\Data\FilterDto;

/**
 * Filtros del listado paginado de plantillas (query string).
 *
 * Extiende FilterDto (shared-http-laravel) añadiendo los criterios
 * propios del dominio DMS: proceso, estado, nivel de visibilidad,
 * ámbito académico y uso para documentos.
 */
final readonly class TemplateFilterDto extends FilterDto
{
    public function __construct(
        public readonly ?string $processId = null,
        public readonly ?string $status = null,
        public readonly ?string $visibilityLevel = null,
        public readonly ?string $studyTypeId = null,
        public readonly ?string $studyId = null,
        public readonly ?string $moduleId = null,
        public readonly ?string $teamId = null,
        public readonly bool $usableForDocuments = false,
        /** @var list<string>|null Ids de versión (head o publicada) marcadas como favoritas por el usuario. */
        public readonly ?array $favoriteIds = null,
        int $page = 1,
        int $perPage = 15,
        ?string $sortBy = 'updated_at',
        string $sortDir = 'desc',
        ?string $search = null,
    ) {
        parent::__construct($page, $perPage, $sortBy, $sortDir, $search);
    }
}
