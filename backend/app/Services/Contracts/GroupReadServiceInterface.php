<?php

namespace App\Services\Contracts;

interface GroupReadServiceInterface
{
    /**
     * Devuelve grupos visibles para el usuario.
     * 
     * @return list<array{id: string, name: string}>
     */
    public function listVisibleGroupsForUser(string $userId): array;

    /**
     * Devuelve un grupo visible por ID para el usuario o null.
     * 
     * @return array{id: string, name: string}|null
     */
    public function findVisibleGroupByIdForUser(string $userId, string $groupId): ?array;
}

