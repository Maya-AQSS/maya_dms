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
     * Devuelve equipos visibles para el usuario.
     */
    public function listVisibleTeamsForUser(string $userId): array
    {
        return $this->repository->findVisibleTeamsForUser($userId);
    }

    /**
     * Devuelve un equipo visible por ID para el usuario o null.
     */
    public function findVisibleTeamByIdForUser(string $userId, string $teamId): ?array
    {
        return $this->repository->findVisibleTeamByIdForUser($userId, $teamId);
    }

    /**
     * Devuelve un equipo visible por ID para el usuario o null.
     */
    public function embeddableTeamForGroup(?string $groupId, string $viewerUserId): ?array
    {
        if ($groupId === null || $groupId === '' || $viewerUserId === '') {
            return null;
        }

        return $this->findVisibleTeamByIdForUser($viewerUserId, $groupId);
    }
}

