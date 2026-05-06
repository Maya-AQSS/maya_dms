<?php

namespace App\Repositories\Contracts;

use App\Models\TemplateVersion;
use Illuminate\Support\Collection;

interface TemplateVersionRepositoryInterface
{
    public function findOrFail(string $id): TemplateVersion;

    /**
     * Última versión publicada de la plantilla (mayor {@see TemplateVersion::$version_number}), o null.
     */
    public function findLatestPublishedForTemplate(string $templateId): ?TemplateVersion;

    /**
     * Fila legacy por plantilla y número de versión publicada.
     */
    public function findByTemplateIdAndVersionNumber(string $templateId, int $versionNumber): ?TemplateVersion;

    /**
     * Metadatos de una versión publicada por id (sin cargar blocks_snapshot).
     *
     * @return array{id: string, version_number: int, changelog: string}|null
     */
    public function findPublishedMetaById(string $versionId): ?array;

    /**
     * Metadatos de la versión publicada más reciente de la plantilla (sin blocks_snapshot).
     *
     * @return array{id: string, version_number: int, changelog: string}|null
     */
    public function findLatestPublishedMetaForTemplate(string $templateId): ?array;

    /**
     * Lista todas las versiones de una plantilla ordenadas por número de versión.
     *
     * @return Collection<int, TemplateVersion>
     */
    public function listForTemplateOrdered(string $templateId): Collection;

    /**
     * Obtiene el próximo número de versión para una plantilla.
     */
    public function nextVersionNumber(string $templateId): int;

    /**
     * Crea un snapshot de una plantilla.
     *
     * @param  array<int, array<string, mixed>>  $blocksSnapshot
     */
    public function createSnapshot(
        string $templateId,
        int $versionNumber,
        array $blocksSnapshot,
        string $changelog,
        string $publishedBy,
    ): TemplateVersion;
}
