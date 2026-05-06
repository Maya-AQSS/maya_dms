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
