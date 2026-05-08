<?php

namespace App\Repositories\Eloquent;

use App\Models\EntityVersion;
use App\Repositories\Contracts\EntityVersionRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EntityVersionRepository implements EntityVersionRepositoryInterface
{
    /**
     * Obtiene una versión por su id.
     *
     * @param string $id El id de la versión.
     * @return EntityVersion La versión encontrada.
     * @throws EntityVersionNotFoundException Si la versión no existe.
     */
    public function findOrFail(string $id): EntityVersion
    {
        return EntityVersion::query()->findOrFail($id);
    }

    /**
     * Obtiene una versión por su id para actualización.
     *
     * @param string $id El id de la versión.
     * @return EntityVersion La versión encontrada.
     * @throws EntityVersionNotFoundException Si la versión no existe.
     */
    public function findOrFailForUpdate(string $id): EntityVersion
    {
        return EntityVersion::query()->whereKey($id)->lockForUpdate()->firstOrFail();
    }

    /**
     * Obtiene el próximo número de versión para un tipo y id de versionable.
     *
     * @param string $versionableType El tipo de versionable.
     * @param string $versionableId El id de la versionable.
     * @return int El próximo número de versión.
     */
    public function nextVersionNumber(string $versionableType, string $versionableId): int
    {
        $max = EntityVersion::query()
            ->where('versionable_type', $versionableType)
            ->where('versionable_id', $versionableId)
            ->max('version_number');

        return (int) $max + 1;
    }

    /**
     * Obtiene la última versión publicada para una entidad.
     *
     * @param string $versionableType El tipo de versionable.
     * @param string $versionableId El id de la versionable.
     * @return ?EntityVersion La versión encontrada o null si no existe.
     */
    public function findLatestPublishedForEntity(string $versionableType, string $versionableId): ?EntityVersion
    {
        return EntityVersion::query()
            ->where('versionable_type', $versionableType)
            ->where('versionable_id', $versionableId)
            ->where('status', 'published')
            ->where('version_number', '>', 0)
            ->orderByDesc('version_number')
            ->orderByDesc('created_at')
            ->first();
    }

    /**
     * {@inheritdoc}
     */
    public function findLatestPublishedMetaForVersionable(string $versionableType, string $versionableId): ?array
    {
        $row = $this->findLatestPublishedForEntity($versionableType, $versionableId);
        if ($row === null) {
            return null;
        }

        return [
            'id' => (string) $row->id,
            'version_number' => (int) $row->version_number,
            'changelog' => (string) ($row->changelog ?? ''),
        ];
    }

    /**
     * Obtiene una versión publicada concreta por número de versión.
     *
     * @param string $versionableType El tipo de versionable.
     * @param string $versionableId El id de la versionable.
     * @param int $versionNumber El número de versión.
     * @return ?EntityVersion La versión encontrada o null si no existe.
     */
    public function findPublishedForEntityVersionNumber(
        string $versionableType,
        string $versionableId,
        int $versionNumber,
    ): ?EntityVersion {
        return EntityVersion::query()
            ->where('versionable_type', $versionableType)
            ->where('versionable_id', $versionableId)
            ->where('status', 'published')
            ->where('version_number', $versionNumber)
            ->first();
    }

    /**
     * Lista versiones publicadas de una entidad ordenadas por número de versión.
     *
     * @return Collection<int, EntityVersion>
     */
    public function listPublishedForEntityOrdered(string $versionableType, string $versionableId): Collection
    {
        return EntityVersion::query()
            ->where('versionable_type', $versionableType)
            ->where('versionable_id', $versionableId)
            ->where('status', 'published')
            ->where('version_number', '>', 0)
            ->orderBy('version_number')
            ->get();
    }

    /**
     * Metadatos de una versión publicada por id de entity_versions (consulta ligera).
     *
     * @return array{id: string, version_number: int, changelog: string}|null
     */
    public function findPublishedMetaByIdForVersionable(
        string $entityVersionId,
        string $versionableType,
        string $versionableId,
    ): ?array {
        $row = EntityVersion::query()
            ->whereKey($entityVersionId)
            ->where('versionable_type', $versionableType)
            ->where('versionable_id', $versionableId)
            ->where('status', 'published')
            ->first();

        if ($row === null) {
            return null;
        }

        return [
            'id' => (string) $row->id,
            'version_number' => (int) $row->version_number,
            'changelog' => (string) ($row->changelog ?? ''),
        ];
    }

    /**
     * Versión publicada por id de entity_versions (incluye snapshot_data).
     */
    public function findPublishedByIdForVersionable(
        string $entityVersionId,
        string $versionableType,
        string $versionableId,
    ): ?EntityVersion {
        return EntityVersion::query()
            ->whereKey($entityVersionId)
            ->where('versionable_type', $versionableType)
            ->where('versionable_id', $versionableId)
            ->where('status', 'published')
            ->first();
    }

    /**
     * Crea una nueva versión.
     *
     * @param array<string, mixed> $attributes Los atributos de la versión.
     * @return EntityVersion La versión creada.
     */
    public function create(array $attributes): EntityVersion
    {
        return EntityVersion::query()->create($attributes);
    }

    /**
     * Actualiza una versión.
     *
     * @param EntityVersion $version La versión a actualizar.
     * @param array<string, mixed> $attributes Los atributos de la versión.
     * @return EntityVersion La versión actualizada.
     */
    public function update(EntityVersion $version, array $attributes): EntityVersion
    {
        if ($attributes !== []) {
            $version->update($attributes);
        }

        return $version->fresh();
    }

    /**
     * Ejecuta una transacción.
     *
     * @param callable $callback La función a ejecutar en la transacción.
     * @return mixed El resultado de la función.
     */
    public function transaction(callable $callback): mixed
    {
        return DB::transaction($callback);
    }
}
