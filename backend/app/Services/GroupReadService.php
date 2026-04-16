<?php

namespace App\Services;

use App\Repositories\Contracts\GroupReadRepositoryInterface;
use App\Services\Contracts\GroupReadServiceInterface;

class GroupReadService implements GroupReadServiceInterface
{
    public function __construct(
        private readonly GroupReadRepositoryInterface $repository,
    ) {}

    /**
     * Devuelve grupos visibles para el usuario.
     * 
     * @return list<array{id: string, name: string}>
     */
    public function listVisibleGroupsForUser(string $userId): array
    {
        return $this->repository->findVisibleGroupsForUser($userId);
    }

    /**
     * Devuelve un grupo visible por ID para el usuario o null.
     * 
     * @return array{id: string, name: string}|null
     */
    public function findVisibleGroupByIdForUser(string $userId, string $groupId): ?array
    {
        return $this->repository->findVisibleGroupByIdForUser($userId, $groupId);
    }
}

