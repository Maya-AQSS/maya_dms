<?php
declare(strict_types=1);

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

    public function find(string $id): ?EntityVersion
    {
        return EntityVersion::query()->find($id);
    }

    public function findOrFailPublishedByEntityAndNumber(
        string $versionableType,
        string $versionableId,
        int $versionNumber,
    ): EntityVersion {
        return EntityVersion::query()
            ->where('versionable_type', $versionableType)
            ->where('versionable_id', $versionableId)
            ->where('status', 'published')
            ->where('version_number', $versionNumber)
            ->firstOrFail();
    }

    public function findPublishedByEntityAndNumber(
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

    public function findPublishedByIdAndType(string $entityVersionId, string $versionableType): ?EntityVersion
    {
        return EntityVersion::query()
            ->whereKey($entityVersionId)
            ->where('versionable_type', $versionableType)
            ->where('status', 'published')
            ->first();
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
        // Se ejecuta dentro de transacciones de publicación: bloqueamos la fila más alta
        // de la entidad para serializar el cálculo "max + 1" por (type,id).
        $latest = EntityVersion::query()
            ->where('versionable_type', $versionableType)
            ->where('versionable_id', $versionableId)
            ->orderByDesc('version_number')
            ->lockForUpdate()
            ->first();

        if ($latest === null) {
            return 1;
        }

        return (int) $latest->version_number + 1;
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

    public function findLatestPublishedIdsByVersionables(string $versionableType, array $versionableIds): array
    {
        if ($versionableIds === []) {
            return [];
        }

        $maxVersions = EntityVersion::query()
            ->where('versionable_type', $versionableType)
            ->whereIn('versionable_id', $versionableIds)
            ->where('status', 'published')
            ->where('version_number', '>', 0)
            ->groupBy('versionable_id')
            ->select('versionable_id', DB::raw('MAX(version_number) as max_version'));

        $rows = EntityVersion::query()->from('entity_versions as ev')
            ->joinSub($maxVersions, 'mv', function ($join): void {
                $join->on('ev.versionable_id', '=', 'mv.versionable_id')
                    ->on('ev.version_number', '=', 'mv.max_version');
            })
            ->where('ev.versionable_type', $versionableType)
            ->where('ev.status', 'published')
            ->get(['ev.id', 'ev.versionable_id']);

        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row->versionable_id] = (string) $row->id;
        }

        return $out;
    }

    public function findLatestPublishedRowsByVersionables(string $versionableType, array $versionableIds): array
    {
        if ($versionableIds === []) {
            return [];
        }

        $maxVersions = EntityVersion::query()
            ->where('versionable_type', $versionableType)
            ->whereIn('versionable_id', $versionableIds)
            ->where('status', 'published')
            ->where('version_number', '>', 0)
            ->groupBy('versionable_id')
            ->select('versionable_id', DB::raw('MAX(version_number) as max_version'));

        $rows = EntityVersion::query()->from('entity_versions as ev')
            ->joinSub($maxVersions, 'mv', function ($join): void {
                $join->on('ev.versionable_id', '=', 'mv.versionable_id')
                    ->on('ev.version_number', '=', 'mv.max_version');
            })
            ->where('ev.versionable_type', $versionableType)
            ->where('ev.status', 'published')
            ->get(['ev.versionable_id', 'ev.id', 'ev.version_number', 'ev.snapshot_data']);

        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row->versionable_id] = [
                'id'             => (string) $row->id,
                'version_number' => (int) $row->version_number,
                'snapshot_data'  => $row->snapshot_data,
            ];
        }

        return $out;
    }

    public function findVersionNumbersByIds(array $entityVersionIds): array
    {
        if ($entityVersionIds === []) {
            return [];
        }

        return EntityVersion::query()
            ->whereIn('id', $entityVersionIds)
            ->pluck('version_number', 'id')
            ->map(static fn ($value): int => (int) $value)
            ->all();
    }
}
