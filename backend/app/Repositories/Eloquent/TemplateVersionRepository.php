<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\EntityVersion;
use App\Models\Template;
use App\Repositories\Contracts\EntityVersionRepositoryInterface;
use App\Repositories\Contracts\TemplateVersionRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

class TemplateVersionRepository implements TemplateVersionRepositoryInterface
{
    public function __construct(
        private readonly EntityVersionRepositoryInterface $entityVersionRepository,
    ) {}

    public function findOrFail(string $id): EntityVersion
    {
        $ev = $this->entityVersionRepository->findOrFail($id);
        $this->assertPublishedTemplateSnapshot($ev);

        return $ev;
    }

    public function findOptional(string $id): ?EntityVersion
    {
        return $this->entityVersionRepository->findPublishedByIdAndType($id, Template::class);
    }

    public function findLatestPublishedForTemplate(string $templateId): ?EntityVersion
    {
        return $this->entityVersionRepository->findLatestPublishedForEntity(Template::class, $templateId);
    }

    public function findByTemplateIdAndVersionNumber(string $templateId, int $versionNumber): ?EntityVersion
    {
        return $this->entityVersionRepository->findPublishedForEntityVersionNumber(
            Template::class,
            $templateId,
            $versionNumber,
        );
    }

    public function findPublishedMetaById(string $versionId): ?array
    {
        $ev = $this->entityVersionRepository->findPublishedByIdAndType($versionId, Template::class);

        if ($ev === null) {
            return null;
        }

        return [
            'id' => (string) $ev->id,
            'version_number' => (int) $ev->version_number,
            'changelog' => (string) ($ev->changelog ?? ''),
        ];
    }

    public function findLatestPublishedMetaForTemplate(string $templateId): ?array
    {
        return $this->entityVersionRepository->findLatestPublishedMetaForVersionable(Template::class, $templateId);
    }

    public function listForTemplateOrdered(string $templateId): Collection
    {
        return $this->entityVersionRepository->listPublishedForEntityOrdered(Template::class, $templateId);
    }

    public function nextVersionNumber(string $templateId): int
    {
        return $this->entityVersionRepository->nextVersionNumber(Template::class, $templateId);
    }

    private function assertPublishedTemplateSnapshot(EntityVersion $ev): void
    {
        if ($ev->versionable_type !== Template::class || $ev->status !== 'published') {
            throw (new ModelNotFoundException)->setModel(EntityVersion::class, [$ev->id]);
        }
    }
}
