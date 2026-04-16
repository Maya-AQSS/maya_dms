<?php

namespace App\Services;

use App\Repositories\Contracts\TeamReadRepositoryInterface;
use App\Services\Contracts\TeamReadServiceInterface;

class TeamReadService implements TeamReadServiceInterface
{
    public function __construct(
        private readonly TeamReadRepositoryInterface $repository,
    ) {}

    /**
     * @return list<array{id: string, name: string}>
     */
    public function listVisibleTeamsForUser(string $userId): array
    {
        return $this->repository->findVisibleTeamsForUser($userId);
    }

    /**
     * @return array{id: string, name: string}|null
     */
    public function findVisibleTeamByIdForUser(string $userId, string $teamId): ?array
    {
        return $this->repository->findVisibleTeamByIdForUser($userId, $teamId);
    }
}

