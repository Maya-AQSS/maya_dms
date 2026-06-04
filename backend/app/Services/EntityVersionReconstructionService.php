<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EntityVersion;
use App\Repositories\Contracts\EntityVersionRepositoryInterface;
use RuntimeException;

class EntityVersionReconstructionService
{
    public function __construct(
        private readonly EntityVersionRepositoryInterface $repository,
    ) {}

    /**
     * Reconstruye el estado efectivo de una versión aplicando su cadena base + change_set.
     *
     * @param  string  $versionId  ID de la versión a reconstruir
     * @return array<string, mixed>
     */
    public function reconstruct(string $versionId): array
    {
        $target = $this->repository->findOrFail($versionId);

        if (is_array($target->snapshot_data)) {
            return $target->snapshot_data;
        }

        $chain = $this->resolveChainFromRoot($target);
        $state = [];

        foreach ($chain as $node) {
            $changeSet = is_array($node->change_set) ? $node->change_set : [];
            $state = $this->mergeRecursivePreservingLists($state, $changeSet);
        }

        return $state;
    }

    /**
     * @return list<EntityVersion>
     */
    private function resolveChainFromRoot(EntityVersion $target): array
    {
        $visited = [];
        $chain = [];
        $cursor = $target;
        $expectedType = (string) $target->versionable_type;
        $expectedId = (string) $target->versionable_id;

        while ($cursor !== null) {
            $id = (string) $cursor->id;
            if ($id === '') {
                throw new RuntimeException('Versión inválida sin identificador.');
            }
            $cursorType = (string) $cursor->versionable_type;
            $cursorEntityId = (string) $cursor->versionable_id;
            if ($cursorType !== $expectedType || $cursorEntityId !== $expectedId) {
                throw new RuntimeException('Cadena de versiones inválida: mezcla de entidades detectada.');
            }
            if (isset($visited[$id])) {
                throw new RuntimeException('Cadena de versiones inválida: ciclo detectado.');
            }
            $visited[$id] = true;
            $chain[] = $cursor;

            $baseId = $cursor->base_version_id;
            if ($baseId === null || $baseId === '') {
                break;
            }

            $cursor = $this->repository->find($baseId);
            if ($cursor === null) {
                throw new RuntimeException('Cadena de versiones inválida: base_version_id no encontrada.');
            }
        }

        return array_reverse($chain);
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $delta
     * @return array<string, mixed>
     */
    private function mergeRecursivePreservingLists(array $base, array $delta): array
    {
        $result = $base;

        foreach ($delta as $key => $value) {
            if (! array_key_exists($key, $result)) {
                $result[$key] = $value;

                continue;
            }

            $baseValue = $result[$key];
            if (is_array($baseValue) && is_array($value)) {
                $baseIsList = array_is_list($baseValue);
                $valueIsList = array_is_list($value);
                if ($baseIsList || $valueIsList) {
                    $result[$key] = $value;

                    continue;
                }

                /** @var array<string, mixed> $baseAssoc */
                $baseAssoc = $baseValue;
                /** @var array<string, mixed> $deltaAssoc */
                $deltaAssoc = $value;
                $result[$key] = $this->mergeRecursivePreservingLists($baseAssoc, $deltaAssoc);

                continue;
            }

            $result[$key] = $value;
        }

        return $result;
    }
}
