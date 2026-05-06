<?php

namespace App\Repositories\Eloquent;

use App\Models\EntityVersion;
use App\Repositories\Contracts\EntityVersionRepositoryInterface;
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
