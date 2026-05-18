<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\EntityVersion;
use Illuminate\Support\Collection;

/**
 * Consultas de publicaciones de plantilla en {@see EntityVersion} (morph Template).
 */
interface TemplateVersionRepositoryInterface
{
    public function findOrFail(string $id): EntityVersion;

    public function findOptional(string $id): ?EntityVersion;

    public function findLatestPublishedForTemplate(string $templateId): ?EntityVersion;

    public function findByTemplateIdAndVersionNumber(string $templateId, int $versionNumber): ?EntityVersion;

    /**
     * @return array{id: string, version_number: int, changelog: string}|null
     */
    public function findPublishedMetaById(string $versionId): ?array;

    /**
     * @return array{id: string, version_number: int, changelog: string}|null
     */
    public function findLatestPublishedMetaForTemplate(string $templateId): ?array;

    /**
     * @return Collection<int, EntityVersion>
     */
    public function listForTemplateOrdered(string $templateId): Collection;

    public function nextVersionNumber(string $templateId): int;
}
