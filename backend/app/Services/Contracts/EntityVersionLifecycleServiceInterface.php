<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\DTOs\Versioning\EntityVersionDto;

interface EntityVersionLifecycleServiceInterface
{
    /**
     * Publica una versión con snapshot obligatorio e inmutable.
     *
     * @param  array<string, mixed>  $snapshotData
     */
    public function publish(
        string $versionId,
        array $snapshotData,
        string $actorId,
        ?string $changelog = null,
    ): EntityVersionDto;

    /**
     * Crea una nueva versión publicada inmutable para una entidad versionable.
     *
     * @param  array<string, mixed>  $snapshotData
     */
    public function createPublishedSnapshotVersion(
        string $versionableType,
        string $versionableId,
        int $versionNumber,
        array $snapshotData,
        string $actorId,
        ?string $changelog = null,
    ): EntityVersionDto;
}
