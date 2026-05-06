<?php

namespace App\Services;

use App\Models\EntityVersion;
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
    ): EntityVersion {
        return $this->entityVersionRepository->transaction(function () use ($versionId, $snapshotData, $actorId, $changelog) {
            $version = $this->entityVersionRepository->findOrFailForUpdate($versionId);

            if (! in_array($version->status, ['draft', 'in_review'], true)) {
                throw ValidationException::withMessages([
                    'status' => ['Solo se puede publicar una versión en borrador o en revisión.'],
                ]);
            }

            if ($version->is_snapshot_immutable) {
                throw ValidationException::withMessages([
                    'snapshot_data' => ['La versión ya tiene un snapshot inmutable publicado.'],
                ]);
            }

            if ($snapshotData === []) {
                throw ValidationException::withMessages([
                    'snapshot_data' => ['El snapshot de publicación es obligatorio.'],
                ]);
            }

            $resolvedChangelog = is_string($changelog) ? trim($changelog) : null;
            if ($resolvedChangelog === '') {
                $resolvedChangelog = null;
            }

            return $this->entityVersionRepository->update($version, [
                'status' => 'published',
                'snapshot_data' => $snapshotData,
                'is_snapshot_immutable' => true,
                'published_by' => $actorId,
                'published_at' => now(),
                'changelog' => $resolvedChangelog,
            ]);
        });
    }
}
