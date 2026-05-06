<?php

namespace App\Services;

use App\Models\Template;
use App\Repositories\Contracts\EntityVersionRepositoryInterface;
use App\Repositories\Contracts\TemplateVersionRepositoryInterface;

/**
 * Expone el número de versión publicada de plantilla anclada a un documento,
 * leyendo solo vía repositorios (legacy primero, luego entity_versions).
 */
final class DocumentTemplateVersionNumberResolver
{
    public function __construct(
        private readonly TemplateVersionRepositoryInterface $templateVersionRepository,
        private readonly EntityVersionRepositoryInterface $entityVersionRepository,
    ) {}

    /**
     * Resuelve el número de versión publicada de plantilla anclada a un documento.
     *
     * @param string|null $templateId ID de la plantilla o null si no hay plantilla.
     * @param string|null $templateVersionId ID de la versión de plantilla o null si no hay versión.
     * @return int|null El número de versión publicada o null si no se puede resolver.
     */
    public function resolve(?string $templateId, ?string $templateVersionId): ?int
    {
        if ($templateVersionId === null || $templateVersionId === '') {
            return null;
        }

        $legacy = $this->templateVersionRepository->findOptional($templateVersionId);
        if ($legacy !== null) {
            return (int) $legacy->version_number;
        }

        if ($templateId === null || $templateId === '') {
            return null;
        }

        $meta = $this->entityVersionRepository->findPublishedMetaByIdForVersionable(
            $templateVersionId,
            Template::class,
            $templateId,
        );

        return $meta !== null ? (int) $meta['version_number'] : null;
    }
}
