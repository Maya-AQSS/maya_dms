<?php

namespace App\Repositories\Contracts;

use App\Models\EntityVersion;
use Illuminate\Support\Collection;

interface EntityVersionRepositoryInterface
{
    /**
     * Obtiene una versión por su id.
     *
     * @param string $id El id de la versión.
     * @return EntityVersion La versión encontrada.
     * @throws EntityVersionNotFoundException Si la versión no existe.
     */
    public function findOrFail(string $id): EntityVersion;

    /**
     * Find a version by id or return null. Used to traverse base_version_id chains.
     */
    public function find(string $id): ?EntityVersion;

    /**
     * Versión publicada para un (versionableType, versionableId, version_number).
     */
    public function findOrFailPublishedByEntityAndNumber(
        string $versionableType,
        string $versionableId,
        int $versionNumber,
    ): EntityVersion;

    /**
     * Misma búsqueda que {@see self::findOrFailPublishedByEntityAndNumber} pero devuelve
     * null si no existe.
     */
    public function findPublishedByEntityAndNumber(
        string $versionableType,
        string $versionableId,
        int $versionNumber,
    ): ?EntityVersion;

    /**
     * Versión publicada por id (filtrada por versionable_type). Devuelve null si no existe.
     */
    public function findPublishedByIdAndType(string $entityVersionId, string $versionableType): ?EntityVersion;

    /**
     * Obtiene una versión por su id para actualización.
     *
     * @param string $id El id de la versión.
     * @return EntityVersion La versión encontrada.
     * @throws EntityVersionNotFoundException Si la versión no existe.
     */
    public function findOrFailForUpdate(string $id): EntityVersion;

    /**
     * Obtiene el próximo número de versión para un tipo y id de versionable.
     *
     * @param string $versionableType El tipo de versionable.
     * @param string $versionableId El id de la versionable.
     * @return int El próximo número de versión.
     */
    public function nextVersionNumber(string $versionableType, string $versionableId): int;

    /**
     * Obtiene la última versión publicada para una entidad.
     */
    public function findLatestPublishedForEntity(string $versionableType, string $versionableId): ?EntityVersion;

    /**
     * Metadatos ligeros de la última versión publicada de una entidad (sin snapshot).
     *
     * @return array{id: string, version_number: int, changelog: string}|null
     */
    public function findLatestPublishedMetaForVersionable(string $versionableType, string $versionableId): ?array;

    /**
     * Obtiene una versión publicada concreta por número de versión.
     */
    public function findPublishedForEntityVersionNumber(
        string $versionableType,
        string $versionableId,
        int $versionNumber,
    ): ?EntityVersion;

    /**
     * Lista versiones publicadas de una entidad ordenadas por número de versión.
     *
     * @return Collection<int, EntityVersion>
     */
    public function listPublishedForEntityOrdered(string $versionableType, string $versionableId): Collection;

    /**
     * Metadatos de una fila publicada en entity_versions por id (sin snapshot).
     *
     * @return array{id: string, version_number: int, changelog: string}|null
     */
    public function findPublishedMetaByIdForVersionable(
        string $entityVersionId,
        string $versionableType,
        string $versionableId,
    ): ?array;

    /**
     * Versión publicada por id de entity_versions (incluye snapshot_data).
     */
    public function findPublishedByIdForVersionable(
        string $entityVersionId,
        string $versionableType,
        string $versionableId,
    ): ?EntityVersion;

    /**
     * Crea una nueva versión.
     *
     * @param array<string, mixed> $attributes Los atributos de la versión.
     * @return EntityVersion La versión creada.
     */
    public function create(array $attributes): EntityVersion;

    /**
     * Actualiza una versión.
     *
     * @param EntityVersion $version La versión a actualizar.
     * @param array<string, mixed> $attributes Los atributos de la versión.
     * @return EntityVersion La versión actualizada.
     */
    public function update(EntityVersion $version, array $attributes): EntityVersion;

    /**
     * Ejecuta una transacción.
     *
     * @param callable $callback La función a ejecutar en la transacción.
     * @return mixed El resultado de la función.
     */
    public function transaction(callable $callback): mixed;
}
