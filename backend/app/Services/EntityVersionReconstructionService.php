<?php

namespace App\Services;

use App\Models\EntityVersion;
use RuntimeException;

class EntityVersionReconstructionService
{
    /**
     * Reconstruye el estado efectivo de una versión aplicando su cadena base + change_set.
     *
     * Si la versión objetivo contiene snapshot_data, se devuelve como fuente canónica.
     *
     * @return array<string, mixed>
     */
    public function reconstruct(EntityVersion|string $version): array
    {
        $target = is_string($version)
            ? EntityVersion::query()->findOrFail($version)
            : $version;

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
     * Resuelve la cadena de versiones desde la raíz hasta la versión objetivo.
     *
     * @param EntityVersion $target Versión objetivo.
     * @return list<EntityVersion>
     */
    private function resolveChainFromRoot(EntityVersion $target): array
    {
        $visited = [];
        $chain = [];
        $cursor = $target;

        while ($cursor !== null) {
            $id = (string) $cursor->id;
            if ($id === '') {
                throw new RuntimeException('Versión inválida sin identificador.');
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

            $cursor = EntityVersion::query()->find($baseId);
            if ($cursor === null) {
                throw new RuntimeException('Cadena de versiones inválida: base_version_id no encontrada.');
            }
        }

        return array_reverse($chain);
    }

    /**
     * Mezcla recursiva para payloads asociativos.
     * Si ambos valores son listas, prevalece el nuevo valor completo (reemplazo).
     *
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
