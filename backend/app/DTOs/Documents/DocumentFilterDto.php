<?php

declare(strict_types=1);

namespace App\DTOs\Documents;

use Maya\Http\Data\FilterDto;

/**
 * Filtros del listado paginado de documentos (query string).
 *
 * Extiende FilterDto (shared-http-laravel) añadiendo los criterios
 * propios del dominio DMS: proceso, estado, plantilla, creador y rango de fechas.
 */
final readonly class DocumentFilterDto extends FilterDto
{
    public function __construct(
        public readonly ?string $processId = null,
        public readonly ?string $status = null,
        public readonly ?string $templateId = null,
        public readonly ?string $createdBy = null,
        public readonly ?string $from = null,
        public readonly ?string $to = null,
        /** @var list<string>|null Ids de documentos marcados como favoritos por el usuario. */
        public readonly ?array $favoriteIds = null,
        // Contexto académico (snapshot del cabezal): filtro estructurado en cascada o unión.
        public readonly ?string $studyTypeId = null,
        public readonly ?string $studyId = null,
        public readonly ?string $moduleId = null,
        /** @var list<string>|null */
        public readonly ?array $studyTypeIds = null,
        /** @var list<string>|null */
        public readonly ?array $studyIds = null,
        /** @var list<string>|null */
        public readonly ?array $moduleIds = null,
        /** Preselección server-side desde el perfil académico del usuario (/me). */
        public readonly bool $profileAcademicDefault = false,
        public readonly ?string $teamId = null,
        int $page = 1,
        int $perPage = 15,
        ?string $sortBy = 'updated_at',
        string $sortDir = 'desc',
        ?string $search = null,
    ) {
        parent::__construct($page, $perPage, $sortBy, $sortDir, $search);
    }
}
