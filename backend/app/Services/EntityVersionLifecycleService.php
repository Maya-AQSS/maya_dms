<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Versioning\EntityVersionDto;
use App\Repositories\Contracts\EntityVersionRepositoryInterface;
use App\Services\Contracts\EntityVersionLifecycleServiceInterface;
use Illuminate\Validation\ValidationException;

class EntityVersionLifecycleService implements EntityVersionLifecycleServiceInterface
{
    public function __construct(
        private readonly EntityVersionRepositoryInterface $entityVersionRepository,
    ) {}

    /**
     * Publica una versión con snapshot obligatorio e inmutable.
     */
    public function publish(
        string $versionId,
        array $snapshotData,
        string $actorId,
        ?string $changelog = null,
    ): EntityVersionDto {
        return $this->entityVersionRepository->transaction(function () use ($versionId, $snapshotData, $actorId, $changelog) {
            $version = $this->entityVersionRepository->findOrFailForUpdate($versionId);

            if (! in_array($version->status, ['draft', 'in_review'], true)) {
                throw ValidationException::withMessages([
                    'status' => [__('validation.version.publish_state')],
                ]);
            }

            if ($version->is_snapshot_immutable) {
                throw ValidationException::withMessages([
                    'snapshot_data' => [__('validation.version.already_snapshot')],
                ]);
            }

            $this->assertSnapshotNotEmpty($snapshotData);

            $resolvedChangelog = $this->normalizeChangelog($changelog);

            return EntityVersionDto::fromModel($this->entityVersionRepository->update($version, [
                'status' => 'published',
                'snapshot_data' => $snapshotData,
                'is_snapshot_immutable' => true,
                'published_by' => $actorId,
                'published_at' => now(),
                'changelog' => $resolvedChangelog,
            ]));
        });
    }

    /**
     * Crea una nueva versión publicada inmutable para una entidad versionable.
     */
    public function createPublishedSnapshotVersion(
        string $versionableType,
        string $versionableId,
        int $versionNumber,
        array $snapshotData,
        string $actorId,
        ?string $changelog = null,
    ): EntityVersionDto {
        if ($versionNumber < 1) {
            throw ValidationException::withMessages([
                'version_number' => [__('validation.version.number_min')],
            ]);
        }

        $this->assertSnapshotNotEmpty($snapshotData);

        $resolvedChangelog = $this->normalizeChangelog($changelog);

        return $this->entityVersionRepository->transaction(function () use (
            $versionableType,
            $versionableId,
            $versionNumber,
            $snapshotData,
            $actorId,
            $resolvedChangelog
        ) {
            $baseVersion = $this->entityVersionRepository->findLatestPublishedForEntity($versionableType, $versionableId);

            return EntityVersionDto::fromModel($this->entityVersionRepository->create([
                'versionable_type' => $versionableType,
                'versionable_id' => $versionableId,
                'version_number' => $versionNumber,
                'base_version_id' => $baseVersion?->id,
                'change_set' => null,
                'status' => 'published',
                'created_by' => $actorId,
                'published_by' => $actorId,
                'published_at' => now(),
                'changelog' => $resolvedChangelog,
                'snapshot_data' => $snapshotData,
                'is_snapshot_immutable' => true,
            ]));
        });
    }

    /**
     * Normaliza el changelog: trim y los vacíos pasan a null.
     */
    private function normalizeChangelog(?string $changelog): ?string
    {
        $resolved = is_string($changelog) ? trim($changelog) : null;

        return $resolved === '' ? null : $resolved;
    }

    /**
     * El snapshot de publicación es obligatorio e inmutable: nunca vacío.
     */
    private function assertSnapshotNotEmpty(array $snapshotData): void
    {
        if ($snapshotData === []) {
            throw ValidationException::withMessages([
                'snapshot_data' => [__('validation.version.snapshot_required')],
            ]);
        }
    }
}
