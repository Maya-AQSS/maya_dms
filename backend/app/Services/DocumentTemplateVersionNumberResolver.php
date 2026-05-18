<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Template;
use App\Repositories\Contracts\EntityVersionRepositoryInterface;

/**
 * Resuelve el número de versión publicada a partir del id anclado en {@see EntityVersion}.
 */
final class DocumentTemplateVersionNumberResolver
{
    public function __construct(
        private readonly EntityVersionRepositoryInterface $entityVersionRepository,
    ) {}

    /**
     * Resuelve el número de versión publicada de plantilla anclada a un documento.
     *
     * @param  string|null  $templateId  ID de la plantilla o null si no hay plantilla.
     * @param  string|null  $templateVersionId  ID de la versión de plantilla o null si no hay versión.
     * @return int|null El número de versión publicada o null si no se puede resolver.
     */
    public function resolve(?string $templateId, ?string $templateVersionId): ?int
    {
        if ($templateVersionId === null || $templateVersionId === '') {
            return null;
        }

        if ($templateId !== null && $templateId !== '') {
            $meta = $this->entityVersionRepository->findPublishedMetaByIdForVersionable(
                $templateVersionId,
                Template::class,
                $templateId,
            );
            if ($meta !== null) {
                return (int) $meta['version_number'];
            }
        }

        return null;
    }
}
